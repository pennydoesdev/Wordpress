<?php
/**
 * Video Player — Video.js-powered player, thumbnail helpers, paywall gate, view counter.
 *
 * Uses Video.js v8 from CDN. Player options come from:
 *   - WP Customizer (theme): skin, controls, playback rates, autoplay policy
 *   - Per-post meta: R2 key, WP media ID, YouTube ID
 *
 * Customizer options (read via get_theme_mod):
 *   videojs_skin             — 'vjs-default-skin' | 'vjs-big-play-centered' | 'apollo-dark'
 *   videojs_playback_rates   — comma-separated, e.g. '0.5,1,1.25,1.5,2'
 *   videojs_show_rates       — bool
 *   videojs_autoplay_policy  — 'never' | 'muted' | 'always'
 *   videojs_fluid            — bool (responsive mode)
 *   videojs_pip              — bool (picture-in-picture button)
 *   videojs_preload          — 'auto' | 'metadata' | 'none'
 *   videojs_accent_color     — hex color for progress bar + big play button
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

define( 'VICINITY_VIDEOJS_VERSION', '8.21.1' );
define( 'VICINITY_VIDEOJS_CDN',     'https://vjs.zencdn.net/' . VICINITY_VIDEOJS_VERSION );

// ═══════════════════════════════════════════════════════════════════════════
// PAYWALL HELPERS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_video_is_paywalled( int $post_id ): bool {
	return (bool) get_post_meta( $post_id, '_svh_paywall', true );
}

function vicinity_user_has_video_access(): bool {
	if ( ! is_user_logged_in() ) return false;
	if ( current_user_can( 'edit_others_posts' ) ) return true;

	$required_role = (string) get_option( 'vicinity_paywall_role', 'subscriber' );
	$user          = wp_get_current_user();

	if ( ! $required_role ) return true;
	if ( in_array( $required_role, (array) $user->roles, true ) ) return true;
	if ( $required_role === 'subscriber' && current_user_can( 'read' ) ) return true;

	return false;
}

// ═══════════════════════════════════════════════════════════════════════════
// THUMBNAIL + POSTER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_video_thumbnail_url( int $post_id, string $size = 'medium_large' ): string {
	$jpeg_id = absint( get_post_meta( $post_id, '_svh_thumb_id', true ) );
	if ( $jpeg_id ) {
		$url = wp_get_attachment_url( $jpeg_id );
		if ( $url ) return $url;
	}
	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		if ( $url && ! str_ends_with( strtolower( parse_url( $url, PHP_URL_PATH ) ?: '' ), '.gif' ) ) {
			return (string) $url;
		}
	}
	return '';
}

function vicinity_video_poster_url( int $post_id ): string {
	return vicinity_video_thumbnail_url( $post_id, 'large' );
}

// ═══════════════════════════════════════════════════════════════════════════
// VIDEO.JS OPTIONS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Build the Video.js options object from theme customizer settings.
 * Returns a PHP array suitable for wp_json_encode.
 */
