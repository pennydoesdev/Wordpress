<?php
/**
 * Vicinity Theme Customizer
 *
 * Architecture
 * ────────────
 * One top-level panel "Vicinity Theme" houses all sections:
 *   • Branding       — logo, site name, social links
 *   • Colors         — full 12-token palette
 *   • Typography     — headline / body / UI fonts, sizes, line-height
 *   • Header         — layout variant, sticky, search bar, social row
 *   • Homepage       — breaking ticker + dynamic Section Blocks (JSON repeater)
 *   • Article Pages  — sidebar, related posts, reading-time, author box
 *   • Archive Pages  — layout, sidebar, show excerpts
 *   • Footer         — column count, tagline, social icons, copyright
 *   • Video Player   — Video.js skin, accent, preload, autoplay, PiP, rates
 *
 * Auto-registration hook
 * ──────────────────────
 * Any module (plugin, child theme) can add appearance controls:
 *
 *   add_filter( 'vicinity_appearance_blocks', function( array $blocks ): array {
 *       $blocks[] = [
 *           'section_id'  => 'my_module_section',
 *           'title'       => 'My Module',
 *           'priority'    => 90,
 *           'settings'    => [
 *               [
 *                   'id'       => 'my_option_key',
 *                   'type'     => 'checkbox|text|select|color|number|url|textarea|image',
 *                   'label'    => 'My setting',
 *                   'default'  => false,
 *                   'choices'  => [],   // for select
 *                   'transport'=> 'postMessage',
 *               ],
 *           ],
 *       ];
 *       return $blocks;
 *   } );
 *
 * Homepage Section Blocks
 * ───────────────────────
 * Stored as JSON in option `vicinity_home_blocks`. Each block:
 *   { id, layout, category, title, count, show_more }
 *
 * Available layouts: 3col | hero-stack | card-row | list-feed | full-hero | video-row | podcast-row
 *
 * @package Apollo (Vicinity)
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// DEFAULTS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_default_home_blocks(): array {
	return [
		[ 'id' => 'block_1', 'layout' => '3col',       'category' => 0, 'title' => '', 'count' => 5, 'show_more' => true ],
		[ 'id' => 'block_2', 'layout' => 'hero-stack',  'category' => 0, 'title' => '', 'count' => 4, 'show_more' => true ],
		[ 'id' => 'block_3', 'layout' => 'card-row',    'category' => 0, 'title' => '', 'count' => 4, 'show_more' => false ],
	];
}

function vicinity_home_blocks(): array {
	$raw = get_option( 'vicinity_home_blocks', '' );
	if ( ! $raw ) return vicinity_default_home_blocks();
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : vicinity_default_home_blocks();
}

function vicinity_sanitize_home_blocks( string $input ): string {
	$decoded = json_decode( $input, true );
	if ( ! is_array( $decoded ) ) return '';
	$allowed_layouts = [ '3col', 'hero-stack', 'card-row', 'list-feed', 'full-hero', 'video-row', 'podcast-row' ];
	$clean = [];
	foreach ( $decoded as $block ) {
		if ( ! is_array( $block ) ) continue;
		$clean[] = [
			'id'        => sanitize_key( $block['id'] ?? wp_generate_uuid4() ),
			'layout'    => in_array( $block['layout'] ?? '3col', $allowed_layouts, true ) ? $block['layout'] : '3col',
			'category'  => absint( $block['category'] ?? 0 ),
			'title'     => sanitize_text_field( $block['title'] ?? '' ),
			'count'     => min( 12, max( 1, absint( $block['count'] ?? 5 ) ) ),
			'show_more' => (bool) ( $block['show_more'] ?? true ),
		];
	}
	return wp_json_encode( $clean );
}

// ═══════════════════════════════════════════════════════════════════════════
// CUSTOM CONTROL: BLOCKS REPEATER
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'customize_register', static function ( WP_Customize_Manager $wp_customize ): void {

	/**
	 * Blocks-repeater control — renders the JSON editor + "+" button.
	 * The heavy lifting is done in customizer-controls.js.
	 */
	class Vicinity_Blocks_Control extends WP_Customize_Control {
		public string $type = 'vicinity_blocks';

		public function enqueue(): void {
			wp_enqueue_script(
				'vicinity-customizer-controls',
				VICINITY_THEME_URI . '/assets/js/customizer-controls.js',
				[ 'customize-controls', 'jquery' ],
				VICINITY_THEME_VERSION,
				true
			);
			wp_add_inline_style( 'customize-controls', vicinity_customizer_controls_css() );
			wp_localize_script( 'vicinity-customizer-controls', 'vicinityBlocks', [
				'categories' => vicinity_categories_for_js(),
				'layouts'    => vicinity_layouts_for_js(),
				'nonce'      => wp_create_nonce( 'vicinity_blocks' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'l10n'       => [
					'addBlock'     => __( '+ Add Section Block', 'vicinity' ),
					'removeBlock'  => __( 'Remove', 'vicinity' ),
					'moveUp'       => __( '↑', 'vicinity' ),
					'moveDown'     => __( '↓', 'vicinity' ),
					'layoutLabel'  => __( 'Layout', 'vicinity' ),
					'categoryLabel'=> __( 'Category', 'vicinity' ),
					'titleLabel'   => __( 'Section Title (optional)', 'vicinity' ),
					'countLabel'   => __( 'Post Count', 'vicinity' ),
					'showMoreLabel'=> __( 'Show "More" link', 'vicinity' ),
					'latest'       => __( 'Latest Posts', 'vicinity' ),
				],
			] );
		}

		public function render_content(): void {
			$value = $this->value();
			?>
			<label class="customize-control-title"><?php echo esc_html( $this->label ); ?></label>
			<?php if ( $this->description ) : ?>
				<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>
			<div class="vicinity-blocks-editor" id="vicinity-blocks-editor">
				<div class="vicinity-blocks-list" id="vicinity-blocks-list">
					<!-- Populated by customizer-controls.js -->
				</div>
				<button type="button" class="button button-secondary vicinity-add-block" id="vicinity-add-block">
					<?php esc_html_e( '+ Add Section Block', 'vicinity' ); ?>
				</button>
			</div>
			<input
				type="hidden"
				id="<?php echo esc_attr( $this->id ); ?>"
				name="<?php echo esc_attr( $this->id ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php $this->link(); ?>
			/>
			<?php
		}
	}

	vicinity_register( $wp_customize );

}, 10 );

