<?php
/**
 * Activation / deactivation routines.
 *
 * @package Apollo
 */

namespace Vicinity;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		// Ensure CPTs are registered before flushing.
		if ( ! post_type_exists( 'serve_video' ) ) {
			require_once VICINITY_PATH . 'modules/cpt.php';
		}
		flush_rewrite_rules( false );

		// Seed default options (only on first activate).
		add_option( 'vicinity_storage_video', 'r2' );
		add_option( 'vicinity_storage_audio', 'r2' );
	}

	public static function deactivate(): void {
		// Unschedule YouTube Live cron.
		$timestamp = wp_next_scheduled( 'vicinity_yt_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'vicinity_yt_cron' );
		}

		// Clear transients.
		delete_transient( 'vicinity_yt_live_status' );
		flush_rewrite_rules( false );
	}
}