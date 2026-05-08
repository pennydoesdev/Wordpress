<?php
/**
 * Apollo Theme — Video Hub archive (NYT-style with red accent)
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;
get_header();

global $wp_query;
$found_posts = (int) $wp_query->found_posts;

// Video categories for filter pills.
$video_cats = get_terms( [
	'taxonomy'   => 'serve_video_category',
	'hide_empty' => true,
	'number'     => 10,
] );
$all_items   = $wp_query->posts ?? [];
$feat_hero   = null;
$feat_stack  = [];
$list_items  = [];

if ( $all_items ) {
	foreach ( $all_items as $idx => $item ) {
		if ( ! $feat_hero && has_post_thumbnail( $item->ID ) ) {
			$feat_hero = $item;
			unset( $all_items[ $idx ] );
			break;
		}
	}
	if ( ! $feat_hero ) $feat_hero = array_shift( $all_items );
	$all_items  = array_values( $all_items );
	$feat_stack = array_splice( $all_items, 0, 3 );
	$list_items = $all_items;
}
?>
<main id="primary" class="site-main video-hub-page" role="main">

<!-- HEADER -->
<div class="cat-header">
	<div class="cat-header__inner">
		<h1 class="cat-header__title"><?php esc_html_e( 'Video', 'vicinity' ); ?></h1>
		<?php if ( $video_cats && ! is_wp_error( $video_cats ) ) : ?>
		<nav class="cat-pills">
			<?php foreach ( $video_cats as $vc ) : ?>
				<a class="cat-pill" href="<?php echo esc_url( get_term_link( $vc ) ); ?>">
					<?php echo esc_html( strtoupper( $vc->name ) ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</div>
</div>

<?php if ( $feat_hero ) : ?>
<!-- FEATURED -->
<div class="cat-feature">
	<div class="cat-feature__inner">

		<article class="cat-feature-hero">
			<?php if ( has_post_thumbnail( $feat_hero->ID ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $feat_hero->ID ) ); ?>" class="cat-feature-hero__image-link" style="position:relative;">
					<?php echo get_the_post_thumbnail( $feat_hero->ID, 'apollo-hero', [ 'class' => 'cat-feature-hero__image' ] ); ?>
					<span class="thumb-play" aria-hidden="true" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
						<svg viewBox="0 0 60 60" width="60" height="60"><circle cx="30" cy="30" r="30" fill="rgba(0,0,0,.55)"/><path d="M22 15l26 15-26 15V15z" fill="#fff"/></svg>
					</span>
				</a>
			<?php endif; ?>
			<h2 class="cat-feature-hero__headline">
				<a href="<?php echo esc_url( get_permalink( $feat_hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $feat_hero->ID ) ); ?></a>
			</h2>
			<p class="cat-feature-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $feat_hero->ID ), 28, '…' ) ); ?></p>
			<div class="cat-feature-hero__byline">
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $feat_hero->ID ) ); ?>">
					<?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $feat_hero->ID ) ) ); ?>
				</time>
			</div>
		</article>

		<?php if ( $feat_stack ) : ?>
		<div class="cat-feature-stack">
			<?php foreach ( $feat_stack as $sp ) : ?>
			<article class="cat-stack-item">
				<?php if ( has_post_thumbnail( $sp->ID ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>" style="display:block;line-height:0;margin-bottom:var(--sp-2);position:relative;">
						<?php echo get_the_post_thumbnail( $sp->ID, 'apollo-card-lg', [ 'style' => 'width:100%;height:auto;display:block;' ] ); ?>
						<span class="thumb-play" aria-hidden="true" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
							<svg viewBox="0 0 40 40" width="36" height="36"><circle cx="20" cy="20" r="20" fill="rgba(0,0,0,.5)"/><path d="M15 10l18 10-18 10V10z" fill="#fff"/></svg>
						</span>
					</a>
				<?php endif; ?>
				<h3 class="cat-stack-item__headline">
					<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>"><?php echo esc_html( get_the_title( $sp->ID ) ); ?></a>
				</h3>
				<div class="cat-stack-item__meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c', $sp->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $sp->ID ) ) ); ?></time>
				</div>
			</article>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

	</div>
</div>
<?php endif; ?>

<?php vicinity_ad_zone( 'after_header' ); ?>

<!-- LATEST LIST -->
<?php if ( $list_items ) : ?>
<div class="cat-body">
	<div class="cat-body__inner">
		<div class="cat-feed">
			<div class="cat-feed__tabs">
				<span class="cat-feed__tab is-active"><?php esc_html_e( 'Latest Videos', 'vicinity' ); ?></span>
				<span class="cat-feed__count"><?php printf( esc_html__( '%s videos', 'vicinity' ), number_format_i18n( $found_posts ) ); ?></span>
			</div>
			<div class="cat-list">
				<?php foreach ( $list_items as $item ) :
					$item_author = get_the_author_meta( 'display_name', $item->post_author );
					$is_today    = ( date( 'Y-m-d' ) === get_the_date( 'Y-m-d', $item->ID ) );
				?>
				<article class="cat-list-item">
					<div class="cat-list-item__date">
						<time datetime="<?php echo esc_attr( get_the_date( 'c', $item->ID ) ); ?>">
							<?php echo esc_html( $is_today ? vicinity_relative_date( get_post_time( 'U', false, $item->ID ) ) : get_the_date( 'M j', $item->ID ) ); ?>
						</time>
					</div>
					<div class="cat-list-item__body">
						<h3 class="cat-list-item__headline">
							<a href="<?php echo esc_url( get_permalink( $item->ID ) ); ?>"><?php echo esc_html( get_the_title( $item->ID ) ); ?></a>
						</h3>
						<p class="cat-list-item__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $item->ID ), 18, '…' ) ); ?></p>
					</div>
					<?php if ( has_post_thumbnail( $item->ID ) ) : ?>
					<div class="cat-list-item__thumb" style="position:relative;">
						<a href="<?php echo esc_url( get_permalink( $item->ID ) ); ?>">
							<?php echo get_the_post_thumbnail( $item->ID, 'apollo-card-sm' ); ?>
							<span class="thumb-play" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
								<svg viewBox="0 0 32 32" width="28" height="28"><circle cx="16" cy="16" r="16" fill="rgba(0,0,0,.55)"/><path d="M11 8l14 8-14 8V8z" fill="#fff"/></svg>
							</span>
						</a>
					</div>
					<?php endif; ?>
				</article>
				<?php endforeach; ?>
			</div>
			<?php vicinity_pagination(); ?>
		</div>
		<aside class="cat-rail">
			<?php vicinity_ad_zone( 'sidebar_top' ); ?>
			<?php if ( is_active_sidebar( 'archive-sidebar' ) ) dynamic_sidebar( 'archive-sidebar' ); ?>
		</aside>
	</div>
</div>
<?php endif; ?>

</main>
<?php get_footer(); ?>