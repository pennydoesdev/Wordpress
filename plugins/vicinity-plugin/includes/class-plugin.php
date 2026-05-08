<?php
/**
 * Plugin bootstrap — loads modules in the correct order.
 *
 * @package Apollo
 */

namespace Vicinity;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Modules loaded on every request (relative to VICINITY_PATH). */
	private const MODULES = [
		'modules/cloudflare.php',  // R2/S3 config helpers (must come before storage)
		'modules/storage.php',     // SigV4 signing utilities
		'modules/cpt.php',         // CPT + taxonomy + meta registration
		'modules/video-player.php',// Video.js player HTML, thumbnail helpers, paywall gate
		'modules/video-upload.php',// MPU upload AJAX handlers (R2 + S3)
		'modules/video-hub.php',   // Hub layout blocks + section renderers
		'modules/audio-rss.php',   // iTunes podcast RSS feed + WebSub pings
		'modules/audio-upload.php',// Audio MPU upload AJAX handlers
		'modules/youtube.php',     // YouTube Live detection + cron
		'modules/ai.php',          // AI provider abstraction layer (OpenAI / Claude / Gemini / MiniMax / Featherless)
		'modules/admin-menu.php',  // Admin Menu Editor (drag/rename/hide menu items)
		'modules/editorial.php',   // Editorial flow with AI checklist
		'modules/settings.php',    // Admin settings page (credentials + AI + Editorial UI)
	];

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		foreach ( self::MODULES as $relative ) {
			$path = VICINITY_PATH . $relative;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}
}