function vicinity_videojs_options( bool $is_short = false, array $overrides = [] ): array {
	$fluid          = (bool) get_theme_mod( 'videojs_fluid', true );
	$pip            = (bool) get_theme_mod( 'videojs_pip', true );
	$preload        = get_theme_mod( 'videojs_preload', 'metadata' );
	$show_rates     = (bool) get_theme_mod( 'videojs_show_rates', true );
	$rates_raw      = (string) get_theme_mod( 'videojs_playback_rates', '0.5,0.75,1,1.25,1.5,2' );
	$autoplay_policy = get_theme_mod( 'videojs_autoplay_policy', 'never' );
	$accent_color   = get_theme_mod( 'videojs_accent_color', '' ) ?: get_theme_mod( 'vicinity_accent_color', '#c62828' );

	$rates = array_values( array_filter( array_map( 'floatval', explode( ',', $rates_raw ) ) ) );
	if ( empty( $rates ) ) $rates = [ 0.5, 1, 1.25, 1.5, 2 ];

	$opts = [
		'fluid'      => $fluid,
		'preload'    => $preload,
		'responsive' => true,
		'aspectRatio' => $is_short ? '9:16' : '16:9',
		'playbackRates' => $show_rates ? $rates : [],
		'controlBar' => [
			'pictureInPictureToggle' => $pip,
			'playbackRateMenuButton' => $show_rates,
		],
		'userActions' => [
			'hotkeys' => [
				'enableNumbers'     => true,
				'enableMute'        => true,
				'enableFullscreen'  => true,
				'enableVolumeScroll'=> false,
			],
		],
		'_accentColor' => sanitize_hex_color( $accent_color ),
	];

	if ( $autoplay_policy === 'muted' ) {
		$opts['autoplay'] = 'muted';
	} elseif ( $autoplay_policy === 'always' ) {
		$opts['autoplay'] = true;
		$opts['muted']    = true;
	}

	return array_merge( $opts, $overrides );
}

// ═══════════════════════════════════════════════════════════════════════════
// PLAYER HTML (Video.js)
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_get_video_player( int $post_id, array $opts = [] ): string {
	if ( vicinity_video_is_paywalled( $post_id ) && ! vicinity_user_has_video_access() ) {
		return vicinity_paywall_html( $post_id );
	}

	$r2_key   = (string) get_post_meta( $post_id, '_svh_r2_key', true );
	$wp_mid   = absint( get_post_meta( $post_id, '_svh_wp_media_id', true ) );
	$yt_id    = (string) get_post_meta( $post_id, '_svh_youtube_id', true );
	$is_short = get_post_meta( $post_id, '_svh_format', true ) === 'short';
	$title    = esc_attr( get_the_title( $post_id ) );
	$poster   = vicinity_video_poster_url( $post_id );

	// ── R2-hosted ─────────────────────────────────────────────────────
	if ( $r2_key ) {
		$cfg = vicinity_video_r2_config();
		$url = vicinity_r2_public_url( $r2_key, $cfg );
		if ( $url ) {
			$ext  = strtolower( pathinfo( $r2_key, PATHINFO_EXTENSION ) );
			$mime = match( $ext ) { 'webm' => 'video/webm', 'mov' => 'video/quicktime', default => 'video/mp4' };
			return vicinity_videojs_wrap( $post_id, $url, $mime, $poster, $title, $is_short, $opts );
		}
	}

	// ── WP Media Library ──────────────────────────────────────────────
	if ( $wp_mid ) {
		$url = wp_get_attachment_url( $wp_mid );
		if ( $url ) {
			return vicinity_videojs_wrap( $post_id, (string) $url, 'video/mp4', $poster, $title, $is_short, $opts );
		}
	}

	// ── YouTube embed (no Video.js needed) ────────────────────────────
	if ( $yt_id ) {
		$params = 'rel=0&modestbranding=1&playsinline=1';
		if ( ! empty( $opts['autoplay'] ) ) $params .= '&autoplay=1&mute=1';
		return '<div class="apollo-yt-embed" style="' . ( $is_short ? 'aspect-ratio:9/16' : 'aspect-ratio:16/9' ) . ';position:relative;overflow:hidden;background:#000;">'
			. '<iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $yt_id ) . '?' . $params . '"'
			. ' title="' . $title . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture"'
			. ' allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"'
			. ' style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>'
			. '</div>';
	}

	// ── No video ──────────────────────────────────────────────────────
	if ( current_user_can( 'edit_post', $post_id ) ) {
		return '<div class="apollo-player-empty">'
			. '<p>' . esc_html__( 'No video uploaded yet.', 'vicinity' ) . '</p>'
			. '<a class="button" href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html__( 'Upload a video', 'vicinity' ) . '</a>'
			. '</div>';
	}
	return '<div class="apollo-player-empty"><p>' . esc_html__( 'Video coming soon.', 'vicinity' ) . '</p></div>';
}

