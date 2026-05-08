<?php
/**
 * Apollo AI — Unified provider abstraction.
 *
 * Supported providers:
 *   openai      — OpenAI Chat Completions API
 *   claude      — Anthropic Messages API
 *   gemini      — Google Gemini generateContent API
 *   minimax     — MiniMax Chat Completions API
 *   featherless — Featherless.ai (OpenAI-compatible)
 *
 * Per-feature provider routing:
 *   option  vicinity_ai_provider_{feature}  → provider slug
 *   option  vicinity_ai_model_{feature}     → model string
 *   option  vicinity_ai_prompt_{feature}    → system prompt override
 *
 * Built-in features:
 *   rewriter      — Gutenberg text rewriter
 *   editorial     — Editorial AI checklist review
 *   seo           — SEO title/description suggestions (future)
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// PROVIDER REGISTRY
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_ai_providers(): array {
	return [
		'openai' => [
			'label'    => 'OpenAI',
			'key_opt'  => 'vicinity_ai_openai_key',
			'key_const'=> 'VICINITY_OPENAI_KEY',
			'models'   => [
				'gpt-4.1'          => 'GPT-4.1',
				'gpt-4.1-mini'     => 'GPT-4.1 Mini',
				'gpt-4.1-nano'     => 'GPT-4.1 Nano',
				'gpt-4o'           => 'GPT-4o',
				'gpt-4o-mini'      => 'GPT-4o Mini',
			],
			'default_model' => 'gpt-4.1-mini',
		],
		'claude' => [
			'label'    => 'Claude (Anthropic)',
			'key_opt'  => 'vicinity_ai_claude_key',
			'key_const'=> 'VICINITY_CLAUDE_KEY',
			'models'   => [
				'claude-opus-4-6'             => 'Claude Opus 4.6',
				'claude-sonnet-4-6'           => 'Claude Sonnet 4.6',
				'claude-haiku-4-5-20251001'   => 'Claude Haiku 4.5',
			],
			'default_model' => 'claude-haiku-4-5-20251001',
		],
		'gemini' => [
			'label'    => 'Google Gemini',
			'key_opt'  => 'vicinity_ai_gemini_key',
			'key_const'=> 'VICINITY_GEMINI_KEY',
			'models'   => [
				'gemini-2.0-flash'        => 'Gemini 2.0 Flash',
				'gemini-2.0-flash-lite'   => 'Gemini 2.0 Flash-Lite',
				'gemini-1.5-pro'          => 'Gemini 1.5 Pro',
				'gemini-1.5-flash'        => 'Gemini 1.5 Flash',
			],
			'default_model' => 'gemini-2.0-flash',
		],
		'minimax' => [
			'label'    => 'MiniMax',
			'key_opt'  => 'vicinity_ai_minimax_key',
			'key_const'=> 'VICINITY_MINIMAX_KEY',
			'models'   => [
				'MiniMax-Text-01' => 'MiniMax Text-01',
				'abab6.5s-chat'   => 'ABAB 6.5s Chat',
			],
			'default_model' => 'MiniMax-Text-01',
		],
		'featherless' => [
			'label'    => 'Featherless.ai',
			'key_opt'  => 'vicinity_ai_featherless_key',
			'key_const'=> 'VICINITY_FEATHERLESS_KEY',
			'models'   => [], // User-defined models
			'default_model' => 'meta-llama/Llama-3.3-70B-Instruct',
		],
	];
}

// ═══════════════════════════════════════════════════════════════════════════
// CONFIG RESOLUTION
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get API key for a provider. Constants override options.
 */
function vicinity_ai_key( string $provider ): string {
	$providers = vicinity_ai_providers();
	if ( ! isset( $providers[ $provider ] ) ) return '';
	$p     = $providers[ $provider ];
	$const = $p['key_const'];
	if ( defined( $const ) ) return (string) constant( $const );
	return (string) get_option( $p['key_opt'], '' );
}

/**
 * Get routing config for a feature.
 * Returns: [ provider, model, system_prompt ]
 */
function vicinity_ai_feature_config( string $feature ): array {
	$default_provider = (string) get_option( 'vicinity_ai_default_provider', 'openai' );
	$provider = (string) get_option( 'vicinity_ai_provider_' . $feature, $default_provider );

	$providers = vicinity_ai_providers();
	if ( ! isset( $providers[ $provider ] ) ) $provider = 'openai';

	$default_model = $providers[ $provider ]['default_model'];
	$model = (string) get_option( 'vicinity_ai_model_' . $feature, $default_model );
	if ( ! $model ) $model = $default_model;

	$system_prompt = (string) get_option( 'vicinity_ai_prompt_' . $feature, '' );

	return [
		'provider' => $provider,
		'model'    => $model,
		'prompt'   => $system_prompt,
		'key'      => vicinity_ai_key( $provider ),
	];
}

