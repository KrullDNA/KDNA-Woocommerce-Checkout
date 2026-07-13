<?php
/**
 * The standalone KDNA Trust Badges Elementor widget.
 *
 * The same trust block used inside the KDNA Checkout widget, placeable
 * anywhere on the page. Controls and rendering come from the shared
 * KDNA_Checkout_Trust helper, so both stay identical.
 *
 * Atomic architecture: has_widget_inner_wrapper() returns false when
 * e_optimized_markup is active, the render output is a single wrapper
 * div, and no CSS targets .elementor-widget-container.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * KDNA Trust Badges widget.
 */
class KDNA_Checkout_Widget_Trust extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-trust-badges';
	}

	/**
	 * Widget title shown in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'KDNA Trust Badges', 'kdna-checkout' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-lock';
	}

	/**
	 * Widget category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( KDNA_Checkout_Elementor::CATEGORY );
	}

	/**
	 * Search keywords for the Elementor panel.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'kdna', 'trust', 'badges', 'secure', 'payment', 'icons' );
	}

	/**
	 * Style handle Elementor enqueues only on pages containing the widget.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'kdna-checkout' );
	}

	/**
	 * Atomic architecture: no inner wrapper when optimised markup is active.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Register the shared trust controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_trust_badges',
			array(
				'label' => __( 'Trust Badges', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		KDNA_Checkout_Trust::register_content_controls( $this );
		$this->end_controls_section();

		$this->start_controls_section(
			'style_trust_badges',
			array(
				'label' => __( 'Trust Block', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		KDNA_Checkout_Trust::register_style_controls( $this );
		$this->end_controls_section();
	}

	/**
	 * Render the trust block (a single wrapper div).
	 *
	 * @return void
	 */
	protected function render() {
		echo KDNA_Checkout_Trust::render( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trust markup is escaped where it is built.
	}
}
