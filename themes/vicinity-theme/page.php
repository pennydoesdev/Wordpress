<?php
/**
 * Apollo Theme — Static page (NYT-style)
 *
 * @package Apollo
 */
get_header();
the_post();
?>
<main id="primary" class="site-main" role="main">
	<div class="container">
		<div class="article-layout article-layout--full" style="max-width:var(--max-w-article);margin:0 auto;">
			<article>
				<header class="article-header">
					<h1 class="article-headline"><?php the_title(); ?></h1>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="article-lead-image"><?php the_post_thumbnail( 'apollo-hero' ); ?></figure>
				<?php endif; ?>
				<div class="article-body"><?php the_content(); ?></div>
			</article>
		</div>
	</div>
</main>
<?php get_footer(); ?>