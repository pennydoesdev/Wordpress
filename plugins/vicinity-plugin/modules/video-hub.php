<?php
/**
 * Video Hub — Netflix-style layout blocks for the /videos/ archive page.
 *
 * Layout is stored as JSON in the 'vicinity_vh_layout' option (not theme mod).
 * Each block: { id, enabled, options{} }
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// CARD COMPONENT
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_video_card( int $post_id, bool $large = false ): void {
	$title     = get_the_title( $post_id );
	$link      = get_permalink( $post_id );
	$duration  = (string) get_post_meta( $post_id, '_svh_duration', true );
	$views     = absint( get_post_meta( $post_id, '_svh_views', true ) );
	$thumb     = vicinity_video_thumbnail_url( $post_id );
	$preview   = (string) get_post_meta( $post_id, '_svh_preview_mp4', true );
	$is_short  = get_post_meta( $post_id, '_svh_format', true ) === 'short';
	$paywalled = vicinity_video_is_paywalled( $post_id );
	$terms     = get_the_terms( $post_id, 'serve_video_category' );
	$term      = is_array( $terms ) && ! is_wp_error( $terms ) ? $terms[0] : null;
	$ratio     = $is_short ? '9/16' : '16/9';
	$classes   = 'apollo-vcard' . ( $large ? ' apollo-vcard--large' : '' ) . ( $is_short ? ' apollo-vcard--short' : '' );
	?>
	<article class="<?php echo esc_attr( $classes ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
		<a class="apollo-vcard__thumb" href="<?php echo esc_url( $link ); ?>" style="aspect-ratio:<?php echo $ratio; ?>">
			<?php if ( $preview ) : ?>
				<?php if ( $thumb ) : ?>
					<img class="apollo-vcard__poster" src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" aria-hidden="true">
				<?php endif; ?>
				<video class="apollo-vcard__preview" src="<?php echo esc_url( $preview ); ?>" autoplay loop muted playsinline preload="none" aria-hidden="true"></video>
			<?php elseif ( $thumb ) : ?>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
			<?php else : ?>
				<div class="apollo-vcard__no-thumb"></div>
			<?php endif; ?>
			<div class="apollo-vcard__play-overlay" aria-hidden="true">
				<svg viewBox="0 0 60 60" width="44" height="44"><circle cx="30" cy="30" r="30" fill="rgba(0,0,0,.55)"/><polygon points="23,15 23,45 48,30" fill="#fff"/></svg>
			</div>
			<?php if ( $duration ) : ?>
				<span class="apollo-vcard__duration"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
			<?php if ( $paywalled ) : ?>
				<span class="apollo-vcard__lock" aria-label="<?php esc_attr_e( 'Members only', 'vicinity' ); ?>">🔒</span>
			<?php endif; ?>
		</a>
		<div class="apollo-vcard__body">
			<?php if ( $term ) : ?>
				<a class="apollo-vcard__series" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<?php endif; ?>
			<<?php echo $large ? 'h2' : 'h3'; ?> class="apollo-vcard__title">
				<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
			</<?php echo $large ? 'h2' : 'h3'; ?>>
			<div class="apollo-vcard__meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $post_id ) ); ?>"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></time>
				<?php if ( $views ) : ?>
					<span><?php echo esc_html( number_format_i18n( $views ) . ' ' . _n( 'view', 'views', $views, 'vicinity' ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// SHARED QUERY HELPER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_vh_query( array $args, array &$exclude ): ?WP_Query {
	$params = [
		'post_type'      => 'serve_video',
		'post_status'    => 'publish',
		'posts_per_page' => $args['count'] ?? 6,
		'no_found_rows'  => empty( $args['paged'] ),
		'post__not_in'   => $exclude,
	];
	if ( ! empty( $args['meta_query'] ) )  $params['meta_query']  = $args['meta_query'];
	if ( ! empty( $args['tax_query'] ) )   $params['tax_query']   = $args['tax_query'];
	if ( ! empty( $args['meta_key'] ) ) {
		$params['orderby']  = 'meta_value_num';
		$params['meta_key'] = $args['meta_key'];
		$params['order']    = 'DESC';
	}
	if ( ! empty( $args['paged'] ) ) {
		$params['paged']         = $args['paged'];
		$params['no_found_rows'] = false;
	}
	$q = new WP_Query( $params );
	if ( ! $q->have_posts() ) { wp_reset_postdata(); return null; }
	return $q;
}

function vicinity_vh_tax_query( string $slug ): array {
	if ( ! $slug ) return [];
	$t = get_term_by( 'slug', $slug, 'serve_video_category' );
	if ( ! $t || is_wp_error( $t ) ) return [];
	return [ [ 'taxonomy' => 'serve_video_category', 'field' => 'term_id', 'terms' => $t->term_id ] ];
}

// ═══════════════════════════════════════════════════════════════════════════
// BLOCK RENDERERS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_vh_render_hero( array $opts, array &$exclude, int $paged ): void {
	$cat_slug = $opts['cat'] ?? '';
	$q = vicinity_vh_query( [
		'count'      => 4,
		'meta_query' => $cat_slug ? [] : [ 'relation' => 'OR',
			[ 'key' => '_svh_featured', 'value' => '1', 'compare' => '=' ],
			[ 'key' => '_svh_featured', 'compare' => 'NOT EXISTS' ],
		],
		'meta_key'   => $cat_slug ? '' : '_svh_views',
		'tax_query'  => vicinity_vh_tax_query( $cat_slug ),
	], $exclude );
	if ( ! $q ) return;

	$posts = $q->posts;
	$featured = $posts[0] ?? null;
	$aside    = array_slice( $posts, 1, 3 );
	foreach ( $posts as $p ) $exclude[] = $p->ID;
	wp_reset_postdata();
	?>
	<section class="apollo-vh-hero">
		<?php if ( $featured ) : ?>
		<div class="apollo-vh-hero__main">
			<?php vicinity_video_card( $featured->ID, true ); ?>
		</div>
		<?php endif; ?>
		<?php if ( $aside ) : ?>
		<div class="apollo-vh-hero__aside">
			<?php foreach ( $aside as $v ) vicinity_video_card( $v->ID ); ?>
		</div>
		<?php endif; ?>
	</section>
	<?php
}

function vicinity_vh_render_carousel( array $opts, array &$exclude, int $paged ): void {
	$cat_slug = $opts['cat'] ?? '';
	$count    = max( 3, min( 12, (int) ( $opts['count'] ?? 6 ) ) );
	$label    = $opts['label'] ?? '';

	// Blank category = one row per series.
	if ( ! $cat_slug ) {
		$terms = get_terms( [ 'taxonomy' => 'serve_video_category', 'hide_empty' => true, 'number' => 10, 'orderby' => 'count', 'order' => 'DESC' ] );
		if ( is_wp_error( $terms ) || ! $terms ) return;
		foreach ( $terms as $term ) {
			$q = vicinity_vh_query( [ 'count' => $count, 'tax_query' => vicinity_vh_tax_query( $term->slug ) ], $exclude );
			if ( ! $q ) continue;
			$posts = $q->posts;
			foreach ( $posts as $p ) $exclude[] = $p->ID;
			wp_reset_postdata();
			?>
			<section class="apollo-vh-row">
				<div class="apollo-vh-row__header">
					<h2 class="apollo-vh-row__title"><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></h2>
					<a class="apollo-vh-row__all" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php esc_html_e( 'See all', 'vicinity' ); ?> →</a>
				</div>
				<div class="apollo-vh-row__track">
					<?php foreach ( $posts as $v ) vicinity_video_card( $v->ID ); ?>
				</div>
			</section>
			<?php
		}
		return;
	}

	$term = get_term_by( 'slug', $cat_slug, 'serve_video_category' );
	$q    = vicinity_vh_query( [ 'count' => $count, 'tax_query' => vicinity_vh_tax_query( $cat_slug ) ], $exclude );
	if ( ! $q ) return;
	$posts = $q->posts;
	foreach ( $posts as $p ) $exclude[] = $p->ID;
	wp_reset_postdata();

	$row_label = $label ?: ( $term ? $term->name : __( 'Latest Videos', 'vicinity' ) );
	?>
	<section class="apollo-vh-row">
		<div class="apollo-vh-row__header">
			<h2 class="apollo-vh-row__title">
				<?php if ( $term ) : ?><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php endif; ?>
				<?php echo esc_html( $row_label ); ?>
				<?php if ( $term ) : ?></a><?php endif; ?>
			</h2>
			<?php if ( $term ) : ?><a class="apollo-vh-row__all" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php esc_html_e( 'See all', 'vicinity' ); ?> →</a><?php endif; ?>
		</div>
		<div class="apollo-vh-row__track">
			<?php foreach ( $posts as $v ) vicinity_video_card( $v->ID ); ?>
		</div>
	</section>
	<?php
}

function vicinity_vh_render_shorts( array $opts, array &$exclude ): void {
	$cat_slug = $opts['cat'] ?? '';
	$count    = max( 4, min( 16, (int) ( $opts['count'] ?? 8 ) ) );
	$label    = $opts['label'] ?? __( 'Shorts', 'vicinity' );

	// Auto-detect Shorts category if not set.
	if ( ! $cat_slug ) {
		$auto = get_option( 'vicinity_shorts_category', '' );
		if ( ! $auto ) {
			$t = get_term_by( 'name', 'Shorts', 'serve_video_category' )
			  ?: get_term_by( 'slug', 'shorts', 'serve_video_category' );
			if ( $t && ! is_wp_error( $t ) ) $auto = $t->slug;
		}
		$cat_slug = $auto;
	}

	$q = vicinity_vh_query( [
		'count'      => $count,
		'meta_query' => [ [ 'key' => '_svh_format', 'value' => 'short', 'compare' => '=' ] ],
		'tax_query'  => $cat_slug ? vicinity_vh_tax_query( $cat_slug ) : [],
	], $exclude );
	if ( ! $q ) return;
	$posts = $q->posts;
	foreach ( $posts as $p ) $exclude[] = $p->ID;
	wp_reset_postdata();
	?>
	<section class="apollo-vh-shorts">
		<div class="apollo-vh-shorts__header">
			<h2 class="apollo-vh-shorts__title"><?php echo esc_html( $label ); ?></h2>
		</div>
		<div class="apollo-vh-shorts__track">
			<?php foreach ( $posts as $v ) vicinity_video_card( $v->ID ); ?>
		</div>
	</section>
	<?php
}

function vicinity_vh_render_grid( array $opts, array &$exclude, int $paged ): void {
	$label = $opts['label'] ?? __( 'All Videos', 'vicinity' );
	$count = max( 6, min( 24, (int) ( $opts['count'] ?? 12 ) ) );
	$cols  = max( 2, min( 4, (int) ( $opts['cols'] ?? 3 ) ) );
	$paged_on = ! empty( $opts['paged'] );

	$q = vicinity_vh_query( [
		'count'     => $count,
		'tax_query' => $opts['cat'] ? vicinity_vh_tax_query( $opts['cat'] ) : [],
		'paged'     => $paged_on ? $paged : 0,
	], $exclude );
	if ( ! $q ) return;
	$posts = $q->posts;
	foreach ( $posts as $p ) $exclude[] = $p->ID;
	$max_pages = $q->max_num_pages;
	wp_reset_postdata();
	?>
	<section class="apollo-vh-grid">
		<div class="apollo-vh-grid__header">
			<h2 class="apollo-vh-grid__title"><?php echo esc_html( $label ); ?></h2>
		</div>
		<div class="apollo-vh-grid__cards" style="--apollo-vh-cols:<?php echo esc_attr( $cols ); ?>">
			<?php foreach ( $posts as $v ) vicinity_video_card( $v->ID ); ?>
		</div>
		<?php if ( $paged_on && $max_pages > 1 ) : ?>
		<div class="apollo-vh-grid__pagination">
			<?php echo paginate_links( [ 'total' => $max_pages, 'current' => $paged ] ); ?>
		</div>
		<?php endif; ?>
	</section>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// LAYOUT STATE + RENDERER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_vh_default_layout(): array {
	return [
		[ 'id' => 'hero',      'enabled' => true,  'options' => [ 'cat' => '', 'excerpt' => true ] ],
		[ 'id' => 'carousel',  'enabled' => true,  'options' => [ 'cat' => '', 'count' => 6, 'label' => '' ] ],
		[ 'id' => 'shorts',    'enabled' => true,  'options' => [ 'cat' => '', 'count' => 8, 'label' => 'Shorts' ] ],
		[ 'id' => 'grid',      'enabled' => true,  'options' => [ 'cat' => '', 'count' => 12, 'cols' => 3, 'label' => 'All Videos', 'paged' => true ] ],
	];
}

function vicinity_vh_get_layout(): array {
	$stored = get_option( 'vicinity_vh_layout', '' );
	if ( ! $stored ) return vicinity_vh_default_layout();
	$decoded = json_decode( $stored, true );
	return is_array( $decoded ) ? $decoded : vicinity_vh_default_layout();
}

function vicinity_vh_render_all( int $paged ): void {
	$layout  = vicinity_vh_get_layout();
	$exclude = [];
	foreach ( $layout as $block ) {
		if ( empty( $block['enabled'] ) ) continue;
		$id   = $block['id'] ?? '';
		$opts = $block['options'] ?? [];
		match ( $id ) {
			'hero'     => vicinity_vh_render_hero( $opts, $exclude, $paged ),
			'carousel' => vicinity_vh_render_carousel( $opts, $exclude, $paged ),
			'shorts'   => vicinity_vh_render_shorts( $opts, $exclude ),
			'grid'     => vicinity_vh_render_grid( $opts, $exclude, $paged ),
			default    => null,
		};
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// CATEGORY NAV BAR
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_vh_category_nav(): void {
	$terms = get_terms( [ 'taxonomy' => 'serve_video_category', 'hide_empty' => true, 'number' => 20, 'orderby' => 'count', 'order' => 'DESC' ] );
	if ( is_wp_error( $terms ) || ! $terms ) return;
	?>
	<nav class="apollo-vh-series-nav" aria-label="<?php esc_attr_e( 'Video series', 'vicinity' ); ?>">
		<a class="apollo-vh-series-nav__item<?php echo ! is_tax() ? ' is-current' : ''; ?>"
		   href="<?php echo esc_url( get_post_type_archive_link( 'serve_video' ) ); ?>">
			<?php esc_html_e( 'All', 'vicinity' ); ?>
		</a>
		<?php foreach ( $terms as $term ) :
			$current = is_tax( 'serve_video_category', $term->term_id ); ?>
			<a class="apollo-vh-series-nav__item<?php echo $current ? ' is-current' : ''; ?>"
			   href="<?php echo esc_url( get_term_link( $term ) ); ?>">
				<?php echo esc_html( $term->name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}