<?php
/**
 * Apollo Theme — Search results (NYT-style)
 *
 * @package Apollo
 */
get_header();
$query = get_search_query();
?>
<main id="primary" class="site-main" role="main">
	<div class="container">
		<div class="search-header">
			<h1 class="search-header__query">
				<?php printf( esc_html__( 'Search results for: %s', 'vicinity' ), '<em>' . esc_html( $query ) . '</em>' ); ?>
			</h1>
			<p class="search-header__count">
				<?php printf( esc_html__( '%s results', 'vicinity' ), number_format_i18n( $wp_query->found_posts ) ); ?>
			</p>
		</div>

		<?php if ( have_posts() ) : ?>
		<div class="archive-list">
			<?php while ( have_posts() ) : the_post(); ?>
			<article class="archive-item">
				<div>
					<?php $cats = get_the_category(); if ( $cats ) : ?><span class="kicker"><a href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a></span><?php endif; ?>
					<h2 class="archive-item__headline"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="archive-item__summary"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<div class="archive-item__byline">
						<?php if ( get_the_author() ) : ?><strong><?php the_author(); ?></strong> &middot; <?php endif; ?>
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_the_time( 'U' ) ) ); ?></time>
					</div>
				</div>
				<?php if ( has_post_thumbnail() ) : ?><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'apollo-card-lg', [ 'class' => 'archive-item__image', 'alt' => esc_attr( get_the_title() ) ] ); ?></a><?php endif; ?>
			</article>
			<?php endwhile; ?>
		</div>
		<?php vicinity_pagination(); ?>
		<?php else : ?>
		<p style="color:var(--color-ink-muted);padding:var(--sp-8) 0;"><?php esc_html_e( 'No results found. Try a different search term.', 'vicinity' ); ?></p>
		<div style="max-width:480px;margin-top:var(--sp-6);"><?php get_search_form(); ?></div>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>