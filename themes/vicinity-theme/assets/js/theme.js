/**
 * Apollo Theme — theme.js
 *
 * Handles:
 *   - Mobile nav toggle
 *   - Search overlay (open / close / AJAX live results)
 *   - Sticky header shadow on scroll
 *
 * Zero dependencies. ES2015+.
 *
 * @package Apollo
 */

( function () {
	'use strict';

	// ── Mobile nav ────────────────────────────────────────────────────────

	const navToggle = document.getElementById( 'apollo-nav-toggle' );
	const primaryNav = document.getElementById( 'apollo-primary-nav' );

	if ( navToggle && primaryNav ) {
		navToggle.addEventListener( 'click', function () {
			const expanded = navToggle.getAttribute( 'aria-expanded' ) === 'true';
			navToggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			primaryNav.classList.toggle( 'header-nav--open', ! expanded );
		} );
	}

	// ── Search overlay ────────────────────────────────────────────────────

	const searchOverlay = document.getElementById( 'apollo-search-overlay' );
	const searchOpen    = document.getElementById( 'apollo-search-open' );
	const searchClose   = document.getElementById( 'apollo-search-close' );
	const searchInput   = document.getElementById( 'apollo-search-input' );
	const searchResults = document.getElementById( 'apollo-search-results' );

	function openSearch() {
		if ( ! searchOverlay ) return;
		searchOverlay.classList.add( 'search-overlay--open' );
		if ( searchInput ) {
			searchInput.value = '';
			searchInput.focus();
		}
		document.body.style.overflow = 'hidden';
	}

	function closeSearch() {
		if ( ! searchOverlay ) return;
		searchOverlay.classList.remove( 'search-overlay--open' );
		document.body.style.overflow = '';
		if ( searchResults ) searchResults.innerHTML = '';
	}

	if ( searchOpen )  searchOpen.addEventListener(  'click', openSearch );
	if ( searchClose ) searchClose.addEventListener( 'click', closeSearch );

	// Close on backdrop click.
	if ( searchOverlay ) {
		searchOverlay.addEventListener( 'click', function ( e ) {
			if ( e.target === searchOverlay ) closeSearch();
		} );
	}

	// Escape key.
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) closeSearch();
	} );

	// ── Live search ───────────────────────────────────────────────────────

	var liveDebounce;
	var lastQuery = '';
	var cfg = window.apolloTheme || {};

	function renderResults( results ) {
		if ( ! searchResults ) return;
		if ( ! results || ! results.length ) {
			searchResults.innerHTML = '<p style="color:rgba(255,255,255,.5);margin-top:20px;font-size:14px;">No results found.</p>';
			return;
		}

		var html = '<ul style="list-style:none;margin-top:24px;">';
		results.forEach( function ( r ) {
			var typeLabel = r.type === 'serve_video' ? 'Video'
				: r.type === 'serve_episode' ? 'Podcast'
				: 'Story';
			html += '<li style="border-bottom:1px solid rgba(255,255,255,.08);padding:12px 0;">';
			html += '<a href="' + escHtml( r.url ) + '" style="display:flex;align-items:center;gap:16px;color:#fff;text-decoration:none;">';
			if ( r.thumb ) {
				html += '<img src="' + escHtml( r.thumb ) + '" alt="" style="width:60px;height:40px;object-fit:cover;border-radius:2px;flex-shrink:0;" loading="lazy">';
			}
			html += '<div>';
			html += '<span style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);">' + escHtml( typeLabel ) + '</span>';
			html += '<p style="font-family:var(--font-serif);font-size:16px;font-weight:700;line-height:1.2;margin-top:4px;">' + escHtml( r.title ) + '</p>';
			html += '</div></a></li>';
		} );
		html += '</ul>';
		searchResults.innerHTML = html;
	}

	function liveSearch( query ) {
		if ( ! cfg.ajaxUrl || ! cfg.nonce ) return;
		if ( query.length < 2 ) {
			if ( searchResults ) searchResults.innerHTML = '';
			return;
		}
		if ( query === lastQuery ) return;
		lastQuery = query;

		var fd = new FormData();
		fd.append( 'action', 'vicinity_search' );
		fd.append( 'nonce',  cfg.nonce );
		fd.append( 's',      query );

		fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				if ( d.success ) renderResults( d.data.results );
			} )
			.catch( function () {} );
	}

	if ( searchInput ) {
		searchInput.addEventListener( 'input', function () {
			clearTimeout( liveDebounce );
			var q = searchInput.value.trim();
			liveDebounce = setTimeout( function () { liveSearch( q ); }, 280 );
		} );
	}

	// ── Sticky header shadow ──────────────────────────────────────────────

	var siteHeader = document.getElementById( 'site-header' );
	if ( siteHeader ) {
		var lastScroll = 0;
		window.addEventListener( 'scroll', function () {
			var y = window.scrollY;
			siteHeader.classList.toggle( 'is-scrolled', y > 10 );
			lastScroll = y;
		}, { passive: true } );
	}

	// ── Escape helper ─────────────────────────────────────────────────────

	function escHtml( s ) {
		return String( s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

} )();
