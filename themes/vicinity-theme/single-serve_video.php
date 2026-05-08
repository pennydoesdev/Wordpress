<?php
/**
 * Single Video — NYT-style layout.
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>

<?php while ( have_posts() ) : the_post(); ?>

<?php
$kicker      = get_post_meta( get_the_ID(), '_vicinity_kicker', true );
$deck        = get_post_meta( get_the_ID(), '_vicinity_deck', true );
$video_id    = get_post_meta( get_the_ID(), '_vicinity_video_id', true );
$video_src   = get_post_meta( get_the_ID(), '_vicinity_video_src', true );
$categories  = get_the_terms( get_the_ID(), 'serve_video_category' );
$primary_cat = $categories && ! is_wp_error( $categories ) ? $categories[0] : null;
?>

<main id="primary" class="site-main is-video-article">

  <div class="article-container">

    <!-- ARTICLE HEADER -->
    <header class="article-header">

      <?php if ( $primary_cat ) : ?>
        <nav class="article-kicker">
          <a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>">
            <?php echo esc_html( $primary_cat->name ); ?>
          </a>
        </nav>
      <?php elseif ( $kicker ) : ?>
        <p class="article-kicker"><?php echo esc_html( $kicker ); ?></p>
      <?php endif; ?>

      <h1 class="article-headline"><?php the_title(); ?></h1>

      <?php if ( $deck ) : ?>
        <p class="article-deck"><?php echo esc_html( $deck ); ?></p>
      <?php endif; ?>

      <div class="article-byline-block">
        <?php echo vicinity_byline( get_the_ID() ); ?>

        <div class="article-meta-right">
          <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
            <?php echo esc_html( get_the_date( 'M j, Y' ) ); ?>
          </time>
          <div class="article-share">
            <button class="share-btn" aria-label="Share">
              <?php echo vicinity_icon( 'share' ); ?>
            </button>
          </div>
        </div>
      </div>

    </header><!-- .article-header -->

    <div class="article-rule article-rule--strong"></div>

    <!-- VIDEO PLAYER -->
    <figure class="article-video-player">
      <?php
      // Attempt to render the Apollo video player.
      if ( $video_id && function_exists( 'vicinity_video_player_html' ) ) {
          echo vicinity_video_player_html( get_the_ID(), [
              'width'  => 1280,
              'height' => 720,
              'fluid'  => true,
          ] );
      } elseif ( $video_src ) {
          echo '<video class="video-js vjs-big-play-centered" controls preload="metadata" width="1280" height="720">';
          echo '<source src="' . esc_url( $video_src ) . '" type="video/mp4">';
          echo '</video>';
      } elseif ( has_post_thumbnail() ) {
          the_post_thumbnail( 'apollo-hero', [ 'class' => 'article-lead-image' ] );
      }
      ?>
      <?php if ( $caption = get_post_meta( get_the_ID(), '_vicinity_video_caption', true ) ) : ?>
        <figcaption class="article-caption"><?php echo esc_html( $caption ); ?></figcaption>
      <?php endif; ?>
    </figure>

    <!-- ARTICLE BODY + SIDEBAR -->
    <div class="article-layout">

      <div class="article-body-col">

        <div class="article-body">
          <?php the_content(); ?>
        </div>

        <?php
        // Tags.
        $tags = get_the_terms( get_the_ID(), 'serve_video_tag' );
        if ( $tags && ! is_wp_error( $tags ) ) :
        ?>
        <footer class="article-tags">
          <span class="tags-label"><?php esc_html_e( 'Topics', 'vicinity' ); ?></span>
          <?php foreach ( $tags as $tag ) : ?>
            <a class="tag-pill" href="<?php echo esc_url( get_term_link( $tag ) ); ?>">
              <?php echo esc_html( $tag->name ); ?>
            </a>
          <?php endforeach; ?>
        </footer>
        <?php endif; ?>

        <!-- RELATED VIDEOS -->
        <?php
        $related_args = [
            'post_type'      => 'serve_video',
            'posts_per_page' => 4,
            'post__not_in'   => [ get_the_ID() ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ( $primary_cat ) {
            $related_args['tax_query'] = [ [
                'taxonomy' => 'serve_video_category',
                'field'    => 'term_id',
                'terms'    => $primary_cat->term_id,
            ] ];
        }
        $related = new WP_Query( $related_args );
        if ( $related->have_posts() ) :
        ?>
        <section class="related-articles" aria-label="<?php esc_attr_e( 'More Videos', 'vicinity' ); ?>">
          <h2 class="related-heading"><?php esc_html_e( 'More Videos', 'vicinity' ); ?></h2>
          <div class="story-grid story-grid--4">
            <?php while ( $related->have_posts() ) : $related->the_post(); ?>
              <?php echo vicinity_article_card( get_the_ID(), 'vertical' ); ?>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </section>
        <?php endif; ?>

      </div><!-- .article-body-col -->

      <aside class="article-sidebar">

        <!-- AD: SIDEBAR TOP -->
        <?php vicinity_ad_zone( 'sidebar_top' ); ?>

        <!-- MOST READ -->
        <section class="most-read-rail">
          <h3 class="rail-heading"><?php esc_html_e( 'Most Watched', 'vicinity' ); ?></h3>
          <?php
          $most_watched = new WP_Query( [
              'post_type'      => 'serve_video',
              'posts_per_page' => 5,
              'meta_key'       => '_vicinity_view_count',
              'orderby'        => 'meta_value_num',
              'order'          => 'DESC',
              'post__not_in'   => [ get_the_ID() ],
          ] );
          $rank = 1;
          while ( $most_watched->have_posts() ) : $most_watched->the_post(); ?>
            <article class="most-read-item">
              <span class="most-read-rank"><?php echo esc_html( $rank++ ); ?></span>
              <div class="most-read-body">
                <h4 class="most-read-title">
                  <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h4>
              </div>
            </article>
          <?php endwhile; wp_reset_postdata(); ?>
        </section>

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

<?php endwhile; ?>

<?php get_footer(); ?>
