<?php
/**
 * Plugin Name:       Vicinity
 * Plugin URI:        https://thepennytribune.com
 * Description:       Business-logic plugin for the Vicinity Theme. Provides CPTs (Video, Podcast,
 *                    Episode), Cloudflare R2/S3 storage, Netflix-style video hub, iTunes-compatible
 *                    podcast RSS feed, YouTube Live detection, block-editor sidebars, Video.js player,
 *                    AI Rewriter, AI Editorial Review, and Admin Menu Editor.
 * Version:           3.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Penny Tribune
 * License:           Proprietary
 * Text Domain:       vicinity
 * Domain Path:       /languages
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// Guard against double-load (e.g. must-use + regular plugins dir).
if ( defined( 'VICINITY_VERSION' ) ) {
	return;
}

define( 'VICINITY_VERSION',   '3.1.0' );
define( 'VICINITY_FILE',      __FILE__ );
define( 'VICINITY_PATH',      plugin_dir_path( __FILE__ ) );
define( 'VICINITY_URL',       plugin_dir_url( __FILE__ ) );
define( 'VICINITY_BASENAME',  plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version check — deactivates cleanly if not met.
 */
add_action( 'plugins_loaded', static function (): void {
	if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
		add_action( 'admin_notices', static function (): void {
			printf(
				'<div class="notice notice-error"><p><strong>Apollo</strong> requires PHP 8.2+. Current: %s.</p></div>',
				esc_html( PHP_VERSION )
			);
		} );
		return;
	}

	require_once VICINITY_PATH . 'includes/class-plugin.php';
	Vicinity\Plugin::boot();
}, 1 );

register_activation_hook( __FILE__, static function (): void {
	require_once VICINITY_PATH . 'includes/class-activator.php';
	Vicinity\Activator::activate();
} );

register_deactivation_hook( __FILE__, static function (): void {
	require_once VICINITY_PATH . 'includes/class-activator.php';
	Vicinity\Activator::deactivate();
} );

/**
 * Flush rewrite rules after an in-place plugin update (file-replace upload flow).
 * register_activation_hook only fires on first activate, not on file replacements.
 */
add_action( 'upgrader_process_complete', static function ( $upgrader, array $hook_extra ): void {
	if (
		( $hook_extra['type'] ?? '' ) === 'plugin' &&
		in_array( VICINITY_BASENAME, (array) ( $hook_extra['plugins'] ?? [] ), true )
	) {
		set_transient( 'vicinity_flush_rewrites', 1, 60 );
	}
}, 10, 2 );

add_action( 'init', static function (): void {
	if ( get_transient( 'vicinity_flush_rewrites' ) ) {
		delete_transient( 'vicinity_flush_rewrites' );
		flush_rewrite_rules( false );
	}
}, 9999 );