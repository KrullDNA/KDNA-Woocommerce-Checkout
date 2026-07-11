<?php
/**
 * Core loader for KDNA Checkout.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin class. Loads every component as a singleton.
 */
final class KDNA_Checkout {

	/**
	 * Single instance of the plugin.
	 *
	 * @var KDNA_Checkout|null
	 */
	private static $instance = null;

	/**
	 * Custom post types component.
	 *
	 * @var KDNA_Checkout_CPT
	 */
	public $cpt;

	/**
	 * Admin screen component (admin requests only).
	 *
	 * @var KDNA_Checkout_Admin|null
	 */
	public $admin = null;

	/**
	 * Front-end assets component (Stage 2).
	 *
	 * @var KDNA_Checkout_Assets
	 */
	public $assets;

	/**
	 * Cart strip component (Stage 4).
	 *
	 * @var KDNA_Checkout_Cart_Strip
	 */
	public $cart_strip;

	/**
	 * Checkout fields component (Stage 6).
	 *
	 * @var KDNA_Checkout_Fields
	 */
	public $fields;

	/**
	 * Order bump component (Stage 7).
	 *
	 * @var KDNA_Checkout_Order_Bump
	 */
	public $order_bump;

	/**
	 * Address autocomplete component (Stage 9).
	 *
	 * @var KDNA_Checkout_Autocomplete
	 */
	public $autocomplete;

	/**
	 * Return the single plugin instance, creating it on first call.
	 *
	 * @return KDNA_Checkout
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set up the plugin. Private, use instance().
	 */
	private function __construct() {
		$this->includes();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Load component files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-install.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-cpt.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-assets.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-cart-strip.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-express.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-fields.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-order-bump.php';
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-autocomplete.php';

		// Stage 2: Elementor bootstrap, registers its hooks at file-load time.
		require_once KDNA_CHECKOUT_PATH . 'elementor/class-kdna-checkout-elementor.php';

		if ( is_admin() ) {
			require_once KDNA_CHECKOUT_PATH . 'admin/class-kdna-checkout-admin.php';
		}
	}

	/**
	 * Instantiate components.
	 *
	 * @return void
	 */
	private function init_components() {
		$this->cpt        = new KDNA_Checkout_CPT();
		$this->assets     = new KDNA_Checkout_Assets();
		$this->cart_strip = new KDNA_Checkout_Cart_Strip();
		$this->fields     = new KDNA_Checkout_Fields();
		$this->order_bump   = new KDNA_Checkout_Order_Bump();
		$this->autocomplete = new KDNA_Checkout_Autocomplete();

		if ( is_admin() ) {
			$this->admin = new KDNA_Checkout_Admin();
		}
	}

	/**
	 * Register core hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'kdna-checkout',
			false,
			dirname( KDNA_CHECKOUT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialising.
	 *
	 * @throws \Exception Always, unserialising a singleton is not allowed.
	 */
	public function __wakeup() {
		throw new \Exception( 'Unserialising KDNA_Checkout is not allowed.' );
	}
}
