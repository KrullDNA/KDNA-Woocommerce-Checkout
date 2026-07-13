<?php
/**
 * Uninstall routine for KDNA Checkout.
 *
 * Runs only when the plugin is deleted from wp-admin. Removes the
 * captured-carts table, all plugin custom post type content (including
 * post meta), and every plugin option.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if not called by WordPress during plugin deletion.
}

/**
 * Remove all KDNA Checkout data for the current site.
 *
 * @return void
 */
function kdna_checkout_uninstall_site() {
	global $wpdb;

	// 1. Drop the captured-carts table.
	$table_name = $wpdb->prefix . 'kdna_checkout_carts';
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name is built from $wpdb->prefix and a fixed string; schema change on uninstall.

	// 2. Delete all plugin custom post type content, including post meta.
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( %s, %s )",
			'kdna_recovery_email',
			'kdna_order_bump'
		)
	);

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	// 3. Delete every plugin option (all options use the kdna_checkout_ prefix).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'kdna_checkout_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$kdna_checkout_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $kdna_checkout_site_ids as $kdna_checkout_site_id ) {
		switch_to_blog( (int) $kdna_checkout_site_id );
		kdna_checkout_uninstall_site();
		restore_current_blog();
	}
} else {
	kdna_checkout_uninstall_site();
}
