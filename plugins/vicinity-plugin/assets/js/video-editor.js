/**
 * Apollo Video Editor — Gutenberg Sidebar for serve_video
 *
 * Registers a plugin sidebar with panels for:
 *   - R2 / S3 multipart video upload with progress bar
 *   - Thumbnail management (FFmpeg-generated or custom)
 *   - Video meta fields (duration, format, paywall, YouTube ID, preview MP4)
 *
 * Localized data: window.apolloVideoEditor = {
 *   nonceUpload, nonceThumb, r2Ready, s3Ready,
 *   r2PublicUrl, s3PublicUrl, ajaxUrl, postId
 * }
 *
 * @package Apollo
 */

( function ( wp ) {
	'use strict';

	const { registerPlugin }        = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editor;
	const { PanelBody, TextControl, SelectControl, ToggleControl,
	        Button, ProgressBar, Notice, Spinner, Icon } = wp.components;
	const { useState, useEffect, useCallback } = wp.element;
	const { useSelect, useDispatch }           = wp.data;
	const { __ }                               = wp.i18n;

	const cfg = window.apolloVideoEditor || {};

	// ── Constants ─────────────────────────────────────────────────────────
	const CHUNK_SIZE = 10 * 1024 * 1024; // 10 MB — matches PHP constant

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

	// ── Upload engine ─────────────────────────────────────────────────────

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

	async function uploadFile( file, onProgress ) {
		// 1. Init MPU.
		const init = await ajax( cfg.r2Ready ? 'vicinity_video_mpu_init' : 'vicinity_video_mpu_init_s3', {
			nonce:        cfg.nonceUpload,
			filename:     file.name,
			content_type: file.type || 'video/mp4',
			post_id:      cfg.postId,
			file_size:    file.size,
		} );

		const { upload_id, object_key, public_url, presigned_urls, chunk_size, total_parts } = init;
		const realChunk = chunk_size || CHUNK_SIZE;
		const etags     = [];

		// 2. Upload parts.
		for ( let i = 1; i <= total_parts; i++ ) {
			const start = ( i - 1 ) * realChunk;
			const slice = file.slice( start, start + realChunk );
			const presigned = presigned_urls?.[ i ];

			if ( presigned ) {
				// Direct browser → R2.
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
				pfd.append( 'action',     'vicinity_video_mpu_part' );
				pfd.append( 'nonce',      cfg.nonceUpload );
				pfd.append( 'object_key', object_key );
				pfd.append( 'upload_id',  upload_id );
				pfd.append( 'part_num',   i );
				pfd.append( 'chunk',      slice, 'chunk' );
				const pr  = await fetch( cfg.ajaxUrl, { method: 'POST', body: pfd } );
				const pj  = await pr.json();
				if ( ! pj.success ) throw new Error( pj.data );
				etags.push( { part_num: i, etag: pj.data.etag } );
			}

			onProgress( Math.round( ( i / total_parts ) * 90 ) );
		}

		// 3. Complete MPU.
		const complete = await ajax( 'vicinity_video_mpu_complete', {
			nonce:      cfg.nonceUpload,
			object_key: object_key,
			upload_id:  upload_id,
			parts:      JSON.stringify( etags ),
		} );

		onProgress( 100 );
		return { object_key: complete.object_key || object_key, public_url: complete.public_url || public_url };
	}

	// ── Upload Panel ─────────────────────────────────────────────────────

	function UploadPanel( { onUploaded } ) {
		const [ progress, setProgress ] = useState( 0 );
		const [ status,   setStatus   ] = useState( '' );     // '', 'uploading', 'done', 'error'
		const [ error,    setError    ] = useState( '' );
		const [ fileName, setFileName ] = useState( '' );

		async function handleFile( file ) {
			if ( ! file || ! file.type.startsWith( 'video/' ) ) {
				setError( __( 'Please select a valid video file.', 'vicinity' ) );
				setStatus( 'error' );
				return;
			}
			if ( ! cfg.r2Ready && ! cfg.s3Ready ) {
				setError( __( 'No R2 or S3 storage configured. Visit Settings → Apollo.', 'vicinity' ) );
				setStatus( 'error' );
				return;
			}
			setFileName( file.name );
			setStatus( 'uploading' );
			setProgress( 0 );
			setError( '' );
			try {
				const result = await uploadFile( file, setProgress );
				setStatus( 'done' );
				onUploaded( result );
			} catch ( e ) {
				setError( e.message );
				setStatus( 'error' );
			}
		}

		function onDrop( e ) {
			e.preventDefault();
			const file = e.dataTransfer?.files?.[0];
			if ( file ) handleFile( file );
		}
		function onDragOver( e ) { e.preventDefault(); }
		function onInputChange( e ) {
			const file = e.target.files?.[0];
			if ( file ) handleFile( file );
		}

		return wp.element.createElement( 'div', { className: 'apollo-upload-panel' },
			status === 'uploading' && wp.element.createElement( 'div', { className: 'apollo-upload-progress' },
				wp.element.createElement( 'p', null, __( 'Uploading', 'vicinity' ) + ' ' + fileName + '…' ),
				wp.element.createElement( 'progress', { value: progress, max: 100, style: { width: '100%' } } ),
				wp.element.createElement( 'p', { className: 'apollo-upload-pct' }, progress + '%' )
			),
			status === 'done' && wp.element.createElement( Notice, { status: 'success', isDismissible: false },
				__( 'Upload complete.', 'vicinity' )
			),
			status === 'error' && wp.element.createElement( Notice, { status: 'error', isDismissible: true, onRemove: function() { setStatus(''); } },
				error
			),
			status !== 'uploading' && wp.element.createElement( 'div', {
					className: 'apollo-drop-zone',
					onDrop,
					onDragOver,
					style: {
						border:  '2px dashed #ccc',
						padding: '20px',
						textAlign: 'center',
						borderRadius: '4px',
						cursor: 'pointer',
					}
				},
				wp.element.createElement( 'p', null, __( 'Drag a video file here, or', 'vicinity' ) ),
				wp.element.createElement( 'label', {
						style: { display: 'inline-block', marginTop: '8px' },
						className: 'button button-secondary'
					},
					__( 'Choose File', 'vicinity' ),
					wp.element.createElement( 'input', {
						type:     'file',
						accept:   'video/*',
						style:    { display: 'none' },
						onChange: onInputChange,
					} )
				),
				( ! cfg.r2Ready && ! cfg.s3Ready ) && wp.element.createElement( 'p', {
					style: { color: '#c62828', fontSize: '12px', marginTop: '8px' }
				}, __( 'Configure storage in Settings → Apollo to enable uploads.', 'vicinity' ) )
			)
		);
	}

	// ── Thumbnail Panel ───────────────────────────────────────────────────

	function ThumbPanel( { thumbUrl, setThumbUrl } ) {
		const [ generating, setGenerating ] = useState( false );
		const [ error, setError ] = useState( '' );

		async function generateThumb() {
			setGenerating( true );
			setError( '' );
			try {
				const data = await ajax( 'vicinity_video_gen_thumb', {
					nonce:   cfg.nonceThumb || cfg.nonceUpload,
					post_id: cfg.postId,
				} );
				setThumbUrl( data.thumb_url || '' );
			} catch ( e ) {
				setError( e.message );
			}
			setGenerating( false );
		}

		return wp.element.createElement( 'div', { className: 'apollo-thumb-panel' },
			thumbUrl && wp.element.createElement( 'img', {
				src:   thumbUrl,
				alt:   __( 'Video thumbnail', 'vicinity' ),
				style: { width: '100%', marginBottom: '8px', borderRadius: '3px' }
			} ),
			wp.element.createElement( Button, {
				variant:   'secondary',
				onClick:   generateThumb,
				disabled:  generating,
				isBusy:    generating,
				style:     { width: '100%', justifyContent: 'center', marginBottom: '8px' },
			}, generating ? __( 'Generating…', 'vicinity' ) : __( 'Generate Thumbnail (FFmpeg)', 'vicinity' ) ),
			error && wp.element.createElement( Notice, { status: 'error', isDismissible: false }, error ),
			wp.element.createElement( 'p', { style: { fontSize: '11px', color: '#999', margin: 0 } },
				__( 'Extracted from video at 25% mark. Requires FFmpeg on the server.', 'vicinity' )
			)
		);
	}

	// ── Main sidebar component ────────────────────────────────────────────

	function ApolloVideoSidebar() {
		const [ r2Key,       setR2Key       ] = useMeta( '_svh_r2_key' );
		const [ youtubeId,   setYoutubeId   ] = useMeta( '_svh_youtube_id' );
		const [ duration,    setDuration    ] = useMeta( '_svh_duration' );
		const [ format,      setFormat      ] = useMeta( '_svh_format' );
		const [ featured,    setFeatured    ] = useMeta( '_svh_featured' );
		const [ paywall,     setPaywall     ] = useMeta( '_svh_paywall' );
		const [ previewMp4,  setPreviewMp4  ] = useMeta( '_svh_preview_mp4' );
		const [ thumbUrl,    setThumbUrl    ] = useState( '' );

		// Resolve thumbnail from meta on mount.
		useEffect( function () {
			// Could fetch from server, but we rely on the featured image block for now.
		}, [] );

		function onUploaded( result ) {
			setR2Key( result.object_key || '' );
		}

		return wp.element.createElement( PluginSidebar, {
				name:  'apollo-video-sidebar',
				title: __( 'Apollo Video', 'vicinity' ),
				icon:  'video-alt3',
			},
			// ── Upload ────────────────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'Video File', 'vicinity' ), initialOpen: ! r2Key },
				r2Key
					? wp.element.createElement( 'div', null,
						wp.element.createElement( Notice, { status: 'success', isDismissible: false },
							__( 'Uploaded: ', 'vicinity' ) + r2Key.split( '/' ).pop()
						),
						wp.element.createElement( Button, {
							variant: 'link',
							isDestructive: true,
							onClick: function() { if ( confirm( __( 'Remove video file reference?', 'vicinity' ) ) ) setR2Key( '' ); }
						}, __( 'Remove', 'vicinity' ) ),
						wp.element.createElement( 'hr', { style: { margin: '12px 0' } } ),
						wp.element.createElement( 'p', { style: { fontSize: '12px', color: '#666' } }, __( 'Re-upload to replace:', 'vicinity' ) ),
						wp.element.createElement( UploadPanel, { onUploaded } )
					  )
					: wp.element.createElement( UploadPanel, { onUploaded } )
			),

			// ── YouTube fallback ──────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'YouTube Fallback', 'vicinity' ), initialOpen: false },
				wp.element.createElement( TextControl, {
					label:    __( 'YouTube Video ID', 'vicinity' ),
					value:    youtubeId,
					onChange: setYoutubeId,
					help:     __( 'Used if no R2/S3 file is set. Paste the 11-char ID from the YouTube URL.', 'vicinity' ),
				} )
			),

			// ── Thumbnail ─────────────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'Thumbnail', 'vicinity' ), initialOpen: false },
				wp.element.createElement( ThumbPanel, { thumbUrl, setThumbUrl } )
			),

			// ── Meta ──────────────────────────────────────────────────
			wp.element.createElement( PanelBody, { title: __( 'Video Details', 'vicinity' ), initialOpen: true },
				wp.element.createElement( SelectControl, {
					label:    __( 'Format', 'vicinity' ),
					value:    format,
					onChange: setFormat,
					options:  [
						{ value: 'short',       label: __( 'Short (≤ 60s / 9:16)', 'vicinity' ) },
						{ value: 'standard',    label: __( 'Standard', 'vicinity' ) },
						{ value: 'long-form',   label: __( 'Long-form / Documentary', 'vicinity' ) },
						{ value: 'livestream',  label: __( 'Livestream Recording', 'vicinity' ) },
					],
				} ),
				wp.element.createElement( TextControl, {
					label:    __( 'Duration', 'vicinity' ),
					value:    duration,
					onChange: setDuration,
					help:     __( 'Human-readable, e.g. "4:32" or "1h 12m". Auto-filled by FFmpeg if thumbnail generated.', 'vicinity' ),
				} ),
				wp.element.createElement( TextControl, {
					label:    __( 'Preview MP4 URL', 'vicinity' ),
					value:    previewMp4,
					onChange: setPreviewMp4,
					help:     __( 'Short muted clip for hover preview on video cards. Optional.', 'vicinity' ),
				} ),
				wp.element.createElement( ToggleControl, {
					label:    __( 'Featured Video', 'vicinity' ),
					checked:  !! featured,
					onChange: function( v ) { setFeatured( v ? '1' : '' ); },
					help:     __( 'Pin to the Hero slot on the Video Hub.', 'vicinity' ),
				} ),
				wp.element.createElement( ToggleControl, {
					label:    __( 'Members Only (Paywall)', 'vicinity' ),
					checked:  !! paywall,
					onChange: function( v ) { setPaywall( v ? '1' : '' ); },
					help:     __( 'Restrict playback to logged-in members. Requires paywall to be enabled in Apollo Settings.', 'vicinity' ),
				} )
			)
		);
	}

	// ── Register ──────────────────────────────────────────────────────────

	registerPlugin( 'apollo-video-sidebar', {
		render: ApolloVideoSidebar,
		icon:   'video-alt3',
	} );

} )( window.wp );
