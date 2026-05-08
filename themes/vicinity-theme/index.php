<?php
/**
 * Index — WP fallback template. Should not normally be reached.
 *
 * @package Apollo
 */

get_header();
?>

<main id="primary" class="site-main" role="main">
	<div class="container" style="padding:var(--sp-16) 0;">
		<?php if ( have_posts() ) : ?>
		<div class="archive-list">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php vicinity_article_card( get_the_ID(), 'horizontal', true, 'apollo-card-sm' ); ?>
			<?php endwhile; ?>
		</div>
		<?php vicinity_pagination(); ?>
		<?php else : ?>
		<p style="color:var(--color-ink-muted);"><?php esc_html_e( 'Nothing to show here yet.', 'vicinity' ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
