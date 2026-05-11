<?php
/**
 * Must-Use Plugin: Vicinity Activation Switch
 *
 * Permanently writes vicinity-theme and vicinity-plugin activation
 * to the database on first load, then becomes a no-op.
 */

defined( 'ABSPATH' ) || exit;

// ── 1. Switch theme permanently in DB ─────────────────────────────────────
if ( get_option( 'stylesheet' ) !== 'vicinity-theme' ) {
	update_option( 'stylesheet',    'vicinity-theme' );
	update_option( 'template',      'vicinity-theme' );
	update_option( 'current_theme', 'Vicinity' );
}

// ── 2. Deactivate apollo-plugin, activate vicinity-plugin ─────────────────
add_filter( 'option_active_plugins', static function ( array $plugins ): array {
	$plugins = array_filter(
		$plugins,
		static fn( string $p ) => strpos( $p, 'apollo-plugin/' ) === false
	);

	$vicinity = 'vicinity-plugin/vicinity-plugin.php';
	if ( ! in_array( $vicinity, $plugins, true ) ) {
		$plugins[] = $vicinity;
		// Persist so subsequent loads don't need the filter.
		update_option( 'active_plugins', array_values( $plugins ) );
	}

	return array_values( $plugins );
} );
