<?php
/**
 * Plugin Name:       KDNA Checkout
 * Plugin URI:        https://krulldna.com/
 * Description:       Streamlined WooCommerce checkout and abandoned-cart recovery, fully styled in Elementor.
 * Version:           0.3.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Krull Design & Advertising
 * Author URI:        https://krulldna.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-checkout
 * Domain Path:       /languages
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * Constants.
 */
define( 'KDNA_CHECKOUT_VERSION', '0.3.0' );
define( 'KDNA_CHECKOUT_FILE', __FILE__ );
define( 'KDNA_CHECKOUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_CHECKOUT_BASENAME', plugin_basename( __FILE__ ) );

/*
 * HPOS (High-Performance Order Storage) compatibility declaration.
 * Registered at file-load time so WooCommerce picks it up early.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/*
 * Activation and deactivation.
 * Activation creates the captured-carts table and stores version options.
 * Deactivation fires an action so later, additive components can clean up
 * their own scheduled events without this file ever needing to change.
 */
register_activation_hook(
	__FILE__,
	function () {
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-install.php';
		KDNA_Checkout_Install::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout-install.php';
		KDNA_Checkout_Install::deactivate();
	}
);

/**
 * Admin notice shown when WooCommerce is not active.
 *
 * @return void
 */
function kdna_checkout_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'KDNA Checkout requires WooCommerce to be installed and active. The plugin is paused until WooCommerce is available.', 'kdna-checkout' )
	);
}

/**
 * Bootstrap the plugin once all plugins are loaded.
 *
 * Confirms WooCommerce is active before any WooCommerce-dependent code runs.
 * If it is not, an admin notice is shown and the plugin stops loading.
 *
 * @return void
 */
function kdna_checkout_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'kdna_checkout_woocommerce_missing_notice' );
		return;
	}

	require_once KDNA_CHECKOUT_PATH . 'includes/class-kdna-checkout.php';
	kdna_checkout();
}
add_action( 'plugins_loaded', 'kdna_checkout_init' );

/**
 * Main instance accessor.
 *
 * @return KDNA_Checkout|null The core plugin instance, or null before load.
 */
function kdna_checkout() {
	if ( ! class_exists( 'KDNA_Checkout' ) ) {
		return null;
	}
	return KDNA_Checkout::instance();
}
