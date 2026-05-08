<?php
/**
 * Apollo Editorial Flow
 *
 * Adds an advanced publish workflow to all Gutenberg-enabled post types.
 *
 * Post meta stored:
 *   _vicinity_editorial_review      — last AI review result (JSON)
 *   _vicinity_editorial_overrides   — per-check override reasons (JSON)
 *   _vicinity_editorial_reviewed_at — timestamp of last review
 *
 * @package Apollo
 * @since   3.1.0
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════════════════════════════════

define( 'VICINITY_EDITORIAL_OPTION',          'vicinity_editorial_settings' );
define( 'VICINITY_EDITORIAL_META_REVIEW',     '_vicinity_editorial_review' );
define( 'VICINITY_EDITORIAL_META_OVERRIDES',  '_vicinity_editorial_overrides' );
define( 'VICINITY_EDITORIAL_META_REVIEWED_AT','_vicinity_editorial_reviewed_at' );

// Default AI prompt stored as a function so heredoc syntax works at any scope.
function vicinity_editorial_default_prompt(): string {
	return <<<'PROMPT'
You are an editorial standards AI for a digital news publication.

Review the following article and evaluate it against each of these criteria:

1. **Headline quality** — Is the headline accurate, not clickbait, and under 80 characters?
2. **Lede paragraph** — Does the first paragraph answer Who, What, When, Where, Why?
3. **Source attribution** — Are all factual claims attributed to a named source, study, or official record?
4. **Factual consistency** — Are there any internal contradictions or obviously incorrect facts?
5. **Tone and bias** — Is the language neutral and free from unsubstantiated opinion?
6. **SEO / excerpt** — Is an SEO description or excerpt present and between 120–160 characters?
7. **Word count** — Is the article at least 300 words?
8. **Sensitive content** — Does the article follow responsible reporting guidelines for violence, grief, or medical topics?

Return ONLY a JSON object in this exact schema (no markdown, no prose):
{
  "passed": true/false,
  "score": 0-100,
  "checks": [
    { "label": "Headline quality", "passed": true/false, "issue": "" },
    ...
  ]
}

"passed" is true if score >= 80. The "issue" field should be an empty string when passed, or a short sentence explaining the problem.
PROMPT;
}

// ═══════════════════════════════════════════════════════════════════════════
// SETTINGS REGISTRATION
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_init', static function (): void {
	register_setting( 'vicinity_editorial_group', VICINITY_EDITORIAL_OPTION, [
		'sanitize_callback' => 'vicinity_editorial_sanitize_settings',
	] );
} );

function vicinity_editorial_sanitize_settings( mixed $input ): array {
	if ( ! is_array( $input ) ) return vicinity_editorial_defaults();
	return [
		'enabled'        => ! empty( $input['enabled'] ),
		'prompt'         => sanitize_textarea_field( $input['prompt'] ?? vicinity_editorial_default_prompt() ),
		'pass_threshold' => (int) ( $input['pass_threshold'] ?? 80 ),
		'post_types'     => array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [ 'post', 'serve_video', 'serve_episode' ] ) ),
		'require_review' => ! empty( $input['require_review'] ),
	];
}

function vicinity_editorial_defaults(): array {
	return [
		'enabled'        => true,
		'prompt'         => vicinity_editorial_default_prompt(),
		'pass_threshold' => 80,
		'post_types'     => [ 'post', 'serve_video', 'serve_episode' ],
		'require_review' => false,
	];
}

function vicinity_editorial_settings(): array {
	$stored = get_option( VICINITY_EDITORIAL_OPTION, [] );
	return is_array( $stored ) ? array_merge( vicinity_editorial_defaults(), $stored ) : vicinity_editorial_defaults();
}

// ═══════════════════════════════════════════════════════════════════════════
// META REGISTRATION
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', static function (): void {
	$post_types = get_post_types( [ 'show_in_rest' => true ], 'names' );
	foreach ( $post_types as $pt ) {
		foreach ( [ VICINITY_EDITORIAL_META_REVIEW, VICINITY_EDITORIAL_META_OVERRIDES, VICINITY_EDITORIAL_META_REVIEWED_AT ] as $key ) {
			register_post_meta( $pt, $key, [
				'show_in_rest'  => false,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
			] );
		}
	}
} );

// ═══════════════════════════════════════════════════════════════════════════
// GUTENBERG JS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'enqueue_block_editor_assets', static function (): void {
	$settings  = vicinity_editorial_settings();
	if ( empty( $settings['enabled'] ) ) return;

	$screen    = get_current_screen();
	$post_type = $screen->post_type ?? '';

	if ( ! in_array( $post_type, $settings['post_types'], true ) ) return;

	wp_enqueue_script(
		'apollo-editorial',
		VICINITY_URL . 'assets/js/editorial.js',
		[
			'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components',
			'wp-data', 'wp-compose', 'wp-hooks', 'wp-api-fetch',
		],
		VICINITY_VERSION,
		true
	);

	wp_localize_script( 'apollo-editorial', 'apolloEditorial', [
		'nonce'         => wp_create_nonce( 'vicinity_editorial_review' ),
		'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
		'passThreshold' => (int) $settings['pass_threshold'],
		'requireReview' => (bool) $settings['require_review'],
		'postType'      => $post_type,
	] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// EDITORIAL SETTINGS SUB-PAGE
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', static function (): void {
	add_submenu_page(
		'options-general.php',
		__( 'Editorial Settings', 'vicinity' ),
		__( 'Editorial', 'vicinity' ),
		'manage_options',
		'apollo-editorial',
		'vicinity_editorial_settings_page'
	);
} );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	if ( $hook !== 'settings_page_apollo-editorial' ) return;
	wp_add_inline_style( 'wp-admin', vicinity_editorial_admin_css() );
} );

function vicinity_editorial_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_die();

	$settings = vicinity_editorial_settings();
	$all_pt   = get_post_types( [ 'public' => true ], 'objects' );
	$saved    = isset( $_GET['settings-updated'] );
	?>
	<div class="wrap apollo-editorial-wrap">
		<h1><?php esc_html_e( 'Editorial Flow Settings', 'vicinity' ); ?></h1>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved.', 'vicinity' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'vicinity_editorial_group' ); ?>

			<div class="ed-card">
				<h2><?php esc_html_e( 'General', 'vicinity' ); ?></h2>

				<label class="ed-toggle-label">
					<input type="checkbox" name="<?php echo esc_attr( VICINITY_EDITORIAL_OPTION ); ?>[enabled]" value="1"
						<?php checked( $settings['enabled'] ); ?>>
					<?php esc_html_e( 'Enable editorial review flow', 'vicinity' ); ?>
				</label>

				<label class="ed-toggle-label">
					<input type="checkbox" name="<?php echo esc_attr( VICINITY_EDITORIAL_OPTION ); ?>[require_review]" value="1"
						<?php checked( $settings['require_review'] ); ?>>
					<?php esc_html_e( 'Require review to publish (editors cannot skip)', 'vicinity' ); ?>
				</label>

				<div class="ed-field">
					<label><?php esc_html_e( 'Pass score threshold (0–100)', 'vicinity' ); ?></label>
					<input type="number" name="<?php echo esc_attr( VICINITY_EDITORIAL_OPTION ); ?>[pass_threshold]"
						value="<?php echo esc_attr( $settings['pass_threshold'] ); ?>"
						min="0" max="100" style="width:80px;">
					<p class="description">
						<?php esc_html_e( 'Posts with a score below this are considered failing. Default: 80.', 'vicinity' ); ?>
					</p>
				</div>
			</div>

			<div class="ed-card">
				<h2><?php esc_html_e( 'Post Types', 'vicinity' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Enable the editorial review button for these post types.', 'vicinity' ); ?>
				</p>
				<div class="ed-pt-grid">
					<?php foreach ( $all_pt as $pt_key => $pt_obj ) : ?>
						<label class="ed-toggle-label">
							<input type="checkbox"
								name="<?php echo esc_attr( VICINITY_EDITORIAL_OPTION ); ?>[post_types][]"
								value="<?php echo esc_attr( $pt_key ); ?>"
								<?php checked( in_array( $pt_key, $settings['post_types'], true ) ); ?>>
							<?php echo esc_html( $pt_obj->labels->singular_name . ' (' . $pt_key . ')' ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ed-card">
				<h2><?php esc_html_e( 'AI Review Prompt', 'vicinity' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'This prompt is sent to the AI along with the post\'s title, excerpt, and body.', 'vicinity' ); ?>
				</p>
				<div class="ed-field">
					<textarea name="<?php echo esc_attr( VICINITY_EDITORIAL_OPTION ); ?>[prompt]"
						rows="18" class="large-text code" spellcheck="false"><?php echo esc_textarea( $settings['prompt'] ); ?></textarea>
				</div>
				<div class="ed-schema-note">
					<strong><?php esc_html_e( 'Required JSON output schema:', 'vicinity' ); ?></strong>
					<pre>{
  "passed": true,
  "score": 85,
  "checks": [
    { "label": "Check name",   "passed": true,  "issue": "" },
    { "label": "Other check",  "passed": false, "issue": "Short description of the problem." }
  ]
}</pre>
				</div>
				<button type="button" class="button button-secondary" id="ed-reset-prompt">
					<?php esc_html_e( 'Reset to default prompt', 'vicinity' ); ?>
				</button>
				<textarea id="ed-default-prompt" style="display:none"><?php echo esc_textarea( vicinity_editorial_default_prompt() ); ?></textarea>
			</div>

			<?php submit_button( __( 'Save Settings', 'vicinity' ) ); ?>
		</form>
	</div>

	<script>
	document.getElementById('ed-reset-prompt').addEventListener('click', function(){
		if( confirm('Reset prompt to the default? Your changes will be lost.') ){
			var textarea = document.querySelector('textarea[name="<?php echo esc_js( VICINITY_EDITORIAL_OPTION ); ?>[prompt]"]');
			textarea.value = document.getElementById('ed-default-prompt').value;
		}
	});
	</script>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// ADMIN CSS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_editorial_admin_css(): string {
	return '
	.apollo-editorial-wrap { max-width: 820px; }
	.ed-card { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px 24px; margin-bottom: 20px; }
	.ed-card h2 { margin: 0 0 14px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #555; }
	.ed-toggle-label { display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 10px; cursor: pointer; }
	.ed-field { margin-bottom: 14px; }
	.ed-field label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; }
	.ed-pt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px 24px; }
	.ed-schema-note { background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 3px; padding: 12px 16px; margin: 12px 0; }
	.ed-schema-note pre { margin: 8px 0 0; font-size: 12px; line-height: 1.5; overflow-x: auto; }
	';
}