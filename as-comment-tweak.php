<?php
/**
 * Plugin Name: Action Scheduler Comment Tweak
 * Plugin URI: https://github.com/Prospress/as-comment-tweak
 * Description: Prototype performance tweak
 * Author: Prospress Inc.
 * Author URI: https://prospress.com/
 * Version: 1.0 alpha
 *
 */

// Initialize
add_action( 'init', 'as_comment_tweak' );

function as_comment_tweak() {

	// Remove default action scheduler comment clause filter
	remove_action( 'pre_get_comments', array( ActionScheduler_Logger::instance(), 'filter_comment_queries' ), 10, 1 );

	// Add our own filter
	add_action( 'pre_get_comments', 'the_magic', 10, 1);

	// If running WooCommerce, remove their filter so that nothing funky goes down
	remove_filter( 'wp_count_comments', array( 'WC_Comments', 'wp_count_comments' ), 10 );

	// Remove unmoderated comment counts from the admin menu
	add_filter( 'wp_count_comments', 'pmgarman_unmoderated_comment_counts', 10, 2 );

	// Order counts in the admin menu can be removed once this filter is merged into WooCommerce
	// https://github.com/woothemes/woocommerce/pull/9820
	add_filter( 'woocommerce_include_order_count_in_menu', '__return_false' );

	// Remove WC Status Dashboard Widget
	add_action('wp_dashboard_setup', 'remove_dashboard_widget' );
}

function the_magic( $query ) {

	// Don't slow down queries that wouldn't include action_log comments anyway
	foreach ( array('ID', 'parent', 'post_author', 'post_name', 'post_parent', 'type', 'post_type', 'post_id', 'post_ID') as $key ) {
		if ( !empty($query->query_vars[$key]) ) {
			return;
		}
	}

	// Variable to check for later
	$query->query_vars['action_log_filter'] = TRUE;

	// Remove default wc order and webhooks comment clause filter
	remove_filter( 'comments_clauses', 'WC_Comments::exclude_order_comments', 10, 1 );
	remove_filter( 'comments_clauses', 'WC_Comments::exclude_webhook_comments', 10, 1 );

	// Now add an optimized version
	add_filter( 'comments_clauses', 'filter_comment_query_clauses', 10, 2 );
}


/**
 * Instead of joining with the wp_posts table (which can be massive) we should be able to just exclude comment types we don't to include
 */
function filter_comment_query_clauses( $clauses, $query ) {

	// Only apply to queries we want
	if ( !empty($query->query_vars['action_log_filter']) ) {
		global $wpdb;

		// Comment types we want to exclude
		$comment_types = array(
			'order_note',
			'webhook_delivery',
			'action_log'
		);

		if ( $clauses['where'] ) {
			$clauses['where'] .= ' AND ';
		}

		// Exclude away
		$clauses['where'] .= " {$wpdb->comments}.comment_type NOT IN ('" . implode( "','", $comment_types ) . "') ";
	}

	return $clauses;
}

/**
 * From Patrick Garman: https://gist.github.com/pmgarman/d64c768754dbc0ff5f49
 *
 * Removes unmoderated comment counts from the admin menu
 */
function pmgarman_unmoderated_comment_counts( $stats, $post_id ) {
	global $wpdb;

	if ( 0 === $post_id ) {
		$stats = json_decode( json_encode( array(
			'moderated'      => 0,
			'approved'       => 0,
			'post-trashed'   => 0,
			'trash'          => 0,
			'total_comments' => 0
		) ) );
	}

	return $stats;
}


/**
 * Remove WC Widgets that are performance hogs on large sites
 */
function remove_dashboard_widget() {
 	remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'core' );
	remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'core' );
	remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
} 