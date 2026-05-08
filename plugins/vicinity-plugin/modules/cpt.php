<?php
/**
 * Custom Post Types, Taxonomies, and Post Meta registration.
 *
 * CPTs: serve_video, serve_podcast, serve_episode
 * Taxonomies: serve_video_category
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// 1. REGISTRATION
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', static function (): void {

	// ── serve_video ──────────────────────────────────────────────────
	register_post_type( 'serve_video', [
		'labels'             => [
			'name'          => __( 'Videos',      'vicinity' ),
			'singular_name' => __( 'Video',       'vicinity' ),
			'add_new_item'  => __( 'Add Video',   'vicinity' ),
			'edit_item'     => __( 'Edit Video',  'vicinity' ),
			'all_items'     => __( 'All Videos',  'vicinity' ),
			'menu_name'     => __( 'Video Hub',   'vicinity' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'menu_position'      => 6,
		'menu_icon'          => 'dashicons-video-alt3',
		'capability_type'    => 'post',
		'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ],
		'taxonomies'         => [ 'serve_video_category' ],
		'has_archive'        => true,
		'rewrite'            => [ 'slug' => 'videos', 'with_front' => false ],
	] );

	// ── serve_video_category (Series) ─────────────────────────────────────
	register_taxonomy( 'serve_video_category', 'serve_video', [
		'labels'            => [
			'name'          => __( 'Video Series', 'vicinity' ),
			'singular_name' => __( 'Series',       'vicinity' ),
			'menu_name'     => __( 'Series',        'vicinity' ),
		],
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'video-series', 'with_front' => false ],
	] );

	// ── serve_podcast ────────────────────────────────────────────────
	register_post_type( 'serve_podcast', [
		'labels'             => [
			'name'          => __( 'Podcasts',      'vicinity' ),
			'singular_name' => __( 'Podcast',       'vicinity' ),
			'add_new_item'  => __( 'Add Podcast',   'vicinity' ),
			'edit_item'     => __( 'Edit Podcast',  'vicinity' ),
			'all_items'     => __( 'All Podcasts',  'vicinity' ),
			'menu_name'     => __( 'Podcasts',      'vicinity' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'menu_position'      => 7,
		'menu_icon'          => 'dashicons-microphone',
		'capability_type'    => 'post',
		'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ],
		'has_archive'        => true,
		'rewrite'            => [ 'slug' => 'listen', 'with_front' => false ],
	] );

	// ── serve_episode ────────────────────────────────────────────────
	register_post_type( 'serve_episode', [
		'labels'             => [
			'name'          => __( 'Episodes',      'vicinity' ),
			'singular_name' => __( 'Episode',       'vicinity' ),
			'add_new_item'  => __( 'Add Episode',   'vicinity' ),
			'edit_item'     => __( 'Edit Episode',  'vicinity' ),
			'all_items'     => __( 'All Episodes',  'vicinity' ),
			'menu_name'     => __( 'Episodes',      'vicinity' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => 'edit.php?post_type=serve_podcast',
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-format-audio',
		'capability_type'    => 'post',
		'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ],
		'has_archive'        => true,
		'rewrite'            => [ 'slug' => 'episodes', 'with_front' => false ],
	] );

	// ── Podcast RSS rewrite ───────────────────────────────────────────────
	add_rewrite_rule( '^feed/podcast/([^/]+)/?$', 'index.php?feed=podcast_rss&podcast_slug=$matches[1]', 'top' );
	add_rewrite_tag( '%podcast_slug%', '([^/]+)' );

} );

// ─── Flush rewrites after theme switch / first boot ──────────────────
add_action( 'after_switch_theme', static function (): void { flush_rewrite_rules(); } );
add_action( 'init', static function (): void {
	if ( get_transient( 'vicinity_cpt_rewrites_flushed' ) ) return;
	flush_rewrite_rules();
	set_transient( 'vicinity_cpt_rewrites_flushed', '1', YEAR_IN_SECONDS );
}, 999 );

// ═══════════════════════════════════════════════════════════════════════════
// 2. VIDEO META
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', static function (): void {
	$auth = fn() => current_user_can( 'edit_posts' );

	$video_meta = [
		'_svh_r2_key'      => 'string',   // R2 object key — primary playback source
		'_svh_wp_media_id' => 'integer',  // WP attachment ID — self-hosted fallback
		'_svh_youtube_id'  => 'string',   // YouTube video ID — embed fallback
		'_svh_duration'    => 'string',   // Human-readable: "4:32"
		'_svh_views'       => 'integer',  // View counter
		'_svh_featured'    => 'string',   // '1' = pin to hero
		'_svh_format'      => 'string',   // '' = 16:9 | 'short' = 9:16
		'_svh_paywall'     => 'string',   // '1' = members only
		'_svh_transcript'  => 'string',   // Full text transcript
		'_svh_thumb_id'    => 'integer',  // JPEG snapshot at 25% (FFmpeg)
		'_svh_preview_mp4' => 'string',   // Cloudflare Media Transform preview URL
	];

	foreach ( $video_meta as $meta_key => $type ) {
		register_post_meta( 'serve_video', $meta_key, [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => $type,
			'auth_callback' => $auth,
		] );
	}
} );

// ═══════════════════════════════════════════════════════════════════════════
// 3. EPISODE / PODCAST META
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', static function (): void {
	$auth = fn() => current_user_can( 'edit_posts' );

	// Episode audio source + metadata
	$episode_meta = [
		'_ep_r2_key'        => 'string',   // R2 object key for the audio file
		'_ep_wp_media_id'   => 'integer',  // WP attachment ID — self-hosted fallback
		'_ep_duration'      => 'string',   // HH:MM:SS (for RSS)
		'_ep_file_size'     => 'integer',  // Bytes (for RSS enclosure)
		'_ep_season'        => 'integer',  // Season number
		'_ep_episode'       => 'integer',  // Episode number
		'_ep_episode_type'  => 'string',   // full | trailer | bonus
		'_ep_explicit'      => 'string',   // 'yes' | 'no'
		'_ep_podcast_id'    => 'integer',  // Parent serve_podcast post ID
		'_ep_icon_image_id' => 'integer',  // WP attachment ID for episode icon
	];

	foreach ( $episode_meta as $meta_key => $type ) {
		register_post_meta( 'serve_episode', $meta_key, [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => $type,
			'auth_callback' => $auth,
		] );
	}

	// Podcast show-level meta
	$podcast_meta = [
		'_pod_category'       => 'string',   // Apple category, e.g. "News"
		'_pod_subcategory'    => 'string',   // Apple subcategory
		'_pod_language'       => 'string',   // RFC 5646 language tag, e.g. "en-us"
		'_pod_explicit'       => 'string',   // 'yes' | 'no'
		'_pod_author'         => 'string',   // Show author / owner name
		'_pod_owner_email'    => 'string',   // Owner email (iTunes)
		'_pod_hub_url'        => 'string',   // WebSub hub URL
		'_pod_spotify_id'     => 'string',   // Spotify podcast ID
		'_pod_copyright'      => 'string',   // Copyright string
	];

	foreach ( $podcast_meta as $meta_key => $type ) {
		register_post_meta( 'serve_podcast', $meta_key, [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => $type,
			'auth_callback' => $auth,
		] );
	}
} );

// ═══════════════════════════════════════════════════════════════════════════
// 4. ADMIN LIST COLUMNS — VIDEO
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'manage_serve_video_posts_columns', static function ( array $cols ): array {
	$new = [];
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( $k === 'title' ) {
			$new['svh_thumb']   = __( 'Thumb',    'vicinity' );
			$new['svh_format']  = __( 'Format',   'vicinity' );
			$new['svh_paywall'] = '🔒';
			$new['svh_dur']     = __( 'Duration', 'vicinity' );
			$new['svh_views']   = __( 'Views',    'vicinity' );
		}
	}
	return $new;
} );

add_action( 'manage_serve_video_posts_custom_column', static function ( string $col, int $id ): void {
	match ( $col ) {
		'svh_thumb'   => ( function () use ( $id ) {
			$t = get_the_post_thumbnail_url( $id, [ 80, 45 ] );
			if ( $t ) {
				echo '<img src="' . esc_url( $t ) . '" width="80" height="45" style="object-fit:cover;border-radius:2px;">';
			}
		} )(),
		'svh_format'  => print( get_post_meta( $id, '_svh_format', true ) === 'short' ? '📱 Short' : '📺 Video' ),
		'svh_paywall' => print( get_post_meta( $id, '_svh_paywall', true ) ? '🔒' : '—' ),
		'svh_dur'     => ( function () use ( $id ) {
			$d = get_post_meta( $id, '_svh_duration', true );
			echo $d ? '<code>' . esc_html( $d ) . '</code>' : '—';
		} )(),
		'svh_views'   => print( esc_html( number_format_i18n( absint( get_post_meta( $id, '_svh_views', true ) ) ) ) ),
		default       => null,
	};
}, 10, 2 );

// ═══════════════════════════════════════════════════════════════════════════
// 5. AUTO-SET _svh_format = 'short' WHEN ASSIGNED TO SHORTS CATEGORY
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'save_post_serve_video', static function ( int $post_id ): void {
	$shorts_slug = (string) get_option( 'vicinity_shorts_category', '' );
	if ( ! $shorts_slug ) {
		$term = get_term_by( 'name', 'Shorts', 'serve_video_category' )
			 ?: get_term_by( 'slug', 'shorts', 'serve_video_category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$shorts_slug = $term->slug;
		}
	}
	if ( ! $shorts_slug ) return;

	$terms = get_the_terms( $post_id, 'serve_video_category' );
	if ( ! is_wp_error( $terms ) && $terms ) {
		if ( in_array( $shorts_slug, array_column( $terms, 'slug' ), true ) ) {
			update_post_meta( $post_id, '_svh_format', 'short' );
		}
	}
}, 8 );

// ═══════════════════════════════════════════════════════════════════════════
// 6. ADMIN LIST COLUMNS — EPISODE
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'manage_serve_episode_posts_columns', static function ( array $cols ): array {
	$new = [];
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( $k === 'title' ) {
			$new['ep_season']  = __( 'Season', 'vicinity' );
			$new['ep_episode'] = __( 'Ep #',   'vicinity' );
			$new['ep_dur']     = __( 'Duration', 'vicinity' );
		}
	}
	return $new;
} );

add_action( 'manage_serve_episode_posts_custom_column', static function ( string $col, int $id ): void {
	match ( $col ) {
		'ep_season'  => print( esc_html( get_post_meta( $id, '_ep_season', true ) ?: '—' ) ),
		'ep_episode' => print( esc_html( get_post_meta( $id, '_ep_episode', true ) ?: '—' ) ),
		'ep_dur'     => print( esc_html( get_post_meta( $id, '_ep_duration', true ) ?: '—' ) ),
		default      => null,
	};
}, 10, 2 );