<?php
/**
 * Shared trust signals block for KDNA Checkout.
 *
 * One source of truth for the trust block used in two places: inside
 * the KDNA Checkout widget and as the standalone "KDNA Trust Badges"
 * widget. Provides the default payment badge set (rendered through
 * Elementor's icon system, so nothing is hard-coded artwork), the
 * shared Content and Style controls, and the renderer.
 *
 * DOM output is minimal: one wrapper div, an icons row and a message
 * paragraph, nothing else.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Trust block controls and renderer.
 */
class KDNA_Checkout_Trust {

	/**
	 * The default payment badges, rendered as Elementor brand icons so
	 * they scale and recolour like text.
	 *
	 * @return array key => array( label, icon ).
	 */
	public static function badges() {
		return array(
			'visa'       => array(
				'label' => __( 'Visa', 'kdna-checkout' ),
				'icon'  => array(
					'value'   => 'fab fa-cc-visa',
					'library' => 'fa-brands',
				),
			),
			'mastercard' => array(
				'label' => __( 'Mastercard', 'kdna-checkout' ),
				'icon'  => array(
					'value'   => 'fab fa-cc-mastercard',
					'library' => 'fa-brands',
				),
			),
			'amex'       => array(
				'label' => __( 'American Express', 'kdna-checkout' ),
				'icon'  => array(
					'value'   => 'fab fa-cc-amex',
					'library' => 'fa-brands',
				),
			),
			'paypal'     => array(
				'label' => __( 'PayPal', 'kdna-checkout' ),
				'icon'  => array(
					'value'   => 'fab fa-cc-paypal',
					'library' => 'fa-brands',
				),
			),
			'applepay'   => array(
				'label' => __( 'Apple Pay', 'kdna-checkout' ),
				'icon'  => array(
					'value'   => 'fab fa-cc-apple-pay',
					'library' => 'fa-brands',
				),
			),
			'googlepay'  => array(
				'label' => __( 'Google Pay', 'kdna-checkout' ),
				'icon'  => array(
					'value'   => 'fab fa-google-pay',
					'library' => 'fa-brands',
				),
			),
		);
	}

