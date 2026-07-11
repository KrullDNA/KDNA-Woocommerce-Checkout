<?php
/**
 * Order bumps for KDNA Checkout.
 *
 * Admin side: meta boxes on the kdna_order_bump custom post type
 * storing the product to offer, an optional discount (percentage or
 * fixed), a description, an optional image (featured image) and which
 * checkout the bump applies to. The bump headline is the post title.
 *
 * Front end: a tick-to-add box above the pay button. Ticking adds the
 * product to the cart at the discounted price via AJAX with no reload
 * and updates the totals; unticking removes it. Fail-safe defaults:
 * unticked, full price, and any missing/invalid bump silently skips.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Order bump admin, rendering and cart logic.
 */
class KDNA_Checkout_Order_Bump {

	/**
	 * Nonce action for the toggle AJAX call and the admin meta box.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'kdna_checkout_bump';

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'kdna_checkout_bump_toggle';

	/**
	 * Meta keys.
	 *
	 * @var string
	 */
	const META_PRODUCT         = '_kdna_bump_product_id';
	const META_DISCOUNT_TYPE   = '_kdna_bump_discount_type';
	const META_DISCOUNT_AMOUNT = '_kdna_bump_discount_amount';
	const META_DESCRIPTION     = '_kdna_bump_description';
	const META_APPLIES_TO      = '_kdna_bump_applies_to';

	/**
	 * Cart item data key marking an item as a bump.
	 *
	 * @var string
	 */
	const CART_ITEM_KEY = 'kdna_bump_id';

	/**
	 * Allowed discount types.
	 *
	 * @var array
	 */
	const DISCOUNT_TYPES = array( 'none', 'percent', 'fixed' );

	/**
	 * Hook everything in.
	 */
	public function __construct() {
		// Admin.
		add_filter( 'register_post_type_args', array( $this, 'surface_cpt_menu' ), 10, 2 );
		add_action( 'after_setup_theme', array( $this, 'ensure_thumbnail_support' ), 20 );
		add_action( 'add_meta_boxes_kdna_order_bump', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_kdna_order_bump', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		// Front end.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_bumps' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_toggle' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_discounts' ), 20 );
		add_filter( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 3 );

		// Priority 6: attach AJAX data to the shared handle registered at 5.
		add_action( 'wp_enqueue_scripts', array( $this, 'localise_script' ), 6 );
	}

	/* ================================================================== *
	 * Admin
	 * ================================================================== */

	/**
	 * Surface the Order Bumps CPT under the Settings menu, without
	 * modifying the Stage 1 CPT registration.
	 *
	 * @param array  $args      Post type arguments.
	 * @param string $post_type Post type name.
	 * @return array
	 */
	public function surface_cpt_menu( $args, $post_type ) {
		if ( 'kdna_order_bump' === $post_type ) {
			$args['show_in_menu'] = 'options-general.php';
		}
		return $args;
	}

	/**
	 * Make the featured image box available for the optional bump image,
	 * without widening theme support when the theme already has it.
	 *
	 * @return void
	 */
	public function ensure_thumbnail_support() {
		add_post_type_support( 'kdna_order_bump', 'thumbnail' );

		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails', array( 'kdna_order_bump' ) );
		}
	}

	/**
	 * Enqueue WooCommerce's product-search select on the bump edit screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'kdna_order_bump' !== $screen->post_type ) {
			return;
		}

		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
		}
		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	/**
	 * Register the bump settings meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'kdna-checkout-bump-settings',
			__( 'Order Bump Settings', 'kdna-checkout' ),
			array( $this, 'render_meta_box' ),
			'kdna_order_bump',
			'normal',
			'high'
		);
	}

	/**
	 * Render the bump settings meta box.
	 *
	 * @param WP_Post $post Current bump post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, 'kdna_checkout_bump_nonce' );

		$product_id      = (int) get_post_meta( $post->ID, self::META_PRODUCT, true );
		$discount_type   = (string) get_post_meta( $post->ID, self::META_DISCOUNT_TYPE, true );
		$discount_amount = (string) get_post_meta( $post->ID, self::META_DISCOUNT_AMOUNT, true );
		$description     = (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true );
		$applies_to      = (int) get_post_meta( $post->ID, self::META_APPLIES_TO, true );

		if ( ! in_array( $discount_type, self::DISCOUNT_TYPES, true ) ) {
			$discount_type = 'none';
		}

		$product_label = '';
		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_label = $product->get_formatted_name();
			}
		}
		?>
		<p>
			<label for="kdna_bump_product_id"><strong><?php echo esc_html__( 'Product to offer', 'kdna-checkout' ); ?></strong></label><br />
			<select id="kdna_bump_product_id"
				name="kdna_bump_product_id"
				class="wc-product-search"
				style="width: 100%; max-width: 400px;"
				data-placeholder="<?php echo esc_attr__( 'Search for a product&hellip;', 'kdna-checkout' ); ?>"
				data-action="woocommerce_json_search_products_and_variations"
				data-allow_clear="true">
				<?php if ( $product_id ) : ?>
					<option value="<?php echo esc_attr( (string) $product_id ); ?>" selected="selected"><?php echo esc_html( $product_label ? $product_label : '#' . $product_id ); ?></option>
				<?php endif; ?>
			</select>
		</p>
		<p>
			<label for="kdna_bump_discount_type"><strong><?php echo esc_html__( 'Discount (optional)', 'kdna-checkout' ); ?></strong></label><br />
			<select id="kdna_bump_discount_type" name="kdna_bump_discount_type">
				<option value="none" <?php selected( $discount_type, 'none' ); ?>><?php echo esc_html__( 'No discount (full price)', 'kdna-checkout' ); ?></option>
				<option value="percent" <?php selected( $discount_type, 'percent' ); ?>><?php echo esc_html__( 'Percentage off', 'kdna-checkout' ); ?></option>
				<option value="fixed" <?php selected( $discount_type, 'fixed' ); ?>><?php echo esc_html__( 'Fixed amount off', 'kdna-checkout' ); ?></option>
			</select>
			<input type="number"
				id="kdna_bump_discount_amount"
				name="kdna_bump_discount_amount"
				value="<?php echo esc_attr( $discount_amount ); ?>"
				min="0"
				step="0.01"
				style="width: 100px;"
				aria-label="<?php echo esc_attr__( 'Discount amount', 'kdna-checkout' ); ?>" />
		</p>
		<p>
			<label for="kdna_bump_description"><strong><?php echo esc_html__( 'Description', 'kdna-checkout' ); ?></strong></label><br />
			<textarea id="kdna_bump_description"
				name="kdna_bump_description"
				rows="3"
				style="width: 100%; max-width: 500px;"><?php echo esc_textarea( $description ); ?></textarea>
		</p>
		<p>
			<label for="kdna_bump_applies_to"><strong><?php echo esc_html__( 'Applies to', 'kdna-checkout' ); ?></strong></label><br />
			<?php
			wp_dropdown_pages(
				array(
					'id'                => 'kdna_bump_applies_to',
					'name'              => 'kdna_bump_applies_to',
					'selected'          => $applies_to,
					'show_option_none'  => __( 'All checkouts', 'kdna-checkout' ),
					'option_none_value' => '0',
				)
			);
			?>
		</p>
		<p class="description">
			<?php echo esc_html__( 'The bump headline is this entry\'s title. The optional image is the featured image (the product image is used when none is set). The bump only shows while the product is purchasable and in stock.', 'kdna-checkout' ); ?>
		</p>
		<?php
	}

	/**
	 * Save the bump settings.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['kdna_checkout_bump_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kdna_checkout_bump_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_id = isset( $_POST['kdna_bump_product_id'] ) ? absint( wp_unslash( $_POST['kdna_bump_product_id'] ) ) : 0;

		$discount_type = isset( $_POST['kdna_bump_discount_type'] ) ? sanitize_key( wp_unslash( $_POST['kdna_bump_discount_type'] ) ) : 'none';
		if ( ! in_array( $discount_type, self::DISCOUNT_TYPES, true ) ) {
			$discount_type = 'none'; // Fail-safe: unknown discount means full price.
		}

		$discount_amount = isset( $_POST['kdna_bump_discount_amount'] ) ? (float) wp_unslash( $_POST['kdna_bump_discount_amount'] ) : 0;
		$discount_amount = max( 0, $discount_amount );
		if ( 'percent' === $discount_type ) {
			$discount_amount = min( 100, $discount_amount );
		}

		$description = isset( $_POST['kdna_bump_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['kdna_bump_description'] ) ) : '';
		$applies_to  = isset( $_POST['kdna_bump_applies_to'] ) ? absint( wp_unslash( $_POST['kdna_bump_applies_to'] ) ) : 0;

		update_post_meta( $post_id, self::META_PRODUCT, $product_id );
		update_post_meta( $post_id, self::META_DISCOUNT_TYPE, $discount_type );
		update_post_meta( $post_id, self::META_DISCOUNT_AMOUNT, $discount_amount );
		update_post_meta( $post_id, self::META_DESCRIPTION, $description );
		update_post_meta( $post_id, self::META_APPLIES_TO, $applies_to );
	}

	/* ================================================================== *
	 * Front end
	 * ================================================================== */

	/**
	 * Attach the AJAX endpoint and nonce to the front-end script handle.
	 *
	 * @return void
	 */
	public function localise_script() {
		wp_localize_script(
			KDNA_Checkout_Assets::HANDLE,
			'kdnaCheckoutBump',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * The checkout page ID for applies-to matching. During WooCommerce's
	 * fragment refresh the page context is gone, so the ID rides along in
	 * the posted form data via our hidden field.
	 *
	 * @return int
	 */
	public static function current_checkout_page_id() {
		if ( wp_doing_ajax() && isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only context detection inside WooCommerce's own AJAX flow.
			parse_str( wp_unslash( $_POST['post_data'] ), $posted ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual values sanitised below.
			if ( ! empty( $posted['kdna_checkout_page_id'] ) ) {
				return absint( $posted['kdna_checkout_page_id'] );
			}
		}

		return absint( get_the_ID() );
	}

	/**
	 * Published bumps that apply to the given checkout page.
	 *
	 * @param int $page_id Checkout page ID (0 matches "all" bumps only).
	 * @return array Array of WP_Post.
	 */
	public static function get_active_bumps( $page_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'kdna_order_bump',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			)
		);

		$bumps = array();
		foreach ( $posts as $post ) {
			$product_id = (int) get_post_meta( $post->ID, self::META_PRODUCT, true );
			if ( $product_id <= 0 ) {
				continue;
			}
			$applies_to = (int) get_post_meta( $post->ID, self::META_APPLIES_TO, true );
			if ( 0 !== $applies_to && $applies_to !== (int) $page_id ) {
				continue;
			}
			$bumps[] = $post;
		}

		return $bumps;
	}

	/**
	 * The discounted unit price for a bump's product. Fail-safe: any
	 * invalid discount returns the full price.
	 *
	 * @param float   $base Price before discount.
	 * @param WP_Post $bump Bump post.
	 * @return float
	 */
	public static function discounted_price( $base, $bump ) {
		$base   = (float) $base;
		$type   = (string) get_post_meta( $bump->ID, self::META_DISCOUNT_TYPE, true );
		$amount = (float) get_post_meta( $bump->ID, self::META_DISCOUNT_AMOUNT, true );

		if ( $amount <= 0 ) {
			return $base;
		}

		if ( 'percent' === $type ) {
			return (float) max( 0, $base * ( 1 - min( 100, $amount ) / 100 ) );
		}

		if ( 'fixed' === $type ) {
			return (float) max( 0, $base - $amount );
		}

		return $base;
	}

	/**
	 * Whether the cart already contains the given bump.
	 *
	 * @param int $bump_id Bump post ID.
	 * @return bool
	 */
	public static function cart_has_bump( $bump_id ) {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( isset( $item[ self::CART_ITEM_KEY ] ) && (int) $item[ self::CART_ITEM_KEY ] === (int) $bump_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render the tick-to-add bump boxes above the pay button
	 * (woocommerce_review_order_before_submit).
	 *
	 * @return void
	 */
	public function render_bumps() {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return;
		}

		$page_id = self::current_checkout_page_id();
		$bumps   = self::get_active_bumps( $page_id );

		// The hidden field keeps the page context available during
		// WooCommerce's AJAX fragment refreshes.
		printf( '<input type="hidden" name="kdna_checkout_page_id" value="%s" />', esc_attr( (string) $page_id ) );

		foreach ( $bumps as $bump ) {
			$this->render_bump( $bump );
		}
	}

	/**
	 * Render one bump box.
	 *
	 * @param WP_Post $bump Bump post.
	 * @return void
	 */
	private function render_bump( $bump ) {
		$product_id = (int) get_post_meta( $bump->ID, self::META_PRODUCT, true );
		$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		// Fail-safe: no product, unbuyable or out of stock means no bump.
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}

		$base_price  = (float) $product->get_price();
		$bump_price  = self::discounted_price( $base_price, $bump );
		$has_discount = $bump_price < $base_price;

		$image_html = get_the_post_thumbnail( $bump->ID, 'woocommerce_thumbnail' );
		if ( ! $image_html ) {
			$image_html = $product->get_image( 'woocommerce_thumbnail' );
		}

		$description = (string) get_post_meta( $bump->ID, self::META_DESCRIPTION, true );
		$in_cart     = self::cart_has_bump( $bump->ID );

		$display_base = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $base_price;
		$display_bump = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product, array( 'price' => $bump_price ) ) : $bump_price;
		?>
		<div class="kdna-checkout-bump" data-bump="<?php echo esc_attr( (string) $bump->ID ); ?>">
			<label class="kdna-checkout-bump__label">
				<input type="checkbox"
					class="kdna-checkout-bump__checkbox"
					<?php checked( $in_cart ); ?>
					aria-describedby="kdna-checkout-bump-desc-<?php echo esc_attr( (string) $bump->ID ); ?>" />
				<?php if ( $image_html ) : ?>
					<span class="kdna-checkout-bump__image"><?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image markup from WordPress/WooCommerce. ?></span>
				<?php endif; ?>
				<span class="kdna-checkout-bump__text">
					<span class="kdna-checkout-bump__headline"><?php echo esc_html( get_the_title( $bump ) ); ?></span>
					<?php if ( '' !== $description ) : ?>
						<span class="kdna-checkout-bump__description" id="kdna-checkout-bump-desc-<?php echo esc_attr( (string) $bump->ID ); ?>"><?php echo esc_html( $description ); ?></span>
					<?php endif; ?>
					<span class="kdna-checkout-bump__price">
						<?php if ( $has_discount ) : ?>
							<del><?php echo wp_kses_post( wc_price( $display_base ) ); ?></del>
							<ins><?php echo wp_kses_post( wc_price( $display_bump ) ); ?></ins>
						<?php else : ?>
							<?php echo wp_kses_post( wc_price( $display_base ) ); ?>
						<?php endif; ?>
					</span>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * AJAX: tick adds the bump product (marked with the bump ID),
	 * untick removes it. Totals refresh client-side through
	 * WooCommerce's own update_checkout event.
	 *
	 * @return void
	 */
	public function ajax_toggle() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'The cart is not available.', 'kdna-checkout' ) ), 400 );
		}

		$bump_id = isset( $_POST['bump_id'] ) ? absint( wp_unslash( $_POST['bump_id'] ) ) : 0;
		$ticked  = isset( $_POST['ticked'] ) && '1' === $_POST['ticked'];

		$bump = get_post( $bump_id );
		if ( ! $bump || 'kdna_order_bump' !== $bump->post_type || 'publish' !== $bump->post_status ) {
			wp_send_json_error( array( 'message' => __( 'That offer is no longer available.', 'kdna-checkout' ) ), 404 );
		}

		$cart = WC()->cart;

		if ( $ticked ) {
			if ( ! self::cart_has_bump( $bump_id ) ) {
				$product_id = (int) get_post_meta( $bump_id, self::META_PRODUCT, true );
				$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

				if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
					wp_send_json_error( array( 'message' => __( 'That product cannot be added right now.', 'kdna-checkout' ) ), 400 );
				}

				$parent_id    = $product_id;
				$variation_id = 0;
				if ( $product->is_type( 'variation' ) ) {
					$parent_id    = $product->get_parent_id();
					$variation_id = $product->get_id();
				}

				$added = $cart->add_to_cart(
					$parent_id,
					1,
					$variation_id,
					array(),
					array( self::CART_ITEM_KEY => $bump_id )
				);

				if ( ! $added ) {
					wp_send_json_error( array( 'message' => __( 'That product could not be added.', 'kdna-checkout' ) ), 400 );
				}
			}
		} else {
			foreach ( $cart->get_cart() as $cart_item_key => $item ) {
				if ( isset( $item[ self::CART_ITEM_KEY ] ) && (int) $item[ self::CART_ITEM_KEY ] === $bump_id ) {
					$cart->remove_cart_item( $cart_item_key );
				}
			}
		}

		$cart->calculate_totals();

		wp_send_json_success( array( 'in_cart' => self::cart_has_bump( $bump_id ) ) );
	}

	/**
	 * Apply the bump discount to marked cart items.
	 *
	 * The price is always computed from a pristine product instance, so
	 * repeated total calculations never compound a percentage discount.
	 * Fail-safe: a deleted or unpublished bump leaves the full price.
	 *
	 * @param WC_Cart $cart Cart instance.
	 * @return void
	 */
	public function apply_discounts( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item[ self::CART_ITEM_KEY ] ) || empty( $item['data'] ) ) {
				continue;
			}

			$bump = get_post( (int) $item[ self::CART_ITEM_KEY ] );
			if ( ! $bump || 'kdna_order_bump' !== $bump->post_type || 'publish' !== $bump->post_status ) {
				continue; // Fail-safe: full price stands.
			}

			$pristine = function_exists( 'wc_get_product' ) ? wc_get_product( $item['data']->get_id() ) : null;
			if ( ! $pristine ) {
				continue;
			}

			$base       = (float) $pristine->get_price();
			$discounted = self::discounted_price( $base, $bump );

			if ( $discounted < $base ) {
				$item['data']->set_price( $discounted );
			}
		}
	}

	/**
	 * Record the bump ID on the order line item for traceability.
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @return WC_Order_Item_Product
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values ) {
		if ( ! empty( $values[ self::CART_ITEM_KEY ] ) ) {
			$item->add_meta_data( '_kdna_bump_id', absint( $values[ self::CART_ITEM_KEY ] ), true );
		}
		return $item;
	}
}
