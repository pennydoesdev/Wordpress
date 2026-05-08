<?php
/**
 * Apollo Settings — Admin UI.
 *
 * Single settings page under Settings → Apollo with tabbed sections:
 *   Storage  — R2 video, R2 audio, S3 fallback
 *   YouTube  — Channel ID + API key for live detection
 *   Cloudflare — Image Transform zone slug
 *   Content  — Shorts category, paywall, filename anonymization
 *
 * All credentials stored as plain WP options (vicinity_* prefix).
 * Constants defined in wp-config.php always take precedence (see cloudflare.php).
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════════════════
// REGISTER SETTINGS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_init', 'vicinity_register_settings' );

function vicinity_register_settings(): void {

	// ── Storage: R2 Video ────────────────────────────────────────────────
	foreach ( [
		'vicinity_r2_video_account_id',
		'vicinity_r2_video_access_key',
		'vicinity_r2_video_secret_key',
		'vicinity_r2_video_bucket',
		'vicinity_r2_video_public_url',
	] as $opt ) {
		register_setting( 'vicinity_storage', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
	}

	// ── Storage: R2 Audio ────────────────────────────────────────────────
	foreach ( [
		'vicinity_r2_audio_account_id',
		'vicinity_r2_audio_access_key',
		'vicinity_r2_audio_secret_key',
		'vicinity_r2_audio_bucket',
		'vicinity_r2_audio_public_url',
	] as $opt ) {
		register_setting( 'vicinity_storage', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
	}

	// ── Storage: S3 ─────────────────────────────────────────────────────
	foreach ( [
		'vicinity_s3_access_key',
		'vicinity_s3_secret_key',
		'vicinity_s3_bucket',
		'vicinity_s3_region',
		'vicinity_s3_public_url',
	] as $opt ) {
		register_setting( 'vicinity_storage', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
	}

	// ── YouTube ──────────────────────────────────────────────────────────
	register_setting( 'vicinity_youtube', 'vicinity_yt_channel_id',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'vicinity_youtube', 'vicinity_yt_api_key',     [ 'sanitize_callback' => 'sanitize_text_field' ] );

	// ── Cloudflare ───────────────────────────────────────────────────────
	register_setting( 'vicinity_cloudflare', 'vicinity_cf_zone_id',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'vicinity_cloudflare', 'vicinity_cf_api_token', [ 'sanitize_callback' => 'sanitize_text_field' ] );

	// ── Content ──────────────────────────────────────────────────────────
	register_setting( 'vicinity_content', 'vicinity_shorts_category',   [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'vicinity_content', 'vicinity_paywall_enabled',   [ 'sanitize_callback' => 'absint' ] );
	register_setting( 'vicinity_content', 'vicinity_paywall_role',      [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'vicinity_content', 'vicinity_anon_filenames',    [ 'sanitize_callback' => 'absint' ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// ADMIN MENU
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', static function (): void {
	add_options_page(
		__( 'Apollo Settings', 'vicinity' ),
		__( 'Apollo', 'vicinity' ),
		'manage_options',
		'apollo-settings',
		'vicinity_settings_page'
	);
} );

// ═══════════════════════════════════════════════════════════════════════════
// ENQUEUE ADMIN STYLES
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	if ( $hook !== 'settings_page_apollo-settings' ) return;
	wp_add_inline_style( 'wp-admin', vicinity_settings_inline_css() );
} );

function vicinity_settings_inline_css(): string {
	return '
	.apollo-settings-wrap { max-width:900px; }
	.apollo-tab-nav { display:flex; gap:0; border-bottom:2px solid #c62828; margin-bottom:24px; padding:0; list-style:none; }
	.apollo-tab-nav li a { display:block; padding:10px 20px; font-weight:600; font-size:13px; color:#1a1a1a; text-decoration:none; border:1px solid transparent; border-bottom:none; margin-bottom:-2px; border-radius:3px 3px 0 0; }
	.apollo-tab-nav li a:hover { background:#f8f8f8; }
	.apollo-tab-nav li a.active { background:#fff; border-color:#ddd #ddd #fff; color:#c62828; }
	.apollo-tab-content { display:none; }
	.apollo-tab-content.active { display:block; }
	.apollo-card { background:#fff; border:1px solid #ddd; border-radius:4px; padding:20px 24px; margin-bottom:20px; }
	.apollo-card h3 { margin:0 0 16px; padding-bottom:10px; border-bottom:1px solid #f0f0f0; font-size:14px; text-transform:uppercase; letter-spacing:.05em; color:#666; }
	.apollo-field { display:grid; grid-template-columns:220px 1fr; gap:12px; align-items:start; margin-bottom:14px; }
	.apollo-field:last-child { margin-bottom:0; }
	.apollo-field label { padding-top:6px; font-weight:600; font-size:13px; }
	.apollo-field .desc { font-size:12px; color:#777; margin-top:4px; }
	.apollo-field input[type=text], .apollo-field input[type=password], .apollo-field select { width:100%; max-width:480px; }
	.apollo-field input[type=password].revealed { -webkit-text-security:none; }
	.apollo-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:3px; margin-left:8px; }
	.apollo-status--ok { background:#e8f5e9; color:#1b5e20; }
	.apollo-status--warn { background:#fff8e1; color:#e65100; }
	.apollo-status--off { background:#f5f5f5; color:#9e9e9e; }
	.apollo-status::before { content:""; width:7px; height:7px; border-radius:50%; background:currentColor; }
	.apollo-actions { margin-top:24px; }
	.apollo-actions .button-primary { background:#c62828; border-color:#b71c1c; box-shadow:none; }
	.apollo-actions .button-primary:hover { background:#b71c1c; border-color:#9a0007; }
	.apollo-const-notice { background:#fff3cd; border-left:3px solid #e6a817; padding:8px 12px; font-size:12px; border-radius:0 3px 3px 0; color:#6d4c00; margin-top:6px; }
	.apollo-yt-live-status { display:flex; align-items:center; gap:12px; padding:12px; background:#f8f8f8; border:1px solid #e0e0e0; border-radius:4px; margin-top:16px; font-size:13px; }
	.apollo-dot-live { width:10px; height:10px; border-radius:50%; background:#c62828; animation:apollo-pulse 1.5s infinite; flex-shrink:0; }
	.apollo-dot-offline { width:10px; height:10px; border-radius:50%; background:#9e9e9e; flex-shrink:0; }
	@keyframes apollo-pulse { 0%,100%{ opacity:1; } 50%{ opacity:.4; } }
	';
}

// ═══════════════════════════════════════════════════════════════════════════
// SETTINGS PAGE RENDER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Insufficient permissions.', 'vicinity' ) );
	}

	// Handle manual YT status refresh.
	if ( isset( $_GET['vicinity_yt_check'] ) && check_admin_referer( 'vicinity_yt_check' ) ) {
		delete_transient( VICINITY_YT_TRANSIENT );
		vicinity_yt_fetch();
		wp_safe_redirect( admin_url( 'options-general.php?page=apollo-settings&tab=youtube' ) );
		exit;
	}

	$active = sanitize_key( $_GET['tab'] ?? 'storage' );
	$tabs   = [
		'storage'    => __( 'Storage', 'vicinity' ),
		'youtube'    => __( 'YouTube Live', 'vicinity' ),
		'cloudflare' => __( 'Cloudflare', 'vicinity' ),
		'content'    => __( 'Content', 'vicinity' ),
		'ai'         => __( 'AI Providers', 'vicinity' ),
	];
	?>
	<div class="wrap apollo-settings-wrap">
		<h1><?php esc_html_e( 'Apollo Settings', 'vicinity' ); ?></h1>

		<ul class="apollo-tab-nav">
			<?php foreach ( $tabs as $slug => $label ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=apollo-settings&tab=' . $slug ) ); ?>"
				   class="<?php echo $active === $slug ? 'active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $active === 'storage' ) : ?>
			<?php vicinity_settings_tab_storage(); ?>
		<?php elseif ( $active === 'youtube' ) : ?>
			<?php vicinity_settings_tab_youtube(); ?>
		<?php elseif ( $active === 'cloudflare' ) : ?>
			<?php vicinity_settings_tab_cloudflare(); ?>
		<?php elseif ( $active === 'content' ) : ?>
			<?php vicinity_settings_tab_content(); ?>
		<?php elseif ( $active === 'ai' ) : ?>
			<?php vicinity_settings_tab_ai(); ?>
		<?php endif; ?>
	</div>
	<?php
}

// ─── Helpers ─────────────────────────────────────────────────────────────

/**
 * Renders a masked credential input.
 * Shows a const-override notice if the value is locked by a PHP constant.
 *
 * @param string $name    Option name.
 * @param string $const   PHP constant name (or '').
 * @param string $label   Field label.
 * @param string $desc    Description text.
 * @param bool   $secret  Mask as password.
 */
