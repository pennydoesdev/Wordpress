/**
 * Apollo Rewriter — Gutenberg Document Sidebar for AI-powered text rewriting
 *
 * Provides text rewrite modes:
 *   - rewrite, shorten, expand, simplify
 *   - headline, lede, tone adjustments
 *   - custom prompt
 *
 * Selection via stripTags() helper from richtext blocks
 * Result textarea with copy + apply-to-block buttons
 *
 * Config injected by PHP as window.apolloRewriter:
 *   nonce, ajaxUrl, provider, model
 *
 * @package Apollo
 */

( function ( wp ) {
	'use strict';

	const { registerPlugin }                = wp.plugins;
	const { PluginDocumentSettingPanel }    = wp.editPost;
	const { PanelBody, Button, Notice,
	        TextControl, TextareaControl,
	        SelectControl, Spinner }        = wp.components;
	const { useState, useCallback, useEffect } = wp.element;
	const { useSelect, useDispatch }        = wp.data;
	const { __ }                            = wp.i18n;

	const cfg = window.apolloRewriter || {};

	// ── Hook: get text from selected block ────────────────────────────────

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

	// Strip HTML tags from text.
	function stripTags( html ) {
		const tmp = document.createElement( 'DIV' );
		tmp.innerHTML = html;
		return tmp.textContent || tmp.innerText || '';
	}

	// Get selected block's text content.
	function getSelectedBlockText() {
		const select = wp.data.select( 'core/block-editor' );
		const block = select?.getSelectedBlock();
		if ( ! block ) return '';
		return stripTags( block.attributes?.content || '' );
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

	// ── Rewriter Panel Component ──────────────────────────────────────────

	function RewriterPanel() {
		const [ mode,      setMode      ] = useState( 'rewrite' );
		const [ custom,    setCustom    ] = useState( '' );
		const [ loading,   setLoading   ] = useState( false );
		const [ error,     setError     ] = useState( '' );
		const [ result,    setResult    ] = useState( '' );
		const [ selectedText, setSelectedText ] = useState( '' );

		// Update selected text when selection changes.
		useEffect( function () {
			setSelectedText( getSelectedBlockText() );
		}, [ wp.data.select( 'core/block-editor' )?.getSelectedBlockClientId?.() ] );

		const rewriteModes = [
			{ value: 'rewrite',   label: __( 'Rewrite', 'vicinity' ) },
			{ value: 'shorten',   label: __( 'Shorten', 'vicinity' ) },
			{ value: 'expand',    label: __( 'Expand', 'vicinity' ) },
			{ value: 'simplify',  label: __( 'Simplify', 'vicinity' ) },
			{ value: 'headline',  label: __( 'Generate Headline', 'vicinity' ) },
			{ value: 'lede',      label: __( 'Write Lede', 'vicinity' ) },
			{ value: 'casual',    label: __( 'Casual Tone', 'vicinity' ) },
			{ value: 'formal',    label: __( 'Formal Tone', 'vicinity' ) },
			{ value: 'custom',    label: __( 'Custom Prompt', 'vicinity' ) },
		];

		const handleRewrite = useCallback( async function () {
			if ( ! selectedText ) {
				setError( __( 'No text selected. Select text in a block first.', 'vicinity' ) );
				return;
			}

			setLoading( true );
			setError( '' );
			setResult( '' );

			try {
				const data = await ajax( 'vicinity_ai_rewrite', {
					nonce:   cfg.nonce,
					mode:    mode,
					text:    selectedText,
					custom:  custom,
				} );

				setResult( data.result || '' );
			} catch ( e ) {
				setError( e.message );
			} finally {
				setLoading( false );
			}
		}, [ selectedText, mode, custom ] );

		const copyToClipboard = useCallback( async function () {
			if ( ! result ) return;
			try {
				await navigator.clipboard.writeText( result );
				setError( '' ); // Clear errors.
			} catch ( e ) {
				setError( __( 'Failed to copy to clipboard.', 'vicinity' ) );
			}
		}, [ result ] );

		const applyToBlock = useCallback( function () {
			if ( ! result ) return;
			const select = wp.data.select( 'core/block-editor' );
			const dispatch = wp.data.dispatch( 'core/block-editor' );
			const block = select?.getSelectedBlock();

			if ( ! block ) {
				setError( __( 'No block selected.', 'vicinity' ) );
				return;
			}

			dispatch.updateBlockAttributes( block.clientId, {
				content: result,
			} );

			setResult( '' );
		}, [ result ] );

		return wp.element.createElement( 'div', { style: { padding: '12px' } },
			wp.element.createElement( 'div', { style: { marginBottom: 12 } },
				wp.element.createElement( SelectControl, {
					label:    __( 'Rewrite Mode', 'vicinity' ),
					value:    mode,
					onChange: setMode,
					options:  rewriteModes,
				} ),

				mode === 'custom' && wp.element.createElement( TextareaControl, {
					label:       __( 'Custom Prompt', 'vicinity' ),
					value:       custom,
					onChange:    setCustom,
					placeholder: __( 'Enter your custom rewrite instructions…', 'vicinity' ),
					rows:        4,
				} )
			),

			selectedText
				? wp.element.createElement( 'div', { style: { marginBottom: 12, padding: '8px', background: '#f5f5f5', borderRadius: 3 } },
					wp.element.createElement( 'p', { style: { fontSize: 12, color: '#666', margin: '0 0 4px' } },
						__( 'Selected text:', 'vicinity' )
					),
					wp.element.createElement( 'p', { style: { fontSize: 13, margin: 0, maxHeight: 80, overflow: 'auto' } },
						selectedText
					)
				)
				: wp.element.createElement( Notice, { status: 'warning', isDismissible: false },
					__( 'Select text in a block to rewrite.', 'vicinity' )
				),

			wp.element.createElement( Button, {
				variant:  'primary',
				onClick:  handleRewrite,
				isBusy:   loading,
				disabled: loading || ! selectedText,
				style:    { width: '100%', marginBottom: 12 },
			}, loading
				? wp.element.createElement( wp.element.Fragment, null,
					wp.element.createElement( Spinner ),
					' ' + __( 'Rewriting…', 'vicinity' )
				)
				: __( 'Rewrite with AI', 'vicinity' )
			),

			result && wp.element.createElement( 'div', { style: { marginBottom: 12 } },
				wp.element.createElement( Notice, { status: 'success', isDismissible: false },
					__( 'Rewrite complete. Review and apply.', 'vicinity' )
				),
				wp.element.createElement( TextareaControl, {
					label:    __( 'Result', 'vicinity' ),
					value:    result,
					onChange: setResult,
					rows:     6,
				} ),
				wp.element.createElement( 'div', { style: { display: 'flex', gap: 8 } },
					wp.element.createElement( Button, {
						variant: 'primary',
						onClick: applyToBlock,
					}, __( 'Apply to Block', 'vicinity' ) ),
					wp.element.createElement( Button, {
						variant: 'secondary',
						onClick: copyToClipboard,
					}, __( 'Copy to Clipboard', 'vicinity' ) )
				)
			),

			error && wp.element.createElement( Notice, { status: 'error', isDismissible: true, onRemove: function() { setError(''); } },
				error
			),

			wp.element.createElement( 'hr', { style: { margin: '12px 0', borderColor: '#eee' } } ),

			wp.element.createElement( 'p', { style: { fontSize: 11, color: '#999', margin: 0 } },
				__( 'Provider: ', 'vicinity' ) + ( cfg.provider || 'OpenAI' ) + ' • ' +
				__( 'Model: ', 'vicinity' ) + ( cfg.model || 'gpt-4' )
			)
		);
	}

	// ── Register ──────────────────────────────────────────────────────────

	registerPlugin( 'apollo-rewriter', {
		render: function () {
			return wp.element.createElement( PluginDocumentSettingPanel, {
				name:  'apollo-rewriter-panel',
				title: __( 'AI Rewriter', 'vicinity' ),
				className: 'apollo-rewriter-panel',
			},
				wp.element.createElement( RewriterPanel )
			);
		},
	} );

} )( window.wp );
