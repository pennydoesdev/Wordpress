<?php
/**
 * Single Episode — NYT-style dark episode header + audio player.
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>

<?php while ( have_posts() ) : the_post(); ?>

<?php
$podcast_id   = get_post_meta( get_the_ID(), '_vicinity_podcast_id', true );
$episode_num  = get_post_meta( get_the_ID(), '_vicinity_episode_number', true );
$season_num   = get_post_meta( get_the_ID(), '_vicinity_season_number', true );
$audio_url    = get_post_meta( get_the_ID(), '_vicinity_audio_url', true );
$duration     = get_post_meta( get_the_ID(), '_vicinity_duration', true );
$deck         = get_post_meta( get_the_ID(), '_vicinity_deck', true );

$podcast      = $podcast_id ? get_post( $podcast_id ) : null;
?>

<main id="primary" class="site-main is-episode-article">

  <!-- EPISODE HERO — dark band -->
  <div class="episode-hero">
    <div class="episode-hero__inner">

      <?php if ( $podcast ) : ?>
        <a class="episode-show-link" href="<?php echo esc_url( get_permalink( $podcast ) ); ?>">
          <?php if ( $thumb = get_the_post_thumbnail( $podcast->ID, 'apollo-portrait' ) ) : ?>
            <div class="episode-show-art"><?php echo $thumb; ?></div>
          <?php endif; ?>
          <span class="episode-show-name"><?php echo esc_html( get_the_title( $podcast ) ); ?></span>
        </a>
      <?php else : ?>
        <p class="episode-show-name"><?php esc_html_e( 'Podcast', 'vicinity' ); ?></p>
      <?php endif; ?>

      <?php if ( $episode_num ) : ?>
        <p class="episode-label">
          <?php
          $ep_label = sprintf( esc_html__( 'Episode %s', 'vicinity' ), esc_html( $episode_num ) );
          if ( $season_num ) {
              $ep_label = sprintf( esc_html__( 'Season %1$s, Episode %2$s', 'vicinity' ), esc_html( $season_num ), esc_html( $episode_num ) );
          }
          echo $ep_label;
          ?>
        </p>
      <?php endif; ?>

      <h1 class="episode-headline"><?php the_title(); ?></h1>

      <?php if ( $deck ) : ?>
        <p class="episode-deck"><?php echo esc_html( $deck ); ?></p>
      <?php endif; ?>

      <div class="episode-meta">
        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
          <?php echo esc_html( get_the_date( 'M j, Y' ) ); ?>
        </time>
        <?php if ( $duration ) : ?>
          <span class="episode-duration"><?php echo esc_html( $duration ); ?></span>
        <?php endif; ?>
      </div>

      <!-- AUDIO PLAYER BAR -->
      <?php if ( $audio_url ) : ?>
      <div class="episode-player-bar" data-audio="<?php echo esc_url( $audio_url ); ?>">
        <button class="ep-play-btn" aria-label="<?php esc_attr_e( 'Play episode', 'vicinity' ); ?>">
          <svg viewBox="0 0 44 44" width="44" height="44" aria-hidden="true">
            <circle cx="22" cy="22" r="22" fill="currentColor" opacity=".12"/>
            <polygon class="ep-play-icon" points="16,11 38,22 16,33" fill="currentColor"/>
            <g class="ep-pause-icon" style="display:none">
              <rect x="13" y="11" width="6" height="22" fill="currentColor"/>
              <rect x="25" y="11" width="6" height="22" fill="currentColor"/>
            </g>
          </svg>
        </button>
        <div class="ep-scrubber-wrap">
          <div class="ep-time-current">0:00</div>
          <div class="ep-scrubber" role="slider" aria-label="Seek">
            <div class="ep-scrubber__track">
              <div class="ep-scrubber__fill"></div>
              <div class="ep-scrubber__thumb"></div>
            </div>
          </div>
          <div class="ep-time-total">0:00</div>
        </div>
        <div class="ep-volume">
          <button class="ep-mute-btn" aria-label="<?php esc_attr_e( 'Mute', 'vicinity' ); ?>">
            <?php echo vicinity_icon( 'volume' ); ?>
          </button>
          <input class="ep-vol-slider" type="range" min="0" max="1" step="0.05" value="1"
                 aria-label="<?php esc_attr_e( 'Volume', 'vicinity' ); ?>">
        </div>
        <a class="ep-download" href="<?php echo esc_url( $audio_url ); ?>" download
           aria-label="<?php esc_attr_e( 'Download episode', 'vicinity' ); ?>">
          <?php echo vicinity_icon( 'download' ); ?>
        </a>
        <audio preload="metadata" style="display:none">
          <source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
        </audio>
      </div>
      <?php endif; ?>

    </div><!-- .episode-hero__inner -->
  </div><!-- .episode-hero -->

  <!-- ARTICLE BODY -->
  <div class="article-container">

    <div class="article-layout">

      <div class="article-body-col">

        <div class="article-body">
          <?php the_content(); ?>
        </div>

        <!-- SHOW NOTES / TRANSCRIPT toggle -->
        <?php
        $show_notes  = get_post_meta( get_the_ID(), '_vicinity_show_notes', true );
        $transcript  = get_post_meta( get_the_ID(), '_vicinity_transcript', true );
        if ( $show_notes || $transcript ) :
        ?>
        <div class="episode-extras">
          <?php if ( $show_notes ) : ?>
          <details class="episode-extras__section">
            <summary><?php esc_html_e( 'Show Notes', 'vicinity' ); ?></summary>
            <div class="episode-extras__body"><?php echo wp_kses_post( $show_notes ); ?></div>
          </details>
          <?php endif; ?>
          <?php if ( $transcript ) : ?>
          <details class="episode-extras__section">
            <summary><?php esc_html_e( 'Transcript', 'vicinity' ); ?></summary>
            <div class="episode-extras__body"><?php echo wp_kses_post( $transcript ); ?></div>
          </details>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- OTHER EPISODES IN SAME PODCAST -->
        <?php if ( $podcast ) :
          $other_eps = new WP_Query( [
              'post_type'      => 'serve_episode',
              'posts_per_page' => 5,
              'post__not_in'   => [ get_the_ID() ],
              'orderby'        => 'date',
              'order'          => 'DESC',
              'meta_query'     => [ [
                  'key'   => '_vicinity_podcast_id',
                  'value' => $podcast_id,
              ] ],
          ] );
          if ( $other_eps->have_posts() ) :
        ?>
        <section class="related-articles" aria-label="<?php esc_attr_e( 'More Episodes', 'vicinity' ); ?>">
          <h2 class="related-heading">
            <?php printf( esc_html__( 'More from %s', 'vicinity' ), esc_html( get_the_title( $podcast ) ) ); ?>
          </h2>
          <div class="episode-list">
            <?php while ( $other_eps->have_posts() ) : $other_eps->the_post();
              $ep_audio = get_post_meta( get_the_ID(), '_vicinity_audio_url', true );
              $ep_dur   = get_post_meta( get_the_ID(), '_vicinity_duration', true );
            ?>
            <article class="episode-list-item">
              <?php if ( has_post_thumbnail() ) : ?>
                <div class="episode-list-thumb">
                  <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail( 'apollo-portrait' ); ?>
                  </a>
                </div>
              <?php endif; ?>
              <div class="episode-list-body">
                <h3 class="episode-list-title">
                  <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                <div class="episode-list-meta">
                  <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                    <?php echo esc_html( get_the_date( 'M j, Y' ) ); ?>
                  </time>
                  <?php if ( $ep_dur ) : ?>
                    <span class="episode-duration"><?php echo esc_html( $ep_dur ); ?></span>
                  <?php endif; ?>
                </div>
                <p class="episode-list-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
              </div>
            </article>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </section>
        <?php endif; endif; ?>

      </div><!-- .article-body-col -->

      <aside class="article-sidebar">

        <!-- AD: SIDEBAR TOP -->
        <?php vicinity_ad_zone( 'sidebar_top' ); ?>

        <?php if ( $podcast ) : ?>
        <div class="podcast-info-card">
          <?php if ( $thumb = get_the_post_thumbnail( $podcast->ID, 'apollo-card-sq' ) ) : ?>
            <a href="<?php echo esc_url( get_permalink( $podcast ) ); ?>"><?php echo $thumb; ?></a>
          <?php endif; ?>
          <h3 class="podcast-info-title">
            <a href="<?php echo esc_url( get_permalink( $podcast ) ); ?>">
              <?php echo esc_html( get_the_title( $podcast ) ); ?>
            </a>
          </h3>
          <?php
          $pod_desc = get_post_meta( $podcast->ID, '_vicinity_short_description', true )
                      ?: wp_trim_words( get_the_excerpt( $podcast->ID ), 20, '…' );
          if ( $pod_desc ) :
          ?>
          <p class="podcast-info-desc"><?php echo esc_html( $pod_desc ); ?></p>
          <?php endif; ?>
          <a class="btn-subscribe" href="<?php echo esc_url( get_permalink( $podcast ) ); ?>">
            <?php esc_html_e( 'View All Episodes', 'vicinity' ); ?>
          </a>
        </div>
        <?php endif; ?>

        <!-- AD: SIDEBAR MID -->
        <?php vicinity_ad_zone( 'sidebar_mid' ); ?>

        <?php if ( is_active_sidebar( 'article-sidebar' ) ) : ?>
          <div class="widget-area">
            <?php dynamic_sidebar( 'article-sidebar' ); ?>
          </div>
        <?php endif; ?>

      </aside><!-- .article-sidebar -->

    </div><!-- .article-layout -->

  </div><!-- .article-container -->

</main><!-- #primary -->

<script>
(function() {
  var bar = document.querySelector('.episode-player-bar');
  if (!bar) return;
  var audio    = bar.querySelector('audio');
  var playBtn  = bar.querySelector('.ep-play-btn');
  var playIco  = bar.querySelector('.ep-play-icon');
  var pauseIco = bar.querySelector('.ep-pause-icon');
  var fill     = bar.querySelector('.ep-scrubber__fill');
  var thumb    = bar.querySelector('.ep-scrubber__thumb');
  var current  = bar.querySelector('.ep-time-current');
  var total    = bar.querySelector('.ep-time-total');
  var volSlider = bar.querySelector('.ep-vol-slider');
  var muteBtn  = bar.querySelector('.ep-mute-btn');
  var track    = bar.querySelector('.ep-scrubber__track');
  var isDragging = false;

  function fmt(s) {
    s = Math.floor(s || 0);
    var m = Math.floor(s / 60), r = s % 60;
    return m + ':' + (r < 10 ? '0' : '') + r;
  }
  function updateProgress() {
    if (!audio.duration) return;
    var pct = (audio.currentTime / audio.duration) * 100;
    fill.style.width  = pct + '%';
    thumb.style.left  = pct + '%';
    current.textContent = fmt(audio.currentTime);
  }

  audio.addEventListener('loadedmetadata', function() {
    total.textContent = fmt(audio.duration);
  });
  audio.addEventListener('timeupdate', function() {
    if (!isDragging) updateProgress();
  });
  playBtn.addEventListener('click', function() {
    if (audio.paused) {
      audio.play();
      playIco.style.display  = 'none';
      pauseIco.style.display = '';
    } else {
      audio.pause();
      playIco.style.display  = '';
      pauseIco.style.display = 'none';
    }
  });
  track.addEventListener('click', function(e) {
    var rect = track.getBoundingClientRect();
    var pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    audio.currentTime = pct * audio.duration;
  });
  volSlider.addEventListener('input', function() {
    audio.volume = volSlider.value;
  });
  muteBtn.addEventListener('click', function() {
    audio.muted = !audio.muted;
  });
})();
</script>

<?php endwhile; ?>

<?php get_footer(); ?>