/**
 * Build Video.js HTML for a given source URL.
 */
function vicinity_videojs_wrap( int $post_id, string $src, string $mime, string $poster, string $title, bool $is_short, array $opts ): string {
	$player_id = 'apollo-vjs-' . $post_id . '-' . wp_rand( 100, 999 );
	$skin      = get_theme_mod( 'videojs_skin', 'vjs-big-play-centered apollo-dark-skin' );

	$vjs_opts  = vicinity_videojs_options( $is_short, [
		'autoplay' => ! empty( $opts['autoplay'] ) ? ( empty( $opts['muted'] ) ? 'muted' : true ) : false,
	] );

	// Data attributes for our JS to pick up post_id for view counter.
	$data_attrs = ' data-post-id="' . $post_id . '"'
		. ' data-nonce="' . esc_attr( wp_create_nonce( 'vicinity_video_view' ) ) . '"'
		. ' data-ajax="' . esc_attr( admin_url( 'admin-ajax.php' ) ) . '"';

	$poster_attr = $poster ? ' poster="' . esc_url( $poster ) . '"' : '';

	return '<div class="apollo-videojs-wrap" style="' . ( $is_short ? 'max-width:400px;margin:0 auto;' : '' ) . '">'
		. '<video id="' . esc_attr( $player_id ) . '"'
		. ' class="video-js ' . esc_attr( $skin ) . '"'
		. ' controls preload="' . esc_attr( $vjs_opts['preload'] ?? 'metadata' ) . '"'
		. $poster_attr
		. ' data-setup=\'' . esc_attr( wp_json_encode( $vjs_opts ) ) . '\''
		. $data_attrs
		. ' title="' . $title . '">'
		. '<source src="' . esc_url( $src ) . '" type="' . esc_attr( $mime ) . '">'
		. '<p class="vjs-no-js">' . esc_html__( 'Please enable JavaScript to view this video.', 'vicinity' ) . '</p>'
		. '</video>'
		. '</div>';
}

function vicinity_paywall_html( int $post_id ): string {
	$subscribe_url = (string) apply_filters( 'vicinity_paywall_subscribe_url', home_url( '/subscribe/' ) );
	$btn_text      = (string) get_option( 'vicinity_paywall_btn_text', __( 'Subscribe to Watch', 'vicinity' ) );
	$login_url     = wp_login_url( get_permalink( $post_id ) );

	return '<div class="apollo-paywall">'
		. '<div class="apollo-paywall__icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>'
		. '<h3 class="apollo-paywall__title">' . esc_html__( 'Members Only', 'vicinity' ) . '</h3>'
		. '<p class="apollo-paywall__desc">' . esc_html__( 'This video is exclusive to paid subscribers.', 'vicinity' ) . '</p>'
		. '<div class="apollo-paywall__actions">'
		. '<a href="' . esc_url( $subscribe_url ) . '" class="apollo-paywall__cta">' . esc_html( $btn_text ) . '</a>'
		. ( ! is_user_logged_in() ? '<a href="' . esc_url( $login_url ) . '" class="apollo-paywall__login">' . esc_html__( 'Sign in', 'vicinity' ) . '</a>' : '' )
		. '</div>'
		. '</div>';
}

// ═══════════════════════════════════════════════════════════════════════════
// VIEW COUNTER AJAX
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_video_view',        'vicinity_ajax_video_view' );
add_action( 'wp_ajax_nopriv_vicinity_video_view', 'vicinity_ajax_video_view' );