/**
 * Check if any provider is configured (has an API key).
 */
function vicinity_ai_available(): bool {
	foreach ( array_keys( vicinity_ai_providers() ) as $p ) {
		if ( vicinity_ai_key( $p ) ) return true;
	}
	return false;
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN CALL FUNCTION
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Call an AI provider with a user message and optional system prompt.
 *
 * @param string $user_message  The user-facing prompt / content.
 * @param string $feature       Feature key for routing (rewriter, editorial, etc.).
 * @param string $system_override  Override the system prompt for this call.
 * @param array  $opts          Additional options: [ max_tokens, temperature ].
 *
 * @return string|WP_Error  The model response text, or WP_Error on failure.
 */
function vicinity_ai_call( string $user_message, string $feature = 'general', string $system_override = '', array $opts = [] ): string|WP_Error {
	$cfg    = vicinity_ai_feature_config( $feature );
	$key    = $cfg['key'];
	$system = $system_override ?: $cfg['prompt'];

	if ( ! $key ) {
		return new WP_Error( 'no_key', sprintf( __( 'No API key configured for provider: %s', 'vicinity' ), $cfg['provider'] ) );
	}

	$max_tokens  = absint( $opts['max_tokens'] ?? 1500 );
	$temperature = (float) ( $opts['temperature'] ?? 0.7 );

	return match ( $cfg['provider'] ) {
		'claude'      => vicinity_ai_call_claude(      $user_message, $system, $key, $cfg['model'], $max_tokens, $temperature ),
		'gemini'      => vicinity_ai_call_gemini(      $user_message, $system, $key, $cfg['model'], $max_tokens, $temperature ),
		'minimax'     => vicinity_ai_call_minimax(     $user_message, $system, $key, $cfg['model'], $max_tokens, $temperature ),
		'featherless' => vicinity_ai_call_featherless( $user_message, $system, $key, $cfg['model'], $max_tokens, $temperature ),
		default       => vicinity_ai_call_openai(      $user_message, $system, $key, $cfg['model'], $max_tokens, $temperature ),
	};
}

// ═══════════════════════════════════════════════════════════════════════════
// PROVIDER IMPLEMENTATIONS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_ai_call_openai( string $user, string $system, string $key, string $model, int $max_tokens, float $temp ): string|WP_Error {
	$messages = [];
	if ( $system ) $messages[] = [ 'role' => 'system', 'content' => $system ];
	$messages[] = [ 'role' => 'user', 'content' => $user ];

	$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
		'timeout' => 45,
		'headers' => [
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'model'       => $model,
			'messages'    => $messages,
			'max_tokens'  => $max_tokens,
			'temperature' => $temp,
		] ),
	] );

	if ( is_wp_error( $resp ) ) return $resp;
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( $code !== 200 ) {
		return new WP_Error( 'openai_error', $body['error']['message'] ?? "HTTP {$code}" );
	}

	return trim( $body['choices'][0]['message']['content'] ?? '' );
}

function vicinity_ai_call_claude( string $user, string $system, string $key, string $model, int $max_tokens, float $temp ): string|WP_Error {
	$payload = [
		'model'      => $model,
		'max_tokens' => $max_tokens,
		'messages'   => [ [ 'role' => 'user', 'content' => $user ] ],
	];
	if ( $system ) $payload['system'] = $system;

	$resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 45,
		'headers' => [
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'Content-Type'      => 'application/json',
		],
		'body' => wp_json_encode( $payload ),
	] );

	if ( is_wp_error( $resp ) ) return $resp;
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( $code !== 200 ) {
		return new WP_Error( 'claude_error', $body['error']['message'] ?? "HTTP {$code}" );
	}

	$content = $body['content'] ?? [];
	foreach ( $content as $block ) {
		if ( ( $block['type'] ?? '' ) === 'text' ) return trim( $block['text'] );
	}
	return new WP_Error( 'claude_empty', 'Empty response from Claude.' );
}

