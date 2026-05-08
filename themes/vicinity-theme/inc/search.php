<?php
/**
 * Apollo Search Overlay — AJAX-powered live search.
 *
 * Searches posts, videos, episodes via WP_Query.
 * Front-end: search overlay rendered in header.php, driven by theme.js.
 *
 * @package Apollo
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_vicinity_search',        'vicinity_handle_search' );
add_action( 'wp_ajax_nopriv_vicinity_search', 'vicinity_handle_search' );

function vicinity_handle_search(): void {
	check_ajax_referer( 'vicinity_search', 'nonce' );

	$s    = sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) );
	$page = absint( $_POST['page'] ?? 1 );

	if ( strlen( $s ) < 2 ) {
		wp_send_json_success( [ 'results' => [], 'found' => 0 ] );
	}

	$q = new WP_Query( [
		's'              => $s,
		'post_status'    => 'publish',
		'post_type'      => [ 'post', 'serve_video', 'serve_episode' ],
		'posts_per_page' => 6,
		'paged'          => $page,
		'no_found_rows'  => false,
	] );

	$results = [];
	foreach ( $q->posts as $p ) {
		$results[] = [
			'id'    => $p->ID,
			'title' => get_the_title( $p->ID ),
			'url'   => get_permalink( $p->ID ),
			'type'  => $p->post_type,
			'thumb' => get_the_post_thumbnail_url( $p->ID, 'apollo-card-sm' ) ?: '',
			'date'  => get_the_date( 'M j, Y', $p->ID ),
		];
	}

	wp_send_json_success( [
		'results' => $results,
		'found'   => (int) $q->found_posts,
	] );
}
