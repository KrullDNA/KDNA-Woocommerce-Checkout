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
 * Stage 3 adds the complete Style tab. Every selector is emitted
 * through {{WRAPPER}}, which Elementor resolves to the specific widget
 * instance ID, so multiple checkouts on one site can be styled
 * independently. No selector relies on Elementor wrapper divs and
 * .elementor-widget-container is never targeted.
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
	 * The Google Places script rides along only when the autocomplete
	 * feature is enabled and a key is stored (Stage 9).
	 *
	 * @return array
	 */
	public function get_script_depends() {
		$depends = array( 'kdna-checkout' );

		if ( class_exists( 'KDNA_Checkout_Autocomplete' ) && KDNA_Checkout_Autocomplete::is_enabled() ) {
			$depends[] = KDNA_Checkout_Autocomplete::SCRIPT_HANDLE;
		}

		return $depends;
	}

	/**
	 * Atomic architecture: no inner wrapper when optimised markup is active.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/* ================================================================== *
	 * Selector helpers
	 * ================================================================== */

	/**
	 * Selector list for checkout input fields (text inputs, textareas,
	 * selects and the select2 country/state boxes WooCommerce renders).
	 *
	 * @param bool $focus Whether to return the focus-state selectors.
	 * @return string Comma-separated selector list, each part carrying {{WRAPPER}}.
	 */
	private function input_selectors( $focus = false ) {
		if ( $focus ) {
			$parts = array(
				'{{WRAPPER}} .kdna-checkout .form-row .input-text:focus',
				'{{WRAPPER}} .kdna-checkout .form-row select:focus',
				'{{WRAPPER}} .kdna-checkout .form-row .select2-container--focus .select2-selection--single',
				'{{WRAPPER}} .kdna-checkout .form-row .select2-container--open .select2-selection--single',
			);
		} else {
			$parts = array(
				'{{WRAPPER}} .kdna-checkout .form-row .input-text',
				'{{WRAPPER}} .kdna-checkout .form-row select',
				'{{WRAPPER}} .kdna-checkout .form-row .select2-container .select2-selection--single',
			);
		}

		return implode( ', ', $parts );
	}

	/**
	 * Selector for the primary pay (Place order) button.
	 *
	 * @param string $state Empty for normal, 'hover' for hover/focus.
	 * @return string
	 */
	private function pay_button_selectors( $state = '' ) {
		if ( 'hover' === $state ) {
			return '{{WRAPPER}} .kdna-checkout #place_order:hover, {{WRAPPER}} .kdna-checkout #place_order:focus';
		}
		return '{{WRAPPER}} .kdna-checkout #place_order';
	}

	/* ================================================================== *
	 * Controls
	 * ================================================================== */

	/**
	 * Register Content and Style tab controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_layout_controls();
		$this->register_cart_strip_controls();
		$this->register_express_controls();
		$this->register_fields_controls();
		$this->register_coupon_controls();
		$this->register_trust_controls();
		$this->register_style_controls();
	}

	/**
	 * Content tab > Trust Signals (Stage 8).
	 *
	 * @return void
	 */
	private function register_trust_controls() {
		$this->start_controls_section(
			'section_trust',
			array(
				'label' => __( 'Trust Signals', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_trust',
			array(
				'label'        => __( 'Show trust block', 'kdna-checkout' ),
				'description'  => __( 'Payment method icons and a secure-checkout reassurance message.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'trust_position',
			array(
				'label'     => __( 'Position', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'summary' => __( 'In the order summary card, below the pay button', 'kdna-checkout' ),
					'main'    => __( 'Below the customer details', 'kdna-checkout' ),
					'bottom'  => __( 'Full width, below the checkout', 'kdna-checkout' ),
				),
				'default'   => 'summary',
				'condition' => array( 'show_trust' => 'yes' ),
			)
		);

		KDNA_Checkout_Trust::register_content_controls( $this, array( 'show_trust' => 'yes' ) );

		$this->end_controls_section();
	}

	/**
	 * The configurable checkout fields, as repeater select options.
	 *
	 * @return array
	 */
	private function field_options() {
		return array(
			'first_name'     => __( 'First name', 'kdna-checkout' ),
			'last_name'      => __( 'Last name', 'kdna-checkout' ),
			'company'        => __( 'Company', 'kdna-checkout' ),
			'country'        => __( 'Country / Region', 'kdna-checkout' ),
			'address_1'      => __( 'Street address', 'kdna-checkout' ),
			'address_2'      => __( 'Address line 2', 'kdna-checkout' ),
			'city'           => __( 'Town / City', 'kdna-checkout' ),
			'state'          => __( 'State / County', 'kdna-checkout' ),
			'postcode'       => __( 'Postcode', 'kdna-checkout' ),
			'phone'          => __( 'Phone', 'kdna-checkout' ),
			'email'          => __( 'Email (always shown)', 'kdna-checkout' ),
			'order_comments' => __( 'Order notes', 'kdna-checkout' ),
		);
	}

	/**
	 * Content tab > Fields & Account (Stage 6).
	 *
	 * @return void
	 */
	private function register_fields_controls() {
		$this->start_controls_section(
			'section_fields',
			array(
				'label' => __( 'Fields & Account', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_create_account',
			array(
				'label'        => __( 'Offer "create an account"', 'kdna-checkout' ),
				'description'  => __( 'Checkout is always guest by default. This adds an optional create-an-account checkbox near the end of the form.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-checkout' ),
				'label_off'    => __( 'Off', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'combine_names',
			array(
				'label'        => __( 'Combine first and last name', 'kdna-checkout' ),
				'description'  => __( 'One full-width name field. The order still receives first and last name.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-checkout' ),
				'label_off'    => __( 'Off', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'placeholders_as_labels',
			array(
				'label'        => __( 'Placeholders as labels', 'kdna-checkout' ),
				'description'  => __( 'Labels move into the fields as placeholders and stay available to screen readers.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-checkout' ),
				'label_off'    => __( 'Off', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'inline_validation',
			array(
				'label'        => __( 'Inline field validation', 'kdna-checkout' ),
				'description'  => __( 'Errors show per field as the shopper goes, not only on submit.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'kdna-checkout' ),
				'label_off'    => __( 'Off', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'field_key',
			array(
				'label'   => __( 'Field', 'kdna-checkout' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->field_options(),
				'default' => 'first_name',
			)
		);

		$repeater->add_control(
			'field_show',
			array(
				'label'        => __( 'Show', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$repeater->add_control(
			'field_label',
			array(
				'label'       => __( 'Custom label', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Leave blank for the default', 'kdna-checkout' ),
				'default'     => '',
			)
		);

		$default_rows = array();
		foreach ( array_keys( $this->field_options() ) as $key ) {
			$default_rows[] = array(
				'field_key'   => $key,
				'field_show'  => 'yes',
				'field_label' => '',
			);
		}

		$this->add_control(
			'checkout_fields_list',
			array(
				'label'       => __( 'Checkout fields', 'kdna-checkout' ),
				'description' => __( 'Drag to reorder. Hiding a field never loses order data: anything WooCommerce needs still submits a valid default.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => $default_rows,
				'title_field' => '{{{ field_key }}}',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab > Express Payments (Stage 5).
	 *
	 * @return void
	 */
	private function register_express_controls() {
		$this->start_controls_section(
			'section_express',
			array(
				'label' => __( 'Express Payments', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_express',
			array(
				'label'        => __( 'Show express payment row', 'kdna-checkout' ),
				'description'  => __( 'Express buttons from the active gateways (Apple Pay, Google Pay, PayPal, Stripe Link, Afterpay/Zip) gather here, above the form. Buttons only appear when their gateway offers them.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_express_divider',
			array(
				'label'        => __( 'Show divider', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'show_express' => 'yes' ),
			)
		);

		$this->add_control(
			'express_divider_text',
			array(
				'label'     => __( 'Divider text', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'or pay with card below', 'kdna-checkout' ),
				'condition' => array(
					'show_express'         => 'yes',
					'show_express_divider' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab > Coupon.
	 *
	 * @return void
	 */
	private function register_coupon_controls() {
		$this->start_controls_section(
			'section_coupon',
			array(
				'label' => __( 'Coupon', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_coupon',
			array(
				'label'        => __( 'Show coupon field', 'kdna-checkout' ),
				'description'  => __( 'The native WooCommerce "Have a coupon?" field. Turn off if you use a separate coupon widget.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'coupon_question_text',
			array(
				'label'       => __( 'Question text', 'kdna-checkout' ),
				'description' => __( 'The prompt before the link, e.g. "Have a coupon?". Leave blank for the WooCommerce default.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Have a coupon?', 'kdna-checkout' ),
				'condition'   => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_link_text',
			array(
				'label'       => __( 'Link text', 'kdna-checkout' ),
				'description' => __( 'The clickable link that opens the coupon field. Leave blank for the WooCommerce default.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Click here to enter your code', 'kdna-checkout' ),
				'condition'   => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_message_text',
			array(
				'label'       => __( 'Open message', 'kdna-checkout' ),
				'description' => __( 'The line shown above the field once the coupon area is open. Leave blank for the WooCommerce default.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'If you have a coupon code, please apply it below.', 'kdna-checkout' ),
				'condition'   => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_combined',
			array(
				'label'        => __( 'Combine into one box', 'kdna-checkout' ),
				'description'  => __( 'Wrap the question and the coupon field in a single box that expands when opened, instead of two separate boxes.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'One box', 'kdna-checkout' ),
				'label_off'    => __( 'Separate', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_position',
			array(
				'label'       => __( 'Coupon position', 'kdna-checkout' ),
				'description' => __( 'Where the "Have a coupon?" section sits in the checkout.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'top'     => __( 'Top of checkout (full width)', 'kdna-checkout' ),
					'billing' => __( 'Top of billing details', 'kdna-checkout' ),
					'payment' => __( 'Between order summary and payment', 'kdna-checkout' ),
				),
				'default'     => 'top',
				'condition'   => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'show_available_coupons',
			array(
				'label'        => __( 'Show available coupons', 'kdna-checkout' ),
				'description'  => __( 'The "Available Coupons" list that the KDNA Ecommerce Suite injects above the checkout. Turn off to hide it on this checkout.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'coupon_icon_heading',
			array(
				'label'     => __( 'Coupon bar icon', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_icon_mode',
			array(
				'label'       => __( 'Icon', 'kdna-checkout' ),
				'description' => __( 'The little icon on the "Have a coupon?" bar. Keep the theme default, swap in your own icon, or remove it.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array(
					'default' => __( 'Theme default', 'kdna-checkout' ),
					'custom'  => __( 'Custom icon', 'kdna-checkout' ),
					'none'    => __( 'No icon', 'kdna-checkout' ),
				),
				'default'     => 'default',
				'condition'   => array( 'show_coupon' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_icon',
			array(
				'label'     => __( 'Choose icon', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'condition' => array(
					'show_coupon'      => 'yes',
					'coupon_icon_mode' => 'custom',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab controls (unchanged since Stage 2).
	 *
	 * @return void
	 */
	private function register_layout_controls() {
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

		$this->add_control(
			'summary_separate_boxes',
			array(
				'label'        => __( 'Separate order, coupon & payment boxes', 'kdna-checkout' ),
				'description'  => __( 'Split the right column into its own boxes: the order summary, the coupon and the payment area each become a separate card instead of sitting inside one summary box. Style each under Order Summary Card, Coupon and Payment Area.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Separate', 'kdna-checkout' ),
				'label_off'    => __( 'One box', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'summary_stack_gap',
			array(
				'label'      => __( 'Gap between the boxes', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-summary-stack-gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'summary_separate_boxes' => 'yes' ),
			)
		);

		$this->add_control(
			'editor_live_preview',
			array(
				'label'        => __( 'Live checkout in the editor', 'kdna-checkout' ),
				'description'  => __( 'Render the real WooCommerce checkout in the Elementor editor instead of the skeleton, so you can see your styling on the true front end. You need a product in your cart, and payment gateways may occasionally misbehave while editing. If the layout looks unstyled after a change, reload the preview.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Live', 'kdna-checkout' ),
				'label_off'    => __( 'Skeleton', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content tab > Cart Strip (Stage 4).
	 *
	 * @return void
	 */
	private function register_cart_strip_controls() {
		$this->start_controls_section(
			'section_cart_strip',
			array(
				'label' => __( 'Cart Strip', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_cart_strip',
			array(
				'label'        => __( 'Show cart strip', 'kdna-checkout' ),
				'description'  => __( 'A sideways-scrolling row of product tiles at the very top of the checkout.', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'kdna-checkout' ),
				'label_off'    => __( 'Hide', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		// Shared strip behaviour controls (item controls, sticky, labels),
		// gated behind the show toggle. Same IDs as the standalone widget.
		KDNA_Checkout_Strip_Controls::register_content_controls( $this, array( 'show_cart_strip' => 'yes' ) );

		$this->end_controls_section();
	}

	/**
	 * The complete Style tab (Stage 3, cart strip sections added in Stage 4).
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$this->style_section_columns_spacing();
		$this->style_section_headings();
		$this->style_section_field_labels();
		$this->style_section_input_fields();
		$this->style_section_form_extras();
		$this->style_section_address_boxes();
		$this->style_section_summary_card();
		$this->style_section_summary_content();
		$this->style_section_payment_area();
		$this->style_section_pay_button();
		// Shared strip Style sections, gated behind the show toggle. Same
		// IDs/selectors as the standalone widget, so saved data matches.
		KDNA_Checkout_Strip_Controls::register_style_controls( $this, array( 'show_cart_strip' => 'yes' ) );
		$this->style_section_express_row();
		$this->style_section_express_divider();
		$this->style_section_order_bump();
		$this->style_section_coupon();
		$this->style_section_trust();
	}

	/**
	 * Style > Coupon (the native "Have a coupon?" field).
	 *
	 * @return void
	 */
	private function style_section_coupon() {
		$this->start_controls_section(
			'style_coupon',
			array(
				'label'     => __( 'Coupon Field', 'kdna-checkout' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_coupon' => 'yes' ),
			)
		);

		// Combined single-box styling (only when "Combine into one box" is on).
		// These feed CSS variables the stylesheet reads, so control values win
		// over the defaults regardless of stylesheet load order.
		$this->add_control(
			'coupon_box_heading',
			array(
				'label'     => __( 'Coupon box', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'condition' => array( 'coupon_combined' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_box_background',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-checkout-coupon-box-bg: {{VALUE}};',
				),
				'condition' => array( 'coupon_combined' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_box_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--kdna-checkout-coupon-box-border: {{VALUE}};',
				),
				'condition' => array( 'coupon_combined' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'coupon_box_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-coupon-box-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'coupon_combined' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'coupon_box_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-coupon-box-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( 'coupon_combined' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_toggle_heading',
			array(
				'label'     => __( '"Have a coupon?" bar', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'condition' => array( 'coupon_combined!' => 'yes' ),
			)
		);

		$this->add_control(
			'coupon_bar_background',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info' => 'background-color: {{VALUE}};',
				),
				'condition' => array( 'coupon_combined!' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'      => 'coupon_bar_border',
				'label'     => __( 'Border', 'kdna-checkout' ),
				'selector'  => '{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info',
				'condition' => array( 'coupon_combined!' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'coupon_bar_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( 'coupon_combined!' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'coupon_bar_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array( 'coupon_combined!' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'coupon_text_typography',
				'label'    => __( 'Text typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info',
			)
		);

		$this->add_control(
			'coupon_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'description' => __( 'The "Have a coupon?" prompt text.', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'coupon_link_typography',
				'label'    => __( 'Link typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info a',
			)
		);

		$this->add_control(
			'coupon_link_colour',
			array(
				'label'     => __( 'Link colour', 'kdna-checkout' ),
				'description' => __( 'The "Click here to enter your code" link.', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'coupon_icon_colour',
			array(
				'label'     => __( 'Icon colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info::before' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-checkout .kdna-checkout-coupon-icon'                                => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-checkout .kdna-checkout-coupon-icon svg'                            => 'fill: {{VALUE}};',
				),
				'condition' => array( 'coupon_icon_mode!' => 'none' ),
			)
		);

		$this->add_responsive_control(
			'coupon_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 64,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form-coupon-toggle .woocommerce-info::before' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .kdna-checkout .kdna-checkout-coupon-icon'                                => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .kdna-checkout .kdna-checkout-coupon-icon svg'                            => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'coupon_icon_mode!' => 'none' ),
			)
		);

		$this->add_control(
			'coupon_form_heading',
			array(
				'label'     => __( 'Coupon form', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'coupon_input_background',
			array(
				'label'     => __( 'Input background', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon .input-text' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'coupon_input_border',
				'label'    => __( 'Input border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout .checkout_coupon .input-text',
			)
		);

		$this->add_responsive_control(
			'coupon_input_radius',
			array(
				'label'      => __( 'Input border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon .input-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'coupon_button_tabs' );

		$this->start_controls_tab(
			'coupon_button_normal',
			array( 'label' => __( 'Button', 'kdna-checkout' ) )
		);

		$this->add_control(
			'coupon_button_text',
			array(
				'label'     => __( 'Button text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'coupon_button_bg',
			array(
				'label'     => __( 'Button background', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'coupon_button_border',
				'label'    => __( 'Button border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout .checkout_coupon button',
			)
		);

		$this->add_responsive_control(
			'coupon_button_radius',
			array(
				'label'      => __( 'Button border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'coupon_button_hover',
			array( 'label' => __( 'Button hover', 'kdna-checkout' ) )
		);

		$this->add_control(
			'coupon_button_text_hover',
			array(
				'label'     => __( 'Button text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon button:hover, {{WRAPPER}} .kdna-checkout .checkout_coupon button:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'coupon_button_bg_hover',
			array(
				'label'     => __( 'Button background', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .checkout_coupon button:hover, {{WRAPPER}} .kdna-checkout .checkout_coupon button:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Style > Trust Block (Stage 8, shared controls).
	 *
	 * @return void
	 */
	private function style_section_trust() {
		$this->start_controls_section(
			'style_trust',
			array(
				'label'     => __( 'Trust Block', 'kdna-checkout' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_trust' => 'yes' ),
			)
		);

		KDNA_Checkout_Trust::register_style_controls( $this );

		$this->end_controls_section();
	}

	/**
	 * Style > Order Bump (Stage 7).
	 *
	 * @return void
	 */
	private function style_section_order_bump() {
		$this->start_controls_section(
			'style_order_bump',
			array(
				'label' => __( 'Order Bump', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'bump_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-bump' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'bump_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-bump',
			)
		);

		$this->add_responsive_control(
			'bump_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-bump' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'bump_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-bump',
			)
		);

		$this->add_responsive_control(
			'bump_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-bump' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'bump_margin',
			array(
				'label'      => __( 'Margin', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-bump' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'bump_headline_typography',
				'label'     => __( 'Headline typography', 'kdna-checkout' ),
				'selector'  => '{{WRAPPER}} .kdna-checkout-bump__headline',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'bump_headline_colour',
			array(
				'label'     => __( 'Headline colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-bump__headline' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'bump_body_typography',
				'label'    => __( 'Description typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-bump__description',
			)
		);

		$this->add_control(
			'bump_body_colour',
			array(
				'label'     => __( 'Description colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-bump__description' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'bump_price_typography',
				'label'    => __( 'Price typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-bump__price',
			)
		);

		$this->add_control(
			'bump_price_colour',
			array(
				'label'     => __( 'Price colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-bump__price ins, {{WRAPPER}} .kdna-checkout-bump__price' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'bump_checkbox_accent',
			array(
				'label'     => __( 'Checkbox accent colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-bump__checkbox' => 'accent-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'bump_checkbox_size',
			array(
				'label'      => __( 'Checkbox size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 12,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-bump__checkbox' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'bump_image_size',
			array(
				'label'      => __( 'Image size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 32,
						'max' => 160,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-bump-image-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'bump_image_radius',
			array(
				'label'      => __( 'Image border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-bump__image img, {{WRAPPER}} .kdna-checkout-bump__image-ph' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Express Payment Row.
	 *
	 * @return void
	 */
	private function style_section_express_row() {
		$this->start_controls_section(
			'style_express_row',
			array(
				'label'     => __( 'Express Payment Row', 'kdna-checkout' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_express' => 'yes' ),
			)
		);

		$this->add_control(
			'express_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-express' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'express_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-express',
			)
		);

		$this->add_responsive_control(
			'express_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-express' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'express_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-express',
			)
		);

		$this->add_responsive_control(
			'express_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-express' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'express_margin',
			array(
				'label'      => __( 'Margin', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-express' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'express_button_gap',
			array(
				'label'      => __( 'Gap between buttons', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-express-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'express_button_min_width',
			array(
				'label'       => __( 'Button minimum width', 'kdna-checkout' ),
				'description' => __( 'Buttons narrower than this wrap onto a new line.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%' ),
				'range'       => array(
					'px' => array(
						'min' => 120,
						'max' => 600,
					),
				),
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-checkout-express-min-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Express Divider.
	 *
	 * @return void
	 */
	private function style_section_express_divider() {
		$this->start_controls_section(
			'style_express_divider',
			array(
				'label'     => __( 'Express Divider', 'kdna-checkout' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_express'         => 'yes',
					'show_express_divider' => 'yes',
				),
			)
		);

		$this->add_control(
			'express_divider_line_colour',
			array(
				'label'     => __( 'Line colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-express__divider' => '--kdna-checkout-express-divider-colour: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'express_divider_line_thickness',
			array(
				'label'      => __( 'Line thickness', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 1,
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-express__divider' => '--kdna-checkout-express-divider-thickness: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'express_divider_typography',
				'label'    => __( 'Text typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-express__divider-text',
			)
		);

		$this->add_control(
			'express_divider_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-express__divider-text' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'express_divider_text_gap',
			array(
				'label'      => __( 'Gap around the text', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-express__divider' => '--kdna-checkout-express-divider-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'express_divider_spacing',
			array(
				'label'      => __( 'Spacing above the divider', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-express__divider' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Columns & Spacing.
	 *
	 * Responsive controls for the column layout and the spacing between
	 * checkout sections and individual fields.
	 *
	 * @return void
	 */
	private function style_section_columns_spacing() {
		$this->start_controls_section(
			'style_columns',
			array(
				'label' => __( 'Columns & Spacing', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'summary_column_width',
			array(
				'label'      => __( 'Order summary column width', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 240,
						'max' => 720,
					),
					'%'  => array(
						'min' => 20,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-summary-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'summary_price_column_width',
			array(
				'label'       => __( 'Order table price column width', 'kdna-checkout' ),
				'description' => __( 'Narrow the price column in the order table so the product names on the left get more room.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%' ),
				'range'       => array(
					'px' => array(
						'min' => 60,
						'max' => 320,
					),
					'%'  => array(
						'min' => 12,
						'max' => 50,
					),
				),
				'selectors'   => array(
					// Fixed layout so the specified column width is actually honoured
					// (auto layout treats it only as a hint and ignores it here).
					'{{WRAPPER}} .kdna-checkout__summary .shop_table' => 'table-layout: fixed; width: 100%;',
					'{{WRAPPER}} .kdna-checkout__summary .shop_table th:last-child, {{WRAPPER}} .kdna-checkout__summary .shop_table td:last-child' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'column_gap',
			array(
				'label'      => __( 'Gap between the columns', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 160,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-column-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => __( 'Gap between stacked areas', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 120,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-row-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'section_spacing',
			array(
				'label'      => __( 'Spacing between checkout sections', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 120,
					),
				),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-billing-fields, {{WRAPPER}} .kdna-checkout .woocommerce-shipping-fields, {{WRAPPER}} .kdna-checkout .woocommerce-additional-fields' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'field_spacing',
			array(
				'label'      => __( 'Spacing between fields', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .form-row' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Headings.
	 *
	 * Covers every checkout heading: billing details, shipping,
	 * additional information and the order summary heading.
	 *
	 * @return void
	 */
	private function style_section_headings() {
		$this->start_controls_section(
			'style_headings',
			array(
				'label' => __( 'Headings', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout h3',
			)
		);

		$this->add_control(
			'heading_colour',
			array(
				'label'     => __( 'Colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout h3' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'heading_spacing',
			array(
				'label'      => __( 'Spacing below headings', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout h3' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'heading_spacing_above',
			array(
				'label'      => __( 'Spacing above headings', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout h3' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Field Labels.
	 *
	 * @return void
	 */
	private function style_section_field_labels() {
		$this->start_controls_section(
			'style_labels',
			array(
				'label' => __( 'Field Labels', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout .form-row label',
			)
		);

		$this->add_control(
			'label_colour',
			array(
				'label'     => __( 'Colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .form-row label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'label_required_colour',
			array(
				'label'     => __( 'Required mark colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .form-row label .required' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'label_spacing',
			array(
				'label'      => __( 'Spacing below labels', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout .form-row label' => 'margin-bottom: {{SIZE}}{{UNIT}}; display: block;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Input Fields.
	 *
	 * Full box coverage (background, complete border group, separate
	 * radius, box-shadow, padding) plus typography, placeholder colour
	 * and a distinct, fully controllable focus state.
	 *
	 * @return void
	 */
	/**
	 * Style > Form Extras.
	 *
	 * Everything that is not a plain text input or heading: the required
	 * asterisk, the country/state dropdowns, checkboxes (terms, ship-to,
	 * create-account) and the "Ship to a different address?" toggle row.
	 *
	 * @return void
	 */
	private function style_section_form_extras() {
		$this->start_controls_section(
			'style_form_extras',
			array(
				'label' => __( 'Form Extras', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		// --- Required asterisk ---
		$this->add_control(
			'required_asterisk_heading',
			array(
				'label' => __( 'Required asterisk', 'kdna-checkout' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);
		$this->add_control(
			'required_asterisk_colour',
			array(
				'label'     => __( 'Colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .required, {{WRAPPER}} .kdna-checkout abbr.required' => 'color: {{VALUE}};',
				),
			)
		);

		// --- Dropdowns (country / state selects) ---
		$select = '{{WRAPPER}} .kdna-checkout select, {{WRAPPER}} .kdna-checkout .select2-container .select2-selection--single';
		$this->add_style_heading( 'select_heading', __( 'Dropdowns (country / state)', 'kdna-checkout' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'select_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => $select . ', {{WRAPPER}} .kdna-checkout .select2-container .select2-selection__rendered',
			)
		);
		$this->add_control(
			'select_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$select . ', {{WRAPPER}} .kdna-checkout .select2-container .select2-selection__rendered' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'select_background',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$select => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'select_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => $select,
			)
		);
		$this->add_responsive_control(
			'select_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					$select => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'select_arrow_colour',
			array(
				'label'     => __( 'Arrow colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .select2-container .select2-selection__arrow b' => 'border-top-color: {{VALUE}};',
				),
			)
		);

		// --- Checkboxes ---
		$checkbox = '{{WRAPPER}} .kdna-checkout input[type="checkbox"]';
		$this->add_style_heading( 'checkbox_heading', __( 'Checkboxes (terms, ship-to, account)', 'kdna-checkout' ) );
		$this->add_control(
			'checkbox_accent',
			array(
				'label'     => __( 'Tick / accent colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$checkbox => 'accent-color: {{VALUE}};',
				),
			)
		);
		$this->add_responsive_control(
			'checkbox_size',
			array(
				'label'      => __( 'Size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 10,
						'max' => 40,
					),
				),
				'selectors'  => array(
					$checkbox => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'checkbox_label_typography',
				'label'    => __( 'Label typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout .woocommerce-form__label-for-checkbox, {{WRAPPER}} .kdna-checkout .woocommerce-terms-and-conditions-wrapper label, {{WRAPPER}} .kdna-checkout #ship-to-different-address label',
			)
		);
		$this->add_control(
			'checkbox_label_colour',
			array(
				'label'     => __( 'Label colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .woocommerce-form__label-for-checkbox, {{WRAPPER}} .kdna-checkout .woocommerce-terms-and-conditions-wrapper label, {{WRAPPER}} .kdna-checkout #ship-to-different-address label' => 'color: {{VALUE}};',
				),
			)
		);

		// --- "Ship to a different address?" toggle row ---
		$this->add_style_heading( 'ship_toggle_heading', __( '"Ship to a different address?" row', 'kdna-checkout' ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'ship_toggle_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout #ship-to-different-address',
			)
		);
		$this->add_control(
			'ship_toggle_colour',
			array(
				'label'     => __( 'Colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout #ship-to-different-address, {{WRAPPER}} .kdna-checkout #ship-to-different-address label' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_responsive_control(
			'ship_toggle_spacing',
			array(
				'label'      => __( 'Spacing below', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout #ship-to-different-address' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	private function style_section_input_fields() {
		$this->start_controls_section(
			'style_inputs',
			array(
				'label' => __( 'Input Fields', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => $this->input_selectors(),
			)
		);

		$this->add_control(
			'input_placeholder_colour',
			array(
				'label'     => __( 'Placeholder colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout .form-row .input-text::placeholder' => 'color: {{VALUE}};',
				),
			)
		);

		$this->start_controls_tabs( 'input_state_tabs' );

		$this->start_controls_tab(
			'input_tab_normal',
			array( 'label' => __( 'Normal', 'kdna-checkout' ) )
		);

		$this->add_control(
			'input_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->input_selectors() => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->input_selectors() . ', {{WRAPPER}} .kdna-checkout__ph-field' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => $this->input_selectors() . ', {{WRAPPER}} .kdna-checkout__ph-field',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'input_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => $this->input_selectors(),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'input_tab_focus',
			array( 'label' => __( 'Focus', 'kdna-checkout' ) )
		);

		$this->add_control(
			'input_focus_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->input_selectors( true ) => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_focus_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->input_selectors( true ) => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_focus_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->input_selectors( true ) => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'input_focus_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => $this->input_selectors( true ),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control(
			'input_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'separator'  => 'before',
				'selectors'  => array(
					$this->input_selectors() . ', {{WRAPPER}} .kdna-checkout__ph-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'input_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					$this->input_selectors() => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; height: auto;',
				),
			)
		);

		$this->add_control(
			'input_error_colour',
			array(
				'label'       => __( 'Validation error colour', 'kdna-checkout' ),
				'description' => __( 'Colours the inline error message and the invalid field border.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::COLOR,
				'separator'   => 'before',
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-checkout-error-colour: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_error_typography',
				'label'    => __( 'Validation error typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-field-error',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Add a reusable "box" group of style controls (background, border, radius,
	 * padding and box-shadow) for a given selector. Keeps every boxed area of
	 * the checkout consistent and fully styleable.
	 *
	 * @param string $prefix   Unique control-id prefix.
	 * @param string $selector CSS selector the controls target.
	 * @return void
	 */
	private function add_box_controls( $prefix, $selector ) {
		$this->add_control(
			$prefix . '_background',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$selector => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => $prefix . '_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => $selector,
			)
		);

		$this->add_responsive_control(
			$prefix . '_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			$prefix . '_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => $prefix . '_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => $selector,
			)
		);
	}

	/**
	 * Add a heading control to break a style section into labelled groups.
	 *
	 * @param string $id    Control id.
	 * @param string $label Heading label.
	 * @return void
	 */
	private function add_style_heading( $id, $label ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
	}

	/**
	 * Style > Address Boxes.
	 *
	 * Independent card/box styling for the billing, shipping and order-notes
	 * areas in the left column, so each can have its own border like the order
	 * summary card. Off by default (no border/background) until styled.
	 *
	 * @return void
	 */
	private function style_section_address_boxes() {
		$this->start_controls_section(
			'style_address_boxes',
			array(
				'label' => __( 'Address Boxes', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'billing_box_heading',
			array(
				'label' => __( 'Billing details box', 'kdna-checkout' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);
		$this->add_box_controls( 'billing_box', '{{WRAPPER}} .kdna-checkout__main .woocommerce-billing-fields' );

		$this->add_style_heading( 'shipping_box_heading', __( 'Shipping details box', 'kdna-checkout' ) );
		$this->add_box_controls( 'shipping_box', '{{WRAPPER}} .kdna-checkout__main .woocommerce-shipping-fields' );

		$this->add_style_heading( 'notes_box_heading', __( 'Order notes box', 'kdna-checkout' ) );
		$this->add_box_controls( 'notes_box', '{{WRAPPER}} .kdna-checkout__main .woocommerce-additional-fields' );

		$this->end_controls_section();
	}

	/**
	 * Style > Payment Area.
	 *
	 * Box styling around the payment methods block plus the terms, privacy and
	 * "no payment methods" copy that sits with it.
	 *
	 * @return void
	 */
	private function style_section_payment_area() {
		$this->start_controls_section(
			'style_payment_area',
			array(
				'label' => __( 'Payment Area', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'payment_box_heading',
			array(
				'label' => __( 'Payment box', 'kdna-checkout' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);
		$this->add_box_controls( 'payment_box', '{{WRAPPER}} .kdna-checkout__summary #payment' );

		$this->add_style_heading( 'payment_text_heading', __( 'Text', 'kdna-checkout' ) );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'payment_text_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout__summary #payment, {{WRAPPER}} .kdna-checkout__summary .woocommerce-privacy-policy-text, {{WRAPPER}} .kdna-checkout__summary .woocommerce-terms-and-conditions-wrapper',
			)
		);

		$this->add_control(
			'payment_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary #payment, {{WRAPPER}} .kdna-checkout__summary .woocommerce-privacy-policy-text, {{WRAPPER}} .kdna-checkout__summary .woocommerce-terms-and-conditions-wrapper' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'payment_link_colour',
			array(
				'label'     => __( 'Link colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary #payment a, {{WRAPPER}} .kdna-checkout__summary .woocommerce-privacy-policy-text a, {{WRAPPER}} .kdna-checkout__summary .woocommerce-terms-and-conditions-wrapper a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Order Summary Card.
	 *
	 * The full box group for the card plus its sticky top offset.
	 *
	 * @return void
	 */
	private function style_section_summary_card() {
		$this->start_controls_section(
			'style_summary_card',
			array(
				'label' => __( 'Order Summary Card', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'summary_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__order-card, {{WRAPPER}} .kdna-checkout__ph-summary' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'summary_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__order-card, {{WRAPPER}} .kdna-checkout__ph-summary',
			)
		);

		$this->add_responsive_control(
			'summary_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__order-card, {{WRAPPER}} .kdna-checkout__ph-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'summary_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__order-card, {{WRAPPER}} .kdna-checkout__ph-summary',
			)
		);

		$this->add_responsive_control(
			'summary_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__order-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'summary_margin',
			array(
				'label'      => __( 'Margin', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout__summary' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'summary_sticky_offset',
			array(
				'label'       => __( 'Sticky top offset', 'kdna-checkout' ),
				'description' => __( 'Distance from the top of the screen while the card is stuck. Allow room for a fixed site header.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'em' ),
				'range'       => array(
					'px' => array(
						'min' => 0,
						'max' => 240,
					),
				),
				'separator'   => 'before',
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-checkout-sticky-offset: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style > Order Summary Text & Totals.
	 *
	 * @return void
	 */
	private function style_section_summary_content() {
		$this->start_controls_section(
			'style_summary_content',
			array(
				'label' => __( 'Order Summary Text & Totals', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'summary_text_typography',
				'label'    => __( 'Text typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout__summary .shop_table td, {{WRAPPER}} .kdna-checkout__summary .shop_table th',
			)
		);

		$this->add_control(
			'summary_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table td, {{WRAPPER}} .kdna-checkout__summary .shop_table th' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'summary_totals_typography',
				'label'     => __( 'Totals typography', 'kdna-checkout' ),
				'selector'  => '{{WRAPPER}} .kdna-checkout__summary .shop_table .order-total td, {{WRAPPER}} .kdna-checkout__summary .shop_table .order-total th',
			)
		);

		$this->add_control(
			'summary_totals_colour',
			array(
				'label'     => __( 'Totals colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table .order-total td, {{WRAPPER}} .kdna-checkout__summary .shop_table .order-total th' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_style_heading( 'summary_rows_heading', __( 'Individual rows', 'kdna-checkout' ) );

		$this->add_control(
			'summary_product_colour',
			array(
				'label'     => __( 'Product name colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table .cart_item .product-name' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'summary_product_price_colour',
			array(
				'label'     => __( 'Product price colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table .cart_item .product-total' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'summary_subtotal_row_colour',
			array(
				'label'     => __( 'Subtotal row colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table .cart-subtotal th, {{WRAPPER}} .kdna-checkout__summary .shop_table .cart-subtotal td' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'summary_shipping_row_colour',
			array(
				'label'     => __( 'Shipping row colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table .woocommerce-shipping-totals th, {{WRAPPER}} .kdna-checkout__summary .shop_table .woocommerce-shipping-totals td, {{WRAPPER}} .kdna-checkout__summary .shop_table tr.shipping th, {{WRAPPER}} .kdna-checkout__summary .shop_table tr.shipping td' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'summary_separator_colour',
			array(
				'label'     => __( 'Row separator colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table td, {{WRAPPER}} .kdna-checkout__summary .shop_table th, {{WRAPPER}} .kdna-checkout__summary .shop_table tfoot' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'summary_cell_padding',
			array(
				'label'      => __( 'Cell padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout__summary .shop_table td, {{WRAPPER}} .kdna-checkout__summary .shop_table th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_style_heading( 'order_table_box_heading', __( 'Order table box', 'kdna-checkout' ) );
		$this->add_box_controls( 'order_table_box', '{{WRAPPER}} .kdna-checkout__summary .shop_table' );

		$this->end_controls_section();
	}

	/**
	 * Style > Pay Button.
	 *
	 * Typography, optional icon, and full normal/hover box styling for
	 * the primary Place Order button. Hover styling also applies on
	 * keyboard focus so the state is always reachable.
	 *
	 * @return void
	 */
	private function style_section_pay_button() {
		$this->start_controls_section(
			'style_pay_button',
			array(
				'label' => __( 'Pay Button', 'kdna-checkout' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'pay_button_full_width',
			array(
				'label'        => __( 'Full width', 'kdna-checkout' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'kdna-checkout' ),
				'label_off'    => __( 'No', 'kdna-checkout' ),
				'return_value' => 'yes',
				'default'      => '',
				'selectors'    => array(
					$this->pay_button_selectors() => 'width: 100%; display: block;',
				),
			)
		);

		$this->add_responsive_control(
			'pay_button_align',
			array(
				'label'     => __( 'Alignment', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'kdna-checkout' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'kdna-checkout' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'kdna-checkout' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout #order_review .form-row.place-order, {{WRAPPER}} .kdna-checkout .woocommerce-checkout-payment' => 'display: flex; flex-direction: column; align-items: {{VALUE}};',
				),
				'condition' => array( 'pay_button_full_width!' => 'yes' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'pay_button_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => $this->pay_button_selectors(),
			)
		);

		$this->add_control(
			'pay_button_icon',
			array(
				'label'       => __( 'Icon', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
			)
		);

		$this->add_control(
			'pay_button_icon_position',
			array(
				'label'     => __( 'Icon position', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'before' => __( 'Before the text', 'kdna-checkout' ),
					'after'  => __( 'After the text', 'kdna-checkout' ),
				),
				'default'   => 'before',
				'condition' => array( 'pay_button_icon[value]!' => '' ),
			)
		);

		$this->add_control(
			'pay_button_icon_spacing',
			array(
				'label'      => __( 'Icon spacing', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'condition'  => array( 'pay_button_icon[value]!' => '' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-pay-icon-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'pay_button_icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 48,
					),
				),
				'condition'  => array( 'pay_button_icon[value]!' => '' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-pay-icon-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'pay_button_state_tabs' );

		$this->start_controls_tab(
			'pay_button_tab_normal',
			array( 'label' => __( 'Normal', 'kdna-checkout' ) )
		);

		$this->add_control(
			'pay_button_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->pay_button_selectors() => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pay_button_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->pay_button_selectors() . ', {{WRAPPER}} .kdna-checkout__ph-button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'pay_button_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => $this->pay_button_selectors() . ', {{WRAPPER}} .kdna-checkout__ph-button',
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'pay_button_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => $this->pay_button_selectors(),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pay_button_tab_hover',
			array( 'label' => __( 'Hover', 'kdna-checkout' ) )
		);

		$this->add_control(
			'pay_button_hover_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->pay_button_selectors( 'hover' ) => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pay_button_hover_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->pay_button_selectors( 'hover' ) => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pay_button_hover_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					$this->pay_button_selectors( 'hover' ) => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'pay_button_hover_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => $this->pay_button_selectors( 'hover' ),
			)
		);

		$this->add_control(
			'pay_button_transition',
			array(
				'label'      => __( 'Transition duration (s)', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 2,
						'step' => 0.1,
					),
				),
				'selectors'  => array(
					$this->pay_button_selectors() => 'transition: all {{SIZE}}s ease;',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_responsive_control(
			'pay_button_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'separator'  => 'before',
				'selectors'  => array(
					$this->pay_button_selectors() . ', {{WRAPPER}} .kdna-checkout__ph-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'pay_button_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					$this->pay_button_selectors() => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'pay_button_margin',
			array(
				'label'      => __( 'Margin', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					$this->pay_button_selectors() => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/* ================================================================== *
	 * Rendering
	 * ================================================================== */

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$has_icon = ! empty( $settings['pay_button_icon']['value'] );

		$classes = array( 'kdna-checkout' );

		if ( isset( $settings['sticky_summary'] ) && 'yes' === $settings['sticky_summary'] ) {
			$classes[] = 'kdna-checkout--sticky';
		}

		if ( isset( $settings['mobile_summary_position'] ) && 'above' === $settings['mobile_summary_position'] ) {
			$classes[] = 'kdna-checkout--summary-above';
		}

		if ( $has_icon ) {
			$classes[] = 'kdna-checkout--pay-icon';
		}

		if ( ! isset( $settings['inline_validation'] ) || 'yes' === $settings['inline_validation'] ) {
			$classes[] = 'kdna-checkout--validate';
		}

		if ( isset( $settings['show_coupon'] ) && 'yes' !== $settings['show_coupon'] ) {
			$classes[] = 'kdna-checkout--hide-coupon';
		}

		if ( isset( $settings['show_available_coupons'] ) && 'yes' !== $settings['show_available_coupons'] ) {
			$classes[] = 'kdna-checkout--hide-available-coupons';
		}

		$coupon_icon_mode  = $settings['coupon_icon_mode'] ?? 'default';
		$has_coupon_icon   = 'custom' === $coupon_icon_mode && ! empty( $settings['coupon_icon']['value'] );
		$show_coupon_field = ! isset( $settings['show_coupon'] ) || 'yes' === $settings['show_coupon'];
		if ( $show_coupon_field && 'none' === $coupon_icon_mode ) {
			$classes[] = 'kdna-checkout--coupon-icon-hidden';
		}
		if ( $show_coupon_field && $has_coupon_icon ) {
			$classes[] = 'kdna-checkout--coupon-icon-custom';
		}

		$coupon_position = $settings['coupon_position'] ?? 'top';
		if ( ! in_array( $coupon_position, array( 'top', 'billing', 'payment' ), true ) ) {
			$coupon_position = 'top';
		}
		if ( $show_coupon_field ) {
			$classes[] = 'kdna-checkout--coupon-pos-' . $coupon_position;
		}
		if ( $show_coupon_field && isset( $settings['coupon_combined'] ) && 'yes' === $settings['coupon_combined'] ) {
			$classes[] = 'kdna-checkout--coupon-combined';
		}

		$separate_boxes = isset( $settings['summary_separate_boxes'] ) && 'yes' === $settings['summary_separate_boxes'];
		if ( $separate_boxes ) {
			$classes[] = 'kdna-checkout--separate-summary';
		}

		$editor_live = false;
		if ( $this->is_editor_context() ) {
			$live_wanted = isset( $settings['editor_live_preview'] ) && 'yes' === $settings['editor_live_preview'];
			$cart_ready  = $live_wanted && function_exists( 'WC' ) && WC() && WC()->cart && ! WC()->cart->is_empty();
			if ( ! $live_wanted || ! $cart_ready ) {
				// Skeleton preview. When live was requested but the cart is
				// empty, tell the editor why so it is not a surprise.
				$this->render_placeholder( $classes, $live_wanted && ! $cart_ready );
				return;
			}
			// Live editor preview: Elementor does not reliably run the front-end
			// reflow script in the editor canvas, so reflow the checkout markup
			// server-side and flag the wrapper ready so the two-column CSS
			// engages immediately (and the front-end JS leaves it alone).
			$editor_live = true;
			$classes[]   = 'kdna-checkout--ready';
			$classes[]   = 'kdna-checkout--editor-live';
		}

		// Fail-safe: never output checkout markup without WooCommerce.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		// Fields & account configuration (Stage 6) must be in place
		// before the checkout shortcode builds its fields.
		if ( class_exists( 'KDNA_Checkout_Fields' ) ) {
			KDNA_Checkout_Fields::set_config( $this->fields_config( $settings ) );
		}

		printf(
			'<div class="%s" data-coupon-position="%s" data-separate-summary="%s">',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $coupon_position ),
			esc_attr( $separate_boxes ? 'yes' : '' )
		);

		if ( $has_icon ) {
			$this->render_pay_icon_template( $settings );
		}

		// Custom coupon-bar icon: rendered into a <template> and moved onto
		// the "Have a coupon?" bar by the widget JS (the bar is native
		// WooCommerce markup we do not render).
		if ( $has_coupon_icon ) {
			printf( '<template class="kdna-checkout__coupon-icon-tpl">' );
			\Elementor\Icons_Manager::render_icon( $settings['coupon_icon'], array( 'aria-hidden' => 'true' ) );
			echo '</template>';
		}

		// Cart strip (Stage 4): the very top of the checkout, above the
		// express payment row.
		if ( $this->show_cart_strip( $settings ) && class_exists( 'KDNA_Checkout_Cart_Strip' ) ) {
			echo KDNA_Checkout_Cart_Strip::render( $this->strip_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Strip markup is escaped where it is built.
		}

		// Express payment row (Stage 5): below the strip, above the form.
		// Hidden until the widget JS confirms a gateway button is visible.
		if ( $this->show_express( $settings ) && class_exists( 'KDNA_Checkout_Express' ) ) {
			echo KDNA_Checkout_Express::render( $this->express_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Express row markup is escaped where it is built.
		}

		// Custom coupon copy: filter WooCommerce's toggle message and the
		// "apply it below" line, scoped to this checkout's shortcode only.
		$coupon_filters = $show_coupon_field ? $this->add_coupon_copy_filters( $settings ) : array();

		// Native WooCommerce classic shortcode checkout, reflowed by the widget CSS/JS.
		$checkout_html = do_shortcode( '[woocommerce_checkout]' );
		if ( $editor_live ) {
			$checkout_html = $this->reflow_html_for_editor( $checkout_html, $show_coupon_field ? $coupon_position : 'top', $separate_boxes );
		}
		echo $checkout_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce renders and escapes its own checkout markup.

		$this->remove_coupon_copy_filters( $coupon_filters );

		// Trust signals block (Stage 8): rendered after the form (outside
		// every AJAX fragment, so it always survives totals refreshes) and
		// relocated into the chosen position by the widget JS.
		if ( $this->show_trust( $settings ) && class_exists( 'KDNA_Checkout_Trust' ) ) {
			$position = $settings['trust_position'] ?? 'summary';
			if ( ! in_array( $position, array( 'summary', 'main', 'bottom' ), true ) ) {
				$position = 'summary';
			}
			echo KDNA_Checkout_Trust::render( $settings, $position ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trust markup is escaped where it is built.
		}

		echo '</div>';
	}

	/**
	 * Register filters that rewrite WooCommerce's coupon copy from the widget
	 * settings, for the duration of the checkout shortcode. Returns the
	 * callables so they can be removed again straight after.
	 *
	 * @param array $settings Widget settings.
	 * @return array Callables keyed by hook ('message' and/or 'gettext').
	 */
	private function add_coupon_copy_filters( array $settings ) {
		$question = isset( $settings['coupon_question_text'] ) ? trim( (string) $settings['coupon_question_text'] ) : '';
		$link     = isset( $settings['coupon_link_text'] ) ? trim( (string) $settings['coupon_link_text'] ) : '';
		$message  = isset( $settings['coupon_message_text'] ) ? trim( (string) $settings['coupon_message_text'] ) : '';

		$filters = array();

		if ( '' !== $question || '' !== $link ) {
			$filters['message'] = static function () use ( $question, $link ) {
				$q = '' !== $question ? $question : __( 'Have a coupon?', 'woocommerce' );
				$l = '' !== $link ? $link : __( 'Click here to enter your code', 'woocommerce' );
				return esc_html( $q ) . ' <a href="#" class="showcoupon">' . esc_html( $l ) . '</a>';
			};
			add_filter( 'woocommerce_checkout_coupon_message', $filters['message'] );
		}

		if ( '' !== $message ) {
			$filters['gettext'] = static function ( $translated, $text, $domain ) use ( $message ) {
				if ( 'woocommerce' === $domain && 'If you have a coupon code, please apply it below.' === $text ) {
					return $message;
				}
				return $translated;
			};
			add_filter( 'gettext', $filters['gettext'], 10, 3 );
		}

		return $filters;
	}

	/**
	 * Remove the coupon-copy filters registered by add_coupon_copy_filters().
	 *
	 * @param array $filters Callables keyed by hook, as returned above.
	 * @return void
	 */
	private function remove_coupon_copy_filters( array $filters ) {
		if ( isset( $filters['message'] ) ) {
			remove_filter( 'woocommerce_checkout_coupon_message', $filters['message'] );
		}
		if ( isset( $filters['gettext'] ) ) {
			remove_filter( 'gettext', $filters['gettext'], 10 );
		}
	}

	/**
	 * Reflow the checkout markup into the two-column structure server-side.
	 *
	 * The front-end JS does this on the live site, but Elementor does not
	 * reliably run front-end scripts in the editor canvas, so for the live
	 * editor preview we rearrange the DOM here: everything in the form except
	 * the order-review heading and table moves into .kdna-checkout__main, and
	 * the heading + review move into .kdna-checkout__summary. On any parsing
	 * problem it returns the markup untouched (fail-safe).
	 *
	 * @param string $html The checkout shortcode output.
	 * @return string
	 */
	private function reflow_html_for_editor( $html, $coupon_position = 'top', $separate = false ) {
		$html = (string) $html;
		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return $html;
		}

		$wrapped = '<div id="kdna-reflow-root">' . $html . '</div>';
		// Keep UTF-8 intact under libxml's ISO-8859-1 assumption.
		if ( function_exists( 'mb_encode_numericentity' ) ) {
			$wrapped = mb_encode_numericentity( $wrapped, array( 0x80, 0x10FFFF, 0, 0xFFFFFF ), 'UTF-8' );
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$ok   = $doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok ) {
			return $html;
		}

		$xpath = new DOMXPath( $doc );
		$forms = $xpath->query( "//form[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-checkout ')]" );
		$form  = $forms->length ? $forms->item( 0 ) : null;
		if ( ! $form ) {
			return $html;
		}

		$review  = $xpath->query( ".//*[@id='order_review']", $form )->item( 0 );
		$heading = $xpath->query( ".//*[@id='order_review_heading']", $form )->item( 0 );
		if ( ! $review ) {
			return $html;
		}

		$main = $doc->createElement( 'div' );
		$main->setAttribute( 'class', 'kdna-checkout__main' );
		$summary = $doc->createElement( 'div' );
		$summary->setAttribute( 'class', 'kdna-checkout__summary' );

		foreach ( iterator_to_array( $form->childNodes ) as $node ) {
			if ( $node === $heading || $node === $review ) {
				continue;
			}
			$main->appendChild( $node );
		}
		if ( $heading ) {
			$summary->appendChild( $heading );
		}
		$summary->appendChild( $review );
		$form->appendChild( $main );
		$form->appendChild( $summary );

		$root = $xpath->query( "//*[@id='kdna-reflow-root']" )->item( 0 );
		if ( ! $root ) {
			return $html;
		}

		// Optional: split order / coupon / payment into their own boxes by
		// wrapping the heading + review table in an order card and lifting the
		// payment block out to sit beside it in the column.
		if ( $separate ) {
			$payment    = $xpath->query( ".//*[@id='payment']", $review )->item( 0 );
			$order_card = $doc->createElement( 'div' );
			$order_card->setAttribute( 'class', 'kdna-checkout__order-card' );
			if ( $heading ) {
				$order_card->appendChild( $heading );
			}
			$order_card->appendChild( $review );
			$summary->appendChild( $order_card );
			if ( $payment ) {
				$summary->appendChild( $payment );
			}
		}

		// Mirror the front-end coupon positioning for the editor: wrap the
		// native coupon toggle + form in a slot and move it to the chosen spot.
		$toggle      = $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-form-coupon-toggle ')]", $root )->item( 0 );
		$coupon_form = $xpath->query( ".//form[contains(concat(' ', normalize-space(@class), ' '), ' checkout_coupon ')]", $root )->item( 0 );
		if ( $toggle || $coupon_form ) {
			$slot = $doc->createElement( 'div' );
			$slot->setAttribute( 'class', 'kdna-checkout__coupon-slot' );
			if ( $toggle ) {
				$slot->appendChild( $toggle );
			}
			if ( $coupon_form ) {
				$slot->appendChild( $coupon_form );
			}
			if ( 'billing' === $coupon_position && $main->firstChild ) {
				$main->insertBefore( $slot, $main->firstChild );
			} elseif ( 'payment' === $coupon_position && ( $payment = $xpath->query( ".//*[@id='payment']", $summary )->item( 0 ) ) && $payment->parentNode ) {
				$payment->parentNode->insertBefore( $slot, $payment );
			} elseif ( $form->parentNode ) {
				// Default 'top': just before the two-column form.
				$form->parentNode->insertBefore( $slot, $form );
			}
		}

		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		return $out;
	}

	/**
	 * Whether the trust block is enabled in the widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return bool
	 */
	private function show_trust( array $settings ) {
		return ! isset( $settings['show_trust'] ) || 'yes' === $settings['show_trust'];
	}

	/**
	 * Whether the cart strip is enabled in the widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return bool
	 */
	private function show_cart_strip( array $settings ) {
		return isset( $settings['show_cart_strip'] ) && 'yes' === $settings['show_cart_strip'];
	}

	/**
	 * Map widget settings to the checkout fields configuration.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function fields_config( array $settings ) {
		$rows = array();
		foreach ( (array) ( $settings['checkout_fields_list'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[] = array(
				'key'   => $row['field_key'] ?? '',
				'show'  => 'yes' === ( $row['field_show'] ?? 'yes' ),
				'label' => $row['field_label'] ?? '',
			);
		}

		return array(
			'enabled'                => true,
			'create_account'         => 'yes' === ( $settings['show_create_account'] ?? 'yes' ),
			'combine_names'          => 'yes' === ( $settings['combine_names'] ?? '' ),
			'placeholders_as_labels' => 'yes' === ( $settings['placeholders_as_labels'] ?? '' ),
			'fields'                 => $rows,
		);
	}

	/**
	 * Whether the express payment row is enabled in the widget settings.
	 *
	 * @param array $settings Widget settings.
	 * @return bool
	 */
	private function show_express( array $settings ) {
		return isset( $settings['show_express'] ) && 'yes' === $settings['show_express'];
	}

	/**
	 * Map widget settings to express row render arguments.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function express_args( array $settings ) {
		return array(
			'show_divider' => isset( $settings['show_express_divider'] ) && 'yes' === $settings['show_express_divider'],
			'divider_text' => $settings['express_divider_text'] ?? '',
		);
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
			'shrink'         => $settings['strip_shrink_sticky'] ?? '',
			'link_products'  => $settings['strip_link_products'] ?? '',
			'link_new_tab'   => $settings['strip_link_new_tab'] ?? '',
			'subtotal_label' => $settings['strip_subtotal_label'] ?? '',
			'edit_label'     => $settings['strip_edit_label'] ?? '',
			'done_label'     => $settings['strip_done_label'] ?? '',
		);
	}

	/**
	 * Output the inert pay-button icon template.
	 *
	 * The widget JS clones this into the Place Order button on load and
	 * again after every WooCommerce fragment refresh, since WooCommerce
	 * rebuilds the button during AJAX updates.
	 *
	 * @param array $settings Widget settings.
	 * @return void
	 */
	private function render_pay_icon_template( array $settings ) {
		$position = 'after' === ( $settings['pay_button_icon_position'] ?? 'before' ) ? 'after' : 'before';

		printf( '<template class="kdna-checkout__pay-icon-tpl" data-position="%s">', esc_attr( $position ) );
		\Elementor\Icons_Manager::render_icon( $settings['pay_button_icon'], array( 'aria-hidden' => 'true' ) );
		echo '</template>';
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
	 * live checkout, so the editor never runs checkout scripts. Key box
	 * controls (inputs, summary card, pay button) also target the
	 * skeleton so restyling gives live feedback in the editor.
	 *
	 * @param array $classes         Wrapper classes from the current settings.
	 * @param bool  $live_empty_cart Live preview was requested but the cart is empty.
	 * @return void
	 */
	private function render_placeholder( array $classes, $live_empty_cart = false ) {
		$classes[] = 'kdna-checkout--editor';
		$settings  = $this->get_settings_for_display();
		$note      = $live_empty_cart
			? __( 'Live preview is on, but your cart is empty. Add a product to your cart, then reload the preview to see the real checkout here.', 'kdna-checkout' )
			: __( 'The live WooCommerce checkout renders here on the front end. Preview the page to see it working, or switch on "Live checkout in the editor" (Layout) with a product in your cart.', 'kdna-checkout' );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="kdna-checkout__placeholder">
				<div class="kdna-checkout__ph-header">
					<strong><?php echo esc_html__( 'KDNA Checkout', 'kdna-checkout' ); ?></strong>
					<span><?php echo esc_html( $note ); ?></span>
				</div>
				<?php if ( $this->show_cart_strip( $settings ) ) : ?>
					<?php $strip_mode = $settings['strip_item_controls'] ?? 'full'; ?>
					<div class="kdna-checkout-strip kdna-checkout-strip--controls-<?php echo esc_attr( $strip_mode ); ?> kdna-checkout-strip--skeleton">
						<div class="kdna-checkout-strip__items">
							<?php for ( $i = 0; $i < 3; $i++ ) : ?>
								<div class="kdna-checkout-strip__tile">
									<span class="kdna-checkout-strip__image"><span class="kdna-checkout-strip__image-ph"></span></span>
									<span class="kdna-checkout-strip__name"><?php echo esc_html__( 'Product name', 'kdna-checkout' ); ?></span>
									<span class="kdna-checkout-strip__qty-static" aria-hidden="true">&times;&nbsp;1</span>
									<span class="kdna-checkout-strip__controls">
										<span class="kdna-checkout-strip__stepper">
											<button type="button" class="kdna-checkout-strip__step kdna-checkout-strip__step--down" aria-label="<?php echo esc_attr__( 'Decrease quantity', 'kdna-checkout' ); ?>">&minus;</button>
											<input class="kdna-checkout-strip__qty" type="number" value="1" min="0" readonly aria-label="<?php echo esc_attr__( 'Quantity', 'kdna-checkout' ); ?>" />
											<button type="button" class="kdna-checkout-strip__step kdna-checkout-strip__step--up" aria-label="<?php echo esc_attr__( 'Increase quantity', 'kdna-checkout' ); ?>">+</button>
										</span>
										<button type="button" class="kdna-checkout-strip__remove" aria-label="<?php echo esc_attr__( 'Remove', 'kdna-checkout' ); ?>">&times;</button>
									</span>
								</div>
							<?php endfor; ?>
						</div>
						<div class="kdna-checkout-strip__meta">
							<?php if ( 'edit' === $strip_mode ) : ?>
								<button type="button" class="kdna-checkout-strip__edit-link"><?php echo esc_html( $settings['strip_edit_label'] ?? __( 'Edit', 'kdna-checkout' ) ); ?></button>
							<?php endif; ?>
							<div class="kdna-checkout-strip__subtotal">
								<span class="kdna-checkout-strip__subtotal-label"><?php echo esc_html( $settings['strip_subtotal_label'] ?? __( 'Subtotal', 'kdna-checkout' ) ); ?></span>
								<span class="kdna-checkout-strip__subtotal-amount">&#163;0.00</span>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( $this->show_express( $settings ) ) : ?>
					<div class="kdna-checkout-express kdna-checkout-express--active kdna-checkout-express--skeleton">
						<div class="kdna-checkout-express__buttons">
							<span class="kdna-checkout-express__ph-button"></span>
							<span class="kdna-checkout-express__ph-button"></span>
						</div>
						<?php if ( isset( $settings['show_express_divider'] ) && 'yes' === $settings['show_express_divider'] ) : ?>
							<div class="kdna-checkout-express__divider" aria-hidden="true">
								<span class="kdna-checkout-express__divider-text"><?php echo esc_html( '' !== trim( (string) ( $settings['express_divider_text'] ?? '' ) ) ? $settings['express_divider_text'] : __( 'or pay with card below', 'kdna-checkout' ) ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
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
						<div class="kdna-checkout-bump kdna-checkout-bump--skeleton">
							<label class="kdna-checkout-bump__label">
								<input type="checkbox" class="kdna-checkout-bump__checkbox" disabled />
								<span class="kdna-checkout-bump__image"><span class="kdna-checkout-bump__image-ph"></span></span>
								<span class="kdna-checkout-bump__text">
									<span class="kdna-checkout-bump__headline"><?php echo esc_html__( 'Order bump headline', 'kdna-checkout' ); ?></span>
									<span class="kdna-checkout-bump__description"><?php echo esc_html__( 'Published order bumps appear here, above the pay button.', 'kdna-checkout' ); ?></span>
									<span class="kdna-checkout-bump__price"><del>&#163;20.00</del> <ins>&#163;16.00</ins></span>
								</span>
							</label>
						</div>
						<div class="kdna-checkout__ph-button"></div>
						<?php if ( $this->show_trust( $settings ) && class_exists( 'KDNA_Checkout_Trust' ) ) : ?>
							<?php echo KDNA_Checkout_Trust::render( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trust markup is escaped where it is built. ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
