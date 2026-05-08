<?php
/**
 * Apollo Theme — functions.php
 *
 * Clean bootstrap. No business logic. Load order:
 *   1. Theme support + image sizes
 *   2. Enqueue fonts + styles + scripts
 *   3. Menus + sidebars
 *   4. Customizer (inc/customizer.php)
 *   5. Template helpers (inc/helpers.php)
 *   6. Search overlay (inc/search.php)
 *
 * @package Apollo
 * @version 3.0.1
 */

defined( 'ABSPATH' ) || exit;

define( 'VICINITY_THEME_VERSION', '3.1.1' );
define( 'VICINITY_THEME_URI',     get_template_directory_uri() );
define( 'VICINITY_THEME_DIR',     get_template_directory() );

// ═══════════════════════════════════════════════════════════════════════════
// SETUP
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'after_setup_theme', 'vicinity_theme_setup' );

function vicinity_theme_setup(): void {

	load_theme_textdomain( 'vicinity', VICINITY_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );

	// Custom logo.
	add_theme_support( 'custom-logo', [
		'height'      => 80,
		'width'       => 320,
		'flex-height' => true,
		'flex-width'  => true,
	] );

	// Content width.
	global $content_width;
	if ( ! isset( $content_width ) ) $content_width = 1280;

	// Image sizes.
	add_image_size( 'apollo-hero',       1280, 720,  true );  // 16:9 full
	add_image_size( 'apollo-card-lg',    800,  450,  true );  // card large
	add_image_size( 'apollo-card-sm',    400,  225,  true );  // card small
	add_image_size( 'apollo-card-sq',    400,  400,  true );  // square
	add_image_size( 'apollo-portrait',   300,  450,  true );  // episode icon
	add_image_size( 'apollo-wide',       1280, 400,  true );  // panoramic

	// Menus.
	register_nav_menus( [
		'primary'   => __( 'Primary Navigation', 'vicinity' ),
		'footer-1'  => __( 'Footer — Coverage', 'vicinity' ),
		'footer-2'  => __( 'Footer — Company', 'vicinity' ),
		'footer-3'  => __( 'Footer — Legal', 'vicinity' ),
	] );
}

// ═══════════════════════════════════════════════════════════════════════════
// ENQUEUE
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_enqueue_scripts', 'vicinity_theme_enqueue' );

function vicinity_theme_enqueue(): void {

	// Google Fonts — Zilla Slab (400, 400i, 600, 700) + Libre Franklin (400, 400i, 600, 700).
	$font_url = 'https://fonts.googleapis.com/css2?'
		. 'family=Zilla+Slab:ital,wght@0,400;0,600;0,700;1,400&'
		. 'family=Libre+Franklin:ital,wght@0,400;0,600;0,700;1,400&'
		. 'display=swap';

	wp_enqueue_style( 'apollo-fonts', $font_url, [], null );

	// Main stylesheet.
	wp_enqueue_style( 'apollo-style', get_stylesheet_uri(), [ 'apollo-fonts' ], VICINITY_THEME_VERSION );

	// Theme JS — navigation + search overlay.
	wp_enqueue_script( 'vicinity-theme', VICINITY_THEME_URI . '/assets/js/theme.js', [], VICINITY_THEME_VERSION, true );

	// Pass data to JS.
	wp_localize_script( 'vicinity-theme', 'apolloTheme', [
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'vicinity_search' ),
		'isHome'   => is_front_page() ? '1' : '0',
	] );

	// Comment reply.
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// WIDGETS / SIDEBARS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'widgets_init', 'vicinity_register_sidebars' );

function vicinity_register_sidebars(): void {

	$defaults = [
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	];

	register_sidebar( array_merge( $defaults, [
		'name' => __( 'Archive Sidebar', 'vicinity' ),
		'id'   => 'archive-sidebar',
		'description' => __( 'Sidebar displayed on archive and category pages.', 'vicinity' ),
	] ) );

	register_sidebar( array_merge( $defaults, [
		'name' => __( 'Article Sidebar', 'vicinity' ),
		'id'   => 'article-sidebar',
		'description' => __( 'Sidebar displayed on single posts.', 'vicinity' ),
	] ) );

	register_sidebar( array_merge( $defaults, [
		'name' => __( 'Footer Col 1', 'vicinity' ),
		'id'   => 'footer-1',
	] ) );
}

// ═══════════════════════════════════════════════════════════════════════════
// INCLUDES
// ═══════════════════════════════════════════════════════════════════════════

require_once VICINITY_THEME_DIR . '/inc/customizer.php';
require_once VICINITY_THEME_DIR . '/inc/helpers.php';
require_once VICINITY_THEME_DIR . '/inc/search.php';

// ═══════════════════════════════════════════════════════════════════════════
// BODY CLASSES
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'body_class', static function ( array $classes ): array {
	if ( is_singular( 'serve_video' ) )   $classes[] = 'is-video-single';
	if ( is_singular( 'serve_episode' ) ) $classes[] = 'is-episode-single';
	if ( is_singular( 'serve_podcast' ) ) $classes[] = 'is-podcast-single';
	if ( is_post_type_archive( 'serve_video' ) ) $classes[] = 'is-video-hub';
	return array_unique( $classes );
} );

// ═══════════════════════════════════════════════════════════════════════════
// EXCERPT
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'excerpt_length', static fn() => 25 );
add_filter( 'excerpt_more',   static fn() => '…' );

// ═══════════════════════════════════════════════════════════════════════════
// NAV MENU LABEL OVERRIDES
// Applies the custom labels set in Customizer → Navigation to any menu item
// whose URL matches the Videos or Podcasts CPT archives.
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'wp_nav_menu_objects', 'vicinity_apply_nav_labels', 10, 2 );

function vicinity_apply_nav_labels( array $items ): array {
	$videos_url     = get_post_type_archive_link( 'serve_video' );
	$podcasts_url   = get_post_type_archive_link( 'serve_podcast' );
	$videos_label   = (string) get_theme_mod( 'vicinity_nav_videos_label', 'Watch' );
	$podcasts_label = (string) get_theme_mod( 'vicinity_nav_podcasts_label', 'Listen' );

	foreach ( $items as $item ) {
		$item_url = rtrim( $item->url ?? '', '/' );
		if ( $videos_url && $item_url === rtrim( $videos_url, '/' ) && $videos_label ) {
			$item->title = $videos_label;
		} elseif ( $podcasts_url && $item_url === rtrim( $podcasts_url, '/' ) && $podcasts_label ) {
			$item->title = $podcasts_label;
		}
	}
	return $items;
}

// ═══════════════════════════════════════════════════════════════════════════
// RELATIVE DATES HELPER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_relative_date( int $timestamp ): string {
	$diff = time() - $timestamp;
	if ( $diff < 3600 )  return sprintf( __( '%d min ago', 'vicinity' ), max( 1, round( $diff / 60 ) ) );
	if ( $diff < 86400 ) return sprintf( __( '%d hr ago', 'vicinity' ), round( $diff / 3600 ) );
	if ( $diff < 86400 * 7 ) return sprintf( __( '%d days ago', 'vicinity' ), round( $diff / 86400 ) );
	return get_the_date( 'M j, Y', 0 );
}

if ( ! function_exists( 'wp_footer_sidebar_area' ) ) {
	function wp_footer_sidebar_area(): void {
		// Placeholder — ad zone or sidebar widgets can go here.
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// PLUGIN COMPATIBILITY STUBS
// These no-op functions prevent fatal errors when the Apollo plugin is
// inactive or when optional modules (e.g. ads) have been removed.
// ═══════════════════════════════════════════════════════════════════════════

if ( ! function_exists( 'vicinity_ad_zone' ) ) {
	/**
	 * Stub: Ad zone placeholder. Replaced by the Apollo plugin's Ad Manager
	 * when the ads module is active. Currently outputs nothing.
	 *
	 * @param string $zone_id  Zone identifier.
	 * @param array  $args     Optional args.
	 */
	function vicinity_ad_zone( string $zone_id, array $args = [] ): void {
		// Intentionally empty — ads module not loaded.
	}
}

if ( ! function_exists( 'vicinity_get_ad_zone' ) ) {
	function vicinity_get_ad_zone( string $zone_id, array $args = [] ): string {
		return '';
	}
}

/**
 * Theme-facing alias for the plugin's vicinity_get_video_player().
 * Allows templates to call vicinity_video_player_html() regardless of
 * whether the plugin version uses the old or new function name.
 */
if ( ! function_exists( 'vicinity_video_player_html' ) ) {
	function vicinity_video_player_html( int $post_id, array $opts = [] ): string {
		if ( function_exists( 'vicinity_get_video_player' ) ) {
			return vicinity_get_video_player( $post_id, $opts );
		}
		// Fallback: plain HTML5 video element.
		$src = (string) get_post_meta( $post_id, '_vicinity_video_src', true );
		if ( ! $src ) return '';
		return '<video class="video-js vjs-big-play-centered" controls preload="metadata" width="1280" height="720">'
			. '<source src="' . esc_url( $src ) . '" type="video/mp4">'
			. '</video>';
	}
}