// ═══════════════════════════════════════════════════════════════════════════
// REGISTRATION
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_register( WP_Customize_Manager $wp_customize ): void {

	// ── Top-level panel ────────────────────────────────────────────────────
	$wp_customize->add_panel( 'vicinity_theme', [
		'title'       => __( 'Vicinity Theme', 'vicinity' ),
		'description' => __( 'All appearance settings for the Vicinity theme.', 'vicinity' ),
		'priority'    => 25,
	] );

	// ════════════════════════════════════════════════════════════════════════
	// 1. BRANDING
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_branding', [
		'title'    => __( 'Branding', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 10,
	] );

	// Logo upload.
	$wp_customize->add_setting( 'vicinity_logo_id', [
		'default'           => 0,
		'sanitize_callback' => 'absint',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'vicinity_logo_id', [
		'label'      => __( 'Site Logo', 'vicinity' ),
		'section'    => 'vicinity_branding',
		'mime_type'  => 'image',
	] ) );

	// Site tagline (supplementary to WP core).
	$wp_customize->add_setting( 'vicinity_site_tagline', [
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_site_tagline', [
		'label'       => __( 'Site Tagline', 'vicinity' ),
		'description' => __( 'Short descriptor shown beneath the logo.', 'vicinity' ),
		'section'     => 'vicinity_branding',
		'type'        => 'text',
	] );

	// Social links.
	foreach ( [
		'twitter'   => 'Twitter / X URL',
		'facebook'  => 'Facebook URL',
		'instagram' => 'Instagram URL',
		'youtube'   => 'YouTube URL',
		'tiktok'    => 'TikTok URL',
		'linkedin'  => 'LinkedIn URL',
		'rss'       => 'RSS Feed URL',
	] as $key => $label ) {
		$wp_customize->add_setting( 'vicinity_social_' . $key, [
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		] );
		$wp_customize->add_control( 'vicinity_social_' . $key, [
			'label'   => __( $label, 'vicinity' ),
			'section' => 'vicinity_branding',
			'type'    => 'url',
		] );
	}

	// ════════════════════════════════════════════════════════════════════════
	// 2. COLORS
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_colors', [
		'title'    => __( 'Colors', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 20,
	] );

	$color_tokens = [
		'vicinity_accent_color'      => [ 'Accent Color',           '#c62828', 'Kickers, links-on-hover, live badge, section rules, progress bar.' ],
		'vicinity_color_secondary' => [ 'Secondary Accent',       '#326891', 'Category labels, podcast brand color.' ],
		'vicinity_color_text'      => [ 'Body Text',              '#111111', 'Main article and body copy.' ],
		'vicinity_color_text_muted'=> [ 'Muted Text',             '#666666', 'Bylines, captions, meta.' ],
		'vicinity_color_bg'        => [ 'Page Background',        '#f4f1eb', 'Site-wide background.' ],
		'vicinity_color_surface'   => [ 'Card / Surface',         '#ffffff', 'Cards, sidebars, overlays.' ],
		'vicinity_color_border'    => [ 'Border',                 '#d9d9d9', 'Dividers and rule lines.' ],
		'vicinity_color_rule_strong'=>[ 'Strong Rule',            '#000000', 'Section-heading rules.' ],
		'vicinity_color_breaking'  => [ 'Breaking / Live Badge',  '#c62828', 'Breaking news bar and LIVE badge.' ],
		'vicinity_color_headline'  => [ 'Headline Text',          '#0a0a0a', 'All headline elements.' ],
		'vicinity_color_link'      => [ 'Link Color',             '#c62828', 'Inline hyperlinks.' ],
		'vicinity_color_footer_bg' => [ 'Footer Background',      '#0a0a0a', 'Footer band background.' ],
	];

	foreach ( $color_tokens as $id => [ $label, $default, $desc ] ) {
		$wp_customize->add_setting( $id, [
			'default'           => $default,
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'postMessage',
		] );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, [
			'label'       => __( $label, 'vicinity' ),
			'description' => __( $desc, 'vicinity' ),
			'section'     => 'vicinity_colors',
		] ) );
	}

	// ════════════════════════════════════════════════════════════════════════
	// 3. TYPOGRAPHY
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_typography', [
		'title'    => __( 'Typography', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 30,
	] );

	$font_choices = [
		"'Zilla Slab', Georgia, serif"                        => 'Zilla Slab (current)',
		"'Libre Baskerville', Georgia, serif"                 => 'Libre Baskerville',
		"'Playfair Display', Georgia, serif"                  => 'Playfair Display',
		"'Merriweather', Georgia, serif"                      => 'Merriweather',
		"'Lora', Georgia, serif"                              => 'Lora',
		"Georgia, 'Times New Roman', serif"                   => 'Georgia (system)',
		"'Libre Franklin', Arial, sans-serif"                 => 'Libre Franklin',
		"'Inter', Arial, sans-serif"                          => 'Inter',
		"'Source Sans Pro', Arial, sans-serif"                => 'Source Sans Pro',
		"Arial, Helvetica, sans-serif"                        => 'Arial (system)',
	];

	$wp_customize->add_setting( 'vicinity_font_headline', [
		'default'           => "'Zilla Slab', Georgia, 'Times New Roman', serif",
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_font_headline', [
		'label'   => __( 'Headline Font', 'vicinity' ),
		'section' => 'vicinity_typography',
		'type'    => 'select',
		'choices' => $font_choices,
	] );

	$wp_customize->add_setting( 'vicinity_font_body', [
		'default'           => "Georgia, 'Times New Roman', serif",
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_font_body', [
		'label'   => __( 'Body Font', 'vicinity' ),
		'section' => 'vicinity_typography',
		'type'    => 'select',
		'choices' => $font_choices,
	] );

	$wp_customize->add_setting( 'vicinity_font_ui', [
		'default'           => "'Libre Franklin', 'Franklin Gothic Medium', Arial, sans-serif",
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_font_ui', [
		'label'   => __( 'UI / Navigation Font', 'vicinity' ),
		'section' => 'vicinity_typography',
		'type'    => 'select',
		'choices' => $font_choices,
	] );

	$wp_customize->add_setting( 'vicinity_base_font_size', [
		'default'           => 17,
		'sanitize_callback' => 'absint',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_base_font_size', [
		'label'       => __( 'Base Font Size (px)', 'vicinity' ),
		'description' => __( 'Body copy size. Headlines scale proportionally. Default: 17', 'vicinity' ),
		'section'     => 'vicinity_typography',
		'type'        => 'number',
		'input_attrs' => [ 'min' => 13, 'max' => 22, 'step' => 1 ],
	] );

	$wp_customize->add_setting( 'vicinity_body_line_height', [
		'default'           => 1.75,
		'sanitize_callback' => static fn( $v ) => round( max( 1.2, min( 2.2, floatval( $v ) ) ), 2 ),
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_body_line_height', [
		'label'       => __( 'Body Line Height', 'vicinity' ),
		'description' => __( 'Default: 1.75', 'vicinity' ),
		'section'     => 'vicinity_typography',
		'type'        => 'number',
		'input_attrs' => [ 'min' => 1.2, 'max' => 2.2, 'step' => 0.05 ],
	] );

	$wp_customize->add_setting( 'vicinity_content_width', [
		'default'           => 680,
		'sanitize_callback' => 'absint',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_content_width', [
		'label'       => __( 'Article Body Width (px)', 'vicinity' ),
		'description' => __( 'Max-width of article body text column. Default: 680', 'vicinity' ),
		'section'     => 'vicinity_typography',
		'type'        => 'number',
		'input_attrs' => [ 'min' => 480, 'max' => 900, 'step' => 10 ],
	] );

	// ════════════════════════════════════════════════════════════════════════
	// 4. HEADER
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_header', [
		'title'    => __( 'Header', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 40,
	] );

	$wp_customize->add_setting( 'vicinity_header_layout', [
		'default'           => 'nyt-3tier',
		'sanitize_callback' => static fn( $v ) => in_array( $v, [ 'nyt-3tier', 'centered', 'compact', 'minimal' ], true ) ? $v : 'nyt-3tier',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_header_layout', [
		'label'   => __( 'Header Layout', 'vicinity' ),
		'section' => 'vicinity_header',
		'type'    => 'select',
		'choices' => [
			'nyt-3tier' => __( 'NYT 3-tier (date bar + nav + section nav)', 'vicinity' ),
			'centered'  => __( 'Centered logo + nav below', 'vicinity' ),
			'compact'   => __( 'Compact single bar', 'vicinity' ),
			'minimal'   => __( 'Minimal — logo + burger only', 'vicinity' ),
		],
	] );

	$wp_customize->add_setting( 'vicinity_header_sticky', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_header_sticky', [
		'label'   => __( 'Sticky header on scroll', 'vicinity' ),
		'section' => 'vicinity_header',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_header_show_search', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_header_show_search', [
		'label'   => __( 'Show search icon', 'vicinity' ),
		'section' => 'vicinity_header',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_header_show_social', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_header_show_social', [
		'label'   => __( 'Show social icons in header', 'vicinity' ),
		'section' => 'vicinity_header',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_header_show_date', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_header_show_date', [
		'label'   => __( 'Show date in top bar', 'vicinity' ),
		'section' => 'vicinity_header',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_header_bg', [
		'default'           => '#ffffff',
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'vicinity_header_bg', [
		'label'   => __( 'Header Background Color', 'vicinity' ),
		'section' => 'vicinity_header',
	] ) );

	// ════════════════════════════════════════════════════════════════════════
	// 5. NAVIGATION — Menu labels for CPT archive pages
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_navigation', [
		'title'       => __( 'Navigation', 'vicinity' ),
		'panel'       => 'vicinity_theme',
		'priority'    => 45,
		'description' => __( 'Set the label used for Videos and Podcasts in your navigation menus. To add them to a menu, open Appearance → Menus and look under "Post Type Archives".', 'vicinity' ),
	] );

	// Videos archive label.
	$wp_customize->add_setting( 'vicinity_nav_videos_label', [
		'default'           => __( 'Watch', 'vicinity' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	] );
	$wp_customize->add_control( 'vicinity_nav_videos_label', [
		'label'       => __( 'Videos Menu Label', 'vicinity' ),
		'description' => __( 'Text shown in nav menus for the Videos archive (/videos/). E.g. "Watch", "Video Hub".', 'vicinity' ),
		'section'     => 'vicinity_navigation',
		'type'        => 'text',
	] );

	// Podcasts archive label.
	$wp_customize->add_setting( 'vicinity_nav_podcasts_label', [
		'default'           => __( 'Listen', 'vicinity' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	] );
	$wp_customize->add_control( 'vicinity_nav_podcasts_label', [
		'label'       => __( 'Podcasts Menu Label', 'vicinity' ),
		'description' => __( 'Text shown in nav menus for the Podcasts archive (/listen/). E.g. "Podcasts", "Shows", "Audio".', 'vicinity' ),
		'section'     => 'vicinity_navigation',
		'type'        => 'text',
	] );

	// Primary menu Videos position hint (read-only info control).
	$wp_customize->add_setting( 'vicinity_nav_info', [
		'default'           => '',
		'sanitize_callback' => '__return_empty_string',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( new WP_Customize_Control( $wp_customize, 'vicinity_nav_info', [
		'label'       => __( 'How to add to menus', 'vicinity' ),
		'description' => __( 'In Appearance → Menus, click "Screen Options" (top-right) and enable "Video Hub" and "Podcasts". They will appear under "Post Type Archives" ready to drag into any menu.', 'vicinity' ),
		'section'     => 'vicinity_navigation',
		'type'        => 'hidden',
	] ) );

	// ════════════════════════════════════════════════════════════════════════
	// 6. HOMEPAGE — Breaking ticker + Section Blocks
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_homepage', [
		'title'    => __( 'Homepage', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 50,
	] );

	$wp_customize->add_setting( 'vicinity_breaking_text', [
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_breaking_text', [
		'label'       => __( 'Breaking News Text', 'vicinity' ),
		'description' => __( 'Leave blank to hide the breaking news banner.', 'vicinity' ),
		'section'     => 'vicinity_homepage',
		'type'        => 'text',
	] );

	$wp_customize->add_setting( 'vicinity_breaking_url', [
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	] );
	$wp_customize->add_control( 'vicinity_breaking_url', [
		'label'   => __( 'Breaking News URL', 'vicinity' ),
		'section' => 'vicinity_homepage',
		'type'    => 'url',
	] );

	$wp_customize->add_setting( 'vicinity_home_top_layout', [
		'default'           => 'nyt-3col',
		'sanitize_callback' => static fn( $v ) => in_array( $v, [ 'nyt-3col', 'hero-full', 'hero-sidebar' ], true ) ? $v : 'nyt-3col',
	] );
	$wp_customize->add_control( 'vicinity_home_top_layout', [
		'label'   => __( 'Top Grid Layout', 'vicinity' ),
		'section' => 'vicinity_homepage',
		'type'    => 'select',
		'choices' => [
			'nyt-3col'     => __( 'NYT 3-column grid (27/46/27)', 'vicinity' ),
			'hero-full'    => __( 'Full-width hero + card row', 'vicinity' ),
			'hero-sidebar' => __( 'Hero left + sidebar stack', 'vicinity' ),
		],
	] );

	// Homepage Section Blocks — the JSON repeater.
	$wp_customize->add_setting( 'vicinity_home_blocks', [
		'default'           => wp_json_encode( vicinity_default_home_blocks() ),
		'sanitize_callback' => 'vicinity_sanitize_home_blocks',
		'transport'         => 'refresh',
	] );
	$wp_customize->add_control( new Vicinity_Blocks_Control( $wp_customize, 'vicinity_home_blocks', [
		'label'       => __( 'Homepage Section Blocks', 'vicinity' ),
		'description' => __( 'Add, remove and reorder the content sections that appear below the top grid. Each block can use a different layout.', 'vicinity' ),
		'section'     => 'vicinity_homepage',
	] ) );

	// ════════════════════════════════════════════════════════════════════════
	// 7. ARTICLE PAGES
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_article', [
		'title'    => __( 'Article Pages', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 60,
	] );

	$wp_customize->add_setting( 'vicinity_article_sidebar', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_article_sidebar', [
		'label'   => __( 'Show article sidebar', 'vicinity' ),
		'section' => 'vicinity_article',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_article_related', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
	] );
	$wp_customize->add_control( 'vicinity_article_related', [
		'label'   => __( 'Show related articles', 'vicinity' ),
		'section' => 'vicinity_article',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_article_reading_time', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_article_reading_time', [
		'label'   => __( 'Show estimated reading time', 'vicinity' ),
		'section' => 'vicinity_article',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_article_author_box', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
	] );
	$wp_customize->add_control( 'vicinity_article_author_box', [
		'label'   => __( 'Show author bio box below articles', 'vicinity' ),
		'section' => 'vicinity_article',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_article_show_tags', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_article_show_tags', [
		'label'   => __( 'Show tags below article body', 'vicinity' ),
		'section' => 'vicinity_article',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_article_share_buttons', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_article_share_buttons', [
		'label'   => __( 'Show social share buttons', 'vicinity' ),
		'section' => 'vicinity_article',
		'type'    => 'checkbox',
	] );

	// ════════════════════════════════════════════════════════════════════════
	// 8. ARCHIVE PAGES
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_archive', [
		'title'    => __( 'Archive & Category Pages', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 70,
	] );

	$wp_customize->add_setting( 'vicinity_archive_layout', [
		'default'           => 'nyt-feature',
		'sanitize_callback' => static fn( $v ) => in_array( $v, [ 'nyt-feature', 'card-grid', 'list' ], true ) ? $v : 'nyt-feature',
	] );
	$wp_customize->add_control( 'vicinity_archive_layout', [
		'label'   => __( 'Archive Layout', 'vicinity' ),
		'section' => 'vicinity_archive',
		'type'    => 'select',
		'choices' => [
			'nyt-feature' => __( 'NYT — feature hero + dated list', 'vicinity' ),
			'card-grid'   => __( 'Card grid', 'vicinity' ),
			'list'        => __( 'Simple list', 'vicinity' ),
		],
	] );

	$wp_customize->add_setting( 'vicinity_archive_show_excerpt', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
	] );
	$wp_customize->add_control( 'vicinity_archive_show_excerpt', [
		'label'   => __( 'Show excerpts in archive lists', 'vicinity' ),
		'section' => 'vicinity_archive',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_archive_sidebar', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
	] );
	$wp_customize->add_control( 'vicinity_archive_sidebar', [
		'label'   => __( 'Show archive sidebar', 'vicinity' ),
		'section' => 'vicinity_archive',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_archive_posts_per_page', [
		'default'           => 12,
		'sanitize_callback' => 'absint',
	] );
	$wp_customize->add_control( 'vicinity_archive_posts_per_page', [
		'label'       => __( 'Posts per archive page', 'vicinity' ),
		'section'     => 'vicinity_archive',
		'type'        => 'number',
		'input_attrs' => [ 'min' => 5, 'max' => 50 ],
	] );

	// ════════════════════════════════════════════════════════════════════════
	// 9. FOOTER
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_footer', [
		'title'    => __( 'Footer', 'vicinity' ),
		'panel'    => 'vicinity_theme',
		'priority' => 80,
	] );

	$wp_customize->add_setting( 'vicinity_footer_tagline', [
		'default'           => 'Independent journalism for the modern reader.',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_footer_tagline', [
		'label'   => __( 'Footer Tagline', 'vicinity' ),
		'section' => 'vicinity_footer',
		'type'    => 'text',
	] );

	$wp_customize->add_setting( 'vicinity_footer_copyright', [
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_footer_copyright', [
		'label'       => __( 'Copyright Text', 'vicinity' ),
		'description' => __( 'Leave blank to auto-generate "© {year} {site name}".', 'vicinity' ),
		'section'     => 'vicinity_footer',
		'type'        => 'text',
	] );

	$wp_customize->add_setting( 'vicinity_footer_columns', [
		'default'           => 3,
		'sanitize_callback' => static fn( $v ) => min( 4, max( 1, absint( $v ) ) ),
	] );
	$wp_customize->add_control( 'vicinity_footer_columns', [
		'label'       => __( 'Footer Link Columns', 'vicinity' ),
		'section'     => 'vicinity_footer',
		'type'        => 'select',
		'choices'     => [ 1 => '1', 2 => '2', 3 => '3', 4 => '4' ],
	] );

	$wp_customize->add_setting( 'vicinity_footer_show_social', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_footer_show_social', [
		'label'   => __( 'Show social icons in footer', 'vicinity' ),
		'section' => 'vicinity_footer',
		'type'    => 'checkbox',
	] );

	$wp_customize->add_setting( 'vicinity_footer_show_logo', [
		'default'           => true,
		'sanitize_callback' => static fn( $v ) => (bool) $v,
		'transport'         => 'postMessage',
	] );
	$wp_customize->add_control( 'vicinity_footer_show_logo', [
		'label'   => __( 'Show logo in footer', 'vicinity' ),
		'section' => 'vicinity_footer',
		'type'    => 'checkbox',
	] );

	// ════════════════════════════════════════════════════════════════════════
	// 10. VIDEO PLAYER
	// ════════════════════════════════════════════════════════════════════════
	$wp_customize->add_section( 'vicinity_video_player', [
		'title'       => __( 'Video Player', 'vicinity' ),
		'description' => __( 'Configure the Video.js player (v8.21) used throughout the site.', 'vicinity' ),
		'panel'       => 'vicinity_theme',
		'priority'    => 90,
	] );

	$wp_customize->add_setting( 'videojs_skin', [
		'default'           => 'vjs-big-play-centered apollo-dark-skin',
		'sanitize_callback' => 'sanitize_text_field',
	] );
	$wp_customize->add_control( 'videojs_skin', [
		'label'   => __( 'Player Skin', 'vicinity' ),
		'section' => 'vicinity_video_player',
		'type'    => 'select',
		'choices' => [
			'vjs-big-play-centered apollo-dark-skin' => __( 'Apollo Dark (default)', 'vicinity' ),
			'vjs-big-play-centered vjs-default-skin' => __( 'Video.js Default', 'vicinity' ),
		],
	] );

	$wp_customize->add_setting( 'videojs_accent_color', [
		'default'           => '',
		'sanitize_callback' => 'sanitize_hex_color',
	] );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'videojs_accent_color', [
		'label'       => __( 'Player Accent Color', 'vicinity' ),
		'description' => __( 'Progress bar and volume level. Leave blank to inherit site accent.', 'vicinity' ),
		'section'     => 'vicinity_video_player',
	] ) );

	foreach ( [
		'videojs_fluid'      => [ 'Fluid (responsive) player',       true  ],
		'videojs_pip'        => [ 'Enable Picture-in-Picture button', true  ],
		'videojs_show_rates' => [ 'Show playback speed control',      true  ],
	] as $id => [ $label, $default ] ) {
		$wp_customize->add_setting( $id, [
			'default'           => $default,
			'sanitize_callback' => static fn( $v ) => (bool) $v,
		] );
		$wp_customize->add_control( $id, [
			'label'   => __( $label, 'vicinity' ),
			'section' => 'vicinity_video_player',
			'type'    => 'checkbox',
		] );
	}

	$wp_customize->add_setting( 'videojs_preload', [
		'default'           => 'metadata',
		'sanitize_callback' => static fn( $v ) => in_array( $v, [ 'none', 'metadata', 'auto' ], true ) ? $v : 'metadata',
	] );
	$wp_customize->add_control( 'videojs_preload', [
		'label'   => __( 'Preload Strategy', 'vicinity' ),
		'section' => 'vicinity_video_player',
		'type'    => 'select',
		'choices' => [
			'none'     => __( 'None — load nothing until play', 'vicinity' ),
			'metadata' => __( 'Metadata only (recommended)', 'vicinity' ),
			'auto'     => __( 'Auto — browser decides', 'vicinity' ),
		],
	] );

	$wp_customize->add_setting( 'videojs_autoplay_policy', [
		'default'           => 'never',
		'sanitize_callback' => static fn( $v ) => in_array( $v, [ 'never', 'muted', 'always' ], true ) ? $v : 'never',
	] );
	$wp_customize->add_control( 'videojs_autoplay_policy', [
		'label'   => __( 'Autoplay Policy', 'vicinity' ),
		'section' => 'vicinity_video_player',
		'type'    => 'select',
		'choices' => [
			'never'  => __( 'Off — never autoplay', 'vicinity' ),
			'muted'  => __( 'Muted autoplay', 'vicinity' ),
			'always' => __( 'Always (muted + unmuted fallback)', 'vicinity' ),
		],
	] );

	$wp_customize->add_setting( 'videojs_playback_rates', [
		'default'           => '0.5,0.75,1,1.25,1.5,2',
		'sanitize_callback' => 'sanitize_text_field',
	] );
	$wp_customize->add_control( 'videojs_playback_rates', [
		'label'       => __( 'Playback Rate Options', 'vicinity' ),
		'description' => __( 'Comma-separated speeds, e.g. 0.5,1,1.5,2', 'vicinity' ),
		'section'     => 'vicinity_video_player',
		'type'        => 'text',
	] );

	// ════════════════════════════════════════════════════════════════════════
	// AUTO-REGISTERED BLOCKS (from plugin / child-theme filter)
	// ════════════════════════════════════════════════════════════════════════

	/**
	 * Any module can hook 'vicinity_appearance_blocks' to add a customizer section
	 * with full setting + control definitions. Format documented at top of file.
	 */
	$extra_blocks = (array) apply_filters( 'vicinity_appearance_blocks', [] );
	$auto_priority = 100;

	foreach ( $extra_blocks as $block ) {
		if ( empty( $block['section_id'] ) || empty( $block['settings'] ) ) continue;

		$section_id = sanitize_key( $block['section_id'] );
		$auto_priority += 5;

		$wp_customize->add_section( $section_id, [
			'title'       => esc_html( $block['title'] ?? $section_id ),
			'description' => esc_html( $block['description'] ?? '' ),
			'panel'       => 'vicinity_theme',
			'priority'    => $auto_priority,
		] );

		foreach ( (array) $block['settings'] as $cfg ) {
			if ( empty( $cfg['id'] ) ) continue;
			$sid      = sanitize_key( $cfg['id'] );
			$type     = $cfg['type'] ?? 'text';
			$default  = $cfg['default'] ?? '';
			$label    = esc_html( $cfg['label'] ?? $sid );
			$desc     = esc_html( $cfg['description'] ?? '' );
			$transport= in_array( $cfg['transport'] ?? '', [ 'postMessage', 'refresh' ], true )
				? $cfg['transport']
				: 'refresh';

			$sanitize = match( $type ) {
				'checkbox' => static fn( $v ) => (bool) $v,
				'color'    => 'sanitize_hex_color',
				'url'      => 'esc_url_raw',
				'number'   => 'absint',
				default    => 'sanitize_text_field',
			};

			$wp_customize->add_setting( $sid, [
				'default'           => $default,
				'sanitize_callback' => $sanitize,
				'transport'         => $transport,
			] );

			if ( $type === 'color' ) {
				$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $sid, [
					'label'       => $label,
					'description' => $desc,
					'section'     => $section_id,
				] ) );
			} elseif ( $type === 'image' ) {
				$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $sid, [
					'label'   => $label,
					'section' => $section_id,
				] ) );
			} elseif ( $type === 'select' && ! empty( $cfg['choices'] ) ) {
				$wp_customize->add_control( $sid, [
					'label'   => $label,
					'section' => $section_id,
					'type'    => 'select',
					'choices' => (array) $cfg['choices'],
				] );
			} else {
				$wp_customize->add_control( $sid, [
					'label'       => $label,
					'description' => $desc,
					'section'     => $section_id,
					'type'        => $type,
				] );
			}
		}
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: categories list for JS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_categories_for_js(): array {
	$cats = [ [ 'id' => 0, 'name' => __( 'Latest Posts (all categories)', 'vicinity' ) ] ];
	$terms = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => true ] );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			$cats[] = [ 'id' => $t->term_id, 'name' => $t->name ];
		}
	}
	return $cats;
}

