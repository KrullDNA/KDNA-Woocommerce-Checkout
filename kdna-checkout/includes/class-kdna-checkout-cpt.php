<?php
/**
 * Custom post types for KDNA Checkout.
 *
 * Registers kdna_recovery_email (recovery email sequence steps) and
 * kdna_order_bump (order bump offers). Both are admin-only and never
 * publicly queryable.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the plugin's custom post types.
 */
class KDNA_Checkout_CPT {

	/**
	 * Recovery email step post type.
	 *
	 * @var string
	 */
	const RECOVERY_EMAIL = 'kdna_recovery_email';

	/**
	 * Order bump post type.
	 *
	 * @var string
	 */
	const ORDER_BUMP = 'kdna_order_bump';

	/**
	 * Hook the registrations in.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register both custom post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type( self::RECOVERY_EMAIL, $this->recovery_email_args() );
		register_post_type( self::ORDER_BUMP, $this->order_bump_args() );
	}

	/**
	 * Arguments for the recovery email step post type.
	 *
	 * @return array
	 */
	private function recovery_email_args() {
		$labels = array(
			'name'               => __( 'Recovery Emails', 'kdna-checkout' ),
			'singular_name'      => __( 'Recovery Email', 'kdna-checkout' ),
			'add_new'            => __( 'Add Recovery Email', 'kdna-checkout' ),
			'add_new_item'       => __( 'Add Recovery Email', 'kdna-checkout' ),
			'edit_item'          => __( 'Edit Recovery Email', 'kdna-checkout' ),
			'new_item'           => __( 'New Recovery Email', 'kdna-checkout' ),
			'view_item'          => __( 'View Recovery Email', 'kdna-checkout' ),
			'search_items'       => __( 'Search Recovery Emails', 'kdna-checkout' ),
			'not_found'          => __( 'No recovery emails found.', 'kdna-checkout' ),
			'not_found_in_trash' => __( 'No recovery emails found in the bin.', 'kdna-checkout' ),
			'menu_name'          => __( 'Recovery Emails', 'kdna-checkout' ),
		);

		return array_merge(
			$this->shared_private_args(),
			array(
				'labels'   => $labels,
				'supports' => array( 'title', 'editor' ),
			)
		);
	}

	/**
	 * Arguments for the order bump post type.
	 *
	 * @return array
	 */
	private function order_bump_args() {
		$labels = array(
			'name'               => __( 'Order Bumps', 'kdna-checkout' ),
			'singular_name'      => __( 'Order Bump', 'kdna-checkout' ),
			'add_new'            => __( 'Add Order Bump', 'kdna-checkout' ),
			'add_new_item'       => __( 'Add Order Bump', 'kdna-checkout' ),
			'edit_item'          => __( 'Edit Order Bump', 'kdna-checkout' ),
			'new_item'           => __( 'New Order Bump', 'kdna-checkout' ),
			'view_item'          => __( 'View Order Bump', 'kdna-checkout' ),
			'search_items'       => __( 'Search Order Bumps', 'kdna-checkout' ),
			'not_found'          => __( 'No order bumps found.', 'kdna-checkout' ),
			'not_found_in_trash' => __( 'No order bumps found in the bin.', 'kdna-checkout' ),
			'menu_name'          => __( 'Order Bumps', 'kdna-checkout' ),
		);

		return array_merge(
			$this->shared_private_args(),
			array(
				'labels'   => $labels,
				'supports' => array( 'title' ),
			)
		);
	}

	/**
	 * Shared arguments for admin-only, non-public post types.
	 *
	 * The edit screens exist for administrators but stay out of the main
	 * admin menu, later stages surface them under Settings > KDNA Checkout.
	 * Nothing is queryable, searchable or exposed on the front end.
	 *
	 * @return array
	 */
	private function shared_private_args() {
		return array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'hierarchical'        => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);
	}
}
