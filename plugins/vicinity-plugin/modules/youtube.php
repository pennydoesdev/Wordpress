<?php
/**
 * YouTube Live Detection.
 *
 * WP-Cron polls the YouTube Data API v3 every 15 minutes.
 * Result cached in transient 'vicinity_yt_live_status' (15 min TTL).
 * Renders a live embed banner on video hub and homepage.
 *
 * Required options:
 *   vicinity_yt_channel_id — YouTube channel ID (UCxxxxxxxxx)
 *   vicinity_yt_api_key    — YouTube Data API v3 key
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

define( 'VICINITY_YT_TRANSIENT', 'vicinity_yt_live_status' );
define( 'VICINITY_YT_CRON',      'vicinity_yt_cron' );
define( 'VICINITY_YT_CACHE',     15 * MINUTE_IN_SECONDS );

// ═══════════════════════════════════════════════════════════════════════════
// CRON
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'cron_schedules', static function ( array $s ): array {
	$s['vicinity_15min'] = [ 'interval' => VICINITY_YT_CACHE, 'display' => __( 'Every 15 Minutes', 'vicinity' ) ];
	return $s;
} );

add_action( 'init', static function (): void {
	if ( ! get_option( 'vicinity_yt_channel_id' ) || ! get_option( 'vicinity_yt_api_key' ) ) return;
	if ( ! wp_next_scheduled( VICINITY_YT_CRON ) ) {
		wp_schedule_event( time(), 'vicinity_15min', VICINITY_YT_CRON );
	}
} );

add_action( VICINITY_YT_CRON, 'vicinity_yt_fetch' );

// ═══════════════════════════════════════════════════════════════════════════
// API
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_yt_fetch(): array|false {
	$channel_id = (string) get_option( 'vicinity_yt_channel_id', '' );
	$api_key    = (string) get_option( 'vicinity_yt_api_key', '' );
	if ( ! $channel_id || ! $api_key ) {
		delete_transient( VICINITY_YT_TRANSIENT );
		return false;
	}

	$url  = add_query_arg( [
		'part'       => 'snippet',
		'channelId'  => $channel_id,
		'eventType'  => 'live',
		'type'       => 'video',
		'maxResults' => 1,
		'key'        => $api_key,
	], 'https://www.googleapis.com/youtube/v3/search' );

	$resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
	if ( is_wp_error( $resp ) ) {
		set_transient( VICINITY_YT_TRANSIENT, [ 'live' => false, 'error' => $resp->get_error_message() ], 5 * MINUTE_IN_SECONDS );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	$code = wp_remote_retrieve_response_code( $resp );

	if ( $code !== 200 || empty( $body['items'] ) ) {
		$data = [ 'live' => false, 'error' => $body['error']['message'] ?? 'No live stream', 'checked_at' => time() ];
		set_transient( VICINITY_YT_TRANSIENT, $data, VICINITY_YT_CACHE );
		return false;
	}

	$item = $body['items'][0];
	$data = [
		'live'       => true,
		'video_id'   => $item['id']['videoId'] ?? '',
		'title'      => $item['snippet']['title'] ?? '',
		'thumb'      => $item['snippet']['thumbnails']['high']['url'] ?? '',
		'desc'       => wp_trim_words( $item['snippet']['description'] ?? '', 20 ),
		'channel_id' => $channel_id,
		'checked_at' => time(),
	];
	set_transient( VICINITY_YT_TRANSIENT, $data, VICINITY_YT_CACHE );
	return $data;
}

function vicinity_yt_status(): array|false {
	$cached = get_transient( VICINITY_YT_TRANSIENT );
	return $cached !== false ? $cached : vicinity_yt_fetch();
}

function vicinity_yt_is_live(): bool {
	$s = vicinity_yt_status();
	return is_array( $s ) && ! empty( $s['live'] );
}

// ═══════════════════════════════════════════════════════════════════════════
// AJAX REFRESH (throttled, no-priv)
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_yt_refresh',        'vicinity_yt_ajax_refresh' );
add_action( 'wp_ajax_nopriv_vicinity_yt_refresh', 'vicinity_yt_ajax_refresh' );

function vicinity_yt_ajax_refresh(): void {
	if ( ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ?? '' ), 'vicinity_yt_refresh' ) ) {
		wp_send_json_error( 'invalid_nonce' );
	}
	if ( get_transient( 'vicinity_yt_refresh_throttle' ) ) {
		wp_send_json_success( [ 'live' => vicinity_yt_is_live(), 'throttled' => true ] );
	}
	delete_transient( VICINITY_YT_TRANSIENT );
	set_transient( 'vicinity_yt_refresh_throttle', 1, 60 );
	$status = vicinity_yt_fetch();
	wp_send_json_success( [ 'live' => is_array( $status ) && ! empty( $status['live'] ), 'throttled' => false, 'status' => $status ?: [] ] );
}

// ═══════════════════════════════════════════════════════════════════════════
// RENDER — LIVE BANNER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_yt_live_banner( string $context = 'hub' ): void {
	$channel_id = (string) get_option( 'vicinity_yt_channel_id', '' );
	$api_key    = (string) get_option( 'vicinity_yt_api_key', '' );
	if ( ! $channel_id || ! $api_key ) return;

	$status  = vicinity_yt_status();
	$is_live = is_array( $status ) && ! empty( $status['live'] );
	$nonce   = wp_create_nonce( 'vicinity_yt_refresh' );
	$ajax    = admin_url( 'admin-ajax.php' );
	$ch_url  = 'https://www.youtube.com/channel/' . $channel_id . '/live';

	if ( ! $is_live ) {
		?>
		<div class="apollo-yt-bar apollo-yt-bar--offline apollo-yt-bar--<?php echo esc_attr( $context ); ?>"
			 id="apollo-yt-bar-<?php echo esc_attr( $context ); ?>">
			<span class="apollo-yt-dot apollo-yt-dot--offline" aria-hidden="true"></span>
			<span><?php esc_html_e( 'Not currently live', 'vicinity' ); ?></span>
			<span class="apollo-yt-checked">·
				<?php
				$checked = is_array( $status ) ? ( $status['checked_at'] ?? 0 ) : 0;
				echo $checked ? esc_html( sprintf( __( 'Checked %s ago', 'vicinity' ), human_time_diff( $checked, time() ) ) ) : '';
				?>
			</span>
			<button class="apollo-yt-refresh"
					data-context="<?php echo esc_attr( $context ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					data-ajax="<?php echo esc_attr( $ajax ); ?>">
				<?php esc_html_e( 'Check now', 'vicinity' ); ?>
			</button>
			<a href="<?php echo esc_url( $ch_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'YouTube Channel', 'vicinity' ); ?> ↗
			</a>
		</div>
		<?php
		vicinity_yt_inline_js();
		return;
	}

	$video_id = $status['video_id'] ?? '';
	$title    = $status['title'] ?? __( 'Live Now', 'vicinity' );
	?>
	<div class="apollo-yt-banner apollo-yt-banner--<?php echo esc_attr( $context ); ?>"
		 id="apollo-yt-banner-<?php echo esc_attr( $context ); ?>">
		<div class="apollo-yt-banner__head">
			<span class="apollo-yt-badge">
				<span class="apollo-yt-dot" aria-hidden="true"></span>
				<?php esc_html_e( 'LIVE NOW', 'vicinity' ); ?>
			</span>
			<span class="apollo-yt-banner__title"><?php echo esc_html( $title ); ?></span>
			<div class="apollo-yt-banner__actions">
				<button class="apollo-yt-refresh"
						data-context="<?php echo esc_attr( $context ); ?>"
						data-nonce="<?php echo esc_attr( $nonce ); ?>"
						data-ajax="<?php echo esc_attr( $ajax ); ?>"
						aria-label="<?php esc_attr_e( 'Refresh live status', 'vicinity' ); ?>">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M3.5 7A7 7 0 1 1 2 10"/><polyline points="3.5,3 3.5,7 7.5,7"/></svg>
				</button>
				<a href="<?php echo esc_url( $ch_url ); ?>" target="_blank" rel="noopener noreferrer"
				   class="apollo-yt-banner__yt-link">
					<?php esc_html_e( 'Open on YouTube', 'vicinity' ); ?> ↗
				</a>
			</div>
		</div>
		<div class="apollo-yt-banner__embed">
			<div class="apollo-yt-banner__player">
				<iframe
					src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $video_id ); ?>?autoplay=1&mute=1&rel=0&modestbranding=1&playsinline=1"
					title="<?php echo esc_attr( $title ); ?>"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture"
					allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"
					style="position:absolute;inset:0;width:100%;height:100%;border:0;">
				</iframe>
			</div>
			<?php if ( $status['desc'] ?? '' ) : ?>
			<p class="apollo-yt-banner__desc"><?php echo esc_html( $status['desc'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	vicinity_yt_inline_js();
}

function vicinity_yt_inline_js(): void {
	static $done = false;
	if ( $done ) return;
	$done = true;
	?>
	<script>
	(function(){
		document.querySelectorAll('.apollo-yt-refresh').forEach(function(btn){
			if(btn._apolloBound) return;
			btn._apolloBound = true;
			btn.addEventListener('click', function(){
				var ctx=btn.dataset.context, nonce=btn.dataset.nonce, ajax=btn.dataset.ajax;
				btn.disabled=true;
				var fd=new FormData();
				fd.append('action','vicinity_yt_refresh');
				fd.append('nonce',nonce);
				fetch(ajax,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
					btn.disabled=false;
					if(d.success && d.data.live) location.reload();
					else {
						var bar=document.querySelector('.apollo-yt-bar--'+ctx+' .apollo-yt-checked');
						if(bar) bar.textContent='· Checked just now';
					}
				}).catch(function(){ btn.disabled=false; });
			});
		});
	})();
	</script>
	<?php
}