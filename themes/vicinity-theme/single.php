<?php
/**
 * Apollo Theme — Single post (NYT article layout)
 *
 * Layout: full-width lead image → large headline → byline block →
 *         single-column article body → tags → related articles
 * Right sidebar: most-read rail + ad unit (sticky).
 *
 * @package Apollo
 */

get_header();
the_post();
?>

<main id="primary" class="site-main" role="main">
	<div class="container">
		<div class="article-layout">

			<!-- ── Article body column ───────────────────────────────────────── -->
			<div class="article-main">

				<!-- Breadcrumb -->
				<nav class="article-kicker" aria-label="<?php esc_attr_e( 'Section', 'vicinity' ); ?>">
					<?php
					$cats = get_the_category();
					if ( $cats ) {
						printf(
							'<a href="%s">%s</a>',
							esc_url( get_category_link( $cats[0] ) ),
							esc_html( $cats[0]->name )
						);
					}
					?>
				</nav>

				<!-- Headline -->
				<header class="article-header">
					<h1 class="article-headline"><?php the_title(); ?></h1>

					<?php if ( has_excerpt() ) : ?>
						<p class="article-deck"><?php echo wp_kses_post( get_the_excerpt() ); ?></p>
					<?php endif; ?>
				</header>

				<!-- Byline block -->
				<div class="article-byline-block">
					<div class="article-byline-block__text" style="flex:1;">
						<span class="article-byline-block__by"><?php esc_html_e( 'By', 'vicinity' ); ?></span>
						<div class="article-byline-block__author">
							<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
								<?php the_author(); ?>
							</a>
						</div>
						<div class="article-byline-block__date">
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
								<?php printf( esc_html__( 'Published %s', 'vicinity' ), get_the_date() ); ?>
							</time>
							<?php
							$modified = get_the_modified_date( 'U' );
							$pub      = get_the_date( 'U' );
							if ( $modified > $pub + 3600 ) :
							?>
								&nbsp;&middot;&nbsp;
								<time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>">
									<?php printf( esc_html__( 'Updated %s', 'vicinity' ), get_the_modified_date() ); ?>
								</time>
							<?php endif; ?>
						</div>
					</div>

					<!-- Share buttons -->
					<div class="article-share">
						<a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( get_permalink() ); ?>&text=<?php echo rawurlencode( get_the_title() ); ?>"
							class="article-share__btn" target="_blank" rel="noopener noreferrer" aria-label="Share on X">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.632L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
						</a>
						<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( get_permalink() ); ?>"
							class="article-share__btn" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
						</a>
						<button class="article-share__btn" onclick="navigator.clipboard&&navigator.clipboard.writeText(window.location.href)" aria-label="<?php esc_attr_e( 'Copy link', 'vicinity' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
						</button>
					</div>
				</div>

				<!-- Lead image -->
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="article-lead-image">
						<?php the_post_thumbnail( 'apollo-hero', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
						<?php $caption = get_the_post_thumbnail_caption(); if ( $caption ) : ?>
							<figcaption><?php echo esc_html( $caption ); ?></figcaption>
						<?php endif; ?>
					</figure>
				<?php endif; ?>

				<!-- Ad: in-content zone fires automatically via vicinity_ad_zone filter -->

				<!-- Body -->
				<div class="article-body">
					<?php the_content(); ?>
				</div>

				<!-- Tags -->
				<?php
				$tags = get_the_tags();
				if ( $tags ) :
				?>
				<div class="article-tags" aria-label="<?php esc_attr_e( 'Tags', 'vicinity' ); ?>">
					<?php foreach ( $tags as $tag ) : ?>
						<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>" class="article-tag">
							<?php echo esc_html( $tag->name ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- Related articles -->
				<?php
				$related = new WP_Query( [
					'posts_per_page' => 4,
					'post_status'    => 'publish',
					'post__not_in'   => [ get_the_ID() ],
					'category__in'   => wp_get_post_categories( get_the_ID() ),
					'orderby'        => 'date',
				] );
				if ( $related->have_posts() ) :
				?>
				<section class="related-section" aria-label="<?php esc_attr_e( 'Related articles', 'vicinity' ); ?>">
					<h2 class="related-section__title"><?php esc_html_e( 'More to Read', 'vicinity' ); ?></h2>
					<div class="story-grid story-grid--4">
						<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<article class="story-card">
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'apollo-card-lg', [ 'class' => 'story-card__image', 'alt' => esc_attr( get_the_title() ) ] ); ?>
								</a>
							<?php endif; ?>
							<h3 class="story-card__headline story-card__headline--sm">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>
							<div class="story-card__byline">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_the_time( 'U' ) ) ); ?></time>
							</div>
						</article>
						<?php endwhile; wp_reset_postdata(); ?>
					</div>
				</section>
				<?php endif; ?>

			</div><!-- /.article-main -->

			<!-- ── Sidebar ─────────────────────────────────────────────────── -->
			<aside class="article-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'vicinity' ); ?>">

				<!-- Ad unit -->
				<?php if ( function_exists( 'vicinity_ad_zone' ) ) : ?>
					<div class="article-sidebar__block">
						<?php vicinity_ad_zone( 'sidebar_top' ); ?>
					</div>
				<?php endif; ?>

				<!-- Most read -->
				<?php
				$popular = new WP_Query( [
					'posts_per_page' => 5,
					'post_status'    => 'publish',
					'meta_key'       => '_vicinity_view_count',
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
					'date_query'     => [ [ 'after' => '30 days ago' ] ],
				] );
				if ( ! $popular->have_posts() ) {
					// Fallback to recent.
					$popular = new WP_Query( [ 'posts_per_page' => 5, 'post_status' => 'publish' ] );
				}
				if ( $popular->have_posts() ) :
				?>
				<div class="article-sidebar__block">
					<h3 class="article-sidebar__title"><?php esc_html_e( 'Most Read', 'vicinity' ); ?></h3>
					<div class="most-read">
						<?php $rank = 1; while ( $popular->have_posts() ) : $popular->the_post(); ?>
						<div class="most-read__item">
							<span class="most-read__rank"><?php echo esc_html( $rank++ ); ?></span>
							<h4 class="most-read__headline"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
						</div>
						<?php endwhile; wp_reset_postdata(); ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Mid sidebar ad -->
				<?php if ( function_exists( 'vicinity_ad_zone' ) ) : ?>
					<div class="article-sidebar__block">
						<?php vicinity_ad_zone( 'sidebar_mid' ); ?>
					</div>
				<?php endif; ?>

			</aside>

		</div><!-- /.article-layout -->
	</div><!-- /.container -->
</main>

<?php get_footer(); ?>