function vicinity_settings_field( string $name, string $const, string $label, string $desc = '', bool $secret = false ): void {
	$is_const = $const && defined( $const );
	$val      = $is_const ? '••••••••' : (string) get_option( $name, '' );
	$type     = $secret ? 'password' : 'text';
	?>
	<div class="apollo-field">
		<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
		<div>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $name ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo $is_const ? '' : esc_attr( $val ); ?>"
				autocomplete="off"
				<?php echo $is_const ? 'disabled' : ''; ?>
			/>
			<?php if ( $desc ) : ?>
				<p class="desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
			<?php if ( $is_const ) : ?>
				<p class="apollo-const-notice">
					<?php printf( esc_html__( 'Defined in wp-config.php as %s. Remove constant to edit here.', 'vicinity' ), '<code>' . esc_html( $const ) . '</code>' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Status badge.
 *
 * @param bool|null $ok  true = OK, false = not configured, null = partial.
 * @param string    $ok_text
 * @param string    $warn_text
 */
function vicinity_settings_badge( ?bool $ok, string $ok_text = 'Connected', string $warn_text = 'Not configured' ): void {
	if ( $ok === true ) {
		echo '<span class="apollo-status apollo-status--ok">' . esc_html( $ok_text ) . '</span>';
	} elseif ( $ok === null ) {
		echo '<span class="apollo-status apollo-status--warn">Partial</span>';
	} else {
		echo '<span class="apollo-status apollo-status--off">' . esc_html( $warn_text ) . '</span>';
	}
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB: STORAGE
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_settings_tab_storage(): void {
	$v_ready = vicinity_r2_video_ready();
	$a_ready = vicinity_r2_audio_ready();
	$s_ready = vicinity_s3_ready();
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'vicinity_storage' ); ?>

		<!-- R2 Video -->
		<div class="apollo-card">
			<h3>
				<?php esc_html_e( 'R2 — Video Bucket', 'vicinity' ); ?>
				<?php vicinity_settings_badge( $v_ready ); ?>
			</h3>
			<?php
			vicinity_settings_field( 'vicinity_r2_video_account_id', 'VICINITY_R2_VIDEO_ACCOUNT_ID', 'Account ID', 'Cloudflare account ID (32 hex chars).' );
			vicinity_settings_field( 'vicinity_r2_video_access_key', 'VICINITY_R2_VIDEO_ACCESS_KEY', 'Access Key ID', '', false );
			vicinity_settings_field( 'vicinity_r2_video_secret_key', 'VICINITY_R2_VIDEO_SECRET_KEY', 'Secret Access Key', '', true );
			vicinity_settings_field( 'vicinity_r2_video_bucket',     'VICINITY_R2_VIDEO_BUCKET',     'Bucket Name', 'e.g. penny-tribune-video' );
			vicinity_settings_field( 'vicinity_r2_video_public_url', 'VICINITY_R2_VIDEO_PUBLIC_URL', 'Public URL', 'e.g. https://video.pennytribune.com — trailing slash optional.' );
			?>
		</div>

		<!-- R2 Audio -->
		<div class="apollo-card">
			<h3>
				<?php esc_html_e( 'R2 — Audio / Podcast Bucket', 'vicinity' ); ?>
				<?php vicinity_settings_badge( $a_ready ); ?>
			</h3>
			<p style="font-size:12px;color:#777;margin:0 0 14px;">
				<?php esc_html_e( 'Leave all fields blank to share the same R2 bucket as Video.', 'vicinity' ); ?>
			</p>
			<?php
			vicinity_settings_field( 'vicinity_r2_audio_account_id', 'VICINITY_R2_AUDIO_ACCOUNT_ID', 'Account ID', 'Leave blank to use Video account ID.' );
			vicinity_settings_field( 'vicinity_r2_audio_access_key', 'VICINITY_R2_AUDIO_ACCESS_KEY', 'Access Key ID', '' );
			vicinity_settings_field( 'vicinity_r2_audio_secret_key', 'VICINITY_R2_AUDIO_SECRET_KEY', 'Secret Access Key', '', true );
			vicinity_settings_field( 'vicinity_r2_audio_bucket',     'VICINITY_R2_AUDIO_BUCKET',     'Bucket Name', 'e.g. penny-tribune-audio' );
			vicinity_settings_field( 'vicinity_r2_audio_public_url', 'VICINITY_R2_AUDIO_PUBLIC_URL', 'Public URL', 'e.g. https://audio.pennytribune.com' );
			?>
		</div>

		<!-- S3 Fallback -->
		<div class="apollo-card">
			<h3>
				<?php esc_html_e( 'Amazon S3 — Fallback Storage', 'vicinity' ); ?>
				<?php vicinity_settings_badge( $s_ready ); ?>
			</h3>
			<p style="font-size:12px;color:#777;margin:0 0 14px;">
				<?php esc_html_e( 'Used only if R2 video bucket is not configured. S3-compatible endpoints are supported.', 'vicinity' ); ?>
			</p>
			<?php
			vicinity_settings_field( 'vicinity_s3_access_key', 'VICINITY_S3_ACCESS_KEY', 'Access Key ID', '' );
			vicinity_settings_field( 'vicinity_s3_secret_key', 'VICINITY_S3_SECRET_KEY', 'Secret Access Key', '', true );
			vicinity_settings_field( 'vicinity_s3_bucket',     'VICINITY_S3_BUCKET',     'Bucket Name', '' );
			vicinity_settings_field( 'vicinity_s3_region',     'VICINITY_S3_REGION',     'Region', 'e.g. us-east-1' );
			vicinity_settings_field( 'vicinity_s3_public_url', 'VICINITY_S3_PUBLIC_URL', 'Public URL', 'Leave blank to use default S3 URL pattern.' );
			?>
		</div>

		<div class="apollo-actions">
			<?php submit_button( __( 'Save Storage Settings', 'vicinity' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB: YOUTUBE LIVE
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_settings_tab_youtube(): void {
	$channel_id = (string) get_option( 'vicinity_yt_channel_id', '' );
	$api_key    = (string) get_option( 'vicinity_yt_api_key', '' );
	$configured = $channel_id && $api_key;
	$status     = $configured ? vicinity_yt_status() : false;
	$is_live    = is_array( $status ) && ! empty( $status['live'] );
	$checked_at = is_array( $status ) ? ( $status['checked_at'] ?? 0 ) : 0;
	$next_cron  = wp_next_scheduled( VICINITY_YT_CRON );
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'vicinity_youtube' ); ?>

		<div class="apollo-card">
			<h3>
				<?php esc_html_e( 'YouTube Data API v3', 'vicinity' ); ?>
				<?php vicinity_settings_badge( $configured, 'Configured', 'Not configured' ); ?>
			</h3>

			<?php
			vicinity_settings_field( 'vicinity_yt_channel_id', 'VICINITY_YT_CHANNEL_ID', 'Channel ID', 'Starts with UC — e.g. UCxxxxxxxxxxxxxxxxx' );
			vicinity_settings_field( 'vicinity_yt_api_key',    'VICINITY_YT_API_KEY',    'API Key', 'YouTube Data API v3 key from Google Cloud Console.', true );
			?>

			<?php if ( $configured ) : ?>
			<div class="apollo-yt-live-status">
				<span class="<?php echo $is_live ? 'apollo-dot-live' : 'apollo-dot-offline'; ?>"></span>
				<span>
					<?php if ( $is_live ) : ?>
						<strong><?php esc_html_e( 'LIVE NOW:', 'vicinity' ); ?></strong>
						<?php echo esc_html( $status['title'] ?? '' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Not currently live.', 'vicinity' ); ?>
					<?php endif; ?>
				</span>
				<span style="color:#999;margin-left:auto;font-size:12px;">
					<?php
					if ( $checked_at ) {
						printf(
							/* translators: %s: time diff */
							esc_html__( 'Checked %s ago', 'vicinity' ),
							esc_html( human_time_diff( $checked_at, time() ) )
						);
					}
					if ( $next_cron ) {
						echo ' · ' . esc_html(
							sprintf(
								/* translators: %s: time diff */
								__( 'Next check in %s', 'vicinity' ),
								human_time_diff( time(), $next_cron )
							)
						);
					}
					?>
				</span>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=apollo-settings&tab=youtube&vicinity_yt_check=1' ), 'vicinity_yt_check' ) ); ?>"
				   class="button button-small">
					<?php esc_html_e( 'Check Now', 'vicinity' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>

		<div class="apollo-card">
			<h3><?php esc_html_e( 'How YouTube Live Detection Works', 'vicinity' ); ?></h3>
			<p style="font-size:13px;color:#555;line-height:1.6;margin:0;">
				<?php esc_html_e( 'Apollo polls the YouTube Data API every 15 minutes using WP-Cron. Results are cached in a transient. When a live stream is detected, the Video Hub and Homepage display an embedded player. Editors can manually trigger a check using the button above or the "Check Now" button on the live banner.', 'vicinity' ); ?>
			</p>
			<p style="font-size:13px;color:#555;line-height:1.6;margin:12px 0 0;">
				<?php esc_html_e( 'To display the live banner on a page, use the template tag: ', 'vicinity' ); ?>
				<code>&lt;?php vicinity_yt_live_banner(); ?&gt;</code>
			</p>
		</div>

		<div class="apollo-actions">
			<?php submit_button( __( 'Save YouTube Settings', 'vicinity' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB: CLOUDFLARE
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_settings_tab_cloudflare(): void {
	$zone_id   = (string) get_option( 'vicinity_cf_zone_id', '' );
	$api_token = (string) get_option( 'vicinity_cf_api_token', '' );
	$enabled   = $zone_id && $api_token;
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'vicinity_cloudflare' ); ?>

		<div class="apollo-card">
			<h3>
				<?php esc_html_e( 'Cloudflare Image Transforms', 'vicinity' ); ?>
				<?php vicinity_settings_badge( $enabled, 'Enabled', 'Disabled' ); ?>
			</h3>
			<p style="font-size:13px;color:#555;margin:0 0 16px;line-height:1.6;">
				<?php esc_html_e( 'When configured, Apollo uses Cloudflare Image Transforms to resize and optimize video thumbnails on the fly. Thumbnails are served from your Cloudflare zone at /cdn-cgi/image/. Requires the Images product to be enabled on your Cloudflare account.', 'vicinity' ); ?>
			</p>
			<?php
			vicinity_settings_field( 'vicinity_cf_zone_id',   'VICINITY_CF_ZONE_ID',   'Zone ID', 'Found in Cloudflare dashboard → your domain → Overview → Zone ID.' );
			vicinity_settings_field( 'vicinity_cf_api_token', 'VICINITY_CF_API_TOKEN', 'API Token', 'Needs Zone:Read + Zone.Images:Edit permissions.', true );
			?>
		</div>

		<div class="apollo-card">
			<h3><?php esc_html_e( 'Transform URL Preview', 'vicinity' ); ?></h3>
			<?php
			$sample_key = 'video/2024/01/sample-thumb.jpg';
			$cfg        = vicinity_r2_base_config();
			$transform  = vicinity_cf_transform_url( $sample_key, 'w=800,h=450,fit=cover,f=webp', $cfg );
			?>
			<p style="font-size:12px;color:#777;margin:0 0 8px;"><?php esc_html_e( 'Example transform URL for an 800×450 WebP thumbnail:', 'vicinity' ); ?></p>
			<code style="display:block;word-break:break-all;font-size:12px;background:#f5f5f5;padding:8px;border-radius:3px;">
				<?php echo $transform ? esc_html( $transform ) : esc_html__( '(not configured — direct R2/S3 URL will be used)', 'vicinity' ); ?>
			</code>
		</div>

		<div class="apollo-actions">
			<?php submit_button( __( 'Save Cloudflare Settings', 'vicinity' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB: CONTENT
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_settings_tab_content(): void {
	$shorts_cat       = (string) get_option( 'vicinity_shorts_category', '' );
	$paywall_enabled  = (bool) get_option( 'vicinity_paywall_enabled', 0 );
	$paywall_role     = (string) get_option( 'vicinity_paywall_role', 'subscriber' );
	$anon_filenames   = (bool) get_option( 'vicinity_anon_filenames', 1 );

	// Build video category options.
	$cats = get_terms( [ 'taxonomy' => 'serve_video_category', 'hide_empty' => false ] );
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'vicinity_content' ); ?>

		<!-- Shorts -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'Shorts / Vertical Video', 'vicinity' ); ?></h3>
			<div class="apollo-field">
				<label for="vicinity_shorts_category"><?php esc_html_e( 'Shorts Category', 'vicinity' ); ?></label>
				<div>
					<select name="vicinity_shorts_category" id="vicinity_shorts_category">
						<option value=""><?php esc_html_e( '— Auto-detect 9:16 format —', 'vicinity' ); ?></option>
						<?php if ( ! is_wp_error( $cats ) ) : ?>
							<?php foreach ( $cats as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->slug ); ?>"
								<?php selected( $shorts_cat, $cat->slug ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="desc"><?php esc_html_e( 'Videos in this category always render in the Shorts strip. Leave blank to auto-detect by _svh_format meta.', 'vicinity' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Paywall -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'Paywall / Members-Only Video', 'vicinity' ); ?></h3>
			<div class="apollo-field">
				<label><?php esc_html_e( 'Enable Paywall', 'vicinity' ); ?></label>
				<div>
					<label style="font-weight:normal;">
						<input type="checkbox" name="vicinity_paywall_enabled" value="1" <?php checked( $paywall_enabled ); ?> />
						<?php esc_html_e( 'Restrict paywalled videos to logged-in users with the required role', 'vicinity' ); ?>
					</label>
					<p class="desc"><?php esc_html_e( 'When disabled, the paywall meta field is ignored and all videos play freely.', 'vicinity' ); ?></p>
				</div>
			</div>

			<div class="apollo-field">
				<label for="vicinity_paywall_role"><?php esc_html_e( 'Required Role', 'vicinity' ); ?></label>
				<div>
					<select name="vicinity_paywall_role" id="vicinity_paywall_role">
						<?php
						global $wp_roles;
						foreach ( $wp_roles->get_names() as $slug => $name ) {
							echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $paywall_role, $slug, false ) . '>' . esc_html( translate_user_role( $name ) ) . '</option>';
						}
						?>
					</select>
					<p class="desc"><?php esc_html_e( 'Users with this role (or higher) can watch paywalled content.', 'vicinity' ); ?></p>
				</div>
			</div>
		</div>

		<!-- File Naming -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'File Naming', 'vicinity' ); ?></h3>
			<div class="apollo-field">
				<label><?php esc_html_e( 'Anonymize Filenames', 'vicinity' ); ?></label>
				<div>
					<label style="font-weight:normal;">
						<input type="checkbox" name="vicinity_anon_filenames" value="1" <?php checked( $anon_filenames ); ?> />
						<?php esc_html_e( 'Use UUID-based object keys instead of original filenames', 'vicinity' ); ?>
					</label>
					<p class="desc"><?php esc_html_e( 'Recommended. Prevents exposing original filenames in R2/S3 URLs. Disable only for debugging.', 'vicinity' ); ?></p>
				</div>
			</div>

			<div style="font-size:12px;color:#777;margin-top:14px;padding:10px;background:#f8f8f8;border-radius:3px;">
				<strong><?php esc_html_e( 'Enabled:', 'vicinity' ); ?></strong>
				<code>video/2024/01/3f7a9c12e4b60d2f.mp4</code><br>
				<strong><?php esc_html_e( 'Disabled:', 'vicinity' ); ?></strong>
				<code>video/2024/01/breaking-news-segment.mp4</code>
			</div>
		</div>

		<!-- Video Hub Layout -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'Video Hub Layout', 'vicinity' ); ?></h3>
			<p style="font-size:13px;color:#555;line-height:1.6;margin:0;">
				<?php esc_html_e( 'The Video Hub layout is managed via the dedicated layout editor. Access it from the Video Hub admin page or from the top toolbar when viewing the Video Hub on the front end.', 'vicinity' ); ?>
			</p>
			<p style="margin:12px 0 0;">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=apollo-settings&tab=content&vicinity_reset_layout=1' ) ); ?>"
				   class="button"
				   onclick="return confirm('<?php esc_attr_e( 'Reset the Video Hub layout to defaults? This cannot be undone.', 'vicinity' ); ?>')">
					<?php esc_html_e( 'Reset Hub Layout to Defaults', 'vicinity' ); ?>
				</a>
			</p>
			<?php
			// Handle layout reset.
			if ( isset( $_GET['vicinity_reset_layout'] ) && current_user_can( 'manage_options' ) ) {
				delete_option( 'vicinity_vh_layout' );
				echo '<p style="color:#c62828;font-size:13px;margin:8px 0 0;">' . esc_html__( 'Layout reset.', 'vicinity' ) . '</p>';
			}
			?>
		</div>

		<div class="apollo-actions">
			<?php submit_button( __( 'Save Content Settings', 'vicinity' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB: AI PROVIDERS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_init', static function (): void {
	$ai_opts = [
		'vicinity_ai_openai_key',
		'vicinity_ai_claude_key',
		'vicinity_ai_gemini_key',
		'vicinity_ai_minimax_key',
		'vicinity_ai_featherless_key',
		'vicinity_ai_featherless_model',
		// Feature routing: provider + model per feature.
		'vicinity_ai_provider_rewrite',
		'vicinity_ai_model_rewrite',
		'vicinity_ai_provider_editorial',
		'vicinity_ai_model_editorial',
		'vicinity_ai_provider_search',
		'vicinity_ai_model_search',
	];
	foreach ( $ai_opts as $opt ) {
		register_setting( 'vicinity_ai', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
	}
} );

function vicinity_settings_tab_ai(): void {
	$providers = function_exists( 'vicinity_ai_providers' )
		? vicinity_ai_providers()
		: [];

	$features = [
		'rewrite'   => __( 'Rewriter Tool', 'vicinity' ),
		'editorial' => __( 'Editorial Review', 'vicinity' ),
		'search'    => __( 'AI Search', 'vicinity' ),
	];

	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'vicinity_ai' ); ?>

		<!-- ── API Keys ─────────────────────────────────────────── -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'API Keys', 'vicinity' ); ?></h3>
			<p class="desc" style="margin-bottom:14px;">
				<?php esc_html_e( 'Keys stored here are used as fallbacks. Define VICINITY_AI_OPENAI_KEY (etc.) in wp-config.php to lock them.', 'vicinity' ); ?>
			</p>

			<?php
			$key_fields = [
				[ 'vicinity_ai_openai_key',      'VICINITY_AI_OPENAI_KEY',      'OpenAI API Key' ],
				[ 'vicinity_ai_claude_key',       'VICINITY_AI_CLAUDE_KEY',      'Anthropic / Claude API Key' ],
				[ 'vicinity_ai_gemini_key',       'VICINITY_AI_GEMINI_KEY',      'Google Gemini API Key' ],
				[ 'vicinity_ai_minimax_key',      'VICINITY_AI_MINIMAX_KEY',     'MiniMax API Key' ],
				[ 'vicinity_ai_featherless_key',  'VICINITY_AI_FEATHERLESS_KEY', 'Featherless.ai API Key' ],
			];
			foreach ( $key_fields as [ $opt, $const, $label ] ) :
				vicinity_settings_field( $opt, $const, $label, '', true );
			endforeach;
			?>

			<?php vicinity_settings_field(
				'vicinity_ai_featherless_model',
				'',
				'Featherless Default Model',
				'e.g. meta-llama/Llama-3.3-70B-Instruct'
			); ?>
		</div>

		<!-- ── Per-feature routing ──────────────────────────────── -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'Feature Routing', 'vicinity' ); ?></h3>
			<p class="desc" style="margin-bottom:14px;">
				<?php esc_html_e( 'Choose which AI provider and model powers each feature. Providers without a key saved above will be greyed out on the front end but not blocked here.', 'vicinity' ); ?>
			</p>

			<?php foreach ( $features as $feature_key => $feature_label ) :
				$current_provider = get_option( 'vicinity_ai_provider_' . $feature_key, 'openai' );
				$current_model    = get_option( 'vicinity_ai_model_'    . $feature_key, '' );
				?>
				<div style="margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid #f0f0f0;">
					<strong style="font-size:13px;"><?php echo esc_html( $feature_label ); ?></strong>

					<div class="apollo-field" style="margin-top:8px;">
						<label for="vicinity_ai_provider_<?php echo esc_attr( $feature_key ); ?>">
							<?php esc_html_e( 'Provider', 'vicinity' ); ?>
						</label>
						<div>
							<select id="vicinity_ai_provider_<?php echo esc_attr( $feature_key ); ?>"
								name="vicinity_ai_provider_<?php echo esc_attr( $feature_key ); ?>"
								class="ai-provider-select"
								data-feature="<?php echo esc_attr( $feature_key ); ?>">
								<?php foreach ( $providers as $pid => $pdata ) : ?>
									<option value="<?php echo esc_attr( $pid ); ?>"
										<?php selected( $current_provider, $pid ); ?>>
										<?php echo esc_html( $pdata['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="apollo-field">
						<label for="vicinity_ai_model_<?php echo esc_attr( $feature_key ); ?>">
							<?php esc_html_e( 'Model', 'vicinity' ); ?>
						</label>
						<div>
							<select id="vicinity_ai_model_<?php echo esc_attr( $feature_key ); ?>"
								name="vicinity_ai_model_<?php echo esc_attr( $feature_key ); ?>">
								<?php
								foreach ( $providers as $pid => $pdata ) :
									foreach ( $pdata['models'] as $mid => $mlabel ) :
										if ( $pid !== $current_provider ) continue;
										?>
										<option value="<?php echo esc_attr( $mid ); ?>"
											<?php selected( $current_model, $mid ); ?>>
											<?php echo esc_html( $mlabel ); ?>
										</option>
									<?php
									endforeach;
								endforeach;
								?>
							</select>
							<span id="ai-model-note-<?php echo esc_attr( $feature_key ); ?>" style="font-size:12px;color:#888;margin-left:8px;"></span>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- ── Quick links ──────────────────────────────────────── -->
		<div class="apollo-card">
			<h3><?php esc_html_e( 'Related Settings', 'vicinity' ); ?></h3>
			<p>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=apollo-editorial' ) ); ?>" class="button">
					<?php esc_html_e( 'Editorial Flow Settings →', 'vicinity' ); ?>
				</a>
			</p>
		</div>

		<div class="apollo-actions">
			<?php submit_button( __( 'Save AI Settings', 'vicinity' ), 'primary', 'submit', false ); ?>
		</div>
	</form>

	<script>
	(function(){
		// Rebuild model dropdown when provider changes.
		var providerData = <?php
			$out = [];
			foreach ( $providers as $pid => $pdata ) {
				$out[ $pid ] = array_map( null, array_keys( $pdata['models'] ), array_values( $pdata['models'] ) );
			}
			echo wp_json_encode( $out );
		?>;

		document.querySelectorAll('.ai-provider-select').forEach(function(sel){
			sel.addEventListener('change', function(){
				var feature   = this.dataset.feature;
				var pid       = this.value;
				var modelSel  = document.getElementById('vicinity_ai_model_' + feature);
				if(!modelSel) return;
				modelSel.innerHTML = '';
				var models = providerData[pid] || [];
				models.forEach(function(pair){
					var opt = document.createElement('option');
					opt.value       = pair[0];
					opt.textContent = pair[1];
					modelSel.appendChild(opt);
				});
			});
		});
	})();
	</script>
	<?php
}