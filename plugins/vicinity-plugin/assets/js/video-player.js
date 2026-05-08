/**
 * Apollo Video Player — Video.js integration.
 *
 * Initialises any Video.js players that were not auto-initialised via
 * data-setup, wires view-count pings, and applies runtime config from
 * the `apolloVideo` global injected by PHP.
 *
 * Loaded on every front-end page that has a <video class="video-js"> element.
 *
 * @package Apollo
 */

( function () {
	'use strict';

	var cfg = window.apolloVideo || {};

	/**
	 * Fired once per player after it is ready.
	 *
	 * @param {Object} player - Video.js player instance.
	 */
	function onPlayerReady( player ) {
		var postId     = player.el().getAttribute( 'data-post-id' );
		var viewPinged = false;
		var viewDelay  = 10; // seconds of playback before counting a view

		// ── View count ping ───────────────────────────────────────────────

		player.on( 'timeupdate', function () {
			if ( viewPinged ) return;
			if ( player.currentTime() < viewDelay ) return;
			if ( ! postId || ! cfg.ajaxUrl || ! cfg.nonce ) return;

			viewPinged = true;

			var fd = new FormData();
			fd.append( 'action',  'vicinity_video_view' );
			fd.append( 'nonce',   cfg.nonce );
			fd.append( 'post_id', postId );

			fetch( cfg.ajaxUrl, { method: 'POST', body: fd } ).catch( function () {} );
		} );

		// ── Keyboard shortcuts ────────────────────────────────────────────

		player.el().addEventListener( 'keydown', function ( e ) {
			// Don't hijack inputs inside the player (e.g. speed selector).
			if ( e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' ) return;

			switch ( e.key ) {
				case ' ':
				case 'k':
					e.preventDefault();
					player.paused() ? player.play() : player.pause();
					break;
				case 'ArrowRight':
					e.preventDefault();
					player.currentTime( Math.min( player.duration(), player.currentTime() + 5 ) );
					break;
				case 'ArrowLeft':
					e.preventDefault();
					player.currentTime( Math.max( 0, player.currentTime() - 5 ) );
					break;
				case 'ArrowUp':
					e.preventDefault();
					player.volume( Math.min( 1, player.volume() + 0.1 ) );
					break;
				case 'ArrowDown':
					e.preventDefault();
					player.volume( Math.max( 0, player.volume() - 0.1 ) );
					break;
				case 'f':
					e.preventDefault();
					player.isFullscreen() ? player.exitFullscreen() : player.requestFullscreen();
					break;
				case 'm':
					e.preventDefault();
					player.muted( ! player.muted() );
					break;
				case '0': case '1': case '2': case '3': case '4':
				case '5': case '6': case '7': case '8': case '9':
					if ( player.duration() ) {
						player.currentTime( ( parseInt( e.key, 10 ) / 10 ) * player.duration() );
					}
					break;
			}
		} );
	}

	/**
	 * Initialise all .video-js elements on the page.
	 *
	 * Elements with `data-setup` are auto-initialised by Video.js when the
	 * library loads.  We still need to attach our own `onPlayerReady` listener
	 * to every player regardless of how it was created.
	 */
	function initPlayers() {
		if ( typeof videojs === 'undefined' ) return;

		var elements = document.querySelectorAll( '.video-js' );

		elements.forEach( function ( el ) {
			var player;

			// Already initialised by Video.js auto-setup (data-setup attribute).
			if ( videojs.getPlayer( el ) ) {
				player = videojs.getPlayer( el );
			} else {
				// Manual init for elements without data-setup.
				player = videojs( el );
			}

			if ( player.isReady_ ) {
				onPlayerReady( player );
			} else {
				player.ready( function () {
					onPlayerReady( this );
				} );
			}
		} );
	}

	// Run after Video.js CDN script and DOM are both ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initPlayers );
	} else {
		initPlayers();
	}

} )();
