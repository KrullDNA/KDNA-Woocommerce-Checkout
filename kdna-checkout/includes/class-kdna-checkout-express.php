<?php
/**
 * Express payment row for KDNA Checkout.
 *
 * Positions the express/accelerated payment buttons that the active
 * official gateway plugins output (Apple Pay and Google Pay, PayPal
 * Express, Stripe Link, Afterpay/Zip) into a container at the very
 * top of the checkout, above the form, followed by an "or pay with
 * card below" divider.
 *
 * No payment logic lives here: the gateways render, initialise and
 * process their own buttons. This class renders the container and the
 * relocation instructions; the widget JS moves the gateway elements in
 * and shows the row only when at least one button is actually visible.
 * If no gateway or express method is active the row never appears, with
 * no gap and no error (fail-safe).
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Express payment row renderer.
 */
class KDNA_Checkout_Express {

	/**
	 * Default selectors of express button wrappers that known gateway
	 * plugins output on the classic checkout. Filterable so new gateway
	 * versions or additional gateways can be supported without a code
	 * change.
	 *
	 * @return array
	 */
	public static function button_selectors() {
		$selectors = array(
			'#wc-stripe-express-checkout-wrapper', // Stripe express checkout element: Apple Pay, Google Pay, Link.
			'#wc-stripe-payment-request-wrapper',  // Stripe legacy payment request button.
			'.wcpay-express-checkout-wrapper',     // WooPayments express checkout.
			'#wcpay-express-checkout-wrapper',     // WooPayments express checkout (id variant).
			'#ppc-button-checkout-top',            // WooCommerce PayPal Payments express placement.
			'.ppcp-express-buttons--checkout',     // WooCommerce PayPal Payments express buttons.
			'#afterpay-express-checkout-wrapper',  // Afterpay express checkout.
			'#zip-express-checkout',               // Zip express checkout.
		);

		/**
		 * Filter the selectors of gateway express button wrappers moved
		 * into the KDNA Checkout express row.
		 *
		 * @param array $selectors CSS selectors.
		 */
		return (array) apply_filters( 'kdna_checkout_express_selectors', $selectors );
	}

	/**
	 * Selectors of gateway-rendered separators to hide, since the row
	 * provides its own styled divider.
	 *
	 * @return array
	 */
	public static function hide_selectors() {
		$selectors = array(
			'#wc-stripe-express-checkout-button-separator',
			'#wc-stripe-payment-request-button-separator',
			'.wcpay-express-checkout-button-separator',
		);

		/**
		 * Filter the selectors hidden when the KDNA Checkout express row
		 * is active.
		 *
		 * @param array $selectors CSS selectors.
		 */
		return (array) apply_filters( 'kdna_checkout_express_hide_selectors', $selectors );
	}

	/**
	 * Render the express payment row container.
	 *
	 * @param array $args {
	 *     Render arguments.
	 *
	 *     @type bool   $show_divider Whether to render the divider.
	 *     @type string $divider_text Divider text.
	 * }
	 * @return string
	 */
	public static function render( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_divider' => true,
				'divider_text' => __( 'or pay with card below', 'kdna-checkout' ),
			)
		);

		$divider_text = '' !== trim( (string) $args['divider_text'] )
			? sanitize_text_field( $args['divider_text'] )
			: __( 'or pay with card below', 'kdna-checkout' );

		ob_start();
		?>
		<div class="kdna-checkout-express"
			data-selectors="<?php echo esc_attr( wp_json_encode( self::button_selectors() ) ); ?>"
			data-hide="<?php echo esc_attr( wp_json_encode( self::hide_selectors() ) ); ?>">
			<div class="kdna-checkout-express__buttons">
				<?php
				/**
				 * Fires inside the express payment row, before any relocated
				 * gateway buttons. Lets gateways or site snippets render
				 * express content directly into the row.
				 */
				do_action( 'kdna_checkout_express_buttons' );
				?>
			</div>
			<?php if ( $args['show_divider'] ) : ?>
				<div class="kdna-checkout-express__divider" aria-hidden="true">
					<span class="kdna-checkout-express__divider-text"><?php echo esc_html( $divider_text ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
