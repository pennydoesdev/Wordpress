/**
 * Apollo Editorial Flow — Gutenberg integration.
 *
 * Intercepts the Publish button, runs an AI review, shows a checklist modal,
 * and only allows publish once the editor has acknowledged all failed checks.
 *
 * Config injected by PHP as window.apolloEditorial:
 *   nonce, ajaxUrl, passThreshold, requireReview, postType
 *
 * @package Apollo
 */

( function ( wp, apolloEditorial ) {
	'use strict';

	if ( ! wp || ! apolloEditorial ) return;

	const { registerPlugin }           = wp.plugins;
	const { PluginPrePublishPanel }    = wp.editPost;
	const { createElement: el, useState, useCallback, useEffect, Fragment } = wp.element;
	const {
		Button,
		Spinner,
		Modal,
		Notice,
		CheckboxControl,
		TextareaControl,
		PanelBody,
		PanelRow,
	} = wp.components;
	const { useSelect, useDispatch }   = wp.data;
	const { subscribe }                = wp.data;

	const NONCE     = apolloEditorial.nonce          || '';
	const AJAX_URL  = apolloEditorial.ajaxUrl        || '';
	const THRESHOLD = apolloEditorial.passThreshold  || 80;
	const REQUIRED  = apolloEditorial.requireReview  || false;

	// ═══════════════════════════════════════════════════════════════════
	// STATE — shared between the Pre-Publish panel and the publish guard
	// ═══════════════════════════════════════════════════════════════════

	// Module-level state object so the subscribe listener can read it.
	var reviewState = {
		result:     null,   // { passed, score, checks[] }
		overrides:  {},     // { checkLabel: reason }
		reviewed:   false,  // review has been run
		allClear:   false,  // all checks passed or acknowledged
	};

	// ═══════════════════════════════════════════════════════════════════
	// PUBLISH GUARD
	// ═══════════════════════════════════════════════════════════════════

	// We intercept the post status change to "publish" via a lockPostSaving lock.
	// The lock is released only when allClear is true.

	var SAVE_LOCK = 'apollo-editorial-lock';

	// Snapshot of the last "isSavingPost + status" state to detect transitions.
	var prevSavingState = false;

	// The subscribe listener runs on every store change.
	subscribe( function () {
		var select   = wp.data.select( 'core/editor' );
		var dispatch = wp.data.dispatch( 'core/editor' );
		if ( ! select || ! dispatch ) return;

		var isSaving = select.isSavingPost();
		var status   = select.getEditedPostAttribute( 'status' );

		// Only act when transitioning from not-saving to saving toward publish.
		if ( ! isSaving || prevSavingState ) {
			prevSavingState = isSaving;
			return;
		}
		prevSavingState = isSaving;

		if ( status !== 'publish' && status !== 'future' ) return;
		if ( ! REQUIRED && reviewState.allClear ) return;
		if ( reviewState.allClear ) return;

		// Lock saving — editor will need to go through the review.
		dispatch.lockPostSaving( SAVE_LOCK );

		// Unlock immediately if review not required (editorial is advisory only).
		if ( ! REQUIRED ) {
			dispatch.unlockPostSaving( SAVE_LOCK );
		}
	} );

	// ═══════════════════════════════════════════════════════════════════
	// PRE-PUBLISH PANEL COMPONENT
	// ═══════════════════════════════════════════════════════════════════

	function EditorialPanel() {
		const [ loading, setLoading ]      = useState( false );
		const [ error, setError ]          = useState( '' );
		const [ result, setResult ]        = useState( reviewState.result );
		const [ overrides, setOverrides ]  = useState( reviewState.overrides );
		const [ showModal, setShowModal ]  = useState( false );

		const { postTitle, postContent, postExcerpt } = useSelect( select => {
			const editor = select( 'core/editor' );
			return {
				postTitle:   editor.getEditedPostAttribute( 'title' )   || '',
				postContent: editor.getEditedPostAttribute( 'content' ) || '',
				postExcerpt: editor.getEditedPostAttribute( 'excerpt' ) || '',
			};
		}, [] );

		const { unlockPostSaving } = useDispatch( 'core/editor' );

		// Compute allClear from result + overrides.
		function computeAllClear( res, ovr ) {
			if ( ! res ) return false;
			const allAcknowledged = ( res.checks || [] ).every( function ( check ) {
				return check.passed || !! ovr[ check.label ];
			} );
			return allAcknowledged;
		}

		// When result or overrides change, sync into module state and unlock if clear.
		useEffect( function () {
			reviewState.result    = result;
			reviewState.overrides = overrides;
			reviewState.reviewed  = !! result;
			const clear           = computeAllClear( result, overrides );
			reviewState.allClear  = clear;
			if ( clear ) {
				unlockPostSaving( SAVE_LOCK );
			}
		}, [ result, overrides ] );

		const handleReview = useCallback( async function () {
			setLoading( true );
			setError( '' );

			try {
				const fd = new FormData();
				fd.append( 'action',   'vicinity_ai_editorial_review' );
				fd.append( 'nonce',    NONCE );
				fd.append( 'post_id',  wp.data.select( 'core/editor' ).getCurrentPostId() || 0 );
				fd.append( 'title',    postTitle );
				fd.append( 'content',  postContent );
				fd.append( 'excerpt',  postExcerpt );

				const resp = await fetch( AJAX_URL, { method: 'POST', body: fd } );
				const data = await resp.json();

				if ( data.success && data.data.result ) {
					setResult( data.data.result );
					setOverrides( {} );
					setShowModal( true );
				} else {
					setError( data.data?.message || 'Review failed. Please try again.' );
				}
			} catch ( e ) {
				setError( 'Network error. Please try again.' );
			} finally {
				setLoading( false );
			}
		}, [ postTitle, postContent, postExcerpt ] );

		const handleOverride = useCallback( function ( label, reason ) {
			setOverrides( prev => {
				const next = Object.assign( {}, prev );
				if ( reason !== null ) {
					next[ label ] = reason;
				} else {
					delete next[ label ];
				}
				return next;
			} );
		}, [] );

		// Score bar colour.
		function scoreColor( score ) {
			if ( score >= THRESHOLD ) return '#2e7d32';
			if ( score >= THRESHOLD * 0.6 ) return '#e65100';
			return '#b71c1c';
		}

		const allClear = computeAllClear( result, overrides );

		return el( Fragment, null,

			// ── Review trigger ─────────────────────────────────────────
			el( 'div', { style: { padding: '12px 0' } },

				error && el( Notice, { status: 'error', isDismissible: false }, error ),

				result && el( 'div', { style: { marginBottom: 12 } },
					el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 10, marginBottom: 6 } },
						el( 'strong', null, 'AI Review Score: ' ),
						el( 'span', {
							style: {
								fontWeight: 700,
								fontSize: 18,
								color: scoreColor( result.score ),
							}
						}, result.score + ' / 100' ),
						result.passed
							? el( 'span', { style: { background: '#e8f5e9', color: '#1b5e20', borderRadius: 3, fontSize: 11, padding: '2px 8px', fontWeight: 700 } }, 'PASS' )
							: el( 'span', { style: { background: '#ffebee', color: '#b71c1c', borderRadius: 3, fontSize: 11, padding: '2px 8px', fontWeight: 700 } }, 'FAIL' )
					),

					allClear
						? el( Notice, { status: 'success', isDismissible: false },
							'All checks cleared. You may publish.'
						)
						: el( Notice, { status: 'warning', isDismissible: false },
							'Some checks require your attention before publishing.'
						),

					el( Button, { variant: 'link', onClick: () => setShowModal( true ) }, 'View checklist' )
				),

				el( Button, {
					variant:  'primary',
					onClick:  handleReview,
					isBusy:   loading,
					disabled: loading,
					style:    { width: '100%' },
				}, loading
					? el( Fragment, null, el( Spinner ), ' Reviewing…' )
					: ( result ? 'Re-run AI Review' : 'Run AI Auto Review' )
				),

				! result && REQUIRED && el( 'p', {
					style: { fontSize: 12, color: '#888', marginTop: 6 }
				}, 'AI review is required before publishing.' )
			),

			// ── Checklist modal ────────────────────────────────────────
			showModal && result && el( Modal, {
				title:     'Editorial Checklist',
				onRequestClose: () => setShowModal( false ),
				style:     { maxWidth: 600 },
				className: 'apollo-editorial-modal',
			},
				el( 'div', { style: { marginBottom: 16, display: 'flex', alignItems: 'center', gap: 12 } },
					el( 'span', { style: { fontSize: 28, fontWeight: 700, color: scoreColor( result.score ) } },
						result.score + '/100'
					),
					result.passed
						? el( 'span', { style: { color: '#2e7d32', fontWeight: 600 } }, 'Passed all required thresholds' )
						: el( 'span', { style: { color: '#b71c1c', fontWeight: 600 } }, 'Below pass threshold — acknowledge failed items to proceed' )
				),

				el( 'div', { className: 'apollo-checklist' },
					( result.checks || [] ).map( function ( check, i ) {
						const isOverridden = !! overrides[ check.label ];
						const needsAction  = ! check.passed && ! isOverridden;

						return el( 'div', {
							key:       i,
							className: 'apollo-check-item ' + ( check.passed ? 'is-pass' : isOverridden ? 'is-overridden' : 'is-fail' ),
							style:     {
								border:       '1px solid ' + ( check.passed ? '#c8e6c9' : isOverridden ? '#fff3cd' : '#ffcdd2' ),
								borderRadius: 4,
								padding:      '10px 14px',
								marginBottom: 10,
								background:   check.passed ? '#f1f8e9' : isOverridden ? '#fffde7' : '#fff8f8',
							}
						},
							el( 'div', { style: { display: 'flex', alignItems: 'flex-start', gap: 10 } },
								el( 'span', {
									style: {
										fontSize: 18,
										lineHeight: 1,
										flexShrink: 0,
										marginTop: 1,
									}
								}, check.passed ? 'OK' : isOverridden ? 'WARN' : 'FAIL' ),
								el( 'div', { style: { flex: 1 } },
									el( 'strong', { style: { display: 'block', fontSize: 13 } }, check.label ),
									! check.passed && check.issue && el( 'p', {
										style: { fontSize: 12, color: '#555', margin: '4px 0 0' }
									}, check.issue )
								)
							),

							// Override section for failed checks.
							! check.passed && el( 'div', { style: { marginTop: 10 } },
								! isOverridden
									? el( 'div', { style: { display: 'flex', gap: 8, alignItems: 'center' } },
										el( OverrideForm, {
											key:      check.label,
											label:    check.label,
											onSubmit: ( reason ) => handleOverride( check.label, reason || ' ' ),
										} )
									)
									: el( 'div', { style: { fontSize: 12, color: '#888', display: 'flex', alignItems: 'center', gap: 8 } },
										el( 'span', null, 'WARN Overridden: ' + ( overrides[ check.label ] || '(no reason given)' ) ),
										el( Button, {
											variant:  'link',
											isDestructive: true,
											isSmall:  true,
											onClick:  () => handleOverride( check.label, null ),
											style:    { fontSize: 12 },
										}, 'Remove override' )
									)
							)
						);
					} )
				),

				el( 'div', { style: { display: 'flex', justifyContent: 'flex-end', gap: 12, marginTop: 16, borderTop: '1px solid #eee', paddingTop: 16 } },
					el( Button, { variant: 'secondary', onClick: () => setShowModal( false ) }, 'Close' ),
					allClear && el( Button, {
						variant: 'primary',
						onClick: () => setShowModal( false ),
					}, 'Proceed to Publish' )
				)
			)
		);
	}

	// ─── Override form sub-component ────────────────────────────────────────

	function OverrideForm( { label, onSubmit } ) {
		const [ reason, setReason ] = useState( '' );
		const [ open, setOpen ]     = useState( false );

		if ( ! open ) {
			return el( Button, {
				variant:  'secondary',
				isSmall:  true,
				onClick:  () => setOpen( true ),
			}, 'Acknowledge & Override' );
		}

		return el( 'div', { style: { width: '100%' } },
			el( TextareaControl, {
				label:    'Override reason (optional)',
				value:    reason,
				onChange: setReason,
				rows:     2,
				placeholder: 'Explain why this check is acceptable to override…',
			} ),
			el( 'div', { style: { display: 'flex', gap: 8 } },
				el( Button, {
					variant: 'primary',
					isSmall: true,
					onClick: () => onSubmit( reason ),
				}, 'Confirm Override' ),
				el( Button, {
					variant: 'secondary',
					isSmall: true,
					onClick: () => setOpen( false ),
				}, 'Cancel' )
			)
		);
	}

	// ═══════════════════════════════════════════════════════════════════
	// REGISTER PLUGIN
	// ═══════════════════════════════════════════════════════════════════

	registerPlugin( 'apollo-editorial', {
		render: function () {
			return el( PluginPrePublishPanel, {
				name:  'apollo-editorial-panel',
				title: 'AI Auto Review',
				className: 'apollo-editorial-panel',
				initialOpen: true,
			},
				el( EditorialPanel )
			);
		},
	} );

} )( window.wp, window.apolloEditorial );