	/**
	 * Register the shared Content controls on a widget.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Optional condition applied to every control.
	 * @return void
	 */
	public static function register_content_controls( $widget, array $condition = array() ) {
		$with_condition = function ( array $args ) use ( $condition ) {
			if ( ! empty( $condition ) ) {
				$args['condition'] = array_merge( $condition, (array) ( $args['condition'] ?? array() ) );
			}
			return $args;
		};

		$widget->add_control(
			'trust_message',
			$with_condition(
				array(
					'label'   => __( 'Reassurance message', 'kdna-checkout' ),
					'type'    => \Elementor\Controls_Manager::TEXTAREA,
					'rows'    => 2,
					'default' => __( '100% safe and secure checkout. Your payment details are encrypted.', 'kdna-checkout' ),
				)
			)
		);

		$widget->add_control(
			'trust_message_position',
			$with_condition(
				array(
					'label'   => __( 'Message position', 'kdna-checkout' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'options' => array(
						'below' => __( 'Below the icons', 'kdna-checkout' ),
						'above' => __( 'Above the icons', 'kdna-checkout' ),
					),
					'default' => 'below',
				)
			)
		);

		foreach ( self::badges() as $key => $badge ) {
			$widget->add_control(
				'trust_badge_' . $key,
				$with_condition(
					array(
						/* translators: %s: payment method name. */
						'label'        => sprintf( __( 'Show %s', 'kdna-checkout' ), $badge['label'] ),
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => __( 'Show', 'kdna-checkout' ),
						'label_off'    => __( 'Hide', 'kdna-checkout' ),
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				)
			);
		}

		$widget->add_control(
			'trust_custom_badges',
			$with_condition(
				array(
					'label'       => __( 'Custom badge images', 'kdna-checkout' ),
					'description' => __( 'Upload your own badges (for example Afterpay, Zip or an SSL seal). They render after the icons above.', 'kdna-checkout' ),
					'type'        => \Elementor\Controls_Manager::GALLERY,
					'default'     => array(),
				)
			)
		);
	}

	/**
	 * Register the shared Style controls on a widget.
	 *
	 * @param \Elementor\Widget_Base $widget    Target widget.
	 * @param array                  $condition Optional condition applied to every control.
	 * @return void
	 */
	public static function register_style_controls( $widget, array $condition = array() ) {
		$with_condition = function ( array $args ) use ( $condition ) {
			if ( ! empty( $condition ) ) {
				$args['condition'] = array_merge( $condition, (array) ( $args['condition'] ?? array() ) );
			}
			return $args;
		};

		$widget->add_responsive_control(
			'trust_alignment',
			$with_condition(
				array(
					'label'                => __( 'Alignment', 'kdna-checkout' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
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
					'selectors_dictionary' => array(
						'left'   => 'text-align: left; --kdna-checkout-trust-align: flex-start;',
						'center' => 'text-align: center; --kdna-checkout-trust-align: center;',
						'right'  => 'text-align: right; --kdna-checkout-trust-align: flex-end;',
					),
					'selectors'            => array(
						'{{WRAPPER}} .kdna-checkout-trust' => '{{VALUE}}',
					),
				)
			)
		);

		$widget->add_responsive_control(
			'trust_icon_size',
			$with_condition(
				array(
					'label'      => __( 'Icon size', 'kdna-checkout' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 12,
							'max' => 80,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .kdna-checkout-trust' => '--kdna-checkout-trust-icon-size: {{SIZE}}{{UNIT}};',
					),
				)
			)
		);

		$widget->add_responsive_control(
			'trust_icon_spacing',
			$with_condition(
				array(
					'label'      => __( 'Icon spacing', 'kdna-checkout' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 48,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .kdna-checkout-trust' => '--kdna-checkout-trust-gap: {{SIZE}}{{UNIT}};',
					),
				)
			)
		);

		$widget->add_control(
			'trust_icon_colour',
			$with_condition(
				array(
					'label'     => __( 'Icon colour', 'kdna-checkout' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .kdna-checkout-trust__icon' => 'color: {{VALUE}};',
					),
				)
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			$with_condition(
				array(
					'name'      => 'trust_message_typography',
					'label'     => __( 'Message typography', 'kdna-checkout' ),
					'selector'  => '{{WRAPPER}} .kdna-checkout-trust__message',
					'separator' => 'before',
				)
			)
		);

		$widget->add_control(
			'trust_message_colour',
			$with_condition(
				array(
					'label'     => __( 'Message colour', 'kdna-checkout' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .kdna-checkout-trust__message' => 'color: {{VALUE}};',
					),
				)
			)
		);

		$widget->add_responsive_control(
			'trust_message_spacing',
			$with_condition(
				array(
					'label'      => __( 'Spacing between icons and message', 'kdna-checkout' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 48,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .kdna-checkout-trust' => '--kdna-checkout-trust-message-gap: {{SIZE}}{{UNIT}};',
					),
				)
			)
		);

		$widget->add_control(
			'trust_background_colour',
			$with_condition(
				array(
					'label'     => __( 'Background colour', 'kdna-checkout' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array(
						'{{WRAPPER}} .kdna-checkout-trust' => 'background-color: {{VALUE}};',
					),
				)
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			$with_condition(
				array(
					'name'     => 'trust_border',
					'label'    => __( 'Border', 'kdna-checkout' ),
					'selector' => '{{WRAPPER}} .kdna-checkout-trust',
				)
			)
		);

		$widget->add_responsive_control(
			'trust_border_radius',
			$with_condition(
				array(
					'label'      => __( 'Border radius', 'kdna-checkout' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} .kdna-checkout-trust' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			)
		);

		$widget->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			$with_condition(
				array(
					'name'     => 'trust_box_shadow',
					'label'    => __( 'Box shadow', 'kdna-checkout' ),
					'selector' => '{{WRAPPER}} .kdna-checkout-trust',
				)
			)
		);

		$widget->add_responsive_control(
			'trust_padding',
			$with_condition(
				array(
					'label'      => __( 'Padding', 'kdna-checkout' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} .kdna-checkout-trust' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			)
		);

		$widget->add_responsive_control(
			'trust_margin',
			$with_condition(
				array(
					'label'      => __( 'Margin', 'kdna-checkout' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} .kdna-checkout-trust' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			)
		);
	}

	/**
	 * Render the trust block.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $position Optional placement hint (summary|main|bottom)
	 *                         used by the checkout widget JS to position
	 *                         the block; empty renders in place.
	 * @return string Empty when every badge is off and the message is blank.
	 */
	public static function render( array $settings, $position = '' ) {
		$icons_html = '';

		foreach ( self::badges() as $key => $badge ) {
			if ( 'yes' !== ( $settings[ 'trust_badge_' . $key ] ?? 'yes' ) ) {
				continue;
			}

			ob_start();
			\Elementor\Icons_Manager::render_icon( $badge['icon'], array( 'aria-hidden' => 'true' ) );
			$icon = trim( (string) ob_get_clean() );

			if ( '' !== $icon ) {
				$icons_html .= '<span class="kdna-checkout-trust__icon" role="img" aria-label="' . esc_attr( $badge['label'] ) . '">' . $icon . '</span>';
			}
		}

		foreach ( (array) ( $settings['trust_custom_badges'] ?? array() ) as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			$attachment_id = absint( $image['id'] ?? 0 );
			if ( $attachment_id && function_exists( 'wp_get_attachment_image' ) ) {
				$img = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'class' => 'kdna-checkout-trust__badge' ) );
				if ( $img ) {
					$icons_html .= $img;
					continue;
				}
			}

			$url = (string) ( $image['url'] ?? '' );
			if ( '' !== $url ) {
				$icons_html .= '<img class="kdna-checkout-trust__badge" src="' . esc_url( $url ) . '" alt="" />';
			}
		}

		$message = trim( (string) ( $settings['trust_message'] ?? '' ) );

		// Nothing enabled: render nothing (no empty box).
		if ( '' === $icons_html && '' === $message ) {
			return '';
		}

		$classes = 'kdna-checkout-trust';
		if ( 'above' === ( $settings['trust_message_position'] ?? 'below' ) ) {
			$classes .= ' kdna-checkout-trust--message-above';
		}

		$position_attr = '' !== $position ? ' data-position="' . esc_attr( $position ) . '"' : '';

		$html = '<div class="' . esc_attr( $classes ) . '"' . $position_attr . '>';
		if ( '' !== $icons_html ) {
			$html .= '<div class="kdna-checkout-trust__icons">' . $icons_html . '</div>';
		}
		if ( '' !== $message ) {
			$html .= '<p class="kdna-checkout-trust__message">' . esc_html( $message ) . '</p>';
		}
		$html .= '</div>';

		return $html;
	}
}