function vicinity_layouts_for_js(): array {
	return [
		[ 'id' => '3col',       'label' => 'NYT 3-Column',    'desc' => 'Left stack + hero center + right stack' ],
		[ 'id' => 'hero-stack', 'label' => 'Hero + Stack',    'desc' => 'Large hero left, 3 stories right (60/40)' ],
		[ 'id' => 'card-row',   'label' => 'Card Row',        'desc' => 'Horizontal scrolling card row' ],
		[ 'id' => 'list-feed',  'label' => 'Dated List Feed', 'desc' => 'NYT-style dated list with thumbnail right' ],
		[ 'id' => 'full-hero',  'label' => 'Full-Width Hero', 'desc' => 'Single large story spanning full width' ],
		[ 'id' => 'video-row',  'label' => 'Video Row',       'desc' => 'Video thumbnail grid from Videos CPT' ],
		[ 'id' => 'podcast-row','label' => 'Podcast Row',     'desc' => 'Latest podcast episodes list' ],
	];
}

// ═══════════════════════════════════════════════════════════════════════════
// LIVE PREVIEW — postMessage
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'customize_preview_init', static function (): void {
	wp_enqueue_script(
		'apollo-customizer-preview',
		VICINITY_THEME_URI . '/assets/js/customizer-preview.js',
		[ 'customize-preview' ],
		VICINITY_THEME_VERSION,
		true
	);
} );

// ═══════════════════════════════════════════════════════════════════════════
// OUTPUT CSS CUSTOM PROPERTIES
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_head', 'vicinity_customizer_css', 20 );

function vicinity_customizer_css(): void {
	$vars = [];

	// Colors.
	$color_map = [
		'--color-accent'      => [ 'vicinity_accent_color',       '#c62828' ],
		'--color-secondary'   => [ 'vicinity_color_secondary',  '#326891' ],
		'--color-text'        => [ 'vicinity_color_text',        '#111111' ],
		'--color-text-muted'  => [ 'vicinity_color_text_muted',  '#666666' ],
		'--color-bg'          => [ 'vicinity_color_bg',          '#f4f1eb' ],
		'--color-surface'     => [ 'vicinity_color_surface',     '#ffffff' ],
		'--color-border'      => [ 'vicinity_color_border',      '#d9d9d9' ],
		'--color-rule-strong' => [ 'vicinity_color_rule_strong', '#000000' ],
		'--color-breaking'    => [ 'vicinity_color_breaking',    '#c62828' ],
		'--color-headline'    => [ 'vicinity_color_headline',    '#0a0a0a' ],
		'--color-link'        => [ 'vicinity_color_link',        '#c62828' ],
		'--color-footer-bg'   => [ 'vicinity_color_footer_bg',   '#0a0a0a' ],
	];

	foreach ( $color_map as $var => [ $mod, $default ] ) {
		$val = get_theme_mod( $mod, $default );
		if ( $val && $val !== $default ) {
			$vars[ $var ] = sanitize_hex_color( $val );
		}
	}

	// Typography.
	$font_headline = get_theme_mod( 'vicinity_font_headline', '' );
	if ( $font_headline ) $vars['--font-headline'] = $font_headline;

	$font_body = get_theme_mod( 'vicinity_font_body', '' );
	if ( $font_body ) $vars['--font-body'] = $font_body;

	$font_ui = get_theme_mod( 'vicinity_font_ui', '' );
	if ( $font_ui ) $vars['--font-ui'] = $font_ui;

	$base_size = (int) get_theme_mod( 'vicinity_base_font_size', 17 );
	if ( $base_size && $base_size !== 17 ) $vars['--font-size-base'] = $base_size . 'px';

	$line_height = get_theme_mod( 'vicinity_body_line_height', 1.75 );
	if ( $line_height && (float) $line_height !== 1.75 ) $vars['--line-height-body'] = $line_height;

	$content_width = (int) get_theme_mod( 'vicinity_content_width', 680 );
	if ( $content_width && $content_width !== 680 ) $vars['--content-width'] = $content_width . 'px';

	// Header bg.
	$header_bg = get_theme_mod( 'vicinity_header_bg', '#ffffff' );
	if ( $header_bg && $header_bg !== '#ffffff' ) $vars['--header-bg'] = sanitize_hex_color( $header_bg );

	if ( empty( $vars ) ) return;

	$css = ':root{';
	foreach ( $vars as $var => $val ) {
		$css .= $var . ':' . esc_attr( $val ) . ';';
	}
	$css .= '}';

	// Video.js accent override.
	$vjs_accent = sanitize_hex_color( get_theme_mod( 'videojs_accent_color', '' ) ?: get_theme_mod( 'vicinity_accent_color', '' ) );
	if ( $vjs_accent ) {
		$css .= '.video-js .vjs-play-progress,.video-js .vjs-volume-level{background-color:' . $vjs_accent . '!important;}';
	}

	echo '<style id="vicinity-customizer-css">' . $css . '</style>' . "\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// CONTROLS CSS (inline into customize-controls)
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_customizer_controls_css(): string {
	return '
/* Vicinity Blocks Editor */
.vicinity-blocks-editor { padding: 4px 0; }
.vicinity-blocks-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }

.vcn-block {
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 4px;
  overflow: hidden;
}
.vcn-block-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  cursor: pointer;
  background: #f9f9f9;
  border-bottom: 1px solid #eee;
  user-select: none;
}
.vcn-block-header:hover { background: #f1f1f1; }
.vcn-drag-handle { color: #aaa; cursor: grab; font-size: 14px; flex-shrink: 0; }
.vcn-block-label { flex: 1; font-size: 12px; font-weight: 600; color: #333; }
.vcn-block-actions { display: flex; gap: 4px; flex-shrink: 0; }
.vcn-block-actions button {
  background: none;
  border: 1px solid #ccc;
  border-radius: 3px;
  padding: 2px 7px;
  font-size: 11px;
  cursor: pointer;
  line-height: 1.4;
}
.vcn-block-actions button:hover { background: #eee; }
.vcn-block-actions .vcn-remove { border-color: #c62828; color: #c62828; }
.vcn-block-actions .vcn-remove:hover { background: #ffeaea; }
.vcn-chevron { font-size: 10px; color: #aaa; transition: transform .2s; }
.vcn-block.is-open .vcn-chevron { transform: rotate(180deg); }

.vcn-block-body { padding: 10px; display: none; flex-direction: column; gap: 8px; }
.vcn-block.is-open .vcn-block-body { display: flex; }

.vcn-field { display: flex; flex-direction: column; gap: 3px; }
.vcn-field label { font-size: 11px; font-weight: 600; color: #444; text-transform: uppercase; letter-spacing: .04em; }
.vcn-field select, .vcn-field input[type=text], .vcn-field input[type=number] {
  width: 100%;
  padding: 5px 8px;
  border: 1px solid #ddd;
  border-radius: 3px;
  font-size: 12px;
}

.vcn-layout-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 5px;
  margin-top: 3px;
}
.vcn-layout-option {
  border: 2px solid #ddd;
  border-radius: 4px;
  padding: 6px 8px;
  cursor: pointer;
  font-size: 11px;
  line-height: 1.3;
  transition: border-color .15s;
}
.vcn-layout-option:hover { border-color: #aaa; }
.vcn-layout-option.selected { border-color: #c62828; background: #fff5f5; }
.vcn-layout-option-name { font-weight: 700; color: #111; }
.vcn-layout-option-desc { color: #777; font-size: 10px; }

.vicinity-add-block {
  width: 100%;
  padding: 8px;
  font-size: 12px;
  font-weight: 600;
  border: 2px dashed #c62828;
  border-radius: 4px;
  color: #c62828;
  background: transparent;
  cursor: pointer;
  transition: background .15s;
}
.vicinity-add-block:hover { background: #fff5f5; }
';
}
