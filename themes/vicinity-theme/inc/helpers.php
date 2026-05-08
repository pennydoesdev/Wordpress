<?php
/**
 * Apollo Theme — Template helpers
 *
 * NYT-style components: article card (rule-based, no card boxes),
 * section header, byline, pagination, icons.
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// ARTICLE CARD
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Render an article card.
 *
 * @param int    $post_id    Post ID.
 * @param string $layout     'vertical' (default) | 'horizontal' | 'list'
 * @param bool   $show_image Whether to show the featured image.
 * @param string $image_size WP image size.
 */
function vicinity_article_card(
	int    $post_id,
	string $layout     = 'vertical',
	bool   $show_image = true,
	string $image_size = 'apollo-card-lg'
): void {
	$post   = get_post( $post_id );
	if ( ! $post ) return;

	$url      = get_permalink( $post_id );
	$title    = get_the_title( $post_id );
	$excerpt  = get_the_excerpt( $post_id );
	$cats     = get_the_category( $post_id );
	$cat      = $cats[0] ?? null;
	$author   = get_the_author_meta( 'display_name', $post->post_author );
	$date_u   = get_post_time( 'U', false, $post_id );
	$pt       = get_post_type( $post_id );

	// Type badge for media posts.
	$badge = '';
	if ( $pt === 'serve_video' ) {
		$badge = '<span class="media-badge media-badge--video">' . esc_html__( 'Video', 'vicinity' ) . '</span>';
	} elseif ( $pt === 'serve_episode' ) {
		$badge = '<span class="media-badge media-badge--audio">' . esc_html__( 'Podcast', 'vicinity' ) . '</span>';
	}

	if ( $layout === 'horizontal' ) :
	?>
	<article class="story-card story-card--h" data-post-id="<?php echo esc_attr( $post_id ); ?>">
		<div>
			<?php if ( $cat ) : ?>
				<span class="story-card__kicker"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span>
			<?php endif; ?>
			<?php if ( $badge ) echo $badge; // phpcs:ignore ?>
			<h3 class="story-card__headline story-card__headline--sm"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
			<div class="story-card__byline">
				<?php if ( $author ) echo '<strong>' . esc_html( $author ) . '</strong> &middot; '; ?>
				<time datetime="<?php echo esc_attr( get_post_time( 'c', false, $post_id ) ); ?>"><?php echo esc_html( vicinity_relative_date( $date_u ) ); ?></time>
			</div>
		</div>
		<?php if ( $show_image && has_post_thumbnail( $post_id ) ) : ?>
			<a href="<?php echo esc_url( $url ); ?>">
				<?php echo get_the_post_thumbnail( $post_id, 'apollo-card-sq', [ 'class' => 'story-card__image story-card__image--sq', 'alt' => esc_attr( $title ) ] ); ?>
			</a>
		<?php endif; ?>
	</article>

	<?php elseif ( $layout === 'list' ) : ?>

	<div class="story-list__item">
		<?php if ( $cat ) : ?>
			<span class="kicker"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span>
		<?php endif; ?>
		<h3 class="story-list__headline"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
		<div class="byline" style="margin-top:var(--sp-1);">
			<time datetime="<?php echo esc_attr( get_post_time( 'c', false, $post_id ) ); ?>"><?php echo esc_html( vicinity_relative_date( $date_u ) ); ?></time>
		</div>
	</div>

	<?php else : // vertical (default) ?>

	<article class="story-card" data-post-id="<?php echo esc_attr( $post_id ); ?>">
		<?php if ( $show_image && has_post_thumbnail( $post_id ) ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" class="thumb-wrap">
				<?php echo get_the_post_thumbnail( $post_id, $image_size, [ 'class' => 'story-card__image', 'alt' => esc_attr( $title ) ] ); ?>
				<?php if ( $pt === 'serve_video' ) : ?>
					<span class="thumb-play" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					</span>
				<?php endif; ?>
			</a>
		<?php endif; ?>

		<?php if ( $cat ) : ?>
			<span class="story-card__kicker"><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></span>
		<?php endif; ?>
		<?php if ( $badge ) echo $badge; // phpcs:ignore ?>

		<h3 class="story-card__headline">
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>

		<?php if ( $excerpt ) : ?>
			<p class="story-card__summary"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>

		<div class="story-card__byline">
			<?php if ( $author ) : ?>
				<strong><?php echo esc_html( $author ); ?></strong>
				&nbsp;&middot;&nbsp;
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_post_time( 'c', false, $post_id ) ); ?>">
				<?php echo esc_html( vicinity_relative_date( $date_u ) ); ?>
			</time>
		</div>
	</article>

	<?php endif;
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION HEADER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_section_header( string $title, string $url = '', string $eyebrow = '' ): void {
	?>
	<div class="home-section__header">
		<?php if ( $eyebrow ) : ?><span class="kicker"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
		<h2 class="home-section__title">
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $title ); ?>
			<?php endif; ?>
		</h2>
	</div>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// BYLINE
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_byline( int $post_id ): void {
	$post      = get_post( $post_id );
	$author_id = $post->post_author;
	$author    = get_the_author_meta( 'display_name', $author_id );
	$author_url = get_author_posts_url( $author_id );
	$date      = get_post_time( 'U', false, $post_id );
	// Gravatar: force 56px for 2× sharpness; filtered to b&w via CSS.
	$avatar_url = get_avatar_url( $author_id, [ 'size' => 56, 'default' => 'mystery' ] );
	?>
	<div class="byline byline--with-avatar">
		<?php if ( $author ) : ?>
			<div class="byline-author-wrap">
				<a href="<?php echo esc_url( $author_url ); ?>" class="byline-avatar" aria-label="<?php echo esc_attr( $author ); ?>">
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $author ); ?>" width="28" height="28" loading="lazy">
				</a>
				<span class="byline-author">
					<?php esc_html_e( 'By', 'vicinity' ); ?>
					<a href="<?php echo esc_url( $author_url ); ?>"><strong><?php echo esc_html( $author ); ?></strong></a>
				</span>
			</div>
		<?php endif; ?>
		<time datetime="<?php echo esc_attr( get_post_time( 'c', false, $post_id ) ); ?>">
			<?php echo esc_html( vicinity_relative_date( $date ) ); ?>
		</time>
	</div>
	<?php
}

/**
 * Return a compact byline string (for use inside templates via echo).
 */
function vicinity_byline_string( int $post_id ): string {
	$post      = get_post( $post_id );
	$author_id = $post->post_author;
	$author    = get_the_author_meta( 'display_name', $author_id );
	$author_url = get_author_posts_url( $author_id );
	$date      = get_post_time( 'U', false, $post_id );
	$avatar_url = get_avatar_url( $author_id, [ 'size' => 56, 'default' => 'mystery' ] );

	$out = '<div class="byline byline--with-avatar">';
	if ( $author ) {
		$out .= '<div class="byline-author-wrap">'
			. '<a href="' . esc_url( $author_url ) . '" class="byline-avatar" aria-label="' . esc_attr( $author ) . '">'
			. '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $author ) . '" width="28" height="28" loading="lazy">'
			. '</a>'
			. '<span class="byline-author">'
			. esc_html__( 'By', 'vicinity' ) . ' '
			. '<a href="' . esc_url( $author_url ) . '"><strong>' . esc_html( $author ) . '</strong></a>'
			. '</span>'
			. '</div>';
	}
	$out .= '<time datetime="' . esc_attr( get_post_time( 'c', false, $post_id ) ) . '">'
		. esc_html( vicinity_relative_date( $date ) )
		. '</time>';
	$out .= '</div>';
	return $out;
}

// ═══════════════════════════════════════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_pagination(): void {
	$html = paginate_links( [
		'type'      => 'list',
		'prev_text' => '&#8592;',
		'next_text' => '&#8594;',
		'before_page_number' => '',
		'after_page_number'  => '',
	] );

	if ( ! $html ) return;

	// Strip the <ul> wrapper and just use the <li> contents.
	echo '<nav class="pagination" aria-label="' . esc_attr__( 'Pagination', 'vicinity' ) . '">';
	echo preg_replace( '/<\/?ul[^>]*>/', '', $html ); // phpcs:ignore
	echo '</nav>';
}

// ═══════════════════════════════════════════════════════════════════════════
// INLINE SVG ICON
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_icon( string $name, int $size = 20, string $class = '' ): void {
	$icons = [
		'play'       => '<path d="M8 5v14l11-7z"/>',
		'pause'      => '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>',
		'search'     => '<path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
		'menu'       => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
		'close'      => '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>',
		'arrow-right'=> '<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>',
		'share'      => '<path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/>',
		'external'   => '<path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>',
		'volume'     => '<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>',
		'fullscreen' => '<path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>',
	];

	if ( ! isset( $icons[ $name ] ) ) return;

	printf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="%d" height="%d" fill="currentColor"%s aria-hidden="true">%s</svg>',
		$size, $size,
		$class ? ' class="' . esc_attr( $class ) . '"' : '',
		$icons[ $name ] // phpcs:ignore
	);
}
