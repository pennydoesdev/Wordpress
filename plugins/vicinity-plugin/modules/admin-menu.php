<?php
/**
 * Apollo Admin Menu Editor
 *
 * Lets administrators reorder, rename, and hide any WordPress admin menu item
 * (top-level and second-level).  Configuration is stored in the WP option
 * `vicinity_admin_menu_config` as a JSON blob.
 *
 * Applied at runtime via `custom_menu_order`, `menu_order`, and
 * `admin_menu` (late priority) hooks.
 *
 * Access: Apollo → Admin Menu Editor  (requires `manage_options`).
 *
 * @package Apollo
 * @since   3.1.0
 */



defined( 'ABSPATH' ) || exit;

define( 'VICINITY_ADMIN_MENU_OPTION', 'vicinity_admin_menu_config' );

// ═══════════════════════════════════════════════════════════════════════════
// APPLY SAVED CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════

add_filter( 'custom_menu_order', '__return_true' );

/**
 * Reorder and hide top-level items.
 *
 * WP's `menu_order` filter receives an array of slugs; we re-sort and remove
 * hidden items.
 */
add_filter( 'menu_order', static function ( array $menu_order ): array {
	if ( ! is_admin() ) return $menu_order;

	$cfg = vicinity_admin_menu_get_config();
	if ( empty( $cfg['items'] ) ) return $menu_order;

	$items = $cfg['items'];

	// Build index of hidden slugs.
	$hidden = [];
	foreach ( $items as $item ) {
		if ( ! empty( $item['hidden'] ) ) {
			$hidden[] = $item['slug'];
		}
	}

	// Build ordered list from config (preserving only known slugs).
	$ordered = [];
	foreach ( $items as $item ) {
		if ( ! empty( $item['hidden'] ) ) continue;
		if ( in_array( $item['slug'], $menu_order, true ) ) {
			$ordered[] = $item['slug'];
		}
	}

	// Append any slugs not in config (newly installed plugins, etc.).
	foreach ( $menu_order as $slug ) {
		if ( ! in_array( $slug, $ordered, true ) && ! in_array( $slug, $hidden, true ) ) {
			$ordered[] = $slug;
		}
	}

	return $ordered;
}, 9999 );

/**
 * Rename and hide items from the global $menu and $submenu arrays.
 *
 * Runs after all plugins have registered their items (priority 9999).
 */
add_action( 'admin_menu', static function (): void {
	global $menu, $submenu;

	$cfg = vicinity_admin_menu_get_config();
	if ( empty( $cfg['items'] ) ) return;

	foreach ( $cfg['items'] as $item ) {
		$slug = $item['slug'] ?? '';
		if ( ! $slug ) continue;

		// ── Top-level rename ─────────────────────────────────────────────
		foreach ( $menu as $pos => $entry ) {
			if ( ( $entry[2] ?? '' ) === $slug ) {
				if ( ! empty( $item['label'] ) ) {
					// Position 0 is the display label, position 5 is the ID attribute.
					$menu[ $pos ][0] = esc_html( $item['label'] );
				}
				if ( ! empty( $item['hidden'] ) ) {
					unset( $menu[ $pos ] );
				}
				break;
			}
		}

		// ── Sub-menu renames / hides ──────────────────────────────────────
		if ( ! empty( $item['children'] ) && isset( $submenu[ $slug ] ) ) {
			foreach ( $item['children'] as $child ) {
				$child_slug = $child['slug'] ?? '';
				if ( ! $child_slug ) continue;

				foreach ( $submenu[ $slug ] as $spos => $sentry ) {
					if ( ( $sentry[2] ?? '' ) === $child_slug ) {
						if ( ! empty( $child['label'] ) ) {
							$submenu[ $slug ][ $spos ][0] = esc_html( $child['label'] );
						}
						if ( ! empty( $child['hidden'] ) ) {
							unset( $submenu[ $slug ][ $spos ] );
						}
						break;
					}
				}
			}
		}
	}
}, 9999 );

// ═══════════════════════════════════════════════════════════════════════════
// ADMIN PAGE
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', static function (): void {
	add_submenu_page(
		'options-general.php',
		__( 'Admin Menu Editor', 'vicinity' ),
		__( 'Admin Menu Editor', 'vicinity' ),
		'manage_options',
		'apollo-admin-menu',
		'vicinity_admin_menu_page'
	);
} );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	if ( $hook !== 'settings_page_apollo-admin-menu' ) return;

	// Inline JS for drag-sort and AJAX save.
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_add_inline_style( 'wp-admin', vicinity_admin_menu_css() );
	wp_add_inline_script( 'jquery-ui-sortable', vicinity_admin_menu_js(), 'after' );
} );

