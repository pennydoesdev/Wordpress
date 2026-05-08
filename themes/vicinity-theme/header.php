<?php
/**
 * Apollo Theme — Site Header
 *
 * NYT-style 3-tier masthead:
 *   1. Utility bar      — date / links / subscriber CTA
 *   2. Nameplate        — edition label, centered site title, social icons
 *   3. Section nav bar  — primary menu, search button
 *
 * @package Apollo
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$breaking_text = get_theme_mod( 'vicinity_breaking_text', '' );
$breaking_url  = get_theme_mod( 'vicinity_breaking_url', '' );
if ( $breaking_text ) :
?>
<div class="breaking-banner" role="alert">
	<div class="container">
		<div class="breaking-banner__inner">
			<span class="breaking-banner__label"><?php esc_html_e( 'Breaking', 'vicinity' ); ?></span>
			<span class="breaking-banner__text">
				<?php if ( $breaking_url ) : ?>
					<a href="<?php echo esc_url( $breaking_url ); ?>"><?php echo esc_html( $breaking_text ); ?></a>
				<?php else : echo esc_html( $breaking_text ); endif; ?>
			</span>
			<button class="breaking-banner__close" onclick="this.closest('.breaking-banner').style.display='none'" aria-label="<?php esc_attr_e( 'Close', 'vicinity' ); ?>">&#215;</button>
		</div>
	</div>
</div>
<?php endif; ?>

<header id="site-header" role="banner">

	<!-- 1. Utility bar -->
	<div class="header-utility">
		<div class="container">
			<div class="header-utility__inner">
				<span class="header-utility__date"><?php echo esc_html( date_i18n( 'l, F j, Y' ) ); ?></span>
				<div class="header-utility__right">
					<a href="<?php echo esc_url( home_url( '/newsletters/' ) ); ?>" class="header-utility__link"><?php esc_html_e( 'Newsletters', 'vicinity' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/podcasts/' ) ); ?>" class="header-utility__link"><?php esc_html_e( 'Podcasts', 'vicinity' ); ?></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( admin_url() ); ?>" class="header-utility__link"><?php esc_html_e( 'Dashboard', 'vicinity' ); ?></a>
					<?php else : ?>
						<a href="<?php echo esc_url( home_url( '/subscribe/' ) ); ?>" class="header-utility__subscribe"><?php esc_html_e( 'Subscribe', 'vicinity' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- 2. Nameplate -->
	<div class="header-nameplate">
		<div class="container" style="position:relative;display:flex;align-items:center;justify-content:center;width:100%;">
			<span class="header-nameplate__edition"><?php echo esc_html( get_theme_mod( 'vicinity_footer_tagline', '' ) ); ?></span>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="header-nameplate__brand" rel="home">
				<?php if ( has_custom_logo() ) : the_custom_logo(); else : ?>
					<span class="header-nameplate__name"><?php bloginfo( 'name' ); ?></span>
					<?php $desc = get_bloginfo( 'description' ); if ( $desc ) : ?><span class="header-nameplate__tagline"><?php echo esc_html( $desc ); ?></span><?php endif; ?>
				<?php endif; ?>
			</a>
			<nav class="header-nameplate__social" aria-label="<?php esc_attr_e( 'Social', 'vicinity' ); ?>">
				<a href="https://twitter.com/" rel="noopener noreferrer" aria-label="X / Twitter">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.632L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
				</a>
				<a href="https://facebook.com/" rel="noopener noreferrer" aria-label="Facebook">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
				</a>
				<a href="<?php echo esc_url( home_url( '/feed/' ) ); ?>" aria-label="RSS">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M6.18 15.64a2.18 2.18 0 0 1 2.18 2.18C8.36 19.01 7.38 20 6.18 20C4.98 20 4 19.01 4 17.82a2.18 2.18 0 0 1 2.18-2.18M4 4.44A15.56 15.56 0 0 1 19.56 20h-2.83A12.73 12.73 0 0 0 4 7.27V4.44m0 5.66a9.9 9.9 0 0 1 9.9 9.9h-2.83A7.07 7.07 0 0 0 4 12.93V10.1z"/></svg>
				</a>
			</nav>
		</div>
	</div>

	<!-- 3. Section nav -->
	<nav class="header-section-nav" id="apollo-primary-nav" aria-label="<?php esc_attr_e( 'Main navigation', 'vicinity' ); ?>">
		<div class="container">
			<div class="header-section-nav__inner">
				<button class="header-nav-toggle" id="apollo-nav-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle menu', 'vicinity' ); ?>">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
				</button>
				<?php
				wp_nav_menu( [
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'item_spacing'   => 'discard',
					'walker'         => class_exists( 'Apollo_Nav_Walker' ) ? new Apollo_Nav_Walker() : null,
					'fallback_cb'    => 'vicinity_nav_fallback',
				] );
				?>
				<button class="nav-item nav-search-btn" id="apollo-search-open" aria-label="<?php esc_attr_e( 'Search', 'vicinity' ); ?>">
					<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
				</button>
			</div>
		</div>
	</nav>
</header>

<!-- Search overlay -->
<div class="search-overlay" id="apollo-search-overlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search', 'vicinity' ); ?>">
	<div class="search-overlay__box">
		<div class="search-overlay__input-wrap">
			<input type="search" id="apollo-search-input" class="search-overlay__input"
				placeholder="<?php esc_attr_e( 'Search stories…', 'vicinity' ); ?>"
				autocomplete="off" aria-label="<?php esc_attr_e( 'Search', 'vicinity' ); ?>">
			<button class="search-overlay__close" id="apollo-search-close" aria-label="<?php esc_attr_e( 'Close', 'vicinity' ); ?>">&#215;</button>
		</div>
		<div id="apollo-search-results" role="region" aria-live="polite"></div>
	</div>
</div>
