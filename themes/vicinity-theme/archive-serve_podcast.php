<?php
/**
 * Apollo Theme — Podcast Hub archive (NYT-style with blue accent)
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;
get_header();

global $wp_query;
$found_posts = (int) $wp_query->found_posts;
?>
<main id="primary" class="site-main podcast-hub-page" role="main">

<!-- HEADER -->
<div class="cat-header">
	<div class="cat-header__inner">
		<h1 class="cat-header__title"><?php esc_html_e( 'Podcasts', 'vicinity' ); ?></h1>
		<p class="cat-header__desc"><?php esc_html_e( 'Audio journalism and storytelling from the Penny Tribune.', 'vicinity' ); ?></p>
	</div>
</div>

<?php vicinity_ad_zone( 'after_header' ); ?>

<!-- PODCAST LIST -->
<div class="cat-body">
	<div class="cat-body__inner">
		<div class="cat-feed">
			<div class="cat-feed__tabs">
				<span class="cat-feed__tab is-active"><?php esc_html_e( 'All Podcasts', 'vicinity' ); ?></span>
				<span class="cat-feed__count"><?php printf( esc_html__( '%s shows', 'vicinity' ), number_format_i18n( $found_posts ) ); ?></span>
			</div>

			<?php if ( have_posts() ) : ?>
			<div class="podcast-list">
				<?php while ( have_posts() ) : the_post();
					$ep_count  = get_post_meta( get_the_ID(), '_vicinity_episode_count', true );
					$frequency = get_post_meta( get_the_ID(), '_vicinity_frequency', true );
					$rss_url   = home_url( '/podcast-feed/?podcast_id=' . get_the_ID() );
				?>
				<article class="podcast-list-item">
					<?php if ( has_post_thumbnail() ) : ?>
					<div class="podcast-list-art">
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'apollo-portrait', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
						</a>
					</div>
					<?php endif; ?>
					<div class="podcast-list-body">
						<h2 class="podcast-list-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>
						<div class="podcast-list-meta">
							<?php if ( $ep_count ) : ?>
								<span><?php printf( esc_html__( '%s episodes', 'vicinity' ), esc_html( $ep_count ) ); ?></span>
							<?php endif; ?>
							<?php if ( $frequency ) : ?>
								<span><?php echo esc_html( $frequency ); ?></span>
							<?php endif; ?>
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_the_time( 'U' ) ) ); ?></time>
						</div>
						<p class="podcast-list-desc"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<div class="podcast-list-actions" style="display:flex;gap:var(--sp-3);margin-top:var(--sp-3);">
							<a class="btn-subscribe" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Listen Now', 'vicinity' ); ?></a>
							<a class="cat-pill" href="<?php echo esc_url( $rss_url ); ?>" target="_blank" rel="noopener">RSS</a>
						</div>
					</div>

					<!-- Latest episodes mini-list -->
					<?php
					$latest_eps = new WP_Query( [
						'post_type'      => 'serve_episode',
						'posts_per_page' => 3,
						'meta_query'     => [ [
							'key'   => '_vicinity_podcast_id',
							'value' => get_the_ID(),
						] ],
					] );
					if ( $latest_eps->have_posts() ) :
					?>
					<div class="podcast-episodes-mini" style="border-left:1px solid var(--color-rule);padding-left:var(--sp-4);min-width:200px;flex-shrink:0;">
						<p style="font-family:var(--font-ui);font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--color-ink-muted);margin-bottom:var(--sp-2);"><?php esc_html_e( 'LATEST', 'vicinity' ); ?></p>
						<?php while ( $latest_eps->have_posts() ) : $latest_eps->the_post(); ?>
						<div style="margin-bottom:var(--sp-2);padding-bottom:var(--sp-2);border-bottom:1px solid var(--color-rule);">
							<a href="<?php the_permalink(); ?>" style="font-family:var(--font-headline);font-size:var(--text-sm);font-weight:700;color:var(--color-ink);text-decoration:none;line-height:1.35;display:block;margin-bottom:2px;"><?php the_title(); ?></a>
							<time style="font-family:var(--font-ui);font-size:11px;color:var(--color-ink-muted);" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_the_time( 'U' ) ) ); ?></time>
						</div>
						<?php endwhile; wp_reset_postdata(); ?>
					</div>
					<?php endif; ?>

				</article>
				<?php endwhile; ?>
			</div>
			<?php vicinity_pagination(); ?>
			<?php else : ?>
			<p class="no-stories"><?php esc_html_e( 'No podcasts found.', 'vicinity' ); ?></p>
			<?php endif; ?>
		</div>
		<aside class="cat-rail">
			<?php vicinity_ad_zone( 'sidebar_top' ); ?>
			<?php if ( is_active_sidebar( 'archive-sidebar' ) ) dynamic_sidebar( 'archive-sidebar' ); ?>
		</aside>
	</div>
</div>

</main>
<?php get_footer(); ?>