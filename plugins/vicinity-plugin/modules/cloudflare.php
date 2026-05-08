<?php
/**
 * Cloudflare / R2 / S3 configuration helpers.
 *
 * All credential resolution lives here. Every other module calls these functions
 * rather than reading options directly. Constants always win over options.
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// SHARED R2 BASE CONFIG
// ─────────────────────────────────────────────────────────────────────────────

function vicinity_r2_base_config(): array {
	static $cfg = null;
	if ( $cfg !== null ) return $cfg;
	$cfg = [
		'account_id' => defined( 'VICINITY_R2_ACCOUNT_ID' ) ? VICINITY_R2_ACCOUNT_ID : (string) get_option( 'vicinity_r2_account_id', '' ),
		'access_key' => defined( 'VICINITY_R2_ACCESS_KEY' ) ? VICINITY_R2_ACCESS_KEY : (string) get_option( 'vicinity_r2_access_key', '' ),
		'secret_key' => defined( 'VICINITY_R2_SECRET_KEY' ) ? VICINITY_R2_SECRET_KEY : (string) get_option( 'vicinity_r2_secret_key', '' ),
	];
	return $cfg;
}

// ─────────────────────────────────────────────────────────────────────────────
// VIDEO R2 CONFIG
// ─────────────────────────────────────────────────────────────────────────────

function vicinity_video_r2_config(): array {
	static $cfg = null;
	if ( $cfg !== null ) return $cfg;
	$base = vicinity_r2_base_config();
	$cfg  = array_merge( $base, [
		'bucket'     => defined( 'VICINITY_R2_VIDEO_BUCKET' ) ? VICINITY_R2_VIDEO_BUCKET : (string) get_option( 'vicinity_r2_video_bucket', (string) get_option( 'vicinity_r2_bucket', '' ) ),
		'public_url' => rtrim( defined( 'VICINITY_R2_VIDEO_URL' ) ? VICINITY_R2_VIDEO_URL : (string) get_option( 'vicinity_r2_video_public_url', (string) get_option( 'vicinity_r2_public_url', '' ) ), '/' ),
	] );
	return $cfg;
}

// ─────────────────────────────────────────────────────────────────────────────
// AUDIO R2 CONFIG
// ─────────────────────────────────────────────────────────────────────────────

function vicinity_audio_r2_config(): array {
	static $cfg = null;
	if ( $cfg !== null ) return $cfg;
	$base = vicinity_r2_base_config();
	$cfg  = array_merge( $base, [
		'bucket'     => defined( 'VICINITY_R2_AUDIO_BUCKET' ) ? VICINITY_R2_AUDIO_BUCKET : (string) get_option( 'vicinity_r2_audio_bucket', (string) get_option( 'vicinity_r2_bucket', '' ) ),
		'public_url' => rtrim( defined( 'VICINITY_R2_AUDIO_URL' ) ? VICINITY_R2_AUDIO_URL : (string) get_option( 'vicinity_r2_audio_public_url', (string) get_option( 'vicinity_r2_public_url', '' ) ), '/' ),
	] );
	return $cfg;
}

// ─────────────────────────────────────────────────────────────────────────────
// S3 CONFIG
// ─────────────────────────────────────────────────────────────────────────────

function vicinity_s3_config(): array {
	static $cfg = null;
	if ( $cfg !== null ) return $cfg;
	$cfg = [
		'access_key' => defined( 'VICINITY_S3_ACCESS_KEY' ) ? VICINITY_S3_ACCESS_KEY : (string) get_option( 'vicinity_s3_access_key', '' ),
		'secret_key' => defined( 'VICINITY_S3_SECRET_KEY' ) ? VICINITY_S3_SECRET_KEY : (string) get_option( 'vicinity_s3_secret_key', '' ),
		'region'     => defined( 'VICINITY_S3_REGION' )     ? VICINITY_S3_REGION     : (string) get_option( 'vicinity_s3_region', 'us-east-1' ),
		'bucket'     => defined( 'VICINITY_S3_BUCKET' )     ? VICINITY_S3_BUCKET     : (string) get_option( 'vicinity_s3_bucket', '' ),
		'cf_url'     => rtrim( defined( 'VICINITY_S3_CF_URL' ) ? VICINITY_S3_CF_URL   : (string) get_option( 'vicinity_s3_cf_url', '' ), '/' ),
	];
	return $cfg;
}

// ─────────────────────────────────────────────────────────────────────────────
// CLOUDFLARE MEDIA TRANSFORM
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a Cloudflare Media Transform URL.
 *
 * @param string $source_url Full public URL of the source video in R2.
 * @param array  $opts       Transformation options: e.g. ['mode'=>'frame','time'=>'10s'].
 * @return string            cdn-cgi/media URL, or empty string if zone not configured.
 */
function vicinity_cf_transform_url( string $source_url, array $opts ): string {
	$zone = (string) get_option( 'vicinity_cf_transform_zone', '' );
	if ( ! $zone || ! $source_url ) return '';
	$zone  = rtrim( preg_replace( '#^https?://#', '', $zone ), '/' );
	$parts = [];
	foreach ( $opts as $k => $v ) {
		$parts[] = $k . '=' . $v;
	}
	return 'https://' . $zone . '/cdn-cgi/media/' . implode( ',', $parts ) . '/' . $source_url;
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILITY: CHECK IF R2 / S3 IS READY
// ─────────────────────────────────────────────────────────────────────────────

function vicinity_r2_video_ready(): bool {
	$c = vicinity_video_r2_config();
	return $c['account_id'] && $c['access_key'] && $c['secret_key'] && $c['bucket'];
}

function vicinity_r2_audio_ready(): bool {
	$c = vicinity_audio_r2_config();
	return $c['account_id'] && $c['access_key'] && $c['secret_key'] && $c['bucket'];
}

function vicinity_s3_ready(): bool {
	$c = vicinity_s3_config();
	return $c['access_key'] && $c['secret_key'] && $c['region'] && $c['bucket'];
}