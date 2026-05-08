<?php
/**
 * Vicinity Theme — Homepage
 *
 * Layout:
 *   1. Breaking / live ticker
 *   2. Top section  — configurable layout (NYT 3-col default)
 *   3. Dynamic Section Blocks (from Customizer → Homepage Blocks)
 *
 * Each block reads layout, category, count from the
 * vicinity_home_blocks Customizer option.
 *
 * @package Apollo (Vicinity)
 */

defined( 'ABSPATH' ) || exit;
get_header();

// ── Pop helper (deduplication) ────────────────────────────────────────────
if ( ! function_exists( '_vicinity_pop' ) ) {
	function _vicinity_pop( array &$pool, array &$used, int $n = 1 ): array {
		$out = [];
		foreach ( $pool as $k => $p ) {
			if ( count( $out ) >= $n ) break;
			if ( in_array( $p->ID, $used, true ) ) continue;
			$out[]  = $p;
			$used[] = $p->ID;
			unset( $pool[ $k ] );
		}
		return $out;
	}
}

// ── Query posts for top section ───────────────────────────────────────────
$all_recent = new WP_Query( [
	'posts_per_page'      => 25,
	'post_status'         => 'publish',
	'ignore_sticky_posts' => false,
	'post_type'           => [ 'post', 'serve_video' ],
] );
$all_posts = $all_recent->posts ?? [];
$used_ids  = [];

// Sort: thumbnail first → pick hero
usort( $all_posts, static function ( $a, $b ) {
	return ( has_post_thumbnail( $a->ID ) ? 0 : 1 ) - ( has_post_thumbnail( $b->ID ) ? 0 : 1 );
} );
$hero_arr = _vicinity_pop( $all_posts, $used_ids, 1 );
$hero     = $hero_arr[0] ?? null;

// Restore date sort
usort( $all_posts, static fn( $a, $b ) => strcmp( $b->post_date, $a->post_date ) );

$left_posts  = _vicinity_pop( $all_posts, $used_ids, 4 );

usort( $all_posts, static function ( $a, $b ) {
	return ( has_post_thumbnail( $a->ID ) ? 0 : 1 ) - ( has_post_thumbnail( $b->ID ) ? 0 : 1 );
} );
$right_posts = _vicinity_pop( $all_posts, $used_ids, 3 );
usort( $all_posts, static fn( $a, $b ) => strcmp( $b->post_date, $a->post_date ) );

// Ticker (latest 5 not yet claimed)
$ticker_posts = array_slice( $all_posts, 0, 5 );

// Dynamic blocks config
$home_blocks = function_exists( 'vicinity_home_blocks' ) ? vicinity_home_blocks() : [];

?>
<main id="primary" class="site-main" role="main">

<?php if ( $ticker_posts ) : ?>
<!-- TICKER ──────────────────────────────────────────────────────────────── -->
<div class="home-ticker">
	<div class="home-ticker__inner">
		<span class="live-badge"><?php esc_html_e( 'LIVE', 'vicinity' ); ?></span>
		<div class="home-ticker__stories">
			<?php foreach ( $ticker_posts as $tp ) :
				$tp_cats = get_the_category( $tp->ID );
				$tp_cat  = $tp_cats[0] ?? null;
			?>
			<a class="ticker-story" href="<?php echo esc_url( get_permalink( $tp->ID ) ); ?>">
				<?php if ( $tp_cat ) : ?>
					<strong><?php echo esc_html( $tp_cat->name ); ?></strong>
				<?php endif; ?>
				<?php echo esc_html( get_the_title( $tp->ID ) ); ?>
				<span class="ticker-time"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $tp->ID ) ) ); ?></span>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="home-container">

<!-- TOP GRID ───────────────────────────────────────────────────────────── -->
<?php $top_layout = get_theme_mod( 'vicinity_home_top_layout', 'nyt-3col' ); ?>

<?php if ( $top_layout === 'nyt-3col' ) : ?>
<div class="home-top">

	<!-- LEFT: text-only stack -->
	<div class="home-top__left">
		<?php foreach ( $left_posts as $p ) :
			$cats = get_the_category( $p->ID );
			$cat  = $cats[0] ?? null;
		?>
		<article class="ht-text-story">
			<?php if ( $cat ) : ?>
				<span class="ht-kicker"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span>
			<?php endif; ?>
			<h2 class="ht-text-story__headline">
				<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a>
			</h2>
			<p class="ht-text-story__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $p->ID ), 18, '…' ) ); ?></p>
			<div class="ht-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $p->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $p->ID ) ) ); ?></time>
			</div>
		</article>
		<?php endforeach; ?>
	</div>

	<!-- CENTER: hero -->
	<div class="home-top__center">
		<?php if ( $hero ) :
			$cats     = get_the_category( $hero->ID );
			$cat      = $cats[0] ?? null;
			$is_video = get_post_type( $hero->ID ) === 'serve_video';
		?>
		<article class="ht-hero">
			<?php if ( $cat ) : ?>
				<span class="ht-kicker ht-kicker--center"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span>
			<?php endif; ?>
			<?php if ( has_post_thumbnail( $hero->ID ) ) : ?>
			<a href="<?php echo esc_url( get_permalink( $hero->ID ) ); ?>" class="ht-hero__image-link">
				<?php echo get_the_post_thumbnail( $hero->ID, 'apollo-hero', [ 'class' => 'ht-hero__image', 'alt' => esc_attr( get_the_title( $hero->ID ) ) ] ); ?>
				<?php if ( $is_video ) : ?>
					<span class="thumb-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="40" height="40"><circle cx="12" cy="12" r="12" fill="rgba(0,0,0,.5)"/><path d="M9 7l9 5-9 5V7z" fill="#fff"/></svg></span>
				<?php endif; ?>
			</a>
			<?php endif; ?>
			<h2 class="ht-hero__headline">
				<a href="<?php echo esc_url( get_permalink( $hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $hero->ID ) ); ?></a>
			</h2>
			<p class="ht-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $hero->ID ), 25, '…' ) ); ?></p>
			<div class="ht-meta">
				<?php $author = get_the_author_meta( 'display_name', $hero->post_author ); if ( $author ) : ?>
					<span>By <strong><?php echo esc_html( $author ); ?></strong></span>
					<span class="ht-meta-sep">·</span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $hero->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $hero->ID ) ) ); ?></time>
			</div>
		</article>
		<?php endif; ?>
	</div>

	<!-- RIGHT: image-stack -->
	<div class="home-top__right">
		<?php foreach ( $right_posts as $p ) :
			$cats = get_the_category( $p->ID );
			$cat  = $cats[0] ?? null;
		?>
		<article class="ht-side-story">
			<?php if ( has_post_thumbnail( $p->ID ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" class="ht-side-story__image-link">
					<?php echo get_the_post_thumbnail( $p->ID, 'apollo-card-lg', [ 'class' => 'ht-side-story__image', 'alt' => esc_attr( get_the_title( $p->ID ) ) ] ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $cat ) : ?>
				<span class="ht-kicker"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span>
			<?php endif; ?>
			<h3 class="ht-side-story__headline">
				<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a>
			</h3>
			<div class="ht-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $p->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $p->ID ) ) ); ?></time>
			</div>
		</article>
		<?php endforeach; ?>
	</div>

</div><!-- .home-top -->

<?php elseif ( $top_layout === 'hero-full' ) : ?>
<div class="home-top home-top--hero-full">
	<?php if ( $hero ) : ?>
	<article class="ht-hero ht-hero--full">
		<?php if ( has_post_thumbnail( $hero->ID ) ) : ?>
		<a href="<?php echo esc_url( get_permalink( $hero->ID ) ); ?>" class="ht-hero__image-link">
			<?php echo get_the_post_thumbnail( $hero->ID, 'apollo-wide', [ 'class' => 'ht-hero__image', 'alt' => esc_attr( get_the_title( $hero->ID ) ) ] ); ?>
		</a>
		<?php endif; ?>
		<h2 class="ht-hero__headline"><a href="<?php echo esc_url( get_permalink( $hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $hero->ID ) ); ?></a></h2>
		<p class="ht-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $hero->ID ), 30, '…' ) ); ?></p>
	</article>
	<?php endif; ?>
	<div class="ht-card-row">
		<?php foreach ( array_merge( $left_posts, $right_posts ) as $p ) : ?>
		<article class="ht-card">
			<?php if ( has_post_thumbnail( $p->ID ) ) echo get_the_post_thumbnail( $p->ID, 'apollo-card-sm', [ 'alt' => esc_attr( get_the_title( $p->ID ) ) ] ); ?>
			<h3><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a></h3>
		</article>
		<?php endforeach; ?>
	</div>
</div>

<?php elseif ( $top_layout === 'hero-sidebar' ) : ?>
<div class="home-top home-top--hero-sidebar">
	<?php if ( $hero ) : ?>
	<article class="ht-hero">
		<?php if ( has_post_thumbnail( $hero->ID ) ) : ?>
		<a href="<?php echo esc_url( get_permalink( $hero->ID ) ); ?>" class="ht-hero__image-link">
			<?php echo get_the_post_thumbnail( $hero->ID, 'apollo-hero', [ 'class' => 'ht-hero__image', 'alt' => esc_attr( get_the_title( $hero->ID ) ) ] ); ?>
		</a>
		<?php endif; ?>
		<h2 class="ht-hero__headline"><a href="<?php echo esc_url( get_permalink( $hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $hero->ID ) ); ?></a></h2>
		<p class="ht-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $hero->ID ), 30, '…' ) ); ?></p>
	</article>
	<?php endif; ?>
	<div class="home-top__sidebar">
		<?php foreach ( $right_posts as $p ) : ?>
		<article class="ht-side-story">
			<h3><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a></h3>
			<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $p->ID ), 14, '…' ) ); ?></p>
		</article>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<!-- DYNAMIC SECTION BLOCKS ─────────────────────────────────────────────── -->
