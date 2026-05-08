/**
 * Apollo Audio Editor — Gutenberg Sidebar for serve_episode
 *
 * Registers a plugin sidebar with panels for:
 *   - R2 multipart audio upload with progress bar
 *   - Episode meta fields (season, episode#, duration, type, explicit, podcast ID)
 *
 * Localized data: window.apolloAudioEditor = {
 *   nonceUpload, r2Ready, r2PublicUrl, ajaxUrl, postId
 * }
 *
 * @package Apollo
 */

( function ( wp ) {
	'use strict';

	const { registerPlugin }                           = wp.plugins;
	const { PluginSidebar }                            = wp.editor;
	const { PanelBody, TextControl, SelectControl,
	        ToggleControl, Button, Notice }             = wp.components;
	const { useState, useCallback }                    = wp.element;
	const { useSelect, useDispatch }                   = wp.data;
	const { __ }                                       = wp.i18n;

	const cfg = window.apolloAudioEditor || {};

	// ── Chunk size: must match PHP constant VICINITY_AUDIO_CHUNK_SIZE ──────
	const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

	// ── Hook: post meta ──────────────────────────────────────────────────

	function useMeta( key ) {
		const value = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' )?.[ key ] ?? '';
		}, [ key ] );

		const { editPost } = useDispatch( 'core/editor' );

		const set = useCallback( function ( v ) {
			editPost( { meta: { [ key ]: v } } );
		}, [ key, editPost ] );

		return [ value, set ];
	}

	// ── AJAX helper ──────────────────────────────────────────────────────

	async function ajax( action, data ) {
		const fd = new FormData();
		fd.append( 'action', action );
		Object.entries( data ).forEach( ( [ k, v ] ) => fd.append( k, v ) );
		const r = await fetch( cfg.ajaxUrl, { method: 'POST', body: fd } );
		if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
		const j = await r.json();
		if ( ! j.success ) throw new Error( j.data || 'Server error' );
		return j.data;
	}

	// ── Upload engine ─────────────────────────────────────────────────────

	async function uploadAudioFile( file, onProgress ) {
		// 1. Init.
		const init = await ajax( 'vicinity_audio_mpu_init', {
			nonce:        cfg.nonceUpload,
			filename:     file.name,
			content_type: file.type || 'audio/mpeg',
			post_id:      cfg.postId,
			file_size:    file.size,
		} );

		const { upload_id, object_key, public_url, presigned_urls, chunk_size, total_parts } = init;
		const realChunk = chunk_size || CHUNK_SIZE;
		const etags     = [];

		// 2. Upload parts.
		for ( let i = 1; i <= total_parts; i++ ) {
			const start     = ( i - 1 ) * realChunk;
			const slice     = file.slice( start, start + realChunk );
			const presigned = presigned_urls?.[ i ];

			if ( presigned ) {
				const resp = await fetch( presigned, {
					method:  'PUT',
					body:    slice,
					headers: { 'Content-Type': 'application/octet-stream' },
				} );
				if ( ! resp.ok ) throw new Error( 'Part ' + i + ' failed: HTTP ' + resp.status );
				const etag = resp.headers.get( 'ETag' )?.replace( /"/g, '' );
				if ( ! etag ) throw new Error( 'No ETag for part ' + i + '. Check R2 CORS ExposeHeaders.' );
				etags.push( { part_num: i, etag } );
			} else {
				// PHP proxy fallback.
				const pfd = new FormData();
				pfd.append( 'action',     'vicinity_audio_mpu_part' );
				pfd.append( 'nonce',      cfg.nonceUpload );
				pfd.append( 'object_key', object_key );
				pfd.append( 'upload_id',  upload_id );
				pfd.append( 'part_num',   i );
				pfd.append( 'chunk',      slice, 'chunk' );
				const pr = await fetch( cfg.ajaxUrl, { method: 'POST', body: pfd } );
				const pj = await pr.json();
				if ( ! pj.success ) throw new Error( pj.data );
				etags.push( { part_num: i, etag: pj.data.etag } );
			}

			onProgress( Math.round( ( i / total_parts ) * 90 ) );
		}

		// 3. Complete.
		const complete = await ajax( 'vicinity_audio_mpu_complete', {
			nonce:      cfg.nonceUpload,
			object_key: object_key,
			upload_id:  upload_id,
			parts:      JSON.stringify( etags ),
		} );

		onProgress( 100 );
		return {
			object_key: complete.object_key || object_key,
			public_url: complete.public_url || public_url,
		};
	}

	// ── Upload Panel ─────────────────────────────────────────────────────

	function AudioUploadPanel( { onUploaded } ) {
		const [ progress, setProgress ] = useState( 0 );
		const [ status,   setStatus   ] = useState( '' );
		const [ error,    setError    ] = useState( '' );
		const [ fileName, setFileName ] = useState( '' );

		async function handleFile( file ) {
			if ( ! file ) return;
			const allowed = [ 'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/ogg',
			                  'audio/wav', 'audio/flac', 'audio/aac', 'audio/x-m4a' ];
			if ( ! allowed.some( function( t ) { return file.type.startsWith( 'audio/' ); } ) ) {
				setError( __( 'Please select a valid audio file (MP3, M4A, OGG, WAV, FLAC, AAC).', 'vicinity' ) );
				setStatus( 'error' );
				return;
			}
			if ( ! cfg.r2Ready ) {
				setError( __( 'R2 audio storage is not configured. Visit Settings → Apollo.', 'vicinity' ) );
				setStatus( 'error' );
				return;
			}
			setFileName( file.name );
			setStatus( 'uploading' );
			setProgress( 0 );
			setError( '' );
			try {
				const result = await uploadAudioFile( file, setProgress );
				setStatus( 'done' );
				onUploaded( result );
			} catch ( e ) {
				setError( e.message );
				setStatus( 'error' );
			}
		}

		function onDrop( e ) {
			e.preventDefault();
			handleFile( e.dataTransfer?.files?.[0] );
		}
		function onInputChange( e ) {
			handleFile( e.target.files?.[0] );
		}

		return wp.element.createElement( 'div', { className: 'apollo-audio-upload' },
			status === 'uploading' && wp.element.createElement( 'div', null,
				wp.element.createElement( 'p', null, __( 'Uploading', 'vicinity' ) + ' ' + fileName + '…' ),
				wp.element.createElement( 'progress', { value: progress, max: 100, style: { width: '100%' } } ),
				wp.element.createElement( 'p', { style: { textAlign: 'right', fontSize: '12px' } }, progress + '%' )
			),
			status === 'done' && wp.element.createElement( Notice, { status: 'success', isDismissible: false },
				__( 'Audio uploaded successfully.', 'vicinity' )
			),
			status === 'error' && wp.element.createElement( Notice, {
				status: 'error', isDismissible: true, onRemove: function() { setStatus(''); }
			}, error ),
			status !== 'uploading' && wp.element.createElement( 'div', {
					style: {
						border:       '2px dashed #ccc',
						padding:      '20px',
						textAlign:    'center',
						borderRadius: '4px',
					},
					onDrop:     onDrop,
					onDragOver: function( e ) { e.preventDefault(); },
				},
				wp.element.createElement( 'p', null, __( 'Drag an audio file here, or', 'vicinity' ) ),
				wp.element.createElement( 'label', {
						style:     { display: 'inline-block', marginTop: '8px' },
						className: 'button button-secondary',
					},
					__( 'Choose File', 'vicinity' ),
					wp.element.createElement( 'input', {
						type:     'file',
						accept:   'audio/*',
						style:    { display: 'none' },
						onChange: onInputChange,
					} )
				),
				! cfg.r2Ready && wp.element.createElement( 'p', {
					style: { color: '#c62828', fontSize: '12px', marginTop: '8px' }
				}, __( 'Configure R2 storage in Settings → Apollo to enable uploads.', 'vicinity' ) )
			)
		);
	}

	// ── Episode Sidebar ───────────────────────────────────────────────────

	function ApolloEpisodeSidebar() {
		const [ r2Key,      setR2Key      ] = useMeta( '_ep_r2_key' );
		const [ duration,   setDuration   ] = useMeta( '_ep_duration' );
		const [ fileSize,   setFileSize   ] = useMeta( '_ep_file_size' );
		const [ season,     setSeason     ] = useMeta( '_ep_season' );
		const [ episode,    setEpisode    ] = useMeta( '_ep_episode' );
		const [ epType,     setEpType     ] = useMeta( '_ep_episode_type' );
		const [ explicit,   setExplicit   ] = useMeta( '_ep_explicit' );
		const [ podcastId,  setPodcastId  ] = useMeta( '_ep_podcast_id' );

		function onUploaded( result ) {
			setR2Key( result.object_key || '' );
		}

		// Format file size for display.
		function fmtSize( bytes ) {
			const b = parseInt( bytes ) || 0;
			if ( ! b ) return '';
			if ( b < 1024 * 1024 ) return ( b / 1024 ).toFixed(1) + ' KB';
			return ( b / ( 1024 * 1024 ) ).toFixed(1) + ' MB';
		}

		return wp.element.createElement( PluginSidebar, {
				name:  'apollo-episode-sidebar',
				title: __( 'Apollo Episode', 'vicinity' ),
				icon:  'microphone',
			},

			// ── Audio File ────────────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'Audio File', 'vicinity' ), initialOpen: ! r2Key },
				r2Key
					? wp.element.createElement( 'div', null,
						wp.element.createElement( Notice, { status: 'success', isDismissible: false },
							__( 'Uploaded: ', 'vicinity' ) + r2Key.split( '/' ).pop()
						),
						cfg.r2PublicUrl && wp.element.createElement( 'p', { style: { fontSize: '12px', wordBreak: 'break-all', color: '#555' } },
							cfg.r2PublicUrl + '/' + r2Key
						),
						wp.element.createElement( Button, {
							variant: 'link',
							isDestructive: true,
							style:   { marginBottom: '12px' },
							onClick: function() {
								if ( confirm( __( 'Remove audio file reference?', 'vicinity' ) ) ) {
									setR2Key( '' );
									setFileSize( '' );
								}
							}
						}, __( 'Remove', 'vicinity' ) ),
						wp.element.createElement( 'p', { style: { fontSize: '12px', color: '#888' } },
							__( 'Re-upload to replace:', 'vicinity' )
						),
						wp.element.createElement( AudioUploadPanel, { onUploaded } )
					  )
					: wp.element.createElement( AudioUploadPanel, { onUploaded } )
			),

			// ── Technical Details ─────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'File Details', 'vicinity' ), initialOpen: false },
				wp.element.createElement( TextControl, {
					label:    __( 'Duration', 'vicinity' ),
					value:    duration,
					onChange: setDuration,
					help:     __( 'e.g. "42:30" or "1:12:00". Shown in RSS feed and player.', 'vicinity' ),
				} ),
				wp.element.createElement( TextControl, {
					label:    __( 'File Size (bytes)', 'vicinity' ),
					value:    fileSize,
					onChange: setFileSize,
					help:     fileSize ? fmtSize( fileSize ) : __( 'Auto-populated on upload.', 'vicinity' ),
					type:     'number',
				} )
			),

			// ── Episode Info ──────────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'Episode Info', 'vicinity' ), initialOpen: true },
				wp.element.createElement( TextControl, {
					label:    __( 'Season Number', 'vicinity' ),
					value:    season,
					onChange: setSeason,
					type:     'number',
				} ),
				wp.element.createElement( TextControl, {
					label:    __( 'Episode Number', 'vicinity' ),
					value:    episode,
					onChange: setEpisode,
					type:     'number',
				} ),
				wp.element.createElement( SelectControl, {
					label:    __( 'Episode Type', 'vicinity' ),
					value:    epType || 'full',
					onChange: setEpType,
					options:  [
						{ value: 'full',    label: __( 'Full', 'vicinity' ) },
						{ value: 'trailer', label: __( 'Trailer', 'vicinity' ) },
						{ value: 'bonus',   label: __( 'Bonus', 'vicinity' ) },
					],
				} ),
				wp.element.createElement( SelectControl, {
					label:    __( 'Explicit Content', 'vicinity' ),
					value:    explicit || 'no',
					onChange: setExplicit,
					options:  [
						{ value: 'no',  label: __( 'Clean', 'vicinity' ) },
						{ value: 'yes', label: __( 'Explicit', 'vicinity' ) },
					],
				} ),
				wp.element.createElement( TextControl, {
					label:    __( 'Podcast ID', 'vicinity' ),
					value:    podcastId,
					onChange: setPodcastId,
					help:     __( 'ID of the parent serve_podcast post. Required for RSS feed inclusion.', 'vicinity' ),
					type:     'number',
				} )
			)
		);
	}

	// ── Register ──────────────────────────────────────────────────────────

	registerPlugin( 'apollo-episode-sidebar', {
		render: ApolloEpisodeSidebar,
		icon:   'microphone',
	} );

} )( window.wp );