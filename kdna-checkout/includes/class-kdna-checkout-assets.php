<?php
/**
 * Conditional asset registration for KDNA Checkout.
 *
 * Front-end assets are registered, never blanket-enqueued. The widgets
 * declare them via get_style_depends() / get_script_depends(), so
 * Elementor enqueues them only on pages that actually contain a KDNA
 * widget. Admin assets are handled by KDNA_Checkout_Admin on the
 * plugin's own screens only.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers front-end styles and scripts.
 */
class KDNA_Checkout_Assets {

	/**
	 * Shared front-end asset handle.
	 *
	 * @var string
	 */
	const HANDLE = 'kdna-checkout';

	/**
	 * Hook the registrations in.
	 */
	public function __construct() {
		// Early priority so handles exist before Elementor resolves widget dependencies.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ), 5 );
	}

	/**
	 * Register (not enqueue) the front-end assets.
	 *
	 * @return void
	 */
	public function register_frontend_assets() {
		wp_register_style(
			self::HANDLE,
			KDNA_CHECKOUT_URL . 'assets/css/kdna-checkout.css',
			array(),
			KDNA_CHECKOUT_VERSION
		);

		wp_register_script(
			self::HANDLE,
			KDNA_CHECKOUT_URL . 'assets/js/kdna-checkout.js',
			array(),
			KDNA_CHECKOUT_VERSION,
			array( 'in_footer' => true )
		);
	}
}
