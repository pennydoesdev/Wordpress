<?php
/**
 * Must-Use Plugin: Vicinity Activation Switch
 *
 * Runs before any regular plugin loads. Removes apollo-plugin from the
 * active plugins list and ensures vicinity-plugin is active.
 * Safe to leave in permanently — once apollo-plugin is gone from the
 * plugins directory this becomes a no-op.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'option_active_plugins', static function ( array $plugins ): array {
	// Remove old apollo-plugin.
	$plugins = array_filter( $plugins, static function ( string $p ): bool {
		return strpos( $p, 'apollo-plugin/' ) === false;
	} );

	// Ensure vicinity-plugin is active.
	$vicinity = 'vicinity-plugin/vicinity-plugin.php';
	if ( ! in_array( $vicinity, $plugins, true ) ) {
		$plugins[] = $vicinity;
	}

	return array_values( $plugins );
} );
