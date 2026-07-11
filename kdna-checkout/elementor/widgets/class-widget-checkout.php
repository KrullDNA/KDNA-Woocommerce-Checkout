<?php
/**
 * The KDNA Checkout Elementor widget.
 *
 * Renders the native WooCommerce classic shortcode checkout reflowed
 * into a two-column CSS grid: customer details on the left, order
 * summary on the right in a card that becomes sticky on scroll. On
 * mobile it stacks to a single column with the summary above or below
 * per a control. In the Elementor editor a clean placeholder is shown
 * instead of a live checkout.
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
 * KDNA Checkout widget.
 */
class KDNA_Checkout_Widget_Checkout extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-checkout';
	}

	/**
	 * Widget title shown in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'KDNA Checkout', 'kdna-checkout' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-checkout';
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
		return array( 'kdna', 'checkout', 'woocommerce', 'cart', 'payment', 'order' );
	}

	/**
	 * Style handles Elementor enqueues only on pages containing the widget.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'kdna-checkout' );
	}

	/**
	 * Script handles Elementor enqueues only on pages containing the widget.
	 *
	 * @return array
	 */
	public function get_script_depends() {
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
	 * Content tab controls. Structure only in this stage, the full Style
	 * tab arrives in Stage 3.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'sticky_summary',
			array(
				'label'        => __( 'Sticky order summary on desktop', 'kdna-checkout' ),
				'description'  => __( 'The order summary card follows the shopper as they scroll.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-checkout' ),
				'label_off'    => __( 'Off', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'mobile_summary_position',
			array(
				'label'   => __( 'Order summary position on mobile', 'kdna-checkout' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'below' => __( 'Below the customer details', 'kdna-checkout' ),
					'above' => __( 'Above the customer details', 'kdna-checkout' ),
				),
				'default' => 'below',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$classes = array( 'kdna-checkout' );

		if ( isset( $settings['sticky_summary'] ) && 'yes' === $settings['sticky_summary'] ) {
			$classes[] = 'kdna-checkout--sticky';
		}

		if ( isset( $settings['mobile_summary_position'] ) && 'above' === $settings['mobile_summary_position'] ) {
			$classes[] = 'kdna-checkout--summary-above';
		}

		if ( $this->is_editor_context() ) {
			$this->render_placeholder( $classes );
			return;
		}

		// Fail-safe: never output checkout markup without WooCommerce.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		printf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );
		// Native WooCommerce classic shortcode checkout, reflowed by the widget CSS/JS.
		echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce renders and escapes its own checkout markup.
		echo '</div>';
	}

	/**
	 * Whether we are rendering inside the Elementor editor or its preview.
	 *
	 * @return bool
	 */
	private function is_editor_context() {
		$plugin = \Elementor\Plugin::$instance;

		if ( $plugin->editor && $plugin->editor->is_edit_mode() ) {
			return true;
		}

		if ( $plugin->preview && $plugin->preview->is_preview_mode() ) {
			return true;
		}

		return false;
	}

	/**
	 * Clean editor placeholder: a static two-column skeleton instead of a
	 * live checkout, so the editor never runs checkout scripts.
	 *
	 * @param array $classes Wrapper classes from the current settings.
	 * @return void
	 */
	private function render_placeholder( array $classes ) {
		$classes[] = 'kdna-checkout--editor';
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="kdna-checkout__placeholder">
				<div class="kdna-checkout__ph-header">
					<strong><?php echo esc_html__( 'KDNA Checkout', 'kdna-checkout' ); ?></strong>
					<span><?php echo esc_html__( 'The live WooCommerce checkout renders here on the front end. Preview the page to see it working.', 'kdna-checkout' ); ?></span>
				</div>
				<div class="kdna-checkout__ph-columns">
					<div class="kdna-checkout__ph-form">
						<div class="kdna-checkout__ph-line kdna-checkout__ph-line--title"></div>
						<div class="kdna-checkout__ph-field"></div>
						<div class="kdna-checkout__ph-field"></div>
						<div class="kdna-checkout__ph-field"></div>
						<div class="kdna-checkout__ph-field"></div>
						<div class="kdna-checkout__ph-line kdna-checkout__ph-line--title"></div>
						<div class="kdna-checkout__ph-field"></div>
						<div class="kdna-checkout__ph-field"></div>
					</div>
					<div class="kdna-checkout__ph-summary">
						<div class="kdna-checkout__ph-line kdna-checkout__ph-line--title"></div>
						<div class="kdna-checkout__ph-line"></div>
						<div class="kdna-checkout__ph-line"></div>
						<div class="kdna-checkout__ph-line"></div>
						<div class="kdna-checkout__ph-line kdna-checkout__ph-line--total"></div>
						<div class="kdna-checkout__ph-button"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
