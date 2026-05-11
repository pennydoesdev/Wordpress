<?php
/**
 * Must-Use Plugin: Vicinity Activation Switch
 *
 * Switches from apollo-plugin/apollo-theme to vicinity-plugin/vicinity-theme.
 * Runs before any regular plugin or theme loads.
 */

defined( 'ABSPATH' ) || exit;

// ── 1. Deactivate apollo-plugin, activate vicinity-plugin ─────────────────
add_filter( 'option_active_plugins', static function ( array $plugins ): array {
	$plugins = array_filter( $plugins, static fn( string $p ) => strpos( $p, 'apollo-plugin/' ) === false );

	$vicinity = 'vicinity-plugin/vicinity-plugin.php';
	if ( ! in_array( $vicinity, $plugins, true ) ) {
		$plugins[] = $vicinity;
	}

	return array_values( $plugins );
} );

// ── 2. Switch active theme to vicinity-theme ──────────────────────────────
add_filter( 'option_stylesheet',        static fn() => 'vicinity-theme' );
add_filter( 'option_template',          static fn() => 'vicinity-theme' );
add_filter( 'option_current_theme',     static fn() => 'Vicinity' );