<?php foreach ( $home_blocks as $block ) :
	$blk_layout   = $block['layout']    ?? '3col';
	$blk_cat_id   = (int) ( $block['category'] ?? 0 );
	$blk_count    = min( 12, max( 1, (int) ( $block['count'] ?? 5 ) ) );
	$blk_title    = trim( $block['title'] ?? '' );
	$blk_more     = (bool) ( $block['show_more'] ?? true );

	// Resolve section title + link
	$blk_term     = $blk_cat_id ? get_category( $blk_cat_id ) : null;
	$blk_heading  = $blk_title ?: ( $blk_term ? $blk_term->name : __( 'Latest', 'vicinity' ) );
	$blk_link     = $blk_term  ? get_category_link( $blk_cat_id ) : home_url( '/blog/' );

	// Build query args
	$blk_q_args = [
		'posts_per_page'      => $blk_count,
		'post_status'         => 'publish',
		'post__not_in'        => $used_ids,
		'ignore_sticky_posts' => true,
	];
	if ( $blk_cat_id ) {
		$blk_q_args['cat'] = $blk_cat_id;
	}
	if ( in_array( $blk_layout, [ 'video-row' ], true ) ) {
		$blk_q_args['post_type'] = 'serve_video';
		unset( $blk_q_args['cat'] );
	} elseif ( in_array( $blk_layout, [ 'podcast-row' ], true ) ) {
		$blk_q_args['post_type'] = 'serve_episode';
		unset( $blk_q_args['cat'] );
	} else {
		$blk_q_args['post_type'] = [ 'post', 'serve_video' ];
	}

	$blk_q     = new WP_Query( $blk_q_args );
	$blk_posts = $blk_q->posts ?? [];
	if ( empty( $blk_posts ) ) continue;
	$used_ids = array_merge( $used_ids, wp_list_pluck( $blk_posts, 'ID' ) );