function vicinity_ai_call_gemini( string $user, string $system, string $key, string $model, int $max_tokens, float $temp ): string|WP_Error {
	$parts = [];
	if ( $system ) $parts[] = [ 'text' => "System: {$system}\n\n" ];
	$parts[] = [ 'text' => $user ];

	$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . urlencode( $key );

	$resp = wp_remote_post( $url, [
		'timeout' => 45,
		'headers' => [ 'Content-Type' => 'application/json' ],
		'body'    => wp_json_encode( [
			'contents'          => [ [ 'parts' => $parts ] ],
			'generationConfig'  => [ 'maxOutputTokens' => $max_tokens, 'temperature' => $temp ],
		] ),
	] );

	if ( is_wp_error( $resp ) ) return $resp;
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( $code !== 200 ) {
		return new WP_Error( 'gemini_error', $body['error']['message'] ?? "HTTP {$code}" );
	}

	return trim( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );
}

function vicinity_ai_call_minimax( string $user, string $system, string $key, string $model, int $max_tokens, float $temp ): string|WP_Error {
	$messages = [];
	if ( $system ) $messages[] = [ 'role' => 'system', 'name' => 'system', 'content' => $system ];
	$messages[] = [ 'role' => 'user', 'name' => 'user', 'content' => $user ];

	$resp = wp_remote_post( 'https://api.minimaxi.chat/v1/text/chatcompletion_v2', [
		'timeout' => 45,
		'headers' => [
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'model'       => $model,
			'messages'    => $messages,
			'max_tokens'  => $max_tokens,
			'temperature' => $temp,
		] ),
	] );

	if ( is_wp_error( $resp ) ) return $resp;
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( $code !== 200 ) {
		return new WP_Error( 'minimax_error', $body['base_resp']['status_msg'] ?? "HTTP {$code}" );
	}

	return trim( $body['choices'][0]['message']['content'] ?? '' );
}

function vicinity_ai_call_featherless( string $user, string $system, string $key, string $model, int $max_tokens, float $temp ): string|WP_Error {
	// Featherless.ai is OpenAI-compatible.
	$messages = [];
	if ( $system ) $messages[] = [ 'role' => 'system', 'content' => $system ];
	$messages[] = [ 'role' => 'user', 'content' => $user ];

	$resp = wp_remote_post( 'https://api.featherless.ai/v1/chat/completions', [
		'timeout' => 60,
		'headers' => [
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		],
		'body' => wp_json_encode( [
			'model'       => $model,
			'messages'    => $messages,
			'max_tokens'  => $max_tokens,
			'temperature' => $temp,
		] ),
	] );

	if ( is_wp_error( $resp ) ) return $resp;
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	if ( $code !== 200 ) {
		return new WP_Error( 'featherless_error', $body['error']['message'] ?? "HTTP {$code}" );
	}

	return trim( $body['choices'][0]['message']['content'] ?? '' );
}

// ═══════════════════════════════════════════════════════════════════════════
// AJAX — REWRITER
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_ai_rewrite', 'vicinity_ajax_ai_rewrite' );

