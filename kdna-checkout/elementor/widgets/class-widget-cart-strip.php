<?php
/**
 * The standalone KDNA Cart Strip Elementor widget.
 *
 * The same cart strip used inside the KDNA Checkout widget, placeable
 * anywhere on the page, for example a full-width section at the very
 * top. Controls and rendering come from the shared
 * KDNA_Checkout_Strip_Controls helper and KDNA_Checkout_Cart_Strip
 * renderer, so it stays identical to the in-checkout strip, including
 * the live AJAX quantity/remove and sticky behaviour.
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
 * KDNA Cart Strip widget.
 */
class KDNA_Checkout_Widget_Cart_Strip extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-cart-strip';
	}

	/**
	 * Widget title shown in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'KDNA Cart Strip', 'kdna-checkout' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-cart-medium';
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
		return array( 'kdna', 'cart', 'strip', 'mini cart', 'basket', 'sticky' );
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
	 * Script handle Elementor enqueues only on pages containing the widget.
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
	 * Register the shared strip controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_cart_strip',
			array(
				'label' => __( 'Cart Strip', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);
		KDNA_Checkout_Strip_Controls::register_content_controls( $this );
		$this->end_controls_section();

		KDNA_Checkout_Strip_Controls::register_style_controls( $this );
	}

	/**
	 * Map widget settings to cart strip render arguments.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function strip_args( array $settings ) {
		return array(
			'controls'       => $settings['strip_item_controls'] ?? 'full',
			'sticky_desktop' => $settings['strip_sticky_desktop'] ?? '',
			'sticky_mobile'  => $settings['strip_sticky_mobile'] ?? '',
			'subtotal_label' => $settings['strip_subtotal_label'] ?? '',
			'edit_label'     => $settings['strip_edit_label'] ?? '',
			'done_label'     => $settings['strip_done_label'] ?? '',
		);
	}

	/**
	 * Render the strip (a single wrapper div).
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( $this->is_editor_context() ) {
			$this->render_placeholder( $settings );
			return;
		}

		if ( ! class_exists( 'KDNA_Checkout_Cart_Strip' ) ) {
			return;
		}

		echo KDNA_Checkout_Cart_Strip::render( $this->strip_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Strip markup is escaped where it is built.
	}

	/**
	 * Whether we are inside the Elementor editor or its preview.
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
	 * A static skeleton strip for the Elementor editor, so styling
	 * previews without a live cart.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_placeholder( array $settings ) {
		$mode = $settings['strip_item_controls'] ?? 'full';
		?>
		<div class="kdna-checkout-strip kdna-checkout-strip--controls-<?php echo esc_attr( $mode ); ?> kdna-checkout-strip--skeleton">
			<div class="kdna-checkout-strip__items">
				<?php for ( $i = 0; $i < 3; $i++ ) : ?>
					<div class="kdna-checkout-strip__tile">
						<span class="kdna-checkout-strip__image"><span class="kdna-checkout-strip__image-ph"></span></span>
						<span class="kdna-checkout-strip__name"><?php echo esc_html__( 'Product name', 'kdna-checkout' ); ?></span>
						<span class="kdna-checkout-strip__qty-static" aria-hidden="true">&times;&nbsp;1</span>
						<span class="kdna-checkout-strip__controls">
							<input class="kdna-checkout-strip__qty" type="number" value="1" min="0" readonly aria-label="<?php echo esc_attr__( 'Quantity', 'kdna-checkout' ); ?>" />
							<button type="button" class="kdna-checkout-strip__remove" aria-label="<?php echo esc_attr__( 'Remove', 'kdna-checkout' ); ?>">&times;</button>
						</span>
					</div>
				<?php endfor; ?>
			</div>
			<div class="kdna-checkout-strip__meta">
				<?php if ( 'edit' === $mode ) : ?>
					<button type="button" class="kdna-checkout-strip__edit-link"><?php echo esc_html( $settings['strip_edit_label'] ?? __( 'Edit', 'kdna-checkout' ) ); ?></button>
				<?php endif; ?>
				<div class="kdna-checkout-strip__subtotal">
					<span class="kdna-checkout-strip__subtotal-label"><?php echo esc_html( $settings['strip_subtotal_label'] ?? __( 'Subtotal', 'kdna-checkout' ) ); ?></span>
					<span class="kdna-checkout-strip__subtotal-amount">&#163;0.00</span>
				</div>
			</div>
		</div>
		<?php
	}
}