?>

<section class="home-section-band home-block--<?php echo esc_attr( $blk_layout ); ?>" aria-label="<?php echo esc_attr( $blk_heading ); ?>">

	<div class="home-section-band__header">
		<h2 class="home-section-band__title">
			<?php if ( $blk_term ) : ?>
				<a href="<?php echo esc_url( $blk_link ); ?>"><?php echo esc_html( $blk_heading ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $blk_heading ); ?>
			<?php endif; ?>
		</h2>
		<?php if ( $blk_more ) : ?>
			<a class="home-section-band__more" href="<?php echo esc_url( $blk_link ); ?>"><?php esc_html_e( 'See more ›', 'vicinity' ); ?></a>
		<?php endif; ?>
	</div>

	<?php
	// ── Render layout template ────────────────────────────────────────────
	switch ( $blk_layout ) :

		// ── 3-Column (same as original section band) ─────────────────────
		case '3col':
		default:
			$sec_hero  = null;
			$sec_stack = [];
			foreach ( $blk_posts as $sp ) {
				if ( ! $sec_hero && has_post_thumbnail( $sp->ID ) ) { $sec_hero = $sp; }
				else { $sec_stack[] = $sp; }
			}
			if ( ! $sec_hero ) { $sec_hero = array_shift( $blk_posts ); $sec_stack = $blk_posts; }
	?>
	<div class="home-section-grid">
		<?php if ( $sec_hero ) : ?>
		<article class="home-section-hero">
			<?php if ( has_post_thumbnail( $sec_hero->ID ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $sec_hero->ID ) ); ?>" class="home-section-hero__image-link">
					<?php echo get_the_post_thumbnail( $sec_hero->ID, 'apollo-card-lg', [ 'class' => 'home-section-hero__image', 'alt' => esc_attr( get_the_title( $sec_hero->ID ) ) ] ); ?>
				</a>
			<?php endif; ?>
			<h3 class="home-section-hero__headline"><a href="<?php echo esc_url( get_permalink( $sec_hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $sec_hero->ID ) ); ?></a></h3>
			<p class="home-section-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $sec_hero->ID ), 20, '…' ) ); ?></p>
			<div class="ht-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c', $sec_hero->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $sec_hero->ID ) ) ); ?></time></div>
		</article>
		<?php endif; ?>
		<div class="home-section-stack">
			<?php foreach ( $sec_stack as $sp ) : ?>
			<article class="home-stack-story">
				<h4 class="home-stack-story__headline"><a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>"><?php echo esc_html( get_the_title( $sp->ID ) ); ?></a></h4>
				<p class="home-stack-story__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $sp->ID ), 14, '…' ) ); ?></p>
				<div class="ht-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c', $sp->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $sp->ID ) ) ); ?></time></div>
			</article>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
		break;

		// ── Hero + Stack (60/40) ─────────────────────────────────────────
		case 'hero-stack':
			$hs_hero  = array_shift( $blk_posts );
			$hs_stack = $blk_posts;
	?>
	<div class="home-section-grid home-section-grid--hero-stack">
		<?php if ( $hs_hero ) : ?>
		<article class="home-section-hero home-section-hero--large">
			<?php if ( has_post_thumbnail( $hs_hero->ID ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $hs_hero->ID ) ); ?>" class="home-section-hero__image-link">
					<?php echo get_the_post_thumbnail( $hs_hero->ID, 'apollo-hero', [ 'class' => 'home-section-hero__image', 'alt' => esc_attr( get_the_title( $hs_hero->ID ) ) ] ); ?>
				</a>
			<?php endif; ?>
			<h3 class="home-section-hero__headline"><a href="<?php echo esc_url( get_permalink( $hs_hero->ID ) ); ?>"><?php echo esc_html( get_the_title( $hs_hero->ID ) ); ?></a></h3>
			<p class="home-section-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $hs_hero->ID ), 28, '…' ) ); ?></p>
			<div class="ht-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c', $hs_hero->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $hs_hero->ID ) ) ); ?></time></div>
		</article>
		<?php endif; ?>
		<div class="home-section-stack">
			<?php foreach ( $hs_stack as $sp ) : ?>
			<article class="home-stack-story home-stack-story--with-thumb">
				<?php if ( has_post_thumbnail( $sp->ID ) ) echo get_the_post_thumbnail( $sp->ID, 'apollo-card-sm', [ 'class' => 'stack-thumb', 'alt' => esc_attr( get_the_title( $sp->ID ) ) ] ); ?>
				<div class="stack-body">
					<h4 class="home-stack-story__headline"><a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>"><?php echo esc_html( get_the_title( $sp->ID ) ); ?></a></h4>
					<div class="ht-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c', $sp->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $sp->ID ) ) ); ?></time></div>
				</div>
			</article>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
		break;

		// ── Card Row ─────────────────────────────────────────────────────
		case 'card-row':
	?>
	<div class="ht-card-row">
		<?php foreach ( $blk_posts as $p ) :
			$cats = get_the_category( $p->ID );
			$cat  = $cats[0] ?? null;
		?>
		<article class="ht-card">
			<?php if ( has_post_thumbnail( $p->ID ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" class="ht-card__image-link">
					<?php echo get_the_post_thumbnail( $p->ID, 'apollo-card-sm', [ 'class' => 'ht-card__image', 'alt' => esc_attr( get_the_title( $p->ID ) ) ] ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $cat ) : ?><span class="ht-kicker"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span><?php endif; ?>
			<h3 class="ht-card__headline"><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a></h3>
			<div class="ht-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c', $p->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $p->ID ) ) ); ?></time></div>
		</article>
		<?php endforeach; ?>
	</div>
	<?php
		break;

		// ── Dated List Feed ──────────────────────────────────────────────
		case 'list-feed':
	?>
	<div class="cat-body">
		<div class="cat-list">
			<?php foreach ( $blk_posts as $p ) :
				$p_cats = get_the_category( $p->ID );
				$p_cat  = $p_cats[0] ?? null;
			?>
			<article class="cat-list-item">
				<div class="cat-list-date"><?php echo esc_html( get_the_date( 'M j', $p->ID ) ); ?></div>
				<div class="cat-list-body">
					<?php if ( $p_cat ) : ?><span class="ht-kicker"><a href="<?php echo esc_url( get_category_link( $p_cat ) ); ?>"><?php echo esc_html( $p_cat->name ); ?></a></span><?php endif; ?>
					<h3 class="cat-list-headline"><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a></h3>
					<p class="cat-list-deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $p->ID ), 16, '…' ) ); ?></p>
				</div>
				<?php if ( has_post_thumbnail( $p->ID ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" class="cat-list-thumb-link">
						<?php echo get_the_post_thumbnail( $p->ID, 'apollo-card-sq', [ 'class' => 'cat-list-thumb', 'alt' => esc_attr( get_the_title( $p->ID ) ) ] ); ?>
					</a>
				<?php endif; ?>
			</article>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
		break;

		// ── Full-Width Hero ──────────────────────────────────────────────
		case 'full-hero':
			$fh = array_shift( $blk_posts );
			if ( $fh ) :
	?>
	<article class="ht-hero ht-hero--full-width">
		<?php if ( has_post_thumbnail( $fh->ID ) ) : ?>
			<a href="<?php echo esc_url( get_permalink( $fh->ID ) ); ?>" class="ht-hero__image-link">
				<?php echo get_the_post_thumbnail( $fh->ID, 'apollo-wide', [ 'class' => 'ht-hero__image', 'alt' => esc_attr( get_the_title( $fh->ID ) ) ] ); ?>
			</a>
		<?php endif; ?>
		<h2 class="ht-hero__headline"><a href="<?php echo esc_url( get_permalink( $fh->ID ) ); ?>"><?php echo esc_html( get_the_title( $fh->ID ) ); ?></a></h2>
		<p class="ht-hero__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $fh->ID ), 35, '…' ) ); ?></p>
		<div class="ht-meta">
			<?php $fh_author = get_the_author_meta( 'display_name', $fh->post_author ); if ( $fh_author ) : ?>
				<span>By <strong><?php echo esc_html( $fh_author ); ?></strong></span>
				<span class="ht-meta-sep">·</span>
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_the_date( 'c', $fh->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $fh->ID ) ) ); ?></time>
		</div>
	</article>
	<?php
			endif;
		break;

		// ── Video Row ────────────────────────────────────────────────────
		case 'video-row':
	?>
	<div class="ht-card-row ht-card-row--video">
		<?php foreach ( $blk_posts as $p ) : ?>
		<article class="ht-card ht-card--video">
			<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" class="ht-card__image-link">
				<?php if ( has_post_thumbnail( $p->ID ) ) : ?>
					<?php echo get_the_post_thumbnail( $p->ID, 'apollo-card-sm', [ 'class' => 'ht-card__image', 'alt' => esc_attr( get_the_title( $p->ID ) ) ] ); ?>
				<?php else : ?>
					<div class="ht-card__image-placeholder"></div>
				<?php endif; ?>
				<span class="thumb-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="36" height="36"><circle cx="12" cy="12" r="12" fill="rgba(0,0,0,.6)"/><path d="M9 7l9 5-9 5V7z" fill="#fff"/></svg></span>
			</a>
			<h3 class="ht-card__headline"><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a></h3>
			<?php $views = absint( get_post_meta( $p->ID, '_svh_views', true ) ); if ( $views ) : ?>
				<span class="ht-meta"><?php echo number_format( $views ); ?> <?php esc_html_e( 'views', 'vicinity' ); ?></span>
			<?php endif; ?>
		</article>
		<?php endforeach; ?>
	</div>
	<?php
		break;

		// ── Podcast Row ──────────────────────────────────────────────────
		case 'podcast-row':
	?>
	<div class="podcast-ep-list">
		<?php foreach ( $blk_posts as $p ) :
			$duration = get_post_meta( $p->ID, '_ep_duration', true );
			$icon_id  = (int) get_post_meta( $p->ID, '_ep_icon_image_id', true );
			$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, [ 64, 64 ] ) : '';
		?>
		<article class="podcast-ep-row">
			<?php if ( $icon_url ) : ?>
				<a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>" class="podcast-ep-row__art"><img src="<?php echo esc_url( $icon_url ); ?>" width="56" height="56" alt="<?php echo esc_attr( get_the_title( $p->ID ) ); ?>"></a>
			<?php endif; ?>
			<div class="podcast-ep-row__body">
				<h4 class="podcast-ep-row__headline"><a href="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p->ID ) ); ?></a></h4>
				<p class="podcast-ep-row__deck"><?php echo esc_html( wp_trim_words( get_the_excerpt( $p->ID ), 14, '…' ) ); ?></p>
				<div class="ht-meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c', $p->ID ) ); ?>"><?php echo esc_html( vicinity_relative_date( get_post_time( 'U', false, $p->ID ) ) ); ?></time>
					<?php if ( $duration ) : ?><span class="ht-meta-sep">·</span><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
				</div>
			</div>
		</article>
		<?php endforeach; ?>
	</div>
	<?php
		break;

	endswitch;
	?>

</section><!-- .home-section-band -->

<?php endforeach; // end blocks loop ?>

</div><!-- .home-container -->
</main>

<?php get_footer(); ?>
