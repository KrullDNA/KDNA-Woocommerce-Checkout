<?php
/**
 * Elementor integration bootstrap for KDNA Checkout.
 *
 * Registers the KDNA widget category and the plugin's widgets. All
 * Elementor hooks are registered at file-load time, never deferred
 * inside an elementor/loaded callback, so they cannot fire too late.
 * If Elementor is not active the hooks simply never fire (fail-safe).
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the Elementor category and widgets.
 */
final class KDNA_Checkout_Elementor {

	/**
	 * Widget category slug.
	 *
	 * @var string
	 */
	const CATEGORY = 'kdna';

	/**
	 * Bind the Elementor hooks. Called at file-load time below.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register the "KDNA" widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'KDNA', 'kdna-checkout' ),
				'icon'  => 'eicon-cart-medium',
			)
		);
	}

	/**
	 * Register the plugin's widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once KDNA_CHECKOUT_PATH . 'elementor/class-kdna-checkout-trust.php';
		require_once KDNA_CHECKOUT_PATH . 'elementor/class-kdna-checkout-strip-controls.php';
		require_once KDNA_CHECKOUT_PATH . 'elementor/widgets/class-widget-checkout.php';
		require_once KDNA_CHECKOUT_PATH . 'elementor/widgets/class-widget-trust.php';
		require_once KDNA_CHECKOUT_PATH . 'elementor/widgets/class-widget-cart-strip.php';

		$widgets_manager->register( new KDNA_Checkout_Widget_Checkout() );
		$widgets_manager->register( new KDNA_Checkout_Widget_Trust() );
		$widgets_manager->register( new KDNA_Checkout_Widget_Cart_Strip() );
	}
}

// Register the Elementor hooks at file-load time.
KDNA_Checkout_Elementor::init();
