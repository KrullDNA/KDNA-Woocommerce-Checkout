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
		$this->register_style_controls();
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

		$this->end_controls_section();
	}

	/**
	 * The complete Style tab (Stage 3).
	 *
	 * @return void
	 */
	private function register_style_controls() {
		$this->style_section_columns_spacing();
		$this->style_section_headings();
		$this->style_section_field_labels();
		$this->style_section_input_fields();
		$this->style_section_summary_card();
		$this->style_section_summary_content();
		$this->style_section_pay_button();
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
					'{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__ph-summary' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'summary_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__ph-summary',
			)
		);

		$this->add_responsive_control(
			'summary_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__ph-summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'summary_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout__summary, {{WRAPPER}} .kdna-checkout__ph-summary',
			)
		);

		$this->add_responsive_control(
			'summary_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout__summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

		if ( $this->is_editor_context() ) {
			$this->render_placeholder( $classes );
			return;
		}

		// Fail-safe: never output checkout markup without WooCommerce.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		printf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );

		if ( $has_icon ) {
			$this->render_pay_icon_template( $settings );
		}

		// Native WooCommerce classic shortcode checkout, reflowed by the widget CSS/JS.
		echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce renders and escapes its own checkout markup.
		echo '</div>';
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