function vicinity_ajax_ai_rewrite(): void {
	check_ajax_referer( 'vicinity_ai_rewrite', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );

	$text  = wp_kses_post( wp_unslash( $_POST['text']  ?? '' ) );
	$style = sanitize_text_field( wp_unslash( $_POST['style'] ?? 'standard' ) );
	$tone  = sanitize_text_field( wp_unslash( $_POST['tone']  ?? '' ) );

	if ( strlen( trim( $text ) ) < 5 ) {
		wp_send_json_error( 'Please select more text to rewrite.' );
	}

	// Build style instruction.
	$style_map = [
		'shorter'      => 'Rewrite this more concisely — cut words without losing meaning.',
		'longer'       => 'Expand this with more detail, context, and depth.',
		'simpler'      => 'Simplify the language for a general audience.',
		'formal'       => 'Rewrite in a formal, professional journalistic tone.',
		'conversational' => 'Rewrite in a warm, approachable, conversational tone.',
		'seo'          => 'Rewrite to be more SEO-friendly while keeping it natural.',
		'active'       => 'Rewrite using active voice. Remove passive constructions.',
		'standard'     => 'Improve the writing — fix any awkward phrasing, grammar, and flow.',
	];

	$style_instr = $style_map[ $style ] ?? $style_map['standard'];
	$tone_instr  = $tone ? " Maintain a {$tone} tone." : '';

	$system = "You are an expert editor for Penny Tribune, a digital news publication. {$style_instr}{$tone_instr} Return ONLY the rewritten text with no preamble or explanation.";

	$result = vicinity_ai_call( $text, 'rewriter', $system );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( [ 'text' => $result ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// AJAX — EDITORIAL REVIEW
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_ai_editorial_review', 'vicinity_ajax_editorial_review' );

function vicinity_ajax_editorial_review(): void {
	check_ajax_referer( 'vicinity_editorial_review', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden' );

	$post_id   = absint( $_POST['post_id'] ?? 0 );
	$post_type = sanitize_key( $_POST['post_type'] ?? 'post' );

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'Invalid post.' );
	}

	$post = get_post( $post_id );
	if ( ! $post ) wp_send_json_error( 'Post not found.' );

	// Get custom prompt for this post type.
	$custom_prompt = (string) get_option( 'vicinity_editorial_prompt_' . $post_type, '' );

	// Default standards per post type.
	$default_standards = [
		'post'          => "You are a senior editor at Penny Tribune, a digital news publication.\n\nReview this article against these standards:\n1. Headline accuracy — does the headline match the content?\n2. Lead paragraph — does it answer Who, What, When, Where, Why?\n3. Source attribution — are claims attributed to sources?\n4. Factual clarity — are any claims vague or unverifiable?\n5. Tone — is it professional and objective?\n6. Length — is it appropriate for the story?\n7. Spelling and grammar\n8. No hate speech or defamatory language",
		'serve_video'   => "Review this video post:\n1. Title is descriptive and accurate\n2. Description explains what the video covers\n3. Series/category is correctly assigned\n4. Duration is set\n5. No misleading claims in the description",
		'serve_episode' => "Review this podcast episode:\n1. Title is clear and searchable\n2. Episode description is informative\n3. Season and episode number are set\n4. Duration is set\n5. Podcast (show) ID is linked",
	];

	$standards = $custom_prompt ?: ( $default_standards[ $post_type ] ?? $default_standards['post'] );

	// Build the review payload.
	$title   = get_the_title( $post_id );
	$content = wp_strip_all_tags( $post->post_content );
	$excerpt = $post->post_excerpt;
	$content_preview = mb_substr( $content, 0, 3000 );

	$user_msg = "POST TYPE: {$post_type}\nTITLE: {$title}\n" .
		( $excerpt ? "EXCERPT: {$excerpt}\n" : '' ) .
		"CONTENT (first 3000 chars):\n{$content_preview}\n\n" .
		"Return a JSON object with this structure:\n" .
		'{ "passed": boolean, "score": 0-100, "checks": [ { "label": "...", "passed": boolean, "issue": "..." } ] }' . "\n" .
		"Each 'check' corresponds to one standard. 'issue' is empty string if passed.";

	$result = vicinity_ai_call( $user_msg, 'editorial', $standards, [ 'max_tokens' => 1000, 'temperature' => 0.2 ] );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	// Extract JSON from response (model may wrap it in markdown).
	$json_str = preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', trim( $result ) );
	$parsed   = json_decode( $json_str, true );

	if ( ! is_array( $parsed ) ) {
		// Fallback — return raw text so user can still see something.
		wp_send_json_success( [
			'passed' => false,
			'score'  => 0,
			'checks' => [ [ 'label' => 'AI Review', 'passed' => false, 'issue' => $result ] ],
			'raw'    => $result,
		] );
	}

	// Log review to post meta.
	update_post_meta( $post_id, '_vicinity_editorial_review', [
		'timestamp' => time(),
		'user_id'   => get_current_user_id(),
		'result'    => $parsed,
	] );

	wp_send_json_success( $parsed );
}

// ═══════════════════════════════════════════════════════════════════════════
// ENQUEUE REWRITER JS on all post edit screens
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'enqueue_block_editor_assets', static function (): void {
	if ( ! vicinity_ai_available() ) return;

	$js_path = VICINITY_PATH . 'assets/js/rewriter.js';
	if ( ! file_exists( $js_path ) ) return;

	wp_enqueue_script(
		'apollo-rewriter',
		VICINITY_URL . 'assets/js/rewriter.js',
		[ 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-data',
		  'wp-components', 'wp-i18n', 'wp-rich-text', 'wp-block-editor' ],
		VICINITY_VERSION,
		true
	);

	$cfg = vicinity_ai_feature_config( 'rewriter' );

	wp_localize_script( 'apollo-rewriter', 'apolloRewriter', [
		'nonce'    => wp_create_nonce( 'vicinity_ai_rewrite' ),
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'provider' => $cfg['provider'],
		'model'    => $cfg['model'],
	] );
} );