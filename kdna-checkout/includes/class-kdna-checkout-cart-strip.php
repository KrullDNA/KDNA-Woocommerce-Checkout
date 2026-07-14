<?php
/**
 * Cart strip (mini-cart) for KDNA Checkout.
 *
 * Renders the sideways-scrolling product tile strip shown at the very
 * top of the checkout widget, and handles the AJAX quantity/remove
 * updates that refresh the strip and the order summary live.
 *
 * The strip is display-layer only: WooCommerce owns the cart. Every
 * AJAX call is nonce-checked, every input sanitised, every output
 * escaped.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cart strip renderer and AJAX handler.
 */
class KDNA_Checkout_Cart_Strip {

	/**
	 * Nonce action for strip AJAX calls.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'kdna_checkout_strip';

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'kdna_checkout_strip_update';

	/**
	 * Allowed item-control modes.
	 *
	 * @var array
	 */
	const MODES = array( 'full', 'subtle', 'edit', 'locked' );

	/**
	 * Hook the AJAX endpoints and script data in.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_update' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_update' ) );

		// Priority 6: right after the handle is registered at priority 5,
		// so the data rides along only where the widget assets load.
		add_action( 'wp_enqueue_scripts', array( $this, 'localise_script' ), 6 );
	}

	/**
	 * Attach the AJAX endpoint and nonce to the front-end script handle.
	 *
	 * @return void
	 */
	public function localise_script() {
		wp_localize_script(
			KDNA_Checkout_Assets::HANDLE,
			'kdnaCheckoutStrip',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Sanitise render arguments (from widget settings or an AJAX call).
	 *
	 * @param array $raw Raw arguments.
	 * @return array Clean arguments.
	 */
	public static function sanitise_args( array $raw ) {
		$mode = isset( $raw['controls'] ) ? sanitize_key( $raw['controls'] ) : 'full';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = 'full'; // Fail-safe: unknown mode falls back to the default.
		}

		return array(
			'controls'       => $mode,
			'sticky_desktop' => ! empty( $raw['sticky_desktop'] ) && 'yes' === $raw['sticky_desktop'] ? 'yes' : '',
			'sticky_mobile'  => ! empty( $raw['sticky_mobile'] ) && 'yes' === $raw['sticky_mobile'] ? 'yes' : '',
			'shrink'         => ! empty( $raw['shrink'] ) && 'yes' === $raw['shrink'] ? 'yes' : '',
			'subtotal_label' => isset( $raw['subtotal_label'] ) && '' !== trim( (string) $raw['subtotal_label'] )
				? sanitize_text_field( $raw['subtotal_label'] )
				: __( 'Subtotal', 'kdna-checkout' ),
			'edit_label'     => isset( $raw['edit_label'] ) && '' !== trim( (string) $raw['edit_label'] )
				? sanitize_text_field( $raw['edit_label'] )
				: __( 'Edit', 'kdna-checkout' ),
			'done_label'     => isset( $raw['done_label'] ) && '' !== trim( (string) $raw['done_label'] )
				? sanitize_text_field( $raw['done_label'] )
				: __( 'Done', 'kdna-checkout' ),
		);
	}

	/**
	 * Render the cart strip.
	 *
	 * @param array $args Render arguments, see sanitise_args().
	 * @return string Strip HTML, or an empty string when WooCommerce is unavailable.
	 */
	public static function render( array $args = array() ) {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return ''; // Fail-safe: no cart, no strip, no error.
		}

		$args = self::sanitise_args( $args );
		$cart = WC()->cart;

		$classes = array(
			'kdna-checkout-strip',
			'kdna-checkout-strip--controls-' . $args['controls'],
		);
		if ( 'yes' === $args['sticky_desktop'] ) {
			$classes[] = 'kdna-checkout-strip--sticky-desktop';
		}
		if ( 'yes' === $args['sticky_mobile'] ) {
			$classes[] = 'kdna-checkout-strip--sticky-mobile';
		}
		if ( 'yes' === $args['shrink'] ) {
			$classes[] = 'kdna-checkout-strip--shrink';
		}
		if ( $cart->is_empty() ) {
			$classes[] = 'kdna-checkout-strip--empty';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			role="region"
			aria-label="<?php echo esc_attr__( 'Cart contents', 'kdna-checkout' ); ?>"
			data-controls="<?php echo esc_attr( $args['controls'] ); ?>"
			data-sticky-desktop="<?php echo esc_attr( $args['sticky_desktop'] ); ?>"
			data-sticky-mobile="<?php echo esc_attr( $args['sticky_mobile'] ); ?>"
			data-shrink="<?php echo esc_attr( $args['shrink'] ); ?>"
			data-subtotal-label="<?php echo esc_attr( $args['subtotal_label'] ); ?>"
			data-edit-label="<?php echo esc_attr( $args['edit_label'] ); ?>"
			data-done-label="<?php echo esc_attr( $args['done_label'] ); ?>">
			<?php if ( $cart->is_empty() ) : ?>
				<p class="kdna-checkout-strip__empty"><?php echo esc_html__( 'Your cart is empty.', 'kdna-checkout' ); ?></p>
			<?php else : ?>
				<div class="kdna-checkout-strip__items">
					<?php foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) : ?>
						<?php
						$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
						if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 ) {
							continue;
						}
						$name     = $product->get_name();
						$quantity = (int) $cart_item['quantity'];
						$max      = $product->is_sold_individually() ? 1 : 999;
						?>
						<div class="kdna-checkout-strip__tile" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
							<span class="kdna-checkout-strip__image">
								<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes its own image markup. ?>
							</span>
							<span class="kdna-checkout-strip__name"><?php echo esc_html( $name ); ?></span>
							<span class="kdna-checkout-strip__qty-static" aria-hidden="true">&times;&nbsp;<?php echo esc_html( (string) $quantity ); ?></span>
							<span class="kdna-checkout-strip__controls">
								<span class="kdna-checkout-strip__stepper">
									<button type="button"
										class="kdna-checkout-strip__step kdna-checkout-strip__step--down"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Decrease quantity of %s', 'kdna-checkout' ), $name ) ); ?>">&minus;</button>
									<input
										class="kdna-checkout-strip__qty"
										type="number"
										inputmode="numeric"
										min="0"
										max="<?php echo esc_attr( (string) $max ); ?>"
										step="1"
										value="<?php echo esc_attr( (string) $quantity ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Quantity of %s', 'kdna-checkout' ), $name ) ); ?>"
									/>
									<button type="button"
										class="kdna-checkout-strip__step kdna-checkout-strip__step--up"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Increase quantity of %s', 'kdna-checkout' ), $name ) ); ?>">+</button>
								</span>
								<button type="button"
									class="kdna-checkout-strip__remove"
									aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'Remove %s from your cart', 'kdna-checkout' ), $name ) ); ?>">&times;</button>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="kdna-checkout-strip__meta">
					<?php if ( 'edit' === $args['controls'] ) : ?>
						<button type="button" class="kdna-checkout-strip__edit-link" aria-expanded="false"><?php echo esc_html( $args['edit_label'] ); ?></button>
					<?php endif; ?>
					<div class="kdna-checkout-strip__subtotal">
						<span class="kdna-checkout-strip__subtotal-label"><?php echo esc_html( $args['subtotal_label'] ); ?></span>
						<span class="kdna-checkout-strip__subtotal-amount" aria-live="polite"><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX: set a cart item quantity (0 removes it), then return the
	 * refreshed strip HTML. The order summary refresh is triggered
	 * client-side through WooCommerce's own update_checkout event.
	 *
	 * @return void
	 */
	public function ajax_update() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not available.', 'kdna-checkout' ) ), 400 );
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( null === WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'The cart is not available.', 'kdna-checkout' ) ), 400 );
		}

		$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
		$quantity      = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 0;

		$cart = WC()->cart;

		if ( '' === $cart_item_key || null === $cart->get_cart_item( $cart_item_key ) ) {
			wp_send_json_error( array( 'message' => __( 'That item is no longer in the cart.', 'kdna-checkout' ) ), 404 );
		}

		if ( $quantity > 0 ) {
			$cart->set_quantity( $cart_item_key, $quantity, true );
		} else {
			$cart->remove_cart_item( $cart_item_key );
		}

		$cart->calculate_totals();

		$args = self::sanitise_args(
			array(
				'controls'       => isset( $_POST['controls'] ) ? sanitize_key( wp_unslash( $_POST['controls'] ) ) : 'full',
				'sticky_desktop' => isset( $_POST['sticky_desktop'] ) ? sanitize_key( wp_unslash( $_POST['sticky_desktop'] ) ) : '',
				'sticky_mobile'  => isset( $_POST['sticky_mobile'] ) ? sanitize_key( wp_unslash( $_POST['sticky_mobile'] ) ) : '',
				'shrink'         => isset( $_POST['shrink'] ) ? sanitize_key( wp_unslash( $_POST['shrink'] ) ) : '',
				'subtotal_label' => isset( $_POST['subtotal_label'] ) ? sanitize_text_field( wp_unslash( $_POST['subtotal_label'] ) ) : '',
				'edit_label'     => isset( $_POST['edit_label'] ) ? sanitize_text_field( wp_unslash( $_POST['edit_label'] ) ) : '',
				'done_label'     => isset( $_POST['done_label'] ) ? sanitize_text_field( wp_unslash( $_POST['done_label'] ) ) : '',
			)
		);

		wp_send_json_success(
			array(
				'strip_html' => self::render( $args ),
				'cart_empty' => $cart->is_empty(),
			)
		);
	}
}
