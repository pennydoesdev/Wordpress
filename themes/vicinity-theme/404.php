<?php
/**
 * Apollo Theme — 404 (NYT-style)
 *
 * @package Apollo
 */
get_header();
?>
<main id="primary" class="site-main" role="main">
	<div class="container">
		<div class="not-found">
			<span class="not-found__code">404</span>
			<h1 class="not-found__title"><?php esc_html_e( 'Page not found', 'vicinity' ); ?></h1>
			<p class="not-found__text"><?php esc_html_e( 'The page you\'re looking for may have been moved, deleted, or never existed. Try searching for what you need.', 'vicinity' ); ?></p>
			<div style="max-width:480px;margin:0 auto;"><?php get_search_form(); ?></div>
			<p style="margin-top:var(--sp-6);">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn--primary"><?php esc_html_e( '← Back to Home', 'vicinity' ); ?></a>
			</p>
		</div>
	</div>
</main>
<?php get_footer(); ?>