<?php
/**
 * Storage — AWS Signature Version 4 helpers for R2 and S3.
 *
 * No WordPress dependencies in the pure signing functions.
 * All functions that return public URLs DO accept a $cfg array
 * so the caller controls which bucket/domain is used.
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// R2  —  path-style endpoint (region = 'auto')
// URL: https://{account_id}.r2.cloudflarestorage.com/{bucket}/{key}
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Build SigV4 Authorization headers + signed URL for an R2 request.
 *
 * @param string $method       HTTP method (GET, PUT, POST, DELETE).
 * @param string $key          Object key (no leading slash).
 * @param string $content_type Content-Type header value.
 * @param string $body_sha256  SHA-256 hex of the request body (empty body = hash('sha256','')).
 * @param array  $extra_query  Additional query params to include in signing.
 * @param array  $cfg          R2 config array from vicinity_video_r2_config() or vicinity_audio_r2_config().
 * @return array               Headers array + 'url' key with the full endpoint URL.
 */
function vicinity_r2_auth_headers(
	string $method,
	string $key,
	string $content_type,
	string $body_sha256,
	array  $extra_query = [],
	array  $cfg = []
): array {
	$key_id  = trim( $cfg['access_key'] );
	$secret  = trim( $cfg['secret_key'] );
	$bucket  = trim( $cfg['bucket'] );
	$acct    = trim( $cfg['account_id'] );
	$now     = time();
	$ds      = gmdate( 'Ymd', $now );
	$dt      = gmdate( 'Ymd\THis\Z', $now );
	$host    = "{$acct}.r2.cloudflarestorage.com";
	$region  = 'auto';
	$service = 's3';

	// Path-style: bucket name literal (not encoded); key segments rawurlencoded.
	$uri = '/' . $bucket . '/' . implode(
		'/',
		array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) )
	);

	ksort( $extra_query );
	$qs_parts = [];
	foreach ( $extra_query as $k => $v ) {
		$qs_parts[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
	}
	$qs = implode( '&', $qs_parts );

	$canon_hdrs  = "content-type:{$content_type}\nhost:{$host}\nx-amz-content-sha256:{$body_sha256}\nx-amz-date:{$dt}\n";
	$signed_hdrs = 'content-type;host;x-amz-content-sha256;x-amz-date';
	$canon       = implode( "\n", [ $method, $uri, $qs, $canon_hdrs, $signed_hdrs, $body_sha256 ] );
	$scope       = "{$ds}/{$region}/{$service}/aws4_request";
	$sts         = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash( 'sha256', $canon );
	$signing_key = hash_hmac( 'sha256', 'aws4_request',
		hash_hmac( 'sha256', $service,
			hash_hmac( 'sha256', $region,
				hash_hmac( 'sha256', $ds, 'AWS4' . $secret, true ), true ), true ), true );
	$sig         = hash_hmac( 'sha256', $sts, $signing_key );
	$auth        = "AWS4-HMAC-SHA256 Credential={$key_id}/{$scope},SignedHeaders={$signed_hdrs},Signature={$sig}";

	return [
		'Authorization'        => $auth,
		'Content-Type'         => $content_type,
		'x-amz-content-sha256' => $body_sha256,
		'x-amz-date'           => $dt,
		'url'                  => 'https://' . $host . $uri . ( $qs ? "?{$qs}" : '' ),
	];
}

/**
 * Generate a presigned PUT URL for one R2 multipart upload part.
 * Uses UNSIGNED-PAYLOAD (R2 presigned URLs don't validate body hash).
 *
 * @param string $key       R2 object key.
 * @param string $upload_id Multipart UploadId.
 * @param int    $part_num  1-based part number.
 * @param int    $expires   URL validity in seconds.
 * @param array  $cfg       R2 config.
 * @return string           Full presigned PUT URL.
 */
function vicinity_r2_presign_part( string $key, string $upload_id, int $part_num, int $expires = 7200, array $cfg = [] ): string {
	$key_id  = trim( $cfg['access_key'] );
	$secret  = trim( $cfg['secret_key'] );
	$bucket  = trim( $cfg['bucket'] );
	$acct    = trim( $cfg['account_id'] );
	$now     = time();
	$ds      = gmdate( 'Ymd', $now );
	$dt      = gmdate( 'Ymd\THis\Z', $now );
	$host    = "{$acct}.r2.cloudflarestorage.com";
	$region  = 'auto';
	$service = 's3';

	$uri = '/' . $bucket . '/' . implode(
		'/',
		array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) )
	);

	$cred   = "{$key_id}/{$ds}/{$region}/{$service}/aws4_request";
	$params = [
		'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
		'X-Amz-Credential'    => $cred,
		'X-Amz-Date'          => $dt,
		'X-Amz-Expires'       => (string) $expires,
		'X-Amz-SignedHeaders' => 'host',
		'partNumber'          => (string) $part_num,
		'uploadId'            => $upload_id,
	];
	ksort( $params );
	$qs_parts = [];
	foreach ( $params as $k => $v ) {
		$qs_parts[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
	}
	$qs = implode( '&', $qs_parts );

	$canon       = implode( "\n", [ 'PUT', $uri, $qs, "host:{$host}\n", 'host', 'UNSIGNED-PAYLOAD' ] );
	$scope       = "{$ds}/{$region}/{$service}/aws4_request";
	$sts         = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash( 'sha256', $canon );
	$signing_key = hash_hmac( 'sha256', 'aws4_request',
		hash_hmac( 'sha256', $service,
			hash_hmac( 'sha256', $region,
				hash_hmac( 'sha256', $ds, 'AWS4' . $secret, true ), true ), true ), true );
	$sig = hash_hmac( 'sha256', $sts, $signing_key );

	return "https://{$host}{$uri}?{$qs}&X-Amz-Signature={$sig}";
}

/**
 * Build the full public URL for an R2 object key.
 * Requires a custom domain (public_url) to be configured — R2 dev URLs cannot be derived.
 */
function vicinity_r2_public_url( string $key, array $cfg = [] ): string {
	if ( ! $key || ! $cfg['public_url'] ) return '';
	return $cfg['public_url'] . '/' . ltrim( $key, '/' );
}

// ═══════════════════════════════════════════════════════════════════════════
// S3  —  virtual-hosted endpoint (region-specific)
// URL: https://{bucket}.s3.{region}.amazonaws.com/{key}
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Build SigV4 Authorization headers + URL for an S3 request (virtual-hosted style).
 */
function vicinity_s3_auth_headers(
	string $method,
	string $key,
	string $content_type,
	string $body_sha256,
	array  $extra_query = [],
	array  $cfg = []
): array {
	$key_id  = trim( $cfg['access_key'] );
	$secret  = trim( $cfg['secret_key'] );
	$bucket  = trim( $cfg['bucket'] );
	$region  = trim( $cfg['region'] );
	$now     = time();
	$ds      = gmdate( 'Ymd', $now );
	$dt      = gmdate( 'Ymd\THis\Z', $now );
	$host    = "{$bucket}.s3.{$region}.amazonaws.com";
	$service = 's3';

	// Virtual-hosted: bucket is in the Host, key starts the path.
	$uri = '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );

	ksort( $extra_query );
	$qs_parts = [];
	foreach ( $extra_query as $k => $v ) {
		$qs_parts[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
	}
	$qs = implode( '&', $qs_parts );

	$canon_hdrs  = "content-type:{$content_type}\nhost:{$host}\nx-amz-content-sha256:{$body_sha256}\nx-amz-date:{$dt}\n";
	$signed_hdrs = 'content-type;host;x-amz-content-sha256;x-amz-date';
	$canon       = implode( "\n", [ $method, $uri, $qs, $canon_hdrs, $signed_hdrs, $body_sha256 ] );
	$scope       = "{$ds}/{$region}/{$service}/aws4_request";
	$sts         = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash( 'sha256', $canon );
	$signing_key = hash_hmac( 'sha256', 'aws4_request',
		hash_hmac( 'sha256', $service,
			hash_hmac( 'sha256', $region,
				hash_hmac( 'sha256', $ds, 'AWS4' . $secret, true ), true ), true ), true );
	$sig  = hash_hmac( 'sha256', $sts, $signing_key );
	$auth = "AWS4-HMAC-SHA256 Credential={$key_id}/{$scope},SignedHeaders={$signed_hdrs},Signature={$sig}";

	return [
		'Authorization'        => $auth,
		'Content-Type'         => $content_type,
		'x-amz-content-sha256' => $body_sha256,
		'x-amz-date'           => $dt,
		'url'                  => 'https://' . $host . $uri . ( $qs ? "?{$qs}" : '' ),
	];
}

/**
 * Generate a presigned GET URL for an S3 object (for server-side downloads).
 *
 * @param string $key     S3 object key.
 * @param int    $expires Validity in seconds.
 * @param array  $cfg     S3 config from vicinity_s3_config().
 * @return string         Presigned GET URL.
 */
function vicinity_s3_presign_get( string $key, int $expires = 900, array $cfg = [] ): string {
	$key_id  = trim( $cfg['access_key'] );
	$secret  = trim( $cfg['secret_key'] );
	$bucket  = trim( $cfg['bucket'] );
	$region  = trim( $cfg['region'] );
	$now     = time();
	$ds      = gmdate( 'Ymd', $now );
	$dt      = gmdate( 'Ymd\THis\Z', $now );
	$host    = "{$bucket}.s3.{$region}.amazonaws.com";
	$service = 's3';
	$uri     = '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );
	$cred    = "{$key_id}/{$ds}/{$region}/{$service}/aws4_request";

	$params = [
		'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
		'X-Amz-Credential'    => $cred,
		'X-Amz-Date'          => $dt,
		'X-Amz-Expires'       => (string) $expires,
		'X-Amz-SignedHeaders' => 'host',
	];
	ksort( $params );
	$qs_parts = [];
	foreach ( $params as $k => $v ) {
		$qs_parts[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
	}
	$qs = implode( '&', $qs_parts );

	$canon       = implode( "\n", [ 'GET', $uri, $qs, "host:{$host}\n", 'host', 'UNSIGNED-PAYLOAD' ] );
	$scope       = "{$ds}/{$region}/{$service}/aws4_request";
	$sts         = "AWS4-HMAC-SHA256\n{$dt}\n{$scope}\n" . hash( 'sha256', $canon );
	$signing_key = hash_hmac( 'sha256', 'aws4_request',
		hash_hmac( 'sha256', $service,
			hash_hmac( 'sha256', $region,
				hash_hmac( 'sha256', $ds, 'AWS4' . $secret, true ), true ), true ), true );
	$sig = hash_hmac( 'sha256', $sts, $signing_key );

	return "https://{$host}{$uri}?{$qs}&X-Amz-Signature={$sig}";
}

// ═══════════════════════════════════════════════════════════════════════════
// SHARED UTILITIES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Generate an anonymized object key (UUID-style) for uploaded files.
 * Used when 'vicinity_anon_filenames' option is enabled.
 *
 * @param string $ext      File extension without dot.
 * @param string $prefix   Path prefix, e.g. 'videos' or 'audio'.
 * @return string          Key like 'videos/2026/05/a1b2c3d4e5f6.mp4'.
 */
function vicinity_anon_key( string $ext, string $prefix = 'uploads' ): string {
	return $prefix . '/' . gmdate( 'Y/m' ) . '/' . bin2hex( random_bytes( 8 ) ) . '.' . $ext;
}

/**
 * Build an R2/S3 object key from a title slug.
 *
 * @param string $prefix  Path prefix, e.g. 'videos' or 'audio'.
 * @param string $ext     File extension.
 * @param int    $post_id Optional post ID for slug derivation.
 * @param string $filename Original filename fallback.
 * @return string
 */
function vicinity_build_key( string $prefix, string $ext, int $post_id = 0, string $filename = '' ): string {
	if ( get_option( 'vicinity_anon_filenames' ) ) {
		return vicinity_anon_key( $ext, $prefix );
	}
	$slug = $post_id
		? sanitize_title( get_the_title( $post_id ) )
		: sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) );
	$slug = $slug ?: 'file';
	return $prefix . '/' . gmdate( 'Y/m' ) . '/' . $slug . '-' . time() . '.' . $ext;
}