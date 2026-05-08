/**
 * Vicinity Customizer Controls — Homepage Section Blocks Editor
 *
 * Renders the blocks repeater inside the Customizer panel.
 * Blocks are serialised to JSON and stored in the hidden input
 * #vicinity_home_blocks, which is bound to the WP Customizer setting.
 *
 * Dependencies: customize-controls, jquery (both in WP core)
 *
 * @package Apollo (Vicinity)
 */

( function ( wp, $ ) {
	'use strict';

	if ( ! wp || ! wp.customize ) return;

	var cfg      = window.vicinityBlocks || {};
	var layouts  = cfg.layouts   || [];
	var cats     = cfg.categories || [];
	var l10n     = cfg.l10n      || {};

	// ── Unique ID generator ───────────────────────────────────────────────
	var _counter = Date.now();
	function uid() {
		return 'block_' + ( ++_counter );
	}

	// ── Read current blocks from the hidden input ─────────────────────────
	function readBlocks() {
		var raw = $( '#vicinity_home_blocks' ).val();
		try { return JSON.parse( raw ) || []; }
		catch ( e ) { return []; }
	}

	// ── Write blocks back to the hidden input and trigger Customizer change ─
	function writeBlocks( blocks ) {
		var json = JSON.stringify( blocks );
		$( '#vicinity_home_blocks' ).val( json ).trigger( 'change' );
	}

	// ── Build HTML for a single block row ─────────────────────────────────
	function blockHtml( block ) {
		var layoutLabel = '';
		layouts.forEach( function ( l ) {
			if ( l.id === block.layout ) layoutLabel = l.label;
		} );
		if ( ! layoutLabel ) layoutLabel = block.layout;

		var html = '<div class="vcn-block" data-id="' + esc( block.id ) + '">'
			+ '<div class="vcn-block-header">'
			+ '<span class="vcn-drag-handle" title="Drag to reorder">⠿</span>'
			+ '<span class="vcn-block-label">'
			+ ( block.title ? esc( block.title ) : layoutLabel )
			+ '</span>'
			+ '<span class="vcn-block-actions">'
			+ '<button type="button" class="vcn-move-up" title="' + l10n.moveUp + '">' + l10n.moveUp + '</button>'
			+ '<button type="button" class="vcn-move-down" title="' + l10n.moveDown + '">' + l10n.moveDown + '</button>'
			+ '<button type="button" class="vcn-remove">' + l10n.removeBlock + '</button>'
			+ '</span>'
			+ '<span class="vcn-chevron">▼</span>'
			+ '</div>' // .vcn-block-header

			+ '<div class="vcn-block-body">'

			// Layout picker.
			+ '<div class="vcn-field">'
			+ '<label>' + l10n.layoutLabel + '</label>'
			+ '<div class="vcn-layout-grid">'
			+ layouts.map( function ( l ) {
				return '<div class="vcn-layout-option' + ( block.layout === l.id ? ' selected' : '' ) + '" data-layout="' + esc( l.id ) + '">'
					+ '<div class="vcn-layout-option-name">' + esc( l.label ) + '</div>'
					+ '<div class="vcn-layout-option-desc">' + esc( l.desc ) + '</div>'
					+ '</div>';
			} ).join( '' )
			+ '</div>'
			+ '</div>'

			// Category selector.
			+ '<div class="vcn-field">'
			+ '<label>' + l10n.categoryLabel + '</label>'
			+ '<select class="vcn-cat">'
			+ cats.map( function ( c ) {
				return '<option value="' + esc( c.id ) + '"' + ( block.category === c.id ? ' selected' : '' ) + '>'
					+ esc( c.name )
					+ '</option>';
			} ).join( '' )
			+ '</select>'
			+ '</div>'

			// Title override.
			+ '<div class="vcn-field">'
			+ '<label>' + l10n.titleLabel + '</label>'
			+ '<input type="text" class="vcn-title" value="' + esc( block.title || '' ) + '" placeholder="' + esc( layoutLabel ) + '">'
			+ '</div>'

			// Post count.
			+ '<div class="vcn-field">'
			+ '<label>' + l10n.countLabel + '</label>'
			+ '<input type="number" class="vcn-count" value="' + ( block.count || 5 ) + '" min="1" max="12" step="1">'
			+ '</div>'

			// Show more link.
			+ '<div class="vcn-field">'
			+ '<label style="flex-direction:row;align-items:center;gap:6px;cursor:pointer;">'
			+ '<input type="checkbox" class="vcn-show-more"' + ( block.show_more ? ' checked' : '' ) + '>'
			+ l10n.showMoreLabel
			+ '</label>'
			+ '</div>'

			+ '</div>' // .vcn-block-body
			+ '</div>'; // .vcn-block

		return html;
	}

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// ── Re-render the entire list from current blocks state ───────────────
	function renderList( blocks ) {
		var $list = $( '#vicinity-blocks-list' );
		$list.empty();
		blocks.forEach( function ( block ) {
			$list.append( blockHtml( block ) );
		} );
		bindBlockEvents( $list );
		updateMoveButtons( $list );
	}

	// ── Update header labels after any field change ───────────────────────
	function refreshLabel( $block ) {
		var layout = $block.find( '.vcn-layout-option.selected' ).data( 'layout' ) || '';
		var title  = $block.find( '.vcn-title' ).val() || '';
		var layoutLabel = '';
		layouts.forEach( function ( l ) { if ( l.id === layout ) layoutLabel = l.label; } );
		$block.find( '.vcn-block-label' ).text( title || layoutLabel || layout );
	}

	// ── Disable move-up on first / move-down on last ──────────────────────
	function updateMoveButtons( $list ) {
		var $blocks = $list.find( '.vcn-block' );
		$blocks.each( function ( i ) {
			$( this ).find( '.vcn-move-up' ).prop( 'disabled', i === 0 );
			$( this ).find( '.vcn-move-down' ).prop( 'disabled', i === $blocks.length - 1 );
		} );
	}

	// ── Collect current DOM state → blocks array ──────────────────────────
	function collectBlocks() {
		var blocks = [];
		$( '#vicinity-blocks-list .vcn-block' ).each( function () {
			var $b = $( this );
			blocks.push( {
				id:        $b.data( 'id' ),
				layout:    $b.find( '.vcn-layout-option.selected' ).data( 'layout' ) || '3col',
				category:  parseInt( $b.find( '.vcn-cat' ).val(), 10 ) || 0,
				title:     $b.find( '.vcn-title' ).val() || '',
				count:     parseInt( $b.find( '.vcn-count' ).val(), 10 ) || 5,
				show_more: $b.find( '.vcn-show-more' ).is( ':checked' ),
			} );
		} );
		return blocks;
	}

	// ── Bind events inside block rows ─────────────────────────────────────
	function bindBlockEvents( $list ) {
		// Toggle expand / collapse.
		$list.off( 'click.vcn-header', '.vcn-block-header' )
			.on( 'click.vcn-header', '.vcn-block-header', function ( e ) {
				if ( $( e.target ).is( 'button, input, select' ) ) return;
				$( this ).closest( '.vcn-block' ).toggleClass( 'is-open' );
			} );

		// Layout option picker.
		$list.off( 'click.vcn-layout', '.vcn-layout-option' )
			.on( 'click.vcn-layout', '.vcn-layout-option', function () {
				$( this ).closest( '.vcn-layout-grid' ).find( '.vcn-layout-option' ).removeClass( 'selected' );
				$( this ).addClass( 'selected' );
				var $block = $( this ).closest( '.vcn-block' );
				refreshLabel( $block );
				writeBlocks( collectBlocks() );
			} );

		// Field changes.
		$list.off( 'input.vcn change.vcn', '.vcn-cat, .vcn-title, .vcn-count, .vcn-show-more' )
			.on( 'input.vcn change.vcn', '.vcn-cat, .vcn-title, .vcn-count, .vcn-show-more', function () {
				refreshLabel( $( this ).closest( '.vcn-block' ) );
				writeBlocks( collectBlocks() );
			} );

		// Remove block.
		$list.off( 'click.vcn-remove', '.vcn-remove' )
			.on( 'click.vcn-remove', '.vcn-remove', function () {
				if ( ! confirm( 'Remove this section block?' ) ) return;
				$( this ).closest( '.vcn-block' ).remove();
				updateMoveButtons( $list );
				writeBlocks( collectBlocks() );
			} );

		// Move up.
		$list.off( 'click.vcn-up', '.vcn-move-up' )
			.on( 'click.vcn-up', '.vcn-move-up', function () {
				var $block = $( this ).closest( '.vcn-block' );
				var $prev  = $block.prev( '.vcn-block' );
				if ( $prev.length ) $block.insertBefore( $prev );
				updateMoveButtons( $list );
				writeBlocks( collectBlocks() );
			} );

		// Move down.
		$list.off( 'click.vcn-down', '.vcn-move-down' )
			.on( 'click.vcn-down', '.vcn-move-down', function () {
				var $block = $( this ).closest( '.vcn-block' );
				var $next  = $block.next( '.vcn-block' );
				if ( $next.length ) $block.insertAfter( $next );
				updateMoveButtons( $list );
				writeBlocks( collectBlocks() );
			} );
	}

	// ── "+" button — add new block ─────────────────────────────────────────
	function bindAddButton() {
		$( '#vicinity-add-block' ).off( 'click.vcn-add' ).on( 'click.vcn-add', function () {
			var newBlock = {
				id:        uid(),
				layout:    '3col',
				category:  0,
				title:     '',
				count:     5,
				show_more: true,
			};
			var $list = $( '#vicinity-blocks-list' );
			$list.append( blockHtml( newBlock ) );
			// Open the new block.
			$list.find( '.vcn-block:last-child' ).addClass( 'is-open' );
			bindBlockEvents( $list );
			updateMoveButtons( $list );
			writeBlocks( collectBlocks() );
		} );
	}

	// ── Bootstrap: wait for Customizer ready then init ─────────────────────
	wp.customize.bind( 'ready', function () {
		// Short delay to let the panel DOM fully render.
		setTimeout( function () {
			var blocks = readBlocks();
			renderList( blocks );
			bindAddButton();
		}, 200 );

		// Re-init if the section is opened (panels can lazy-render).
		wp.customize.section( 'vicinity_homepage', function ( section ) {
			section.expanded.bind( function ( isExpanded ) {
				if ( isExpanded && $( '#vicinity-blocks-list' ).children().length === 0 ) {
					renderList( readBlocks() );
					bindAddButton();
				}
			} );
		} );
	} );

} )( window.wp, window.jQuery );
