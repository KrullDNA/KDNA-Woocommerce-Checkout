<?php
/**
 * Shared cart strip controls for KDNA Checkout.
 *
 * One source of truth for the cart strip's Content and Style controls,
 * used by both the checkout widget (where the strip is optional and its
 * controls are gated behind the "Show cart strip" toggle) and the
 * standalone "KDNA Cart Strip" widget (where the strip is the whole
 * widget). Control IDs and selectors are identical in both, so an
 * instance styled in one place behaves the same in the other.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the cart strip controls on a widget.
 */
class KDNA_Checkout_Strip_Controls {

	/**
	 * Merge a shared condition into a control's args.
	 *
	 * @param array $args      Control args.
	 * @param array $condition Condition to merge in (empty for none).
	 * @return array
	 */
	private static function with_condition( array $args, array $condition ) {
		if ( ! empty( $condition ) ) {
			$args['condition'] = array_merge( $condition, (array) ( $args['condition'] ?? array() ) );
		}
		return $args;
	}

	/**
	 * Register the strip's behaviour controls into the currently open
	 * Content section (item controls, sticky toggles, labels). The
	 * caller opens and closes the section and adds any show toggle.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Optional condition on every control.
	 * @return void
	 */
	public static function register_content_controls( $widget, array $condition = array() ) {
		$widget->add_control(
			'strip_item_controls',
			self::with_condition(
				array(
					'label'       => __( 'Item controls', 'kdna-checkout' ),
					'description' => __( 'How much editing the strip allows.', 'kdna-checkout' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'full'   => __( 'Full: quantity editable, remove always visible', 'kdna-checkout' ),
						'subtle' => __( 'Subtle: quantity editable, low-emphasis remove', 'kdna-checkout' ),
						'edit'   => __( 'Edit toggle: controls hidden until "Edit" is tapped', 'kdna-checkout' ),
						'locked' => __( 'Locked: read-only display', 'kdna-checkout' ),
					),
					'default'     => 'full',
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_sticky_desktop',
			self::with_condition(
				array(
					'label'        => __( 'Sticky on desktop', 'kdna-checkout' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'On', 'kdna-checkout' ),
					'label_off'    => __( 'Off', 'kdna-checkout' ),
					'return_value' => 'yes',
					'default'      => '',
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_sticky_mobile',
			self::with_condition(
				array(
					'label'        => __( 'Sticky on mobile', 'kdna-checkout' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'On', 'kdna-checkout' ),
					'label_off'    => __( 'Off', 'kdna-checkout' ),
					'return_value' => 'yes',
					'default'      => '',
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_shrink_sticky',
			self::with_condition(
				array(
					'label'        => __( 'Shrink while stuck', 'kdna-checkout' ),
					'description'  => __( 'While the strip is stuck to the top on scroll it collapses to just the product images to save space, and grows back to full height at the top of the page. Set the shrunk tile size under Cart Strip: Container.', 'kdna-checkout' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'On', 'kdna-checkout' ),
					'label_off'    => __( 'Off', 'kdna-checkout' ),
					'return_value' => 'yes',
					'default'      => '',
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_subtotal_label',
			self::with_condition(
				array(
					'label'   => __( 'Subtotal label', 'kdna-checkout' ),
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => __( 'Subtotal', 'kdna-checkout' ),
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_edit_label',
			self::with_condition(
				array(
					'label'     => __( '"Edit" link text', 'kdna-checkout' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => __( 'Edit', 'kdna-checkout' ),
					'condition' => array( 'strip_item_controls' => 'edit' ),
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_done_label',
			self::with_condition(
				array(
					'label'     => __( '"Done" link text', 'kdna-checkout' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => __( 'Done', 'kdna-checkout' ),
					'condition' => array( 'strip_item_controls' => 'edit' ),
				),
				$condition
			)
		);
	}

	/**
	 * Register the strip's Style sections.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Optional condition on every section.
	 * @return void
	 */
	public static function register_style_controls( $widget, array $condition = array() ) {
		self::section_container( $widget, $condition );
		self::section_tiles( $widget, $condition );
		self::section_qty( $widget, $condition );
		self::section_remove( $widget, $condition );
		self::section_edit_link( $widget, $condition );
		self::section_subtotal( $widget, $condition );
	}

	/**
	 * Style > Cart Strip: Container.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Section condition.
	 * @return void
	 */
	private static function section_container( $widget, array $condition ) {
		$widget->start_controls_section(
			'style_strip_container',
			self::with_condition(
				array(
					'label' => __( 'Cart Strip: Container', 'kdna-checkout' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				),
				$condition
			)
		);

		$widget->add_control(
			'strip_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'strip_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip',
			)
		);

		$widget->add_responsive_control(
			'strip_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'strip_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip',
			)
		);

		$widget->add_responsive_control(
			'strip_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_margin',
			array(
				'label'      => __( 'Margin', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_tile_gap',
			array(
				'label'      => __( 'Gap between tiles', 'kdna-checkout' ),
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
					'{{WRAPPER}}' => '--kdna-checkout-strip-tile-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_sticky_offset',
			array(
				'label'       => __( 'Sticky top offset', 'kdna-checkout' ),
				'description' => __( 'Distance from the top of the screen while the strip is stuck.', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'em' ),
				'range'       => array(
					'px' => array(
						'min' => 0,
						'max' => 240,
					),
				),
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-checkout-strip-sticky-offset: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_compact_size',
			array(
				'label'       => __( 'Shrunk tile size', 'kdna-checkout' ),
				'description' => __( 'Tile size while the strip is stuck and shrunk (only the product images show).', 'kdna-checkout' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'em' ),
				'range'       => array(
					'px' => array(
						'min' => 32,
						'max' => 120,
					),
				),
				'selectors'   => array(
					'{{WRAPPER}}' => '--kdna-checkout-strip-compact-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'strip_empty_typography',
				'label'     => __( 'Empty state typography', 'kdna-checkout' ),
				'selector'  => '{{WRAPPER}} .kdna-checkout-strip__empty',
				'separator' => 'before',
			)
		);

		$widget->add_control(
			'strip_empty_colour',
			array(
				'label'     => __( 'Empty state colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__empty' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Style > Cart Strip: Tiles.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Section condition.
	 * @return void
	 */
	private static function section_tiles( $widget, array $condition ) {
		$widget->start_controls_section(
			'style_strip_tiles',
			self::with_condition(
				array(
					'label' => __( 'Cart Strip: Tiles', 'kdna-checkout' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				),
				$condition
			)
		);

		$widget->add_responsive_control(
			'strip_tile_size',
			array(
				'label'      => __( 'Tile size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 56,
						'max' => 220,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--kdna-checkout-strip-tile-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'strip_tile_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__tile' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'strip_tile_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__tile',
			)
		);

		$widget->add_responsive_control(
			'strip_tile_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__tile' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'strip_tile_box_shadow',
				'label'    => __( 'Box shadow', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__tile',
			)
		);

		$widget->add_responsive_control(
			'strip_tile_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__tile' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_image_border_radius',
			array(
				'label'      => __( 'Product image border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__image img, {{WRAPPER}} .kdna-checkout-strip__image-ph' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'strip_name_typography',
				'label'    => __( 'Product name typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__name',
			)
		);

		$widget->add_control(
			'strip_name_colour',
			array(
				'label'     => __( 'Product name colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__name' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Style > Cart Strip: Quantity Field.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Section condition.
	 * @return void
	 */
	private static function section_qty( $widget, array $condition ) {
		$widget->start_controls_section(
			'style_strip_qty',
			self::with_condition(
				array(
					'label' => __( 'Cart Strip: Quantity Field', 'kdna-checkout' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				),
				$condition
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'strip_qty_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__qty, {{WRAPPER}} .kdna-checkout-strip__qty-static',
			)
		);

		$widget->add_control(
			'strip_qty_text_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty, {{WRAPPER}} .kdna-checkout-strip__qty-static' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_qty_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'strip_qty_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__qty',
			)
		);

		$widget->add_responsive_control(
			'strip_qty_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_qty_width',
			array(
				'label'      => __( 'Width', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 32,
						'max' => 120,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_qty_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_control(
			'strip_qty_focus_border_colour',
			array(
				'label'     => __( 'Focus border colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty:focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_qty_focus_background_colour',
			array(
				'label'     => __( 'Focus background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__qty:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Style > Cart Strip: Remove Button.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Section condition.
	 * @return void
	 */
	private static function section_remove( $widget, array $condition ) {
		$widget->start_controls_section(
			'style_strip_remove',
			self::with_condition(
				array(
					'label' => __( 'Cart Strip: Remove Button', 'kdna-checkout' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				),
				$condition
			)
		);

		$widget->add_responsive_control(
			'strip_remove_size',
			array(
				'label'      => __( 'Icon size', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_remove_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; width: auto; height: auto;',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'strip_remove_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__remove',
			)
		);

		$widget->add_responsive_control(
			'strip_remove_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->start_controls_tabs( 'strip_remove_state_tabs' );

		$widget->start_controls_tab(
			'strip_remove_tab_normal',
			array( 'label' => __( 'Normal', 'kdna-checkout' ) )
		);

		$widget->add_control(
			'strip_remove_colour',
			array(
				'label'     => __( 'Icon colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_remove_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab(
			'strip_remove_tab_hover',
			array( 'label' => __( 'Hover', 'kdna-checkout' ) )
		);

		$widget->add_control(
			'strip_remove_hover_colour',
			array(
				'label'     => __( 'Icon colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove:hover, {{WRAPPER}} .kdna-checkout-strip__remove:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_remove_hover_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove:hover, {{WRAPPER}} .kdna-checkout-strip__remove:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_remove_hover_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__remove:hover, {{WRAPPER}} .kdna-checkout-strip__remove:focus' => 'border-color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_tab();
		$widget->end_controls_tabs();

		$widget->end_controls_section();
	}

	/**
	 * Style > Cart Strip: Edit Link (Edit toggle mode only).
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Section condition.
	 * @return void
	 */
	private static function section_edit_link( $widget, array $condition ) {
		$widget->start_controls_section(
			'style_strip_edit',
			self::with_condition(
				array(
					'label'     => __( 'Cart Strip: Edit Link', 'kdna-checkout' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'strip_item_controls' => 'edit' ),
				),
				$condition
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'strip_edit_typography',
				'label'    => __( 'Typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__edit-link',
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'strip_edit_border',
				'label'    => __( 'Border', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__edit-link',
			)
		);

		$widget->add_responsive_control(
			'strip_edit_border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_edit_padding',
			array(
				'label'      => __( 'Padding', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$widget->start_controls_tabs( 'strip_edit_state_tabs' );

		$widget->start_controls_tab(
			'strip_edit_tab_normal',
			array( 'label' => __( 'Normal', 'kdna-checkout' ) )
		);

		$widget->add_control(
			'strip_edit_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_edit_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_tab();

		$widget->start_controls_tab(
			'strip_edit_tab_hover',
			array( 'label' => __( 'Hover', 'kdna-checkout' ) )
		);

		$widget->add_control(
			'strip_edit_hover_colour',
			array(
				'label'     => __( 'Text colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link:hover, {{WRAPPER}} .kdna-checkout-strip__edit-link:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_edit_hover_background_colour',
			array(
				'label'     => __( 'Background colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link:hover, {{WRAPPER}} .kdna-checkout-strip__edit-link:focus' => 'background-color: {{VALUE}};',
				),
			)
		);

		$widget->add_control(
			'strip_edit_hover_border_colour',
			array(
				'label'     => __( 'Border colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__edit-link:hover, {{WRAPPER}} .kdna-checkout-strip__edit-link:focus' => 'border-color: {{VALUE}};',
				),
			)
		);

		$widget->end_controls_tab();
		$widget->end_controls_tabs();

		$widget->end_controls_section();
	}

	/**
	 * Style > Cart Strip: Subtotal.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Section condition.
	 * @return void
	 */
	private static function section_subtotal( $widget, array $condition ) {
		$widget->start_controls_section(
			'style_strip_subtotal',
			self::with_condition(
				array(
					'label' => __( 'Cart Strip: Subtotal', 'kdna-checkout' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				),
				$condition
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'strip_subtotal_label_typography',
				'label'    => __( 'Label typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__subtotal-label',
			)
		);

		$widget->add_control(
			'strip_subtotal_label_colour',
			array(
				'label'     => __( 'Label colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__subtotal-label' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'strip_subtotal_amount_typography',
				'label'    => __( 'Amount typography', 'kdna-checkout' ),
				'selector' => '{{WRAPPER}} .kdna-checkout-strip__subtotal-amount',
			)
		);

		$widget->add_control(
			'strip_subtotal_amount_colour',
			array(
				'label'     => __( 'Amount colour', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__subtotal-amount' => 'color: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_subtotal_alignment',
			array(
				'label'     => __( 'Alignment', 'kdna-checkout' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'kdna-checkout' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centre', 'kdna-checkout' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'kdna-checkout' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-checkout-strip__subtotal' => 'text-align: {{VALUE}};',
				),
			)
		);

		$widget->add_responsive_control(
			'strip_subtotal_spacing',
			array(
				'label'      => __( 'Spacing from tiles', 'kdna-checkout' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 80,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-checkout-strip__meta' => 'padding-left: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$widget->end_controls_section();
	}
}
