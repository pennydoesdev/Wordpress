/**
 * Vicinity Theme — Customizer Live Preview (postMessage bindings)
 *
 * Each setting with transport:'postMessage' needs a binding here that
 * mutates the live preview iframe without a full page reload.
 *
 * Pattern:
 *   wp.customize( 'setting_id', function( value ) {
 *       value.bind( function( newVal ) { … mutate DOM … } );
 *   } );
 *
 * @package Apollo (Vicinity)
 */

( function () {
	'use strict';

	var cz = wp.customize;

	// ── Utility: set a CSS custom property on :root ───────────────────────
	function cssVar( prop, val ) {
		document.documentElement.style.setProperty( prop, val );
	}

	// ── Utility: set text content on all matching elements ────────────────
	function setText( selector, text ) {
		document.querySelectorAll( selector ).forEach( function ( el ) {
			el.textContent = text;
		} );
	}

	// ── Utility: toggle a class on all matching elements ──────────────────
	function toggleClass( selector, cls, force ) {
		document.querySelectorAll( selector ).forEach( function ( el ) {
			el.classList.toggle( cls, force );
		} );
	}

	// ════════════════════════════════════════════════════════════════════════
	// COLORS
	// ════════════════════════════════════════════════════════════════════════

	var colorBindings = {
		'vicinity_accent_color':       '--color-accent',
		'vicinity_color_secondary':  '--color-secondary',
		'vicinity_color_text':       '--color-text',
		'vicinity_color_text_muted': '--color-text-muted',
		'vicinity_color_bg':         '--color-bg',
		'vicinity_color_surface':    '--color-surface',
		'vicinity_color_border':     '--color-border',
		'vicinity_color_rule_strong':'--color-rule-strong',
		'vicinity_color_breaking':   '--color-breaking',
		'vicinity_color_headline':   '--color-headline',
		'vicinity_color_link':       '--color-link',
		'vicinity_color_footer_bg':  '--color-footer-bg',
	};

	Object.keys( colorBindings ).forEach( function ( setting ) {
		cz( setting, function ( value ) {
			value.bind( function ( newVal ) {
				cssVar( colorBindings[ setting ], newVal );
				// Keep VJS in sync with accent colour.
				if ( setting === 'vicinity_accent_color' || setting === 'videojs_accent_color' ) {
					document.querySelectorAll(
						'.video-js .vjs-play-progress, .video-js .vjs-volume-level'
					).forEach( function ( el ) {
						el.style.backgroundColor = newVal;
					} );
				}
			} );
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// TYPOGRAPHY
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_font_headline', function ( value ) {
		value.bind( function ( to ) { cssVar( '--font-headline', to ); } );
	} );

	cz( 'vicinity_font_body', function ( value ) {
		value.bind( function ( to ) { cssVar( '--font-body', to ); } );
	} );

	cz( 'vicinity_font_ui', function ( value ) {
		value.bind( function ( to ) { cssVar( '--font-ui', to ); } );
	} );

	cz( 'vicinity_base_font_size', function ( value ) {
		value.bind( function ( to ) { cssVar( '--font-size-base', to + 'px' ); } );
	} );

	cz( 'vicinity_body_line_height', function ( value ) {
		value.bind( function ( to ) { cssVar( '--line-height-body', to ); } );
	} );

	cz( 'vicinity_content_width', function ( value ) {
		value.bind( function ( to ) { cssVar( '--content-width', to + 'px' ); } );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// HEADER
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_header_bg', function ( value ) {
		value.bind( function ( to ) {
			cssVar( '--header-bg', to );
			document.querySelectorAll( '.site-header' ).forEach( function ( el ) {
				el.style.backgroundColor = to;
			} );
		} );
	} );

	cz( 'vicinity_header_sticky', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.site-header', 'is-sticky', !! to );
		} );
	} );

	cz( 'vicinity_header_show_search', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.header-search-btn, .search-toggle', 'hidden', ! to );
		} );
	} );

	cz( 'vicinity_header_show_social', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.header-social, .site-header .social-links', 'hidden', ! to );
		} );
	} );

	cz( 'vicinity_header_show_date', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.header-topbar__date, .site-date', 'hidden', ! to );
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// HOMEPAGE / BREAKING
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_breaking_text', function ( value ) {
		value.bind( function ( to ) {
			var bar = document.querySelector( '.breaking-banner, .ticker-bar' );
			if ( bar ) {
				bar.style.display = to ? '' : 'none';
				var textEl = bar.querySelector(
					'.breaking-banner__text, .ticker-text, .ticker-message'
				);
				if ( textEl ) textEl.textContent = to;
			}
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// ARTICLE PAGE
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_article_sidebar', function ( value ) {
		value.bind( function ( to ) {
			var sidebar = document.querySelector( '.article-sidebar, .single-sidebar' );
			if ( sidebar ) sidebar.style.display = to ? '' : 'none';
		} );
	} );

	cz( 'vicinity_article_reading_time', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.reading-time, .article-reading-time', 'hidden', ! to );
		} );
	} );

	cz( 'vicinity_article_show_tags', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.article-tags, .post-tags', 'hidden', ! to );
		} );
	} );

	cz( 'vicinity_article_share_buttons', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.article-share, .share-buttons', 'hidden', ! to );
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// FOOTER
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_footer_tagline', function ( value ) {
		value.bind( function ( to ) {
			setText( '.footer-brand__tagline, .footer-tagline', to );
		} );
	} );

	cz( 'vicinity_footer_copyright', function ( value ) {
		value.bind( function ( to ) {
			setText( '.footer-copyright__text, .site-copyright', to );
		} );
	} );

	cz( 'vicinity_footer_show_social', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.footer-social, .site-footer .social-links', 'hidden', ! to );
		} );
	} );

	cz( 'vicinity_footer_show_logo', function ( value ) {
		value.bind( function ( to ) {
			toggleClass( '.footer-brand__logo', 'hidden', ! to );
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// BRANDING — logo swap (postMessage on media control)
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_logo_id', function ( value ) {
		value.bind( function () {
			// Logo changes require a refresh since we need the attachment URL.
			// Trigger selective refresh instead of full reload where possible.
			if ( wp.customize.selectiveRefresh ) {
				wp.customize.selectiveRefresh.requestFullRefresh();
			}
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// SITE TITLE / TAGLINE (built-in WP settings — wire to header)
	// ════════════════════════════════════════════════════════════════════════

	cz( 'blogname', function ( value ) {
		value.bind( function ( to ) {
			setText( '.site-title a, .header-logo__text', to );
		} );
	} );

	cz( 'blogdescription', function ( value ) {
		value.bind( function ( to ) {
			setText( '.site-description, .header-tagline', to );
		} );
	} );

	// ════════════════════════════════════════════════════════════════════════
	// VICINITY SITE TAGLINE (below logo)
	// ════════════════════════════════════════════════════════════════════════

	cz( 'vicinity_site_tagline', function ( value ) {
		value.bind( function ( to ) {
			setText( '.header-logo__tagline', to );
		} );
	} );

} )();