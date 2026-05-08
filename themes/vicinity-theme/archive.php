<?php
/**
 * Apollo Theme — Archive / Category page (NYT-style)
 *
 * Layout:
 *   1. Category title + sub-topic filter pills
 *   2. Top feature: hero left (60%) + text stack right (40%)
 *   3. Full-width ad banner
 *   4. Latest feed: date | headline + deck + byline | thumb — with right rail
 *   5. Pagination
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;
get_header();

global $wp_query;

$archive_title = get_the_archive_title();
$archive_desc  = get_the_archive_description();
$found_posts   = (int) $wp_query->found_posts;

// Strip "Category: " prefix WordPress adds.
$display_title = preg_replace( '/^[^:]+:\s*/', '', strip_tags( $archive_title ) );

// Sub-topic pills: child cats (for category archive) or related terms.
$sub_topics = [];
$queried_obj = get_queried_object();
if ( $queried_obj instanceof WP_Term && $queried_obj->taxonomy === 'category' ) {
	$sub_topics = get_categories( [
		'parent'     => $queried_obj->term_id,
		'hide_empty' => true,
		'number'     => 8,
	] );
}

// Pull ALL posts on this page.
$all_items  = $wp_query->posts;
$feat_hero  = null;
$feat_stack = [];
$list_items = [];

if ( $all_items ) {
	// First post with a thumbnail = feature hero.
	foreach ( $all_items as $idx => $item ) {
		if ( ! $feat_hero && has_post_thumbnail( $item->ID ) ) {
			$feat_hero = $item;
			unset( $all_items[ $idx ] );
			break;
		}
	}
	if ( ! $feat_hero ) {
		$feat_hero = array_shift( $all_items );
	}
	$all_items = array_values( $all_items );

	// Next 3 = feature text stack.
	$feat_stack = array_splice( $all_items, 0, 3 );

	// Remaining = latest feed list.
	$list_items = $all_items;
}
?>

<main id="primary" class="site-main cat-page" role="main">

<!-- CATEGORY HEADER ───────────────────────────────────────────────────── -->
<div class="cat-header">
	<div class="cat-header__inner">
		<h1 class="cat-header__title"><?php echo esc_html( $display_title ); ?></h1>
		<?php if ( $archive_desc ) : ?>
			<p class="cat-header__desc"><?php echo wp_kses_post( $archive_desc ); ?></p>
		<?php endif; ?>
		<?php if ( $sub_topics ) : ?>
		<nav class="cat-pills" aria-label="<?php esc_attr_e( 'Sub-topics', 'vicinity' ); ?>">
			<?php foreach ( $sub_topics as $st ) : ?>
				<a class="cat-pill<?php echo ( is_category( $st->term_id ) ) ? ' is-active' : ''; ?>"
				   href="<?php echo esc_url( get_category_link( $st ) ); ?>">
					<?php echo esc_html( strtoupper( $st->name ) ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</div>
</div><!-- .cat-header -->

<?php if ( $feat_hero || $feat_stack ) : ?>
<!-- TOP FEATURE SECTION ───────────────────────────────────────────────── -->
<div class="cat-feature">
	<div class="cat-feature__inner">

		<!-- Hero -->
		<?php if ( $feat_hero ) :
			$hero_cats   = get_the_category( $feat_hero->ID );
			$hero_author = get_the_author_meta( 'display_name', $feat_hero->post_author );
		?>
		<article class="cat-feature-hero">
			<?php if ( has_post_thumbnail( $feat_hero->ID ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $feat_hero->ID ) ); ?>" class="cat-feature-hero__image-link">
					<?php echo get_the_post_thumbnail( $feat_hero->ID, 'apollo-hero', [ 'class' => 'cat-feature-hero__image', 'alt' => esc_attr( get_the_title( $feat_hero->ID ) ) ] ); ?>
				</a>
			<?php endif; ?>
			<h2 class="cat-feature-hero__headline">
				<a href="<?php echo esc_url( get_permalink( $feat_hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $feat_hero->ID ) ); ?></a>
			</h2>
			<p class="cat-feature-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $feat_hero->ID ), 28, '…' ) ); ?></p>
			<div class="cat-feature-hero__byline">
				<?php if ( $hero_author ) : ?>
					<span><?php printf( esc_html__( 'By %s', 'vicinity' ), '<strong>' . esc_html( strtoupper( $hero_author ) ) . '</strong>' ); ?></span>
					<span class="byline-sep">·</span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $feat_hero->ID ) ); ?>">
					<?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $feat_hero->ID ) ) ); ?>
				</time>
			</div>
		</article>
		<?php endif; ?>

		<!-- Stack: 3 text-only stories -->
		<?php if ( $feat_stack ) : ?>
		<div class="cat-feature-stack">
			<?php foreach ( $feat_stack as $sp ) :
				$sp_author = get_the_author_meta( 'display_name', $sp->post_author );
			?>
			<article class="cat-stack-item">
				<h3 class="cat-stack-item__headline">
					<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>"><?php echo esc_html( get_the_title( $sp->ID ) ); ?></a>
				</h3>
				<p class="cat-stack-item__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $sp->ID ), 18, '…' ) ); ?></p>
				<div class="cat-stack-item__meta">
					<?php if ( $sp_author ) : ?>
						<span><?php printf( esc_html__( 'By %s', 'vicinity' ), '<strong>' . esc_html( strtoupper( $sp_author ) ) . '</strong>' ); ?></span>
						<span class="byline-sep">·</span>
					<?php endif; ?>
					<time datetime="<?php echo esc_attr( get_the_date( 'c', $sp->ID ) ); ?>">
						<?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $sp->ID ) ) ); ?>
					</time>
				</div>
			</article>
			<?php endforeach; ?>
		</div><!-- .cat-feature-stack -->
		<?php endif; ?>

	</div><!-- .cat-feature__inner -->
</div><!-- .cat-feature -->
<?php endif; ?>

<!-- AD BANNER ─────────────────────────────────────────────────────────── -->
<?php vicinity_ad_zone( 'after_header' ); ?>

<!-- LATEST FEED + RIGHT RAIL ──────────────────────────────────────────── -->
<?php if ( $list_items || $feat_hero ) : ?>
<div class="cat-body">
	<div class="cat-body__inner">

		<!-- Latest feed -->
		<div class="cat-feed">

			<!-- Tab header -->
			<div class="cat-feed__tabs">
				<span class="cat-feed__tab is-active"><?php esc_html_e( 'Latest', 'vicinity' ); ?></span>
				<?php if ( $found_posts ) : ?>
					<span class="cat-feed__count"><?php printf( esc_html__( '%s stories', 'vicinity' ), number_format_i18n( $found_posts ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php
			// List includes feat_hero + feat_stack + list_items to fill the feed.
			// Only show feat_hero + feat_stack again if on page > 1; otherwise show list_items.
			$feed_items = $list_items;
			if ( empty( $feed_items ) ) : ?>
				<p class="no-stories"><?php esc_html_e( 'No more stories found.', 'vicinity' ); ?></p>
			<?php else : ?>
			<div class="cat-list">
				<?php foreach ( $feed_items as $item ) :
					$item_author = get_the_author_meta( 'display_name', $item->post_author );
					$item_cats   = get_the_category( $item->ID );
					$item_cat    = $item_cats[0] ?? null;
					$item_date   = get_post_time( 'U', false, $item->ID );
					$is_today    = ( date( 'Y-m-d' ) === get_the_date( 'Y-m-d', $item->ID ) );
				?>
				<article class="cat-list-item">
					<div class="cat-list-item__date">
						<time datetime="<?php echo esc_attr( get_the_date( 'c', $item->ID ) ); ?>">
							<?php echo esc_html( $is_today
								? vicinity_relative_date( $item_date )
								: get_the_date( 'M j, Y', $item->ID )
							); ?>
						</time>
					</div>
					<div class="cat-list-item__body">
						<?php if ( $item_cat ) : ?>
							<span class="cat-list-item__kicker">
								<a href="<?php echo esc_url( get_category_link( $item_cat ) ); ?>"><?php echo esc_html( $item_cat->name ); ?></a>
							</span>
						<?php endif; ?>
						<h3 class="cat-list-item__headline">
							<a href="<?php echo esc_url( get_permalink( $item->ID ) ); ?>"><?php echo esc_html( get_the_title( $item->ID ) ); ?></a>
						</h3>
						<p class="cat-list-item__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $item->ID ), 22, '…' ) ); ?></p>
						<div class="cat-list-item__byline">
							<?php if ( $item_author ) : ?>
								<?php printf( esc_html__( 'By %s', 'vicinity' ), '<strong>' . esc_html( strtoupper( $item_author ) ) . '</strong>' ); ?>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( has_post_thumbnail( $item->ID ) ) : ?>
					<div class="cat-list-item__thumb">
						<a href="<?php echo esc_url( get_permalink( $item->ID ) ); ?>">
							<?php echo get_the_post_thumbnail( $item->ID, 'apollo-card-sm', [ 'alt' => esc_attr( get_the_title( $item->ID ) ) ] ); ?>
						</a>
					</div>
					<?php endif; ?>
				</article>
				<?php endforeach; ?>
			</div><!-- .cat-list -->
			<?php endif; ?>

			<?php vicinity_pagination(); ?>

		</div><!-- .cat-feed -->

		<!-- Right rail -->
		<aside class="cat-rail">
			<?php vicinity_ad_zone( 'sidebar_top' ); ?>

			<?php if ( is_active_sidebar( 'archive-sidebar' ) ) : ?>
				<div class="widget-area"><?php dynamic_sidebar( 'archive-sidebar' ); ?></div>
			<?php endif; ?>

			<?php vicinity_ad_zone( 'sidebar_mid' ); ?>
		</aside><!-- .cat-rail -->

	</div><!-- .cat-body__inner -->
</div><!-- .cat-body -->
<?php endif; ?>

</main>

<?php get_footer(); ?>