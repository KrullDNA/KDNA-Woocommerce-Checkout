<?php
/**
 * Branded recovery email template and merge tags for KDNA Checkout.
 *
 * Owns the brand controls (logo, colours) exposed in WooCommerce > KDNA
 * Checkout, the merge-tag replacement engine, the branded HTML wrapper
 * (templates/emails/recovery-email.php) and the send helper. Kept
 * separate from the recovery scheduler so the two can evolve
 * independently.
 *
 * Supported merge tags: {customer_name}, {cart_items}, {cart_total},
 * {recovery_link}, {coupon_code}, {store_name}, {unsubscribe}.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Recovery email rendering, branding and sending.
 */
class KDNA_Checkout_Emails {

	/**
	 * Brand option names.
	 *
	 * @var string
	 */
	const OPTION_LOGO          = 'kdna_checkout_email_logo';
	const OPTION_BRAND_COLOUR  = 'kdna_checkout_email_brand_colour';
	const OPTION_BUTTON_COLOUR = 'kdna_checkout_email_button_colour';
	const OPTION_FOOTER_TEXT   = 'kdna_checkout_email_footer_text';

	/**
	 * Hook the brand settings in.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/* ================================================================== *
	 * Brand settings
	 * ================================================================== */

	/**
	 * Register brand settings on the shared WooCommerce > KDNA Checkout page.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'kdna_checkout', self::OPTION_LOGO, array( 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
		register_setting( 'kdna_checkout', self::OPTION_BRAND_COLOUR, array( 'sanitize_callback' => array( __CLASS__, 'sanitise_colour' ), 'default' => '#2271b1' ) );
		register_setting( 'kdna_checkout', self::OPTION_BUTTON_COLOUR, array( 'sanitize_callback' => array( __CLASS__, 'sanitise_colour' ), 'default' => '#2271b1' ) );
		register_setting( 'kdna_checkout', self::OPTION_FOOTER_TEXT, array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );

		add_settings_section(
			'kdna_checkout_email_branding',
			__( 'Recovery email branding', 'kdna-checkout' ),
			array( $this, 'render_section_intro' ),
			'kdna-checkout'
		);

		add_settings_field( self::OPTION_LOGO, __( 'Logo URL', 'kdna-checkout' ), array( $this, 'render_logo_field' ), 'kdna-checkout', 'kdna_checkout_email_branding' );
		add_settings_field( self::OPTION_BRAND_COLOUR, __( 'Brand colour', 'kdna-checkout' ), array( $this, 'render_brand_colour_field' ), 'kdna-checkout', 'kdna_checkout_email_branding' );
		add_settings_field( self::OPTION_BUTTON_COLOUR, __( 'Button colour', 'kdna-checkout' ), array( $this, 'render_button_colour_field' ), 'kdna-checkout', 'kdna_checkout_email_branding' );
		add_settings_field( self::OPTION_FOOTER_TEXT, __( 'Footer text', 'kdna-checkout' ), array( $this, 'render_footer_field' ), 'kdna-checkout', 'kdna_checkout_email_branding' );
	}

	/**
	 * Sanitise a hex colour, falling back to a neutral default.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitise_colour( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return $value;
		}
		return '#2271b1';
	}

	/**
	 * Section intro.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Brand the recovery emails to match the checkout. These apply to every step in the recovery sequence.', 'kdna-checkout' )
		);
	}

	/**
	 * Logo field.
	 *
	 * @return void
	 */
	public function render_logo_field() {
		?>
		<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_LOGO ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_LOGO, '' ) ); ?>" placeholder="https://" />
		<p class="description"><?php echo esc_html__( 'Paste the full URL of your logo image. Leave blank to show the store name instead.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * Brand colour field.
	 *
	 * @return void
	 */
	public function render_brand_colour_field() {
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_BRAND_COLOUR ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_BRAND_COLOUR, '#2271b1' ) ); ?>" placeholder="#2271b1" style="width: 100px;" />
		<p class="description"><?php echo esc_html__( 'Header and accent colour (hex, for example #2271b1).', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * Button colour field.
	 *
	 * @return void
	 */
	public function render_button_colour_field() {
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_BUTTON_COLOUR ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_BUTTON_COLOUR, '#2271b1' ) ); ?>" placeholder="#2271b1" style="width: 100px;" />
		<p class="description"><?php echo esc_html__( 'Call-to-action button colour (hex).', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * Footer text field.
	 *
	 * @return void
	 */
	public function render_footer_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_FOOTER_TEXT ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_FOOTER_TEXT, '' ) ); ?>" />
		<p class="description"><?php echo esc_html__( 'A short line shown in the email footer (for example your business address).', 'kdna-checkout' ); ?></p>
		<?php
	}

	/* ================================================================== *
	 * Merge tags
	 * ================================================================== */

	/**
	 * The supported merge tags, for documentation in the step editor.
	 *
	 * @return array
	 */
	public static function merge_tags() {
		return array(
			'{customer_name}',
			'{cart_items}',
			'{cart_total}',
			'{recovery_link}',
			'{coupon_code}',
			'{store_name}',
			'{unsubscribe}',
		);
	}

	/**
	 * Replace merge tags in a string.
	 *
	 * @param string $text    Text containing merge tags.
	 * @param array  $context Raw replacement values keyed without braces.
	 * @param bool   $is_html Whether $text is HTML (keeps the cart_items block).
	 * @return string
	 */
	public static function merge( $text, array $context, $is_html = true ) {
		$replacements = array(
			'{customer_name}' => esc_html( $context['customer_name'] ?? '' ),
			'{cart_total}'    => wp_kses_post( $context['cart_total'] ?? '' ),
			'{recovery_link}' => esc_url( $context['recovery_link'] ?? '' ),
			'{coupon_code}'   => esc_html( $context['coupon_code'] ?? '' ),
			'{store_name}'    => esc_html( $context['store_name'] ?? '' ),
			'{unsubscribe}'   => esc_url( $context['unsubscribe'] ?? '' ),
			'{cart_items}'    => $is_html
				? ( $context['cart_items_html'] ?? '' )
				: ( $context['cart_items_text'] ?? '' ),
		);

		return strtr( (string) $text, $replacements );
	}

	/**
	 * Build the cart-items HTML block from a snapshot.
	 *
	 * @param array  $items    Snapshot items.
	 * @param string $currency Currency code for pricing.
	 * @return string
	 */
	public static function cart_items_html( array $items, $currency = '' ) {
		if ( empty( $items ) || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$rows = '';
		foreach ( $items as $item ) {
			$id      = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) ( $item['product_id'] ?? 0 );
			$product = $id ? wc_get_product( $id ) : null;
			if ( ! $product ) {
				continue;
			}
			$qty   = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$name  = $product->get_name();
			$image = $product->get_image( 'thumbnail' );
			$line  = function_exists( 'wc_price' )
				? wc_price( (float) $product->get_price() * $qty, array( 'currency' => $currency ) )
				: '';

			$rows .= '<tr>'
				. '<td style="padding:8px 12px 8px 0;width:52px;vertical-align:top;">' . $image . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce image markup.
				. '<td style="padding:8px 0;vertical-align:top;font-size:14px;color:#333333;">' . esc_html( $name )
				. ' &times; ' . esc_html( (string) $qty ) . '</td>'
				. '<td style="padding:8px 0;vertical-align:top;text-align:right;font-size:14px;color:#333333;white-space:nowrap;">' . wp_kses_post( $line ) . '</td>'
				. '</tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">' . $rows . '</table>';
	}

	/**
	 * Build a plain-text cart-items summary from a snapshot.
	 *
	 * @param array $items Snapshot items.
	 * @return string
	 */
	public static function cart_items_text( array $items ) {
		if ( empty( $items ) || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$parts = array();
		foreach ( $items as $item ) {
			$id      = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) ( $item['product_id'] ?? 0 );
			$product = $id ? wc_get_product( $id ) : null;
			if ( ! $product ) {
				continue;
			}
			$qty     = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$parts[] = $product->get_name() . ' x ' . $qty;
		}
		return implode( ', ', $parts );
	}

	/* ================================================================== *
	 * Rendering and sending
	 * ================================================================== */

	/**
	 * Wrap a merged body in the branded HTML template.
	 *
	 * @param string $body_html       Merged, sanitised body HTML.
	 * @param string $subject         Subject (used as the document title).
	 * @param string $unsubscribe_url Optional unsubscribe URL for the footer.
	 * @return string
	 */
	public static function render_email( $body_html, $subject = '', $unsubscribe_url = '' ) {
		$template = KDNA_CHECKOUT_PATH . 'templates/emails/recovery-email.php';

		/**
		 * Filter the recovery email template path.
		 *
		 * @param string $template Absolute path to the template.
		 */
		$template = apply_filters( 'kdna_checkout_recovery_email_template', $template );

		if ( ! file_exists( $template ) ) {
			return $body_html;
		}

		$data = array(
			'subject'         => $subject,
			'body_html'       => $body_html,
			'logo_url'        => (string) get_option( self::OPTION_LOGO, '' ),
			'brand_colour'    => self::sanitise_colour( get_option( self::OPTION_BRAND_COLOUR, '#2271b1' ) ),
			'button_colour'   => self::sanitise_colour( get_option( self::OPTION_BUTTON_COLOUR, '#2271b1' ) ),
			'footer_text'     => (string) get_option( self::OPTION_FOOTER_TEXT, '' ),
			'store_name'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'unsubscribe_url' => (string) $unsubscribe_url,
		);

		ob_start();
		// The template reads $email.
		$email = $data; // phpcs:ignore
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Send a branded HTML email.
	 *
	 * @param string $to              Recipient address.
	 * @param string $subject         Subject line.
	 * @param string $body_html       Merged, sanitised body HTML.
	 * @param string $unsubscribe_url Optional unsubscribe URL for the footer.
	 * @param array  $extra_headers   Optional extra mail headers (for example From).
	 * @return bool
	 */
	public static function send( $to, $subject, $body_html, $unsubscribe_url = '', $extra_headers = array() ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$html    = self::render_email( $body_html, $subject, $unsubscribe_url );
		$headers = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), (array) $extra_headers );

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
		$sent = wp_mail( $to, wp_specialchars_decode( $subject, ENT_QUOTES ), $html, $headers );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );

		return (bool) $sent;
	}

	/**
	 * Force HTML content type for our sends.
	 *
	 * @return string
	 */
	public static function html_content_type() {
		return 'text/html';
	}
}