// ═══════════════════════════════════════════════════════════════════════════
// AJAX SAVE
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_vicinity_save_admin_menu', static function (): void {
	check_ajax_referer( 'vicinity_admin_menu', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

	$raw = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';
	$decoded = json_decode( $raw, true );

	if ( ! is_array( $decoded ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid data.', 'vicinity' ) ] );
	}

	// Sanitize recursively.
	$clean = vicinity_admin_menu_sanitize_config( $decoded );
	update_option( VICINITY_ADMIN_MENU_OPTION, $clean );

	wp_send_json_success( [ 'message' => __( 'Saved.', 'vicinity' ) ] );
} );

// AJAX reset.
add_action( 'wp_ajax_vicinity_reset_admin_menu', static function (): void {
	check_ajax_referer( 'vicinity_admin_menu', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );
	delete_option( VICINITY_ADMIN_MENU_OPTION );
	wp_send_json_success( [ 'message' => __( 'Reset to defaults.', 'vicinity' ) ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// PAGE RENDER
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_admin_menu_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'Insufficient permissions.', 'vicinity' ) );
	}

	global $menu, $submenu;

	// Flatten current menu into a structured array for the editor.
	$current_items = [];
	foreach ( $menu as $entry ) {
		if ( empty( $entry[2] ) || $entry[0] === '' ) continue; // separators
		if ( strpos( $entry[0], 'separator' ) !== false ) continue;
		$slug  = $entry[2];
		$label = strip_tags( $entry[0] );
		if ( ! $label ) continue; // hidden WP separators

		$children = [];
		if ( isset( $submenu[ $slug ] ) ) {
			foreach ( $submenu[ $slug ] as $sentry ) {
				if ( empty( $sentry[2] ) ) continue;
				$children[] = [
					'slug'   => $sentry[2],
					'label'  => strip_tags( $sentry[0] ),
					'hidden' => false,
				];
			}
		}

		$current_items[] = [
			'slug'     => $slug,
			'label'    => $label,
			'hidden'   => false,
			'children' => $children,
		];
	}

	// Merge saved config onto live items.
	$saved = vicinity_admin_menu_get_config();
	if ( ! empty( $saved['items'] ) ) {
		$saved_map = [];
		foreach ( $saved['items'] as $s ) {
			$saved_map[ $s['slug'] ] = $s;
		}

		// Update live items with saved labels/hidden flags.
		foreach ( $current_items as &$item ) {
			if ( isset( $saved_map[ $item['slug'] ] ) ) {
				$s = $saved_map[ $item['slug'] ];
				if ( ! empty( $s['label'] ) )  $item['label']  = $s['label'];
				if ( ! empty( $s['hidden'] ) )  $item['hidden'] = true;
				// Children.
				if ( ! empty( $s['children'] ) ) {
					$sc_map = [];
					foreach ( $s['children'] as $sc ) $sc_map[ $sc['slug'] ] = $sc;
					foreach ( $item['children'] as &$child ) {
						if ( isset( $sc_map[ $child['slug'] ] ) ) {
							$sc = $sc_map[ $child['slug'] ];
							if ( ! empty( $sc['label'] ) )  $child['label']  = $sc['label'];
							if ( ! empty( $sc['hidden'] ) ) $child['hidden'] = true;
						}
					}
					unset( $child );
				}
			}
		}
		unset( $item );

		// Re-sort by saved order.
		$saved_order = array_column( $saved['items'], 'slug' );
		usort( $current_items, static function ( array $a, array $b ) use ( $saved_order ): int {
			$ia = array_search( $a['slug'], $saved_order, true );
			$ib = array_search( $b['slug'], $saved_order, true );
			if ( $ia === false ) $ia = 9999;
			if ( $ib === false ) $ib = 9999;
			return $ia - $ib;
		} );
	}

	$nonce          = wp_create_nonce( 'vicinity_admin_menu' );
	$config_json    = esc_attr( wp_json_encode( [ 'items' => $current_items ] ) );

	?>
	<div class="wrap apollo-ame-wrap">
		<h1><?php esc_html_e( 'Admin Menu Editor', 'vicinity' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Drag to reorder, rename, or hide any admin menu item. Changes take effect immediately on save. Reload the page after saving to see your updates.', 'vicinity' ); ?>
		</p>

		<div id="apollo-ame-app"
			data-config="<?php echo $config_json; ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		</div>

		<template id="apollo-ame-item-tpl">
			<li class="ame-item" data-slug="">
				<div class="ame-item-row">
					<span class="ame-handle dashicons dashicons-menu" title="Drag to reorder"></span>
					<input type="text" class="ame-label" placeholder="Menu label" />
					<label class="ame-hidden-wrap">
						<input type="checkbox" class="ame-hidden" />
						<span><?php esc_html_e( 'Hide', 'vicinity' ); ?></span>
					</label>
					<button type="button" class="ame-toggle-children button-link" style="display:none">
						▸ <?php esc_html_e( 'Submenu', 'vicinity' ); ?>
					</button>
				</div>
				<ul class="ame-children sortable-children"></ul>
			</li>
		</template>

		<template id="apollo-ame-child-tpl">
			<li class="ame-child" data-slug="">
				<span class="ame-handle dashicons dashicons-menu"></span>
				<input type="text" class="ame-label" placeholder="Submenu label" />
				<label class="ame-hidden-wrap">
					<input type="checkbox" class="ame-hidden" />
					<span><?php esc_html_e( 'Hide', 'vicinity' ); ?></span>
				</label>
			</li>
		</template>

		<div class="ame-actions">
			<button id="ame-save" class="button button-primary">
				<?php esc_html_e( 'Save Changes', 'vicinity' ); ?>
			</button>
			<button id="ame-reset" class="button button-secondary" style="margin-left:8px;">
				<?php esc_html_e( 'Reset to Defaults', 'vicinity' ); ?>
			</button>
			<span id="ame-status" class="ame-status" aria-live="polite"></span>
		</div>
	</div>
	<?php
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_admin_menu_get_config(): array {
	$raw = get_option( VICINITY_ADMIN_MENU_OPTION, '' );
	if ( ! $raw ) return [];
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : [];
}

function vicinity_admin_menu_sanitize_config( array $config ): string {
	$clean = [ 'items' => [] ];
	foreach ( $config['items'] ?? [] as $item ) {
		$slug    = sanitize_key( $item['slug'] ?? '' );
		if ( ! $slug ) continue;
		$entry   = [
			'slug'     => $slug,
			'label'    => sanitize_text_field( $item['label'] ?? '' ),
			'hidden'   => ! empty( $item['hidden'] ),
			'children' => [],
		];
		foreach ( $item['children'] ?? [] as $child ) {
			$cslug = sanitize_text_field( $child['slug'] ?? '' );
			if ( ! $cslug ) continue;
			$entry['children'][] = [
				'slug'   => $cslug,
				'label'  => sanitize_text_field( $child['label'] ?? '' ),
				'hidden' => ! empty( $child['hidden'] ),
			];
		}
		$clean['items'][] = $entry;
	}
	return wp_json_encode( $clean );
}

// ═══════════════════════════════════════════════════════════════════════════
// INLINE CSS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_admin_menu_css(): string {
	return '
	.apollo-ame-wrap { max-width: 760px; }
	.apollo-ame-wrap .description { margin-bottom: 20px; }
	#apollo-ame-app ul.ame-list { list-style: none; margin: 0; padding: 0; }
	.ame-item { background: #fff; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 6px; }
	.ame-item.ame-hidden-item { opacity: .5; }
	.ame-item-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; }
	.ame-handle { cursor: grab; color: #aaa; flex-shrink: 0; }
	.ame-handle:active { cursor: grabbing; }
	.ame-label { flex: 1; max-width: 280px; font-size: 13px; }
	.ame-hidden-wrap { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #555; white-space: nowrap; }
	.ame-toggle-children { font-size: 12px; color: #888; flex-shrink: 0; }
	.ame-children { list-style: none; margin: 0; padding: 4px 0 8px 40px; display: none; }
	.ame-children.open { display: block; }
	.ame-child { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 0; border-top: 1px solid #f5f5f5; }
	.ame-child .ame-label { max-width: 240px; font-size: 12px; }
	.ame-actions { margin-top: 20px; display: flex; align-items: center; }
	.ame-status { margin-left: 16px; font-size: 13px; font-weight: 600; }
	.ame-status.ok   { color: #1b5e20; }
	.ame-status.err  { color: #b71c1c; }
	.ui-sortable-helper { box-shadow: 0 4px 12px rgba(0,0,0,.15); }
	.ui-sortable-placeholder { background: #f0f4ff; border: 1px dashed #aab; height: 44px; border-radius: 3px; }
	';
}

// ═══════════════════════════════════════════════════════════════════════════
// INLINE JS
// ═══════════════════════════════════════════════════════════════════════════

function vicinity_admin_menu_js(): string {
	// phpcs:disable
	return <<<'JS'
(function($){
	var app    = document.getElementById('apollo-ame-app');
	if(!app) return;

	var cfg    = JSON.parse(app.dataset.config || '{"items":[]}');
	var nonce  = app.dataset.nonce;
	var ajax   = app.dataset.ajax;
	var itemTpl = document.getElementById('apollo-ame-item-tpl');
	var childTpl= document.getElementById('apollo-ame-child-tpl');

	// ── Build UI ──────────────────────────────────────────────────────
	var list = document.createElement('ul');
	list.className = 'ame-list';

	cfg.items.forEach(function(item){
		var li = itemTpl.content.cloneNode(true).querySelector('li');
		li.dataset.slug = item.slug;
		if(item.hidden) li.classList.add('ame-hidden-item');

		li.querySelector('.ame-label').value = item.label || item.slug;
		li.querySelector('.ame-hidden').checked = !!item.hidden;

		li.querySelector('.ame-hidden').addEventListener('change', function(){
			li.classList.toggle('ame-hidden-item', this.checked);
		});

		// Children
		var childrenArr = item.children || [];
		if(childrenArr.length){
			var toggleBtn = li.querySelector('.ame-toggle-children');
			var childList = li.querySelector('.ame-children');
			toggleBtn.style.display = '';

			childrenArr.forEach(function(child){
				var cli = childTpl.content.cloneNode(true).querySelector('li');
				cli.dataset.slug = child.slug;
				cli.querySelector('.ame-label').value = child.label || child.slug;
				cli.querySelector('.ame-hidden').checked = !!child.hidden;
				childList.appendChild(cli);
			});

			toggleBtn.addEventListener('click', function(){
				var open = childList.classList.toggle('open');
				toggleBtn.textContent = (open ? '▾ ' : '▸ ') + 'Submenu';
			});

			$(childList).sortable({ handle: '.ame-handle', tolerance: 'pointer', placeholder: 'ui-sortable-placeholder' });
		}

		list.appendChild(li);
	});

	app.appendChild(list);

	$(list).sortable({ handle: '.ame-handle', tolerance: 'pointer', placeholder: 'ui-sortable-placeholder' });

	// ── Collect config from DOM ───────────────────────────────────────
	function collectConfig(){
		var items = [];
		list.querySelectorAll(':scope > .ame-item').forEach(function(li){
			var children = [];
			li.querySelectorAll('.ame-children > .ame-child').forEach(function(cli){
				children.push({
					slug:   cli.dataset.slug,
					label:  cli.querySelector('.ame-label').value.trim(),
					hidden: cli.querySelector('.ame-hidden').checked,
				});
			});
			items.push({
				slug:     li.dataset.slug,
				label:    li.querySelector('.ame-item-row .ame-label').value.trim(),
				hidden:   li.querySelector('.ame-item-row .ame-hidden').checked,
				children: children,
			});
		});
		return { items: items };
	}

	// ── Save ─────────────────────────────────────────────────────────
	var statusEl = document.getElementById('ame-status');
	function setStatus(msg, type){
		statusEl.textContent = msg;
		statusEl.className = 'ame-status ' + (type||'');
		setTimeout(function(){ statusEl.textContent=''; statusEl.className='ame-status'; }, 3500);
	}

	document.getElementById('ame-save').addEventListener('click', function(){
		var btn = this;
		btn.disabled = true;
		btn.textContent = 'Saving…';
		var fd = new FormData();
		fd.append('action','vicinity_save_admin_menu');
		fd.append('nonce', nonce);
		fd.append('config', JSON.stringify(collectConfig()));
		fetch(ajax,{method:'POST',body:fd})
			.then(function(r){return r.json();})
			.then(function(d){
				if(d.success) setStatus('✓ Saved', 'ok');
				else setStatus('Error: '+(d.data&&d.data.message||'failed'), 'err');
			})
			.catch(function(){ setStatus('Network error', 'err'); })
			.finally(function(){ btn.disabled=false; btn.textContent='Save Changes'; });
	});

	// ── Reset ─────────────────────────────────────────────────────────
	document.getElementById('ame-reset').addEventListener('click', function(){
		if(!confirm('Reset all menu customisations to WordPress defaults?')) return;
		var fd = new FormData();
		fd.append('action','vicinity_reset_admin_menu');
		fd.append('nonce', nonce);
		fetch(ajax,{method:'POST',body:fd})
			.then(function(r){return r.json();})
			.then(function(d){
				if(d.success){ setStatus('✓ Reset', 'ok'); setTimeout(function(){location.reload();},800); }
				else setStatus('Error', 'err');
			})
			.catch(function(){ setStatus('Network error', 'err'); });
	});
})(jQuery);
JS;
	// phpcs:enable
}