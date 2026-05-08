<?php
/**
 * Apollo Theme — Video category archive
 *
 * @package Apollo
 */
get_header();
$term = get_queried_object();
?>
<main id="primary" class="site-main" role="main">
	<div class="container">
		<header class="archive-header">
			<span class="kicker"><?php esc_html_e( 'Video Series', 'vicinity' ); ?></span>
			<h1 class="archive-header__title"><?php echo esc_html( $term->name ); ?></h1>
			<?php if ( $term->description ) : ?><div class="archive-header__desc"><?php echo esc_html( $term->description ); ?></div><?php endif; ?>
		</header>
		<?php if ( have_posts() ) : ?>
		<div class="video-carousel">
			<?php while ( have_posts() ) : the_post(); ?>
			<article class="video-card">
				<a href="<?php the_permalink(); ?>" class="thumb-wrap">
					<?php the_post_thumbnail( 'apollo-card-lg', [ 'class' => 'video-card__thumb', 'alt' => esc_attr( get_the_title() ) ] ); ?>
					<span class="thumb-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
				</a>
				<h3 class="video-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<div class="video-card__meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_the_time( 'U' ) ) ); ?></time></div>
			</article>
			<?php endwhile; ?>
		</div>
		<?php vicinity_pagination(); ?>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>