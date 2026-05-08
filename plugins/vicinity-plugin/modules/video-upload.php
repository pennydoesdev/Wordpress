<?php
/**
 * Video Upload — Multipart upload AJAX handlers for R2 and S3.
 *
 * Flow: Init (PHP ↔ R2) → Parts (Browser → R2 direct via presigned URLs) → Complete (PHP ↔ R2)
 * PHP proxy fallback for parts when CORS blocks ETag header.
 *
 * CORS rule required on R2 bucket:
 *   AllowedOrigins: ["https://thepennytribune.com"]
 *   AllowedMethods: ["PUT"]
 *   AllowedHeaders: ["*"]
 *   ExposeHeaders:  ["ETag"]
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

define( 'VICINITY_VIDEO_CHUNK_SIZE', 10 * 1024 * 1024 ); // 10 MB

// ═══════════════════════════════════════════════════════════════════════════
// 1. INIT MULTIPART UPLOAD
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_video_mpu_init', static function (): void {
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	check_ajax_referer( 'vicinity_video_upload', 'nonce' );

	$filename     = sanitize_file_name( wp_unslash( $_POST['filename']     ?? 'video.mp4' ) );
	$content_type = sanitize_text_field( wp_unslash( $_POST['content_type'] ?? 'video/mp4' ) );
	$post_id      = absint( $_POST['post_id']   ?? 0 );
	$file_size    = absint( $_POST['file_size']  ?? 0 );

	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'forbidden' );
	}

	$allowed = [ 'video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska', 'video/ogg' ];
	if ( ! in_array( $content_type, $allowed, true ) ) {
		$content_type = 'video/mp4';
	}

	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ?: 'mp4';
	$key = vicinity_build_key( 'videos', $ext, $post_id, $filename );

	$backend = get_option( 'vicinity_storage_video', 'r2' );

	if ( $backend === 's3' && vicinity_s3_ready() ) {
		return vicinity_video_mpu_init_s3( $key, $content_type, $file_size, $post_id );
	}

	if ( ! vicinity_r2_video_ready() ) {
		wp_send_json_error( 'R2 credentials not configured. Check Apollo → Settings.' );
	}

	$cfg     = vicinity_video_r2_config();
	$headers = vicinity_r2_auth_headers( 'POST', $key, $content_type, hash( 'sha256', '' ), [ 'uploads' => '' ], $cfg );
	$url     = $headers['url'];
	unset( $headers['url'] );

	$resp = wp_remote_request( $url, [ 'method' => 'POST', 'headers' => $headers, 'body' => '', 'timeout' => 30 ] );
	if ( is_wp_error( $resp ) ) {
		wp_send_json_error( 'R2 error: ' . $resp->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	if ( $code !== 200 ) {
		preg_match( '/<Code>([^<]+)<\/Code>/', $body, $mc );
		preg_match( '/<Message>([^<]+)<\/Message>/', $body, $mm );
		wp_send_json_error( 'R2 init failed (HTTP ' . $code . '): ' . ( $mc[1] ?? '' ) . ' — ' . ( $mm[1] ?? substr( wp_strip_all_tags( $body ), 0, 200 ) ) );
	}

	preg_match( '/<UploadId>([^<]+)<\/UploadId>/', $body, $m );
	$upload_id = $m[1] ?? '';
	if ( ! $upload_id ) wp_send_json_error( 'Could not parse UploadId from R2 response.' );

	$total_parts    = $file_size > 0 ? max( 1, (int) ceil( $file_size / VICINITY_VIDEO_CHUNK_SIZE ) ) : 200;
	$total_parts    = min( $total_parts, 1000 );
	$presigned_urls = [];
	for ( $i = 1; $i <= $total_parts; $i++ ) {
		$presigned_urls[ $i ] = vicinity_r2_presign_part( $key, $upload_id, $i, 7200, $cfg );
	}

	wp_send_json_success( [
		'upload_id'      => $upload_id,
		'object_key'     => $key,
		'public_url'     => vicinity_r2_public_url( $key, $cfg ),
		'presigned_urls' => $presigned_urls,
		'chunk_size'     => VICINITY_VIDEO_CHUNK_SIZE,
		'total_parts'    => $total_parts,
		'backend'        => 'r2',
	] );
} );

function vicinity_video_mpu_init_s3( string $key, string $content_type, int $file_size, int $post_id ): void {
	$cfg     = vicinity_s3_config();
	$headers = vicinity_s3_auth_headers( 'POST', $key, $content_type, hash( 'sha256', '' ), [ 'uploads' => '' ], $cfg );
	$url     = $headers['url'];
	unset( $headers['url'] );

	$resp = wp_remote_request( $url, [ 'method' => 'POST', 'headers' => $headers, 'body' => '', 'timeout' => 30 ] );
	if ( is_wp_error( $resp ) ) wp_send_json_error( 'S3 error: ' . $resp->get_error_message() );

	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	if ( $code !== 200 ) {
		preg_match( '/<Code>([^<]+)<\/Code>/', $body, $mc );
		wp_send_json_error( 'S3 init failed (HTTP ' . $code . '): ' . ( $mc[1] ?? '' ) );
	}

	preg_match( '/<UploadId>([^<]+)<\/UploadId>/', $body, $m );
	$upload_id = $m[1] ?? '';
	if ( ! $upload_id ) wp_send_json_error( 'Could not parse UploadId from S3 response.' );

	$total_parts = $file_size > 0 ? max( 1, (int) ceil( $file_size / VICINITY_VIDEO_CHUNK_SIZE ) ) : 200;
	$total_parts = min( $total_parts, 1000 );

	// S3 presigned part URLs (virtual-hosted, UNSIGNED-PAYLOAD).
	$bucket  = $cfg['bucket'];
	$region  = $cfg['region'];
	$key_id  = $cfg['access_key'];
	$secret  = $cfg['secret_key'];
	$host    = "{$bucket}.s3.{$region}.amazonaws.com";
	$uri     = '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );
	$now     = time();
	$ds      = gmdate( 'Ymd', $now );
	$dt      = gmdate( 'Ymd\THis\Z', $now );
	$cred    = "{$key_id}/{$ds}/{$region}/s3/aws4_request";
	$scope   = "{$ds}/{$region}/s3/aws4_request";
	$sk      = hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 's3', hash_hmac( 'sha256', $region, hash_hmac( 'sha256', $ds, 'AWS4' . $secret, true ), true ), true ), true );

	$presigned_urls = [];
	for ( $i = 1; $i <= $total_parts; $i++ ) {
		$params = [
			'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
			'X-Amz-Credential'    => $cred,
			'X-Amz-Date'          => $dt,
			'X-Amz-Expires'       => '7200',
			'X-Amz-SignedHeaders' => 'host',
			'partNumber'          => (string) $i,
			'uploadId'            => $upload_id,
		];
		ksort( $params );
		$qs_parts = [];
		foreach ( $params as $k => $v ) {
			$qs_parts[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
		}
		$qs    = implode( '&', $qs_parts );
		$canon = implode( "\n", [ 'PUT', $uri, $qs, "host:{$host}\n", 'host', 'UNSIGNED-PAYLOAD' ] );
		$sts   = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash( 'sha256', $canon );
		$sig   = hash_hmac( 'sha256', $sts, $sk );
		$presigned_urls[ $i ] = "https://{$host}{$uri}?{$qs}&X-Amz-Signature={$sig}";
	}

	wp_send_json_success( [
		'upload_id'      => $upload_id,
		'object_key'     => $key,
		'public_url'     => rtrim( $cfg['cf_url'] ?: "https://{$host}", '/' ) . '/' . ltrim( $key, '/' ),
		'presigned_urls' => $presigned_urls,
		'chunk_size'     => VICINITY_VIDEO_CHUNK_SIZE,
		'total_parts'    => $total_parts,
		'backend'        => 's3',
	] );
}

// ═══════════════════════════════════════════════════════════════════════════
// 2. UPLOAD ONE PART (PHP PROXY — CORS FALLBACK)
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_video_mpu_part', static function (): void {
	@set_time_limit( 300 );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	check_ajax_referer( 'vicinity_video_upload', 'nonce' );

	$object_key = sanitize_text_field( wp_unslash( $_POST['object_key'] ?? '' ) );
	$upload_id  = sanitize_text_field( wp_unslash( $_POST['upload_id']  ?? '' ) );
	$part_num   = absint( $_POST['part_num'] ?? 0 );
	$backend    = sanitize_key( $_POST['backend'] ?? 'r2' );

	if ( ! $object_key || ! $upload_id || ! $part_num ) {
		wp_send_json_error( 'Missing params.' );
	}
	if ( empty( $_FILES['chunk'] ) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK ) {
		wp_send_json_error( 'No chunk received (error: ' . ( $_FILES['chunk']['error'] ?? '?' ) . ').' );
	}

	$chunk    = file_get_contents( $_FILES['chunk']['tmp_name'] );
	$sha256   = hash( 'sha256', $chunk );
	$ct       = 'application/octet-stream';
	$byte_len = mb_strlen( $chunk, '8bit' );

	$extra = [ 'partNumber' => (string) $part_num, 'uploadId' => $upload_id ];

	if ( $backend === 's3' && vicinity_s3_ready() ) {
		$cfg     = vicinity_s3_config();
		$headers = vicinity_s3_auth_headers( 'PUT', $object_key, $ct, $sha256, $extra, $cfg );
	} else {
		$cfg     = vicinity_video_r2_config();
		$headers = vicinity_r2_auth_headers( 'PUT', $object_key, $ct, $sha256, $extra, $cfg );
	}

	$url = $headers['url'];
	unset( $headers['url'] );
	$headers['Content-Length'] = (string) $byte_len;

	$resp = wp_remote_request( $url, [
		'method'  => 'PUT',
		'headers' => $headers,
		'body'    => $chunk,
		'timeout' => 120,
	] );
	unset( $chunk );

	if ( is_wp_error( $resp ) ) wp_send_json_error( $resp->get_error_message() );

	$code = wp_remote_retrieve_response_code( $resp );
	if ( $code !== 200 ) {
		wp_send_json_error( "Part {$part_num} failed (HTTP {$code})." );
	}

	$etag = trim( wp_remote_retrieve_header( $resp, 'etag' ), '"' );
	if ( ! $etag ) wp_send_json_error( "No ETag in response for part {$part_num}. Check R2 CORS ExposeHeaders." );

	wp_send_json_success( [ 'part_num' => $part_num, 'etag' => $etag ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// 3. COMPLETE MULTIPART UPLOAD
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_video_mpu_complete', static function (): void {
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	check_ajax_referer( 'vicinity_video_upload', 'nonce' );

	$object_key = sanitize_text_field( wp_unslash( $_POST['object_key'] ?? '' ) );
	$upload_id  = sanitize_text_field( wp_unslash( $_POST['upload_id']  ?? '' ) );
	$parts      = json_decode( wp_unslash( $_POST['parts'] ?? '[]' ), true );
	$backend    = sanitize_key( $_POST['backend'] ?? 'r2' );

	if ( ! $object_key || ! $upload_id || ! is_array( $parts ) || empty( $parts ) ) {
		wp_send_json_error( 'Missing params.' );
	}

	$xml = '<CompleteMultipartUpload>';
	foreach ( $parts as $p ) {
		$num  = absint( $p['part_num'] ?? 0 );
		$etag = sanitize_text_field( $p['etag'] ?? '' );
		if ( ! $num ) continue;
		if ( ! $etag ) wp_send_json_error( "Part {$num} is missing its ETag. Check R2 CORS policy (ExposeHeaders must include ETag)." );
		$xml .= "<Part><PartNumber>{$num}</PartNumber><ETag>{$etag}</ETag></Part>";
	}
	$xml .= '</CompleteMultipartUpload>';

	$sha256 = hash( 'sha256', $xml );
	$extra  = [ 'uploadId' => $upload_id ];

	if ( $backend === 's3' && vicinity_s3_ready() ) {
		$cfg     = vicinity_s3_config();
		$headers = vicinity_s3_auth_headers( 'POST', $object_key, 'application/xml', $sha256, $extra, $cfg );
	} else {
		$cfg     = vicinity_video_r2_config();
		$headers = vicinity_r2_auth_headers( 'POST', $object_key, 'application/xml', $sha256, $extra, $cfg );
	}

	$url = $headers['url'];
	unset( $headers['url'] );

	$resp = wp_remote_request( $url, [
		'method'  => 'POST',
		'headers' => $headers,
		'body'    => $xml,
		'timeout' => 60,
	] );

	if ( is_wp_error( $resp ) ) wp_send_json_error( $resp->get_error_message() );

	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	if ( $code !== 200 ) {
		preg_match( '/<Code>([^<]+)<\/Code>/', $body, $mc );
		wp_send_json_error( 'Complete failed (HTTP ' . $code . '): ' . ( $mc[1] ?? substr( wp_strip_all_tags( $body ), 0, 200 ) ) );
	}

	$public_url = $backend === 's3'
		? ( rtrim( $cfg['cf_url'] ?: '', '/' ) . '/' . ltrim( $object_key, '/' ) )
		: vicinity_r2_public_url( $object_key, $cfg );

	wp_send_json_success( [
		'object_key' => $object_key,
		'public_url' => $public_url,
	] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// 4. SERVER-SIDE THUMBNAIL GENERATION VIA FFMPEG
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_video_gen_thumb', static function (): void {
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	if ( ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ?? '' ), 'vicinity_video_upload' ) ) {
		wp_send_json_error( 'nonce' );
	}

	$post_id = absint( $_POST['post_id']    ?? 0 );
	$key     = sanitize_text_field( wp_unslash( $_POST['object_key'] ?? '' ) );
	if ( ! $post_id || ! $key || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'Missing params or forbidden.' );
	}

	// Find FFmpeg.
	$ffmpeg = '';
	foreach ( [ '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg' ] as $p ) {
		if ( @is_executable( $p ) ) { $ffmpeg = $p; break; }
	}
	if ( ! $ffmpeg ) {
		$ffmpeg = trim( (string) @shell_exec( 'which ffmpeg 2>/dev/null' ) );
	}
	if ( ! $ffmpeg ) wp_send_json_error( 'FFmpeg not available on this server.' );

	// Download video from R2 to a temp file (streamed).
	$cfg  = vicinity_video_r2_config();
	if ( ! vicinity_r2_video_ready() ) wp_send_json_error( 'R2 not configured.' );

	// Build presigned GET URL for server → R2 download.
	// Reuse the presign_part signing pattern but for GET.
	$acct   = $cfg['account_id'];
	$bucket = $cfg['bucket'];
	$secret = $cfg['secret_key'];
	$key_id = $cfg['access_key'];
	$host   = "{$acct}.r2.cloudflarestorage.com";
	$uri    = '/' . $bucket . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );
	$now    = time();
	$ds     = gmdate( 'Ymd', $now );
	$dt     = gmdate( 'Ymd\THis\Z', $now );
	$cred   = "{$key_id}/{$ds}/auto/s3/aws4_request";
	$scope  = "{$ds}/auto/s3/aws4_request";
	$params = [
		'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
		'X-Amz-Credential'    => $cred,
		'X-Amz-Date'          => $dt,
		'X-Amz-Expires'       => '900',
		'X-Amz-SignedHeaders' => 'host',
	];
	ksort( $params );
	$qs_parts = [];
	foreach ( $params as $k => $v ) {
		$qs_parts[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
	}
	$qs    = implode( '&', $qs_parts );
	$sk    = hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 's3', hash_hmac( 'sha256', 'auto', hash_hmac( 'sha256', $ds, 'AWS4' . $secret, true ), true ), true ), true );
	$canon = implode( "\n", [ 'GET', $uri, $qs, "host:{$host}\n", 'host', 'UNSIGNED-PAYLOAD' ] );
	$sts   = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash( 'sha256', $canon );
	$sig   = hash_hmac( 'sha256', $sts, $sk );
	$r2_url = "https://{$host}{$uri}?{$qs}&X-Amz-Signature={$sig}";

	$ext       = strtolower( pathinfo( $key, PATHINFO_EXTENSION ) ) ?: 'mp4';
	$tmp_dir   = trailingslashit( get_temp_dir() );
	$tmp_video = $tmp_dir . 'apollo-vid-' . $post_id . '-' . time() . '.' . $ext;

	$dl = wp_remote_get( $r2_url, [ 'timeout' => 120, 'stream' => true, 'filename' => $tmp_video ] );
	if ( is_wp_error( $dl ) ) wp_send_json_error( 'Download failed: ' . $dl->get_error_message() );
	if ( wp_remote_retrieve_response_code( $dl ) !== 200 ) {
		@unlink( $tmp_video );
		wp_send_json_error( 'R2 download HTTP ' . wp_remote_retrieve_response_code( $dl ) );
	}

	// Probe duration, extract JPEG at 25%.
	$ffprobe  = str_replace( 'ffmpeg', 'ffprobe', $ffmpeg );
	$dur_raw  = @shell_exec( escapeshellarg( $ffprobe ) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg( $tmp_video ) . ' 2>/dev/null' );
	$duration = max( 10.0, (float) trim( $dur_raw ?: '60' ) );
	$start    = round( $duration * 0.25, 3 );

	$slug     = sanitize_title( get_the_title( $post_id ) ?: 'video' );
	$tmp_jpeg = $tmp_dir . $slug . '-' . $post_id . '.jpg';

	@shell_exec( sprintf( '%s -ss %s -i %s -vframes 1 -q:v 2 -y %s 2>/dev/null',
		escapeshellarg( $ffmpeg ),
		escapeshellarg( (string) $start ),
		escapeshellarg( $tmp_video ),
		escapeshellarg( $tmp_jpeg )
	) );
	@unlink( $tmp_video );

	if ( ! file_exists( $tmp_jpeg ) || filesize( $tmp_jpeg ) < 500 ) {
		wp_send_json_error( 'FFmpeg produced no JPEG output.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$att_id = media_handle_sideload(
		[ 'name' => $slug . '-thumb.jpg', 'tmp_name' => $tmp_jpeg, 'error' => 0, 'size' => filesize( $tmp_jpeg ), 'type' => 'image/jpeg' ],
		$post_id,
		get_the_title( $post_id ) . ' — thumbnail'
	);
	@unlink( $tmp_jpeg );

	if ( is_wp_error( $att_id ) ) wp_send_json_error( $att_id->get_error_message() );

	set_post_thumbnail( $post_id, $att_id );
	update_post_meta( $post_id, '_svh_thumb_id', $att_id );

	wp_send_json_success( [
		'attachment_id' => $att_id,
		'thumb_html'    => get_the_post_thumbnail( $post_id, [ 120, 68 ] ),
	] );
} );