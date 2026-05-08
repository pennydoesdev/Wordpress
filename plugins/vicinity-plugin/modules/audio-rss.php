<?php
/**
 * Podcast RSS Feed — iTunes/Spotify-compatible XML for serve_podcast CPT.
 *
 * Feed URL:  /feed/podcast/{podcast-slug}/
 * Example:   /feed/podcast/the-penny-brief/
 *
 * Ping targets on episode publish: iTunes, Spotify, Google, PocketCasts, Overcast, Podcast Index
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// FEED HANDLER
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'do_feed_podcast_rss', 'vicinity_render_podcast_feed', 10, false );

function vicinity_render_podcast_feed(): void {
	$podcast_slug = get_query_var( 'podcast_slug' );
	$podcast      = $podcast_slug
		? get_posts( [ 'post_type' => 'serve_podcast', 'name' => $podcast_slug, 'posts_per_page' => 1, 'post_status' => 'publish' ] )
		: null;

	if ( ! $podcast ) {
		status_header( 404 );
		echo '<?xml version="1.0" encoding="UTF-8"?><error>Podcast not found</error>';
		exit;
	}

	$show = $podcast[0];
	header( 'Content-Type: application/rss+xml; charset=UTF-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: public, max-age=3600' );

	// Resolve show-level metadata.
	$meta = static function( string $key, string $default = '' ) use ( $show ): string {
		return (string) ( get_post_meta( $show->ID, $key, true ) ?: $default );
	};

	$show_title   = get_the_title( $show );
	$show_desc    = $show->post_content ?: $show->post_excerpt ?: '';
	$show_url     = get_permalink( $show );
	$show_img_url = get_the_post_thumbnail_url( $show, 'full' ) ?: '';
	$show_author  = $meta( '_pod_author', get_the_author_meta( 'display_name', $show->post_author ) );
	$owner_email  = $meta( '_pod_owner_email', get_option( 'admin_email' ) );
	$language     = $meta( '_pod_language', get_option( 'rss_language', 'en' ) );
	$explicit     = $meta( '_pod_explicit', 'no' );
	$category     = $meta( '_pod_category', 'News' );
	$sub_category = $meta( '_pod_subcategory', '' );
	$copyright    = $meta( '_pod_copyright', '© ' . gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) );
	$hub_url      = $meta( '_pod_hub_url', 'https://pubsubhubbub.appspot.com' );

	$feed_url = home_url( '/feed/podcast/' . $podcast_slug . '/' );

	// Episodes — most recent 300.
	$episodes = get_posts( [
		'post_type'      => 'serve_episode',
		'post_status'    => 'publish',
		'posts_per_page' => 300,
		'meta_query'     => [ [ 'key' => '_ep_podcast_id', 'value' => $show->ID, 'compare' => '=' ] ],
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	?>
<rss version="2.0"
	xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
	xmlns:podcast="https://podcastindex.org/namespace/1.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><?php echo esc_xml( $show_title ); ?></title>
	<link><?php echo esc_url( $show_url ); ?></link>
	<description><?php echo esc_xml( wp_strip_all_tags( $show_desc ) ); ?></description>
	<language><?php echo esc_xml( $language ); ?></language>
	<copyright><?php echo esc_xml( $copyright ); ?></copyright>
	<lastBuildDate><?php echo esc_xml( gmdate( 'r' ) ); ?></lastBuildDate>
	<atom:link href="<?php echo esc_url( $feed_url ); ?>" rel="self" type="application/rss+xml"/>
	<atom:link rel="hub" href="<?php echo esc_url( $hub_url ); ?>"/>
	<itunes:author><?php echo esc_xml( $show_author ); ?></itunes:author>
	<itunes:owner>
		<itunes:name><?php echo esc_xml( $show_author ); ?></itunes:name>
		<itunes:email><?php echo esc_xml( $owner_email ); ?></itunes:email>
	</itunes:owner>
	<itunes:explicit><?php echo esc_xml( $explicit ); ?></itunes:explicit>
	<itunes:category text="<?php echo esc_attr( $category ); ?>">
		<?php if ( $sub_category ) : ?><itunes:category text="<?php echo esc_attr( $sub_category ); ?>"/><?php endif; ?>
	</itunes:category>
	<?php if ( $show_img_url ) : ?>
	<itunes:image href="<?php echo esc_url( $show_img_url ); ?>"/>
	<image>
		<url><?php echo esc_url( $show_img_url ); ?></url>
		<title><?php echo esc_xml( $show_title ); ?></title>
		<link><?php echo esc_url( $show_url ); ?></link>
	</image>
	<?php endif; ?>

	<?php foreach ( $episodes as $ep ) :
		$ep_r2_key   = (string) get_post_meta( $ep->ID, '_ep_r2_key', true );
		$ep_wp_mid   = absint( get_post_meta( $ep->ID, '_ep_wp_media_id', true ) );
		$ep_duration = (string) get_post_meta( $ep->ID, '_ep_duration', true );
		$ep_size     = absint( get_post_meta( $ep->ID, '_ep_file_size', true ) );
		$ep_season   = absint( get_post_meta( $ep->ID, '_ep_season', true ) );
		$ep_number   = absint( get_post_meta( $ep->ID, '_ep_episode', true ) );
		$ep_type     = (string) get_post_meta( $ep->ID, '_ep_episode_type', true ) ?: 'full';
		$ep_explicit = (string) get_post_meta( $ep->ID, '_ep_explicit', true ) ?: $explicit;
		$ep_img      = get_the_post_thumbnail_url( $ep->ID, 'full' ) ?: $show_img_url;
		$ep_desc     = $ep->post_content ?: $ep->post_excerpt ?: '';

		// Resolve audio URL.
		$audio_url = '';
		if ( $ep_r2_key ) {
			$cfg = vicinity_audio_r2_config();
			$audio_url = vicinity_r2_public_url( $ep_r2_key, $cfg );
		} elseif ( $ep_wp_mid ) {
			$audio_url = (string) wp_get_attachment_url( $ep_wp_mid );
		}
		if ( ! $audio_url ) continue; // Skip episodes with no audio.

		// GUID — always the permalink.
		$guid = get_permalink( $ep->ID );
		?>
	<item>
		<title><?php echo esc_xml( get_the_title( $ep->ID ) ); ?></title>
		<link><?php echo esc_url( $guid ); ?></link>
		<guid isPermaLink="true"><?php echo esc_url( $guid ); ?></guid>
		<pubDate><?php echo esc_xml( gmdate( 'r', strtotime( $ep->post_date_gmt ) ) ); ?></pubDate>
		<description><?php echo esc_xml( wp_strip_all_tags( $ep_desc ) ); ?></description>
		<content:encoded><![CDATA[<?php echo wp_kses_post( $ep_desc ); ?>]]></content:encoded>
		<enclosure url="<?php echo esc_url( $audio_url ); ?>" length="<?php echo esc_attr( $ep_size ); ?>" type="audio/mpeg"/>
		<itunes:duration><?php echo esc_xml( $ep_duration ); ?></itunes:duration>
		<itunes:explicit><?php echo esc_xml( $ep_explicit ); ?></itunes:explicit>
		<?php if ( $ep_img ) : ?><itunes:image href="<?php echo esc_url( $ep_img ); ?>"/><?php endif; ?>
		<?php if ( $ep_season ) : ?><itunes:season><?php echo esc_xml( (string) $ep_season ); ?></itunes:season><?php endif; ?>
		<?php if ( $ep_number ) : ?><itunes:episode><?php echo esc_xml( (string) $ep_number ); ?></itunes:episode><?php endif; ?>
		<itunes:episodeType><?php echo esc_xml( $ep_type ); ?></itunes:episodeType>
		<itunes:author><?php echo esc_xml( $show_author ); ?></itunes:author>
	</item>
	<?php endforeach; ?>
</channel>
</rss>
	<?php
	exit;
}

// ─── esc_xml helper (WP does not ship this) ──────────────────────────────
if ( ! function_exists( 'esc_xml' ) ) {
	function esc_xml( string $s ): string {
		return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// HTTP RESPONSE HEADERS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'template_redirect', static function (): void {
	if ( ! get_query_var( 'podcast_slug' ) ) return;
	header( 'X-Podcasting-App: Penny Tribune' );
} );

// ═══════════════════════════════════════════════════════════════════════════
// WEBSUB + SERVICE PINGS ON EPISODE PUBLISH
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'publish_serve_episode', static function ( int $post_id ): void {
	wp_schedule_single_event( time() + 5, 'vicinity_ping_feeds', [ $post_id ] );
} );

add_action( 'vicinity_ping_feeds', 'vicinity_ping_podcast_services' );

function vicinity_ping_podcast_services( int $post_id ): void {
	$podcast_id = absint( get_post_meta( $post_id, '_ep_podcast_id', true ) );
	if ( ! $podcast_id ) return;

	$podcast = get_post( $podcast_id );
	if ( ! $podcast || $podcast->post_type !== 'serve_podcast' ) return;

	$slug     = $podcast->post_name;
	$feed_url = home_url( '/feed/podcast/' . $slug . '/' );

	// WebSub (PubSubHubbub) — POST hub.mode=publish.
	$hub_url = (string) get_post_meta( $podcast_id, '_pod_hub_url', true ) ?: 'https://pubsubhubbub.appspot.com';
	wp_remote_post( $hub_url, [
		'body'    => http_build_query( [ 'hub.mode' => 'publish', 'hub.url' => $feed_url ] ),
		'timeout' => 10,
	] );

	// Direct pings to podcast index services.
	$pings = [
		// iTunes / Apple
		'https://podcastsconnect.apple.com/ping?urlenc=' . rawurlencode( $feed_url ),
		// Podcast Index
		'https://api.podcastindex.org/notify?feedUrl=' . rawurlencode( $feed_url ),
		// Google Podcasts (deprecated but harmless)
		'https://pubsubhubbub.appspot.com/?hub.mode=publish&hub.url=' . rawurlencode( $feed_url ),
	];

	foreach ( $pings as $url ) {
		wp_remote_get( $url, [ 'timeout' => 10, 'blocking' => false ] );
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// GUTENBERG SIDEBAR ENQUEUE — EPISODE
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'enqueue_block_editor_assets', static function (): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'serve_episode' ) return;

	$js_path = VICINITY_PATH . 'assets/js/audio-editor.js';
	if ( ! file_exists( $js_path ) ) return;

	wp_enqueue_script(
		'apollo-audio-editor',
		VICINITY_URL . 'assets/js/audio-editor.js',
		[ 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
		VICINITY_VERSION,
		true
	);

	$cfg = vicinity_audio_r2_config();
	wp_localize_script( 'apollo-audio-editor', 'apolloAudioEditor', [
		'nonceUpload' => wp_create_nonce( 'vicinity_audio_upload' ),
		'r2Ready'     => vicinity_r2_audio_ready(),
		'r2PublicUrl' => $cfg['public_url'] ?? '',
		'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
		'postId'      => get_the_ID() ?: 0,
	] );
} );