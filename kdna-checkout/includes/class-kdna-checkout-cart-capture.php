<?php
/**
 * Abandoned-cart capture data layer for KDNA Checkout.
 *
 * Quiet background capture, kept fully separate from the checkout
 * display layer. When a customer enters their email on checkout it is
 * captured via AJAX and a row is written to the kdna_checkout_carts
 * table with a unique recovery token, a JSON snapshot of the cart, the
 * total and currency, and a status of "active". The snapshot refreshes
 * as the cart changes, the row flips to "completed" (or "recovered",
 * when it had been abandoned) when the matching order completes, a
 * WP Cron sweep marks stale active carts "abandoned" after a
 * configurable idle period, and an auto-purge removes old rows for
 * privacy. A capture-consent mode gates capture behind an explicit
 * shopper opt-in. No emails are sent in this stage.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cart capture, status transitions, cron sweeps and the admin list.
 */
class KDNA_Checkout_Cart_Capture {

	/**
	 * AJAX action and nonce.
	 *
	 * @var string
	 */
	const AJAX_ACTION  = 'kdna_checkout_capture';
	const NONCE_ACTION = 'kdna_checkout_capture';

	/**
	 * WooCommerce session key holding the current cart's recovery token.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'kdna_checkout_cart_token';

	/**
	 * Cron hooks.
	 *
	 * @var string
	 */
	const CRON_ABANDON = 'kdna_checkout_mark_abandoned';
	const CRON_PURGE   = 'kdna_checkout_purge_carts';

	/**
	 * Options.
	 *
	 * @var string
	 */
	const OPTION_ENABLED         = 'kdna_checkout_capture_enabled';
	const OPTION_CONSENT         = 'kdna_checkout_capture_consent';
	const OPTION_CONSENT_TEXT    = 'kdna_checkout_consent_text';
	const OPTION_ABANDON_MINUTES = 'kdna_checkout_abandon_minutes';
	const OPTION_PURGE_DAYS      = 'kdna_checkout_purge_days';

	/**
	 * Row statuses.
	 *
	 * @var array
	 */
	const STATUSES = array( 'active', 'abandoned', 'recovered', 'completed' );

	/**
	 * Hook everything in.
	 */
	public function __construct() {
		// Settings (rendered by the shared WooCommerce > KDNA Checkout form).
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Capture endpoints (guests included).
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_capture' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_capture' ) );

		// Keep the snapshot current as the cart changes.
		add_action( 'woocommerce_cart_updated', array( $this, 'refresh_snapshot' ) );

		// Status transition when the matching order completes.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'mark_order_complete' ), 10, 3 );

		// Cron sweeps.
		add_filter( 'cron_schedules', array( $this, 'register_cron_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- 15 minute sweep documented below.
		add_action( 'init', array( $this, 'schedule_events' ) );
		add_action( self::CRON_ABANDON, array( $this, 'run_mark_abandoned' ) );
		add_action( self::CRON_PURGE, array( $this, 'run_purge' ) );

		// Stage 1 fires this on deactivation so components clean up
		// without Stage 1 ever changing.
		add_action( 'kdna_checkout_deactivated', array( $this, 'clear_events' ) );

		// Captured Carts admin screen.
		add_action( 'admin_menu', array( $this, 'register_admin_screen' ) );

		// Priority 6: attach capture config to the shared front-end handle.
		add_action( 'wp_enqueue_scripts', array( $this, 'localise_script' ), 6 );
	}

	/* ================================================================== *
	 * Options and settings
	 * ================================================================== */

	/**
	 * Whether capture is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'yes' );
	}

	/**
	 * Whether capture requires an explicit shopper opt-in.
	 *
	 * @return bool
	 */
	public static function consent_required() {
		return 'yes' === get_option( self::OPTION_CONSENT, 'no' );
	}

	/**
	 * Idle minutes before an active cart counts as abandoned.
	 *
	 * @return int
	 */
	public static function abandon_minutes() {
		return max( 5, absint( get_option( self::OPTION_ABANDON_MINUTES, 60 ) ) );
	}

	/**
	 * Days before captured rows are purged (0 = never purge).
	 *
	 * @return int
	 */
	public static function purge_days() {
		return absint( get_option( self::OPTION_PURGE_DAYS, 90 ) );
	}

	/**
	 * Strict yes/no sanitiser.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitise_yes_no( $value ) {
		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * Sanitise the idle period (5 minutes to 7 days).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitise_minutes( $value ) {
		return min( 10080, max( 5, absint( $value ) ) );
	}

	/**
	 * Sanitise the purge age (0 = never, up to 10 years).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitise_days( $value ) {
		return min( 3650, absint( $value ) );
	}

	/**
	 * Register the capture settings on the shared settings page.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'kdna_checkout', self::OPTION_ENABLED, array( 'sanitize_callback' => array( __CLASS__, 'sanitise_yes_no' ), 'default' => 'yes' ) );
		register_setting( 'kdna_checkout', self::OPTION_CONSENT, array( 'sanitize_callback' => array( __CLASS__, 'sanitise_yes_no' ), 'default' => 'no' ) );
		register_setting( 'kdna_checkout', self::OPTION_CONSENT_TEXT, array( 'sanitize_callback' => 'sanitize_text_field', 'default' => __( 'Keep me posted about my order and cart.', 'kdna-checkout' ) ) );
		register_setting( 'kdna_checkout', self::OPTION_ABANDON_MINUTES, array( 'sanitize_callback' => array( __CLASS__, 'sanitise_minutes' ), 'default' => 60 ) );
		register_setting( 'kdna_checkout', self::OPTION_PURGE_DAYS, array( 'sanitize_callback' => array( __CLASS__, 'sanitise_days' ), 'default' => 90 ) );

		add_settings_section(
			'kdna_checkout_capture',
			__( 'Abandoned-cart capture', 'kdna-checkout' ),
			array( $this, 'render_section_intro' ),
			'kdna-checkout'
		);

		add_settings_field( self::OPTION_ENABLED, __( 'Capture carts', 'kdna-checkout' ), array( $this, 'render_enabled_field' ), 'kdna-checkout', 'kdna_checkout_capture' );
		add_settings_field( self::OPTION_CONSENT, __( 'Require consent', 'kdna-checkout' ), array( $this, 'render_consent_field' ), 'kdna-checkout', 'kdna_checkout_capture' );
		add_settings_field( self::OPTION_ABANDON_MINUTES, __( 'Mark abandoned after', 'kdna-checkout' ), array( $this, 'render_abandon_field' ), 'kdna-checkout', 'kdna_checkout_capture' );
		add_settings_field( self::OPTION_PURGE_DAYS, __( 'Auto-purge captured carts', 'kdna-checkout' ), array( $this, 'render_purge_field' ), 'kdna-checkout', 'kdna_checkout_capture' );
	}

	/**
	 * Section introduction.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		printf(
			'<p>%s</p>',
			esc_html__( 'The checkout quietly captures the shopper\'s email and a snapshot of their cart so an abandoned cart can be recovered. View everything under WooCommerce > Captured Carts. No recovery emails are sent until the recovery sequence stage is built and configured.', 'kdna-checkout' )
		);
	}

	/**
	 * Capture toggle field.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		?>
		<label for="<?php echo esc_attr( self::OPTION_ENABLED ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="yes" <?php checked( 'yes', get_option( self::OPTION_ENABLED, 'yes' ) ); ?> />
			<?php echo esc_html__( 'Capture carts and emails on the checkout', 'kdna-checkout' ); ?>
		</label>
		<?php
	}

	/**
	 * Consent toggle and text fields.
	 *
	 * @return void
	 */
	public function render_consent_field() {
		?>
		<label for="<?php echo esc_attr( self::OPTION_CONSENT ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( self::OPTION_CONSENT ); ?>" name="<?php echo esc_attr( self::OPTION_CONSENT ); ?>" value="yes" <?php checked( 'yes', get_option( self::OPTION_CONSENT, 'no' ) ); ?> />
			<?php echo esc_html__( 'Only capture when the shopper ticks a consent box under the email field', 'kdna-checkout' ); ?>
		</label>
		<p>
			<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_CONSENT_TEXT ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_CONSENT_TEXT, __( 'Keep me posted about my order and cart.', 'kdna-checkout' ) ) ); ?>" />
		</p>
		<p class="description"><?php echo esc_html__( 'The consent box label shown to shoppers.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * Idle period field.
	 *
	 * @return void
	 */
	public function render_abandon_field() {
		?>
		<input type="number" min="5" max="10080" step="1" name="<?php echo esc_attr( self::OPTION_ABANDON_MINUTES ); ?>" value="<?php echo esc_attr( (string) self::abandon_minutes() ); ?>" style="width: 90px;" />
		<?php echo esc_html__( 'minutes of inactivity', 'kdna-checkout' ); ?>
		<?php
	}

	/**
	 * Purge field.
	 *
	 * @return void
	 */
	public function render_purge_field() {
		?>
		<input type="number" min="0" max="3650" step="1" name="<?php echo esc_attr( self::OPTION_PURGE_DAYS ); ?>" value="<?php echo esc_attr( (string) self::purge_days() ); ?>" style="width: 90px;" />
		<?php echo esc_html__( 'days (0 keeps rows forever)', 'kdna-checkout' ); ?>
		<p class="description"><?php echo esc_html__( 'Old captured carts are deleted automatically for privacy compliance.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/* ================================================================== *
	 * Capture
	 * ================================================================== */

	/**
	 * Fully prefixed carts table name.
	 *
	 * @return string
	 */
	private static function table() {
		return KDNA_Checkout_Install::carts_table_name();
	}

	/**
	 * JSON snapshot of the current cart, restorable in Stage 11.
	 *
	 * @return string
	 */
	public static function snapshot() {
		$items = array();

		if ( function_exists( 'WC' ) && null !== WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				$items[] = array(
					'product_id'   => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
					'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
					'quantity'     => isset( $item['quantity'] ) ? (int) $item['quantity'] : 1,
					'variation'    => isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array(),
				);
			}
		}

		return (string) wp_json_encode( array( 'items' => $items ) );
	}

	/**
	 * The row (if any) for a recovery token.
	 *
	 * @param string $token Recovery token.
	 * @return object|null
	 */
	public static function find_by_token( $token ) {
		global $wpdb;
		if ( '' === $token ) {
			return null;
		}
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE cart_token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
	}

	/**
	 * AJAX: capture the email plus a cart snapshot for this session.
	 *
	 * @return void
	 */
	public function ajax_capture() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! self::is_enabled() ) {
			wp_send_json_success( array( 'captured' => false ) ); // Fail-safe no-op.
		}

		if ( ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not available.', 'kdna-checkout' ) ), 400 );
		}
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( null === WC()->cart || null === WC()->session ) {
			wp_send_json_error( array( 'message' => __( 'The cart is not available.', 'kdna-checkout' ) ), 400 );
		}

		// Privacy: consent mode stores nothing without the opt-in.
		$consented = isset( $_POST['consent'] ) && '1' === $_POST['consent'];
		if ( self::consent_required() && ! $consented ) {
			wp_send_json_success( array( 'captured' => false ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A valid email address is needed.', 'kdna-checkout' ) ), 400 );
		}

		if ( WC()->cart->is_empty() ) {
			wp_send_json_success( array( 'captured' => false ) ); // Nothing to recover.
		}

		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$total = (float) WC()->cart->get_total( 'edit' );
		$curr  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		$token = (string) WC()->session->get( self::SESSION_KEY, '' );
		$row   = self::find_by_token( $token );

		if ( $row ) {
			$wpdb->update(
				$table,
				array(
					'email'         => $email,
					'cart_snapshot' => self::snapshot(),
					'cart_total'    => $total,
					'currency'      => $curr,
					'status'        => 'active',
					'updated_at'    => $now,
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s', '%f', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$token = bin2hex( random_bytes( 16 ) );
			$wpdb->insert(
				$table,
				array(
					'cart_token'    => $token,
					'email'         => $email,
					'cart_snapshot' => self::snapshot(),
					'cart_total'    => $total,
					'currency'      => $curr,
					'status'        => 'active',
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
			);
			WC()->session->set( self::SESSION_KEY, $token );
		}

		wp_send_json_success( array( 'captured' => true ) );
	}

	/**
	 * Keep the snapshot current while the shopper edits their cart
	 * (woocommerce_cart_updated). A returning shopper flips an
	 * abandoned row back to active; completed/recovered rows are final.
	 *
	 * @return void
	 */
	public function refresh_snapshot() {
		if ( ! self::is_enabled() || ! function_exists( 'WC' ) || null === WC()->cart || null === WC()->session ) {
			return;
		}

		$token = (string) WC()->session->get( self::SESSION_KEY, '' );
		if ( '' === $token ) {
			return;
		}

		$row = self::find_by_token( $token );
		if ( ! $row || in_array( $row->status, array( 'completed', 'recovered' ), true ) ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'cart_snapshot' => self::snapshot(),
				'cart_total'    => (float) WC()->cart->get_total( 'edit' ),
				'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : $row->currency,
				'status'        => 'active',
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%f', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * When the matching order completes: an active cart becomes
	 * "completed", an abandoned one becomes "recovered".
	 *
	 * @param int    $order_id    New order ID.
	 * @param array  $posted_data Posted checkout data.
	 * @param object $order       Order object.
	 * @return void
	 */
	public function mark_order_complete( $order_id, $posted_data = array(), $order = null ) {
		if ( ! function_exists( 'WC' ) || null === WC()->session ) {
			return;
		}

		$token = (string) WC()->session->get( self::SESSION_KEY, '' );
		$row   = self::find_by_token( $token );

		if ( ! $row && $order && method_exists( $order, 'get_billing_email' ) ) {
			// Fallback: latest open row for this email (for example the
			// session rotated between capture and purchase).
			global $wpdb;
			$table = self::table();
			$row   = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE email = %s AND status IN ('active','abandoned') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
					$order->get_billing_email()
				)
			);
		}

		if ( ! $row || in_array( $row->status, array( 'completed', 'recovered' ), true ) ) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'abandoned' === $row->status ? 'recovered' : 'completed',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		WC()->session->set( self::SESSION_KEY, '' );
	}

	/* ================================================================== *
	 * Cron sweeps
	 * ================================================================== */

	/**
	 * A 15-minute interval so abandonment is marked close to the
	 * configured idle period rather than up to an hour late.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public function register_cron_interval( $schedules ) {
		$schedules['kdna_checkout_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (KDNA Checkout)', 'kdna-checkout' ),
		);
		return $schedules;
	}

	/**
	 * Self-healing scheduling: ensure both sweeps are booked.
	 *
	 * @return void
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( self::CRON_ABANDON ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'kdna_checkout_15min', self::CRON_ABANDON );
		}
		if ( ! wp_next_scheduled( self::CRON_PURGE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PURGE );
		}
	}

	/**
	 * Clear the scheduled sweeps (fired on plugin deactivation through
	 * the kdna_checkout_deactivated action).
	 *
	 * @return void
	 */
	public function clear_events() {
		wp_clear_scheduled_hook( self::CRON_ABANDON );
		wp_clear_scheduled_hook( self::CRON_PURGE );
	}

	/**
	 * Mark stale active carts abandoned after the configured idle period.
	 *
	 * @return void
	 */
	public function run_mark_abandoned() {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::abandon_minutes() * MINUTE_IN_SECONDS );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'abandoned', updated_at = %s WHERE status = 'active' AND updated_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
				current_time( 'mysql', true ),
				$cutoff
			)
		);
	}

	/**
	 * Purge old rows for privacy compliance (0 days = keep forever).
	 *
	 * @return void
	 */
	public function run_purge() {
		$days = self::purge_days();
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE updated_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
				$cutoff
			)
		);
	}

	/* ================================================================== *
	 * Captured Carts admin screen
	 * ================================================================== */

	/**
	 * Register WooCommerce > Captured Carts.
	 *
	 * @return void
	 */
	public function register_admin_screen() {
		add_submenu_page(
			'woocommerce',
			__( 'Captured Carts', 'kdna-checkout' ),
			__( 'Captured Carts', 'kdna-checkout' ),
			'manage_woocommerce',
			'kdna-checkout-carts',
			array( $this, 'render_admin_screen' )
		);
	}

	/**
	 * Render the captured carts list: email, items, value, status and
	 * timestamps, filterable by status, paginated, everything escaped.
	 *
	 * @return void
	 */
	public function render_admin_screen() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kdna-checkout' ) );
		}

		global $wpdb;
		$table = self::table();

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = '';
		}

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$offset   = ( $paged - 1 ) * $per_page;

		if ( '' !== $status ) {
			$total_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
			$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d", $status, $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
		} else {
			$total_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix + fixed string.
			$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
		}

		$statuses = array(
			''          => __( 'All statuses', 'kdna-checkout' ),
			'active'    => __( 'Active', 'kdna-checkout' ),
			'abandoned' => __( 'Abandoned', 'kdna-checkout' ),
			'recovered' => __( 'Recovered', 'kdna-checkout' ),
			'completed' => __( 'Completed', 'kdna-checkout' ),
		);
		?>
		<div class="wrap kdna-checkout-admin">
			<h1><?php echo esc_html__( 'Captured Carts', 'kdna-checkout' ); ?></h1>

			<form method="get">
				<input type="hidden" name="page" value="kdna-checkout-carts" />
				<label for="kdna-carts-status" class="screen-reader-text"><?php echo esc_html__( 'Filter by status', 'kdna-checkout' ); ?></label>
				<select name="status" id="kdna-carts-status">
					<?php foreach ( $statuses as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'kdna-checkout' ); ?></button>
			</form>

			<table class="widefat striped" style="margin-top: 12px;">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Email', 'kdna-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Items', 'kdna-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Cart value', 'kdna-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'kdna-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Captured', 'kdna-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Last activity', 'kdna-checkout' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No captured carts yet.', 'kdna-checkout' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$snapshot = json_decode( (string) $row->cart_snapshot, true );
							$items    = isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? count( $snapshot['items'] ) : 0;
							$value    = function_exists( 'wc_price' )
								? wc_price( (float) $row->cart_total, array( 'currency' => (string) $row->currency ) )
								: esc_html( $row->currency . ' ' . number_format( (float) $row->cart_total, 2 ) );
							?>
							<tr>
								<td><?php echo esc_html( $row->email ); ?></td>
								<td><?php echo esc_html( (string) $items ); ?></td>
								<td><?php echo wp_kses_post( $value ); ?></td>
								<td><span class="kdna-checkout-admin__status kdna-checkout-admin__status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span></td>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><?php echo esc_html( $row->updated_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $total_rows / $per_page );
			if ( $total_pages > 1 ) {
				echo '<p>' . wp_kses_post(
					(string) paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $total_pages,
						)
					)
				) . '</p>';
			}
			?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: purge age in days. */
					esc_html__( 'Rows older than %d days are purged automatically (configure under WooCommerce > KDNA Checkout).', 'kdna-checkout' ),
					(int) self::purge_days()
				);
				?>
			</p>
		</div>
		<?php
	}

	/* ================================================================== *
	 * Front-end config
	 * ================================================================== */

	/**
	 * Attach capture config to the shared front-end handle.
	 *
	 * @return void
	 */
	public function localise_script() {
		wp_localize_script(
			KDNA_Checkout_Assets::HANDLE,
			'kdnaCheckoutCapture',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
				'enabled'         => self::is_enabled() ? '1' : '',
				'consentRequired' => self::consent_required() ? '1' : '',
				'consentText'     => (string) get_option( self::OPTION_CONSENT_TEXT, __( 'Keep me posted about my order and cart.', 'kdna-checkout' ) ),
			)
		);
	}
}