function vicinity_ajax_video_view(): void {
	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id ) wp_send_json_error( 'invalid' );
	if ( ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ?? '' ), 'vicinity_video_view' ) ) wp_send_json_error( 'nonce' );
	if ( get_post_type( $post_id ) !== 'serve_video' ) wp_send_json_error( 'type' );
	$views = absint( get_post_meta( $post_id, '_svh_views', true ) );
	update_post_meta( $post_id, '_svh_views', $views + 1 );
	wp_send_json_success( [ 'views' => $views + 1 ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// APOLLO DARK SKIN CSS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Returns the inline CSS for the Apollo dark Video.js skin.
 * Applied on every page that loads Video.js.
 *
 * @param string $accent  Sanitized hex accent colour (default #c62828).
 */
function vicinity_videojs_skin_css( string $accent = '#c62828' ): string {
	$accent = $accent ?: '#c62828';
	return '
/* ── Apollo dark skin variables ── */
:root {
  --vjs-accent:    ' . $accent . ';
  --vjs-bg:        #0a0a0a;
  --vjs-bar-bg:    rgba(255,255,255,.12);
  --vjs-bar-hover: rgba(255,255,255,.25);
  --vjs-text:      #ffffff;
  --vjs-icon:      rgba(255,255,255,.85);
  --vjs-radius:    3px;
}

/* ── Wrapper ── */
.apollo-videojs-wrap {
  position: relative;
  background: var(--vjs-bg);
  border-radius: 4px;
  overflow: hidden;
  box-shadow: 0 4px 32px rgba(0,0,0,.55);
  line-height: 0;
}

/* ── Player base ── */
.video-js.apollo-dark-skin {
  background: var(--vjs-bg);
  font-family: "Libre Franklin", "Franklin Gothic Medium", sans-serif;
  font-size: 14px;
  color: var(--vjs-text);
}

/* ── Big play button ── */
.video-js.apollo-dark-skin .vjs-big-play-button {
  width: 72px;
  height: 72px;
  line-height: 72px;
  border-radius: 50%;
  border: 3px solid var(--vjs-accent);
  background: rgba(0,0,0,.6);
  top: 50%;
  left: 50%;
  transform: translate(-50%,-50%);
  transition: background .2s, border-color .2s, transform .15s;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}
.video-js.apollo-dark-skin:hover .vjs-big-play-button,
.video-js.apollo-dark-skin .vjs-big-play-button:focus {
  background: var(--vjs-accent);
  border-color: var(--vjs-accent);
  transform: translate(-50%,-50%) scale(1.08);
  outline: none;
}
.video-js.apollo-dark-skin .vjs-big-play-button .vjs-icon-placeholder::before {
  font-size: 2.4em;
  line-height: 72px;
}

/* ── Control bar ── */
.video-js.apollo-dark-skin .vjs-control-bar {
  background: linear-gradient(transparent, rgba(0,0,0,.82));
  height: 42px;
  padding: 0 6px;
  align-items: center;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
}

/* ── Progress / seek bar ── */
.video-js.apollo-dark-skin .vjs-progress-control {
  position: absolute;
  bottom: 41px;
  left: 0;
  right: 0;
  width: 100%;
  height: 4px;
  transition: height .15s;
}
.video-js.apollo-dark-skin .vjs-progress-control:hover,
.video-js.apollo-dark-skin .vjs-progress-control:focus-within {
  height: 6px;
}
.video-js.apollo-dark-skin .vjs-progress-holder {
  height: 100%;
  margin: 0;
  border-radius: 0;
}
.video-js.apollo-dark-skin .vjs-load-progress,
.video-js.apollo-dark-skin .vjs-load-progress div {
  background: var(--vjs-bar-hover);
  border-radius: 0;
}
.video-js.apollo-dark-skin .vjs-play-progress {
  background: var(--vjs-accent);
  border-radius: 0;
}
.video-js.apollo-dark-skin .vjs-play-progress::before {
  top: -4px;
  font-size: 14px;
  color: var(--vjs-accent);
  text-shadow: 0 0 6px rgba(0,0,0,.5);
}

/* ── Volume ── */
.video-js.apollo-dark-skin .vjs-volume-level {
  background: var(--vjs-accent);
}
.video-js.apollo-dark-skin .vjs-volume-bar.vjs-slider-horizontal {
  background: var(--vjs-bar-bg);
}
.video-js.apollo-dark-skin .vjs-volume-panel {
  width: auto;
}
.video-js.apollo-dark-skin .vjs-volume-panel .vjs-volume-control.vjs-volume-horizontal {
  width: 5em;
  transition: width .2s, opacity .2s;
}

/* ── Buttons / icons ── */
.video-js.apollo-dark-skin .vjs-button > .vjs-icon-placeholder::before,
.video-js.apollo-dark-skin .vjs-menu-button::before {
  color: var(--vjs-icon);
  font-size: 1.6em;
  line-height: 42px;
  transition: color .15s;
}
.video-js.apollo-dark-skin .vjs-button:hover > .vjs-icon-placeholder::before {
  color: var(--vjs-text);
}

/* ── Time display ── */
.video-js.apollo-dark-skin .vjs-current-time,
.video-js.apollo-dark-skin .vjs-time-divider,
.video-js.apollo-dark-skin .vjs-duration {
  display: block;
  font-size: 12px;
  color: rgba(255,255,255,.75);
  padding: 0 2px;
  line-height: 42px;
  min-width: 0;
}
.video-js.apollo-dark-skin .vjs-time-divider { padding: 0; }

/* ── Menus (playback rate, quality) ── */
.video-js.apollo-dark-skin .vjs-menu-content {
  background: #111;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: var(--vjs-radius);
  padding: 4px 0;
  font-size: 13px;
}
.video-js.apollo-dark-skin .vjs-menu-item {
  color: rgba(255,255,255,.75);
  padding: 6px 18px;
}
.video-js.apollo-dark-skin .vjs-menu-item:hover,
.video-js.apollo-dark-skin .vjs-menu-item:focus {
  color: var(--vjs-text);
  background: rgba(255,255,255,.07);
  outline: none;
}
.video-js.apollo-dark-skin .vjs-menu-item.vjs-selected,
.video-js.apollo-dark-skin .vjs-menu-item.vjs-selected:hover {
  color: var(--vjs-accent);
  background: rgba(198,40,40,.08);
  font-weight: 600;
}

/* ── Tooltips ── */
.video-js.apollo-dark-skin .vjs-time-tooltip,
.video-js.apollo-dark-skin .vjs-mouse-display .vjs-time-tooltip {
  background: rgba(0,0,0,.8);
  color: #fff;
  border-radius: var(--vjs-radius);
  font-size: 11px;
  padding: 2px 6px;
}

/* ── Loading spinner ── */
.video-js.apollo-dark-skin .vjs-loading-spinner {
  border-color: var(--vjs-accent);
}
.video-js.apollo-dark-skin .vjs-loading-spinner::before,
.video-js.apollo-dark-skin .vjs-loading-spinner::after {
  border-top-color: var(--vjs-accent);
}

/* ── Error display ── */
.video-js.apollo-dark-skin .vjs-error-display {
  background: rgba(0,0,0,.85);
}
.video-js.apollo-dark-skin .vjs-error-display .vjs-modal-dialog-content {
  color: #fff;
  font-size: 14px;
}

/* ── Picture-in-picture ── */
.video-js.apollo-dark-skin .vjs-picture-in-picture-control .vjs-icon-placeholder::before {
  font-size: 1.5em;
}

/* ── Paywall overlay ── */
.apollo-paywall {
  background: #111;
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 24px;
  text-align: center;
  aspect-ratio: 16/9;
}
.apollo-paywall__icon { color: var(--vjs-accent); }
.apollo-paywall__title { font-size: 1.25rem; font-weight: 700; margin: 0; }
.apollo-paywall__desc  { color: rgba(255,255,255,.65); font-size: .9rem; margin: 0; }
.apollo-paywall__actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
.apollo-paywall__cta {
  display: inline-block;
  background: var(--vjs-accent);
  color: #fff;
  padding: 10px 24px;
  border-radius: 3px;
  font-weight: 700;
  font-size: .9rem;
  text-decoration: none;
  transition: opacity .2s;
}
.apollo-paywall__cta:hover { opacity: .85; }
.apollo-paywall__login {
  display: inline-block;
  border: 1px solid rgba(255,255,255,.3);
  color: rgba(255,255,255,.7);
  padding: 10px 24px;
  border-radius: 3px;
  font-size: .9rem;
  text-decoration: none;
  transition: border-color .2s, color .2s;
}
.apollo-paywall__login:hover { border-color: #fff; color: #fff; }

/* ── Empty state ── */
.apollo-player-empty {
  background: #111;
  color: rgba(255,255,255,.5);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 48px 24px;
  text-align: center;
  aspect-ratio: 16/9;
  border-radius: 4px;
}
.apollo-player-empty p { margin: 0; font-size: .9rem; }
.apollo-player-empty .button {
  background: var(--vjs-accent);
  color: #fff;
  border: none;
  padding: 8px 20px;
  border-radius: 3px;
  font-size: .85rem;
  text-decoration: none;
}

/* ── YouTube embed ── */
.apollo-yt-embed { border-radius: 4px; overflow: hidden; }
';
}

// ═══════════════════════════════════════════════════════════════════════════
// ENQUEUE VIDEO.JS + PLAYER JS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! is_singular( 'serve_video' ) && ! is_post_type_archive( 'serve_video' ) ) return;

	// Video.js from CDN.
	wp_enqueue_style(  'videojs', VICINITY_VIDEOJS_CDN . '/video-min.css', [], VICINITY_VIDEOJS_VERSION );
	wp_enqueue_script( 'videojs', VICINITY_VIDEOJS_CDN . '/video.min.js',  [], VICINITY_VIDEOJS_VERSION, true );

	// Apollo dark skin + accent color overrides.
	$accent = sanitize_hex_color( get_theme_mod( 'videojs_accent_color', '' ) ?: get_theme_mod( 'vicinity_accent_color', '#c62828' ) );
	wp_add_inline_style( 'videojs', vicinity_videojs_skin_css( $accent ) );

	// Our player glue script.
	$js_path = VICINITY_PATH . 'assets/js/video-player.js';
	if ( ! file_exists( $js_path ) ) return;

	wp_enqueue_script(
		'apollo-video-player',
		VICINITY_URL . 'assets/js/video-player.js',
		[ 'videojs' ],
		VICINITY_VERSION,
		true
	);

	wp_localize_script( 'apollo-video-player', 'apolloVideo', [
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'vicinity_video_view' ),
		'postId'    => get_the_ID() ?: 0,
		'pingAfter' => 10,
	] );
} );

// Also enqueue on front-page / homepage if video spotlight exists.
add_action( 'wp_footer', static function (): void {
	if ( ! is_front_page() ) return;
	if ( wp_script_is( 'videojs', 'done' ) ) return;
	if ( ! is_active_widget( false, false, 'serve_video', true ) ) return;

	wp_enqueue_style(  'videojs', VICINITY_VIDEOJS_CDN . '/video-min.css', [], VICINITY_VIDEOJS_VERSION );
	wp_enqueue_script( 'videojs', VICINITY_VIDEOJS_CDN . '/video.min.js',  [], VICINITY_VIDEOJS_VERSION, true );

	$accent = sanitize_hex_color( get_theme_mod( 'videojs_accent_color', '' ) ?: '#c62828' );
	wp_add_inline_style( 'videojs', vicinity_videojs_skin_css( $accent ) );
}, 5 );

// ═══════════════════════════════════════════════════════════════════════════
// GUTENBERG SIDEBAR ENQUEUE (serve_video editor)
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'enqueue_block_editor_assets', static function (): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'serve_video' ) return;

	$js_path = VICINITY_PATH . 'assets/js/video-editor.js';
	if ( ! file_exists( $js_path ) ) return;

	wp_enqueue_script(
		'apollo-video-editor',
		VICINITY_URL . 'assets/js/video-editor.js',
		[ 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
		VICINITY_VERSION,
		true
	);

	$r2_cfg = vicinity_video_r2_config();
	$s3_cfg = vicinity_s3_config();

	wp_localize_script( 'apollo-video-editor', 'apolloVideoEditor', [
		'nonceUpload' => wp_create_nonce( 'vicinity_video_upload' ),
		'nonceThumb'  => wp_create_nonce( 'vicinity_video_thumb_' . get_the_ID() ),
		'r2Ready'     => vicinity_r2_video_ready(),
		'r2PublicUrl' => $r2_cfg['public_url'] ?? '',
		's3Ready'     => vicinity_s3_ready(),
		's3PublicUrl' => $s3_cfg['public_url'] ?? '',
		'postId'      => get_the_ID() ?: 0,
		'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
	] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// FFmpeg THUMBNAIL GENERATION AJAX
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_video_gen_thumb', 'vicinity_ajax_video_gen_thumb' );

function vicinity_ajax_video_gen_thumb(): void {
	check_ajax_referer( 'vicinity_video_thumb_' . absint( $_POST['post_id'] ?? 0 ), 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );

	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id ) wp_send_json_error( 'No post ID.' );

	$r2_key = (string) get_post_meta( $post_id, '_svh_r2_key', true );
	if ( ! $r2_key ) wp_send_json_error( 'No R2 key on this post.' );

	$ffmpeg = (string) get_option( 'vicinity_ffmpeg_path', '/usr/bin/ffmpeg' );
	if ( ! file_exists( $ffmpeg ) ) wp_send_json_error( 'FFmpeg not found at: ' . $ffmpeg );

	// Download video to temp.
	$cfg = vicinity_video_r2_config();
	$url = vicinity_r2_public_url( $r2_key, $cfg );
	if ( ! $url ) wp_send_json_error( 'Cannot resolve public URL.' );

	$tmp_video = wp_tempnam( 'vthumb_' ) . '.mp4';
	$tmp_thumb = wp_tempnam( 'vthumb_' ) . '.jpg';

	$dl = wp_remote_get( $url, [ 'timeout' => 120, 'stream' => true, 'filename' => $tmp_video ] );
	if ( is_wp_error( $dl ) ) wp_send_json_error( 'Download failed: ' . $dl->get_error_message() );

	// Get duration and extract frame at 25%.
	$probe_out = shell_exec( escapeshellarg( $ffmpeg ) . ' -i ' . escapeshellarg( $tmp_video ) . ' 2>&1' );
	$duration  = 0;
	if ( preg_match( '/Duration: (\d+):(\d+):(\d+)/', (string) $probe_out, $m ) ) {
		$duration = (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
	}
	$seek = max( 1, (int) ( $duration * 0.25 ) );

	$cmd = escapeshellarg( $ffmpeg )
		. ' -ss ' . escapeshellarg( (string) $seek )
		. ' -i ' . escapeshellarg( $tmp_video )
		. ' -vframes 1 -q:v 2 -y '
		. escapeshellarg( $tmp_thumb )
		. ' 2>&1';
	shell_exec( $cmd );

	@unlink( $tmp_video );

	if ( ! file_exists( $tmp_thumb ) ) wp_send_json_error( 'FFmpeg did not produce a thumbnail.' );

	// Sideload into Media Library.
	$upload = wp_upload_bits( 'thumb-p' . $post_id . '-' . $seek . '.jpg', null, file_get_contents( $tmp_thumb ) );
	@unlink( $tmp_thumb );

	if ( ! empty( $upload['error'] ) ) wp_send_json_error( 'Upload error: ' . $upload['error'] );

	$att_id = wp_insert_attachment( [
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'Video Thumbnail — Post ' . $post_id,
		'post_status'    => 'inherit',
	], $upload['file'], $post_id );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $upload['file'] ) );

	update_post_meta( $post_id, '_svh_thumb_id', $att_id );
	set_post_thumbnail( $post_id, $att_id );

	wp_send_json_success( [ 'thumb_id' => $att_id, 'thumb_url' => $upload['url'] ] );
}