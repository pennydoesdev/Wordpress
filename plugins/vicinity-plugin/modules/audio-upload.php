<?php
/**
 * Audio Upload — Multipart upload AJAX handlers for episodes (R2).
 *
 * Same MPU flow as video but for audio/mpeg files.
 * Chunk size: 5 MB (smaller than video — typical podcast files are smaller).
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

define( 'VICINITY_AUDIO_CHUNK_SIZE', 5 * 1024 * 1024 ); // 5 MB

// ═══════════════════════════════════════════════════════════════════════════
// 1. INIT MULTIPART UPLOAD
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_audio_mpu_init', static function (): void {
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	check_ajax_referer( 'vicinity_audio_upload', 'nonce' );

	if ( ! vicinity_r2_audio_ready() ) {
		wp_send_json_error( 'R2 audio credentials not configured. Check Apollo → Settings.' );
	}

	$filename     = sanitize_file_name( wp_unslash( $_POST['filename']     ?? 'episode.mp3' ) );
	$content_type = sanitize_text_field( wp_unslash( $_POST['content_type'] ?? 'audio/mpeg' ) );
	$post_id      = absint( $_POST['post_id']   ?? 0 );
	$file_size    = absint( $_POST['file_size']  ?? 0 );

	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'forbidden' );
	}

	$allowed_audio = [ 'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/flac', 'audio/aac' ];
	if ( ! in_array( $content_type, $allowed_audio, true ) ) {
		$content_type = 'audio/mpeg';
	}

	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ?: 'mp3';
	$key = vicinity_build_key( 'audio', $ext, $post_id, $filename );

	$cfg     = vicinity_audio_r2_config();
	$headers = vicinity_r2_auth_headers( 'POST', $key, $content_type, hash( 'sha256', '' ), [ 'uploads' => '' ], $cfg );
	$url     = $headers['url'];
	unset( $headers['url'] );

	$resp = wp_remote_request( $url, [ 'method' => 'POST', 'headers' => $headers, 'body' => '', 'timeout' => 30 ] );
	if ( is_wp_error( $resp ) ) wp_send_json_error( 'R2 error: ' . $resp->get_error_message() );

	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	if ( $code !== 200 ) {
		preg_match( '/<Code>([^<]+)<\/Code>/', $body, $mc );
		wp_send_json_error( 'R2 init failed (HTTP ' . $code . '): ' . ( $mc[1] ?? substr( wp_strip_all_tags( $body ), 0, 200 ) ) );
	}

	preg_match( '/<UploadId>([^<]+)<\/UploadId>/', $body, $m );
	$upload_id = $m[1] ?? '';
	if ( ! $upload_id ) wp_send_json_error( 'Could not parse UploadId.' );

	$total_parts    = $file_size > 0 ? max( 1, (int) ceil( $file_size / VICINITY_AUDIO_CHUNK_SIZE ) ) : 100;
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
		'chunk_size'     => VICINITY_AUDIO_CHUNK_SIZE,
		'total_parts'    => $total_parts,
	] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// 2. UPLOAD ONE PART (PHP PROXY — CORS FALLBACK)
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_audio_mpu_part', static function (): void {
	@set_time_limit( 300 );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	check_ajax_referer( 'vicinity_audio_upload', 'nonce' );

	$object_key = sanitize_text_field( wp_unslash( $_POST['object_key'] ?? '' ) );
	$upload_id  = sanitize_text_field( wp_unslash( $_POST['upload_id']  ?? '' ) );
	$part_num   = absint( $_POST['part_num'] ?? 0 );

	if ( ! $object_key || ! $upload_id || ! $part_num ) wp_send_json_error( 'Missing params.' );
	if ( empty( $_FILES['chunk'] ) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK ) {
		wp_send_json_error( 'No chunk received.' );
	}

	$chunk    = file_get_contents( $_FILES['chunk']['tmp_name'] );
	$sha256   = hash( 'sha256', $chunk );
	$ct       = 'application/octet-stream';
	$byte_len = mb_strlen( $chunk, '8bit' );
	$cfg      = vicinity_audio_r2_config();
	$headers  = vicinity_r2_auth_headers( 'PUT', $object_key, $ct, $sha256,
		[ 'partNumber' => (string) $part_num, 'uploadId' => $upload_id ], $cfg );
	$url = $headers['url'];
	unset( $headers['url'] );
	$headers['Content-Length'] = (string) $byte_len;

	$resp = wp_remote_request( $url, [ 'method' => 'PUT', 'headers' => $headers, 'body' => $chunk, 'timeout' => 120 ] );
	unset( $chunk );

	if ( is_wp_error( $resp ) ) wp_send_json_error( $resp->get_error_message() );

	$code = wp_remote_retrieve_response_code( $resp );
	if ( $code !== 200 ) wp_send_json_error( "Part {$part_num} failed (HTTP {$code})." );

	$etag = trim( wp_remote_retrieve_header( $resp, 'etag' ), '"' );
	if ( ! $etag ) wp_send_json_error( "No ETag for part {$part_num}. Check R2 CORS ExposeHeaders." );

	wp_send_json_success( [ 'part_num' => $part_num, 'etag' => $etag ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// 3. COMPLETE MULTIPART UPLOAD
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_audio_mpu_complete', static function (): void {
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );
	check_ajax_referer( 'vicinity_audio_upload', 'nonce' );

	$object_key = sanitize_text_field( wp_unslash( $_POST['object_key'] ?? '' ) );
	$upload_id  = sanitize_text_field( wp_unslash( $_POST['upload_id']  ?? '' ) );
	$parts      = json_decode( wp_unslash( $_POST['parts'] ?? '[]' ), true );

	if ( ! $object_key || ! $upload_id || ! is_array( $parts ) || empty( $parts ) ) {
		wp_send_json_error( 'Missing params.' );
	}

	$xml = '<CompleteMultipartUpload>';
	foreach ( $parts as $p ) {
		$num  = absint( $p['part_num'] ?? 0 );
		$etag = sanitize_text_field( $p['etag'] ?? '' );
		if ( ! $num ) continue;
		if ( ! $etag ) wp_send_json_error( "Part {$num} missing ETag." );
		$xml .= "<Part><PartNumber>{$num}</PartNumber><ETag>{$etag}</ETag></Part>";
	}
	$xml .= '</CompleteMultipartUpload>';

	$sha256  = hash( 'sha256', $xml );
	$cfg     = vicinity_audio_r2_config();
	$headers = vicinity_r2_auth_headers( 'POST', $object_key, 'application/xml', $sha256, [ 'uploadId' => $upload_id ], $cfg );
	$url     = $headers['url'];
	unset( $headers['url'] );

	$resp = wp_remote_request( $url, [ 'method' => 'POST', 'headers' => $headers, 'body' => $xml, 'timeout' => 60 ] );
	if ( is_wp_error( $resp ) ) wp_send_json_error( $resp->get_error_message() );

	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	if ( $code !== 200 ) {
		preg_match( '/<Code>([^<]+)<\/Code>/', $body, $mc );
		wp_send_json_error( 'Complete failed (HTTP ' . $code . '): ' . ( $mc[1] ?? '' ) );
	}

	wp_send_json_success( [
		'object_key' => $object_key,
		'public_url' => vicinity_r2_public_url( $object_key, $cfg ),
	] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// AUDIO FILE META HELPERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get the playback URL for an episode.
 */
function vicinity_episode_audio_url( int $post_id ): string {
	$r2_key = (string) get_post_meta( $post_id, '_ep_r2_key', true );
	if ( $r2_key ) {
		$cfg = vicinity_audio_r2_config();
		$url = vicinity_r2_public_url( $r2_key, $cfg );
		if ( $url ) return $url;
	}
	$wp_mid = absint( get_post_meta( $post_id, '_ep_wp_media_id', true ) );
	if ( $wp_mid ) {
		$url = wp_get_attachment_url( $wp_mid );
		if ( $url ) return (string) $url;
	}
	return '';
}