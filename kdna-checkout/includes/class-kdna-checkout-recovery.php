<?php
/**
 * Recovery email sequence engine for KDNA Checkout.
 *
 * Admin-built sequence on the kdna_recovery_email CPT: each step stores
 * a subject, a body (the WordPress editor, with merge tags), a delay
 * measured from cart abandonment, and an optional coupon. A WP Cron
 * loop sends each due step to abandoned carts, records what was sent,
 * and stops the moment the customer buys (the cart flips to recovered)
 * or clicks unsubscribe. A recovery link restores the exact saved cart
 * from its token and lands the customer on the checkout.
 *
 * This is the recovery layer; branding, merge tags and the template
 * live in KDNA_Checkout_Emails. No email is ever sent unless the admin
 * has switched recovery on and built at least one step.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Recovery step editor, scheduler, send loop and link endpoints.
 */
class KDNA_Checkout_Recovery {

	/**
	 * Post type of a recovery step.
	 *
	 * @var string
	 */
	const POST_TYPE = 'kdna_recovery_email';

	/**
	 * Cron hook for the send loop.
	 *
	 * @var string
	 */
	const CRON_SEND = 'kdna_checkout_send_recovery';

	/**
	 * Nonce actions.
	 *
	 * @var string
	 */
	const NONCE_META = 'kdna_checkout_recovery_meta';
	const NONCE_TEST = 'kdna_checkout_recovery_test';

	/**
	 * Options.
	 *
	 * @var string
	 */
	const OPTION_ENABLED    = 'kdna_checkout_recovery_enabled';
	const OPTION_FROM_NAME  = 'kdna_checkout_recovery_from_name';
	const OPTION_FROM_EMAIL = 'kdna_checkout_recovery_from_email';
	const OPTION_DB_VERSION = 'kdna_checkout_recovery_db_version';

	/**
	 * Step meta keys.
	 *
	 * @var string
	 */
	const META_SUBJECT      = '_kdna_recovery_subject';
	const META_DELAY_AMOUNT = '_kdna_recovery_delay_amount';
	const META_DELAY_UNIT   = '_kdna_recovery_delay_unit';
	const META_COUPON       = '_kdna_recovery_coupon';

	/**
	 * Query vars for the front-end link endpoints.
	 *
	 * @var string
	 */
	const VAR_RECOVER     = 'kdna_recover';
	const VAR_COUPON      = 'kdna_coupon';
	const VAR_UNSUBSCRIBE = 'kdna_unsubscribe';

	/**
	 * Current schema version for the recovery columns.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Hook everything in.
	 */
	public function __construct() {
		// Additive schema migration (adds recovery columns to the carts table).
		add_action( 'admin_init', array( $this, 'maybe_upgrade_schema' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_schema' ) );

		// Recovery settings and the step CPT admin.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'register_post_type_args', array( $this, 'surface_cpt_menu' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );

		// Test send.
		add_action( 'admin_post_kdna_checkout_test_recovery', array( $this, 'handle_test_send' ) );
		add_action( 'admin_notices', array( $this, 'maybe_test_notice' ) );

		// Cron send loop (reuses the 15-minute interval registered by capture).
		add_action( 'init', array( $this, 'schedule_events' ) );
		add_action( self::CRON_SEND, array( $this, 'run_send_loop' ) );
		add_action( 'kdna_checkout_deactivated', array( $this, 'clear_events' ) );

		// Front-end link endpoints.
		add_action( 'template_redirect', array( $this, 'handle_link_endpoints' ) );
	}

	/* ================================================================== *
	 * Schema migration (additive, never touches the Stage 1 file)
	 * ================================================================== */

	/**
	 * Add the recovery tracking columns to the carts table once.
	 *
	 * @return void
	 */
	public function maybe_upgrade_schema() {
		if ( get_option( self::OPTION_DB_VERSION ) === self::DB_VERSION ) {
			return;
		}
		if ( ! class_exists( 'KDNA_Checkout_Install' ) ) {
			return;
		}

		global $wpdb;
		$table   = KDNA_Checkout_Install::carts_table_name();
		$columns = $wpdb->get_col( "DESC {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix + fixed string; schema read.

		if ( ! is_array( $columns ) ) {
			return;
		}

		$additions = array(
			'abandoned_at'       => "ADD COLUMN abandoned_at datetime NULL DEFAULT NULL",
			'recovery_step_sent' => "ADD COLUMN recovery_step_sent smallint(5) unsigned NOT NULL DEFAULT 0",
			'recovery_last_sent' => "ADD COLUMN recovery_last_sent datetime NULL DEFAULT NULL",
			'unsubscribed'       => "ADD COLUMN unsubscribed tinyint(1) NOT NULL DEFAULT 0",
		);

		foreach ( $additions as $column => $clause ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} {$clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed DDL, table name from $wpdb->prefix.
			}
		}

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/* ================================================================== *
	 * Settings
	 * ================================================================== */

	/**
	 * Whether the recovery sequence is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Register recovery settings on the shared settings page.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'kdna_checkout', self::OPTION_ENABLED, array( 'sanitize_callback' => array( 'KDNA_Checkout_Cart_Capture', 'sanitise_yes_no' ), 'default' => 'no' ) );
		register_setting( 'kdna_checkout', self::OPTION_FROM_NAME, array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( 'kdna_checkout', self::OPTION_FROM_EMAIL, array( 'sanitize_callback' => 'sanitize_email', 'default' => '' ) );

		add_settings_section(
			'kdna_checkout_recovery',
			__( 'Recovery emails', 'kdna-checkout' ),
			array( $this, 'render_section_intro' ),
			'kdna-checkout'
		);

		add_settings_field( self::OPTION_ENABLED, __( 'Send recovery emails', 'kdna-checkout' ), array( $this, 'render_enabled_field' ), 'kdna-checkout', 'kdna_checkout_recovery' );
		add_settings_field( self::OPTION_FROM_NAME, __( 'From name', 'kdna-checkout' ), array( $this, 'render_from_name_field' ), 'kdna-checkout', 'kdna_checkout_recovery' );
		add_settings_field( self::OPTION_FROM_EMAIL, __( 'From email', 'kdna-checkout' ), array( $this, 'render_from_email_field' ), 'kdna-checkout', 'kdna_checkout_recovery' );
		add_settings_field( 'kdna_checkout_recovery_test', __( 'Test email', 'kdna-checkout' ), array( $this, 'render_test_field' ), 'kdna-checkout', 'kdna_checkout_recovery' );
	}

	/**
	 * Section intro with a link to the step editor.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		$new_step = admin_url( 'post-new.php?post_type=' . self::POST_TYPE );
		$all_steps = admin_url( 'edit.php?post_type=' . self::POST_TYPE );
		printf(
			'<p>%s</p><p><a href="%s" class="button">%s</a> <a href="%s" class="button">%s</a></p>',
			esc_html__( 'Build the sequence as recovery-email steps, each with its own delay and optional coupon. Steps are sent in order of their delay from abandonment. Nothing sends until this is switched on and at least one step exists.', 'kdna-checkout' ),
			esc_url( $all_steps ),
			esc_html__( 'Manage recovery steps', 'kdna-checkout' ),
			esc_url( $new_step ),
			esc_html__( 'Add a step', 'kdna-checkout' )
		);
	}

	/**
	 * Enable toggle field.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		?>
		<label for="<?php echo esc_attr( self::OPTION_ENABLED ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="yes" <?php checked( 'yes', get_option( self::OPTION_ENABLED, 'no' ) ); ?> />
			<?php echo esc_html__( 'Email customers who abandon their cart', 'kdna-checkout' ); ?>
		</label>
		<p class="description"><?php echo esc_html__( 'Respects the capture-consent setting: only carts that were captured are ever emailed.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * From-name field.
	 *
	 * @return void
	 */
	public function render_from_name_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_FROM_NAME ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_FROM_NAME, '' ) ); ?>" placeholder="<?php echo esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>" />
		<?php
	}

	/**
	 * From-email field.
	 *
	 * @return void
	 */
	public function render_from_email_field() {
		?>
		<input type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION_FROM_EMAIL ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_FROM_EMAIL, '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
		<p class="description"><?php echo esc_html__( 'Leave blank to use the WordPress default.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * Test-send button (posts to admin-post, outside the settings form).
	 *
	 * @return void
	 */
	public function render_test_field() {
		$user = wp_get_current_user();
		?>
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kdna_checkout_test_recovery' ), self::NONCE_TEST ) ); ?>" class="button">
			<?php echo esc_html__( 'Send test to me', 'kdna-checkout' ); ?>
		</a>
		<p class="description">
			<?php
			printf(
				/* translators: %s: admin email address. */
				esc_html__( 'Sends the first recovery step (with sample data) to %s.', 'kdna-checkout' ),
				esc_html( $user ? $user->user_email : get_option( 'admin_email' ) )
			);
			?>
		</p>
		<?php
	}

	/* ================================================================== *
	 * Step editor (CPT meta boxes)
	 * ================================================================== */

	/**
	 * Surface the recovery-email CPT under the Settings menu.
	 *
	 * @param array  $args      Post type args.
	 * @param string $post_type Post type name.
	 * @return array
	 */
	public function surface_cpt_menu( $args, $post_type ) {
		if ( self::POST_TYPE === $post_type ) {
			$args['show_in_menu'] = 'woocommerce';
		}
		return $args;
	}

	/**
	 * Register the step settings meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'kdna-checkout-recovery-settings',
			__( 'Step Settings', 'kdna-checkout' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			'kdna-checkout-recovery-tags',
			__( 'Merge Tags', 'kdna-checkout' ),
			array( $this, 'render_tags_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the step settings meta box.
	 *
	 * @param WP_Post $post Current step.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_META, 'kdna_checkout_recovery_nonce' );

		$subject = (string) get_post_meta( $post->ID, self::META_SUBJECT, true );
		$amount  = (int) get_post_meta( $post->ID, self::META_DELAY_AMOUNT, true );
		$unit    = (string) get_post_meta( $post->ID, self::META_DELAY_UNIT, true );
		$coupon  = (string) get_post_meta( $post->ID, self::META_COUPON, true );

		if ( $amount <= 0 ) {
			$amount = 1;
		}
		if ( ! in_array( $unit, array( 'minutes', 'hours', 'days' ), true ) ) {
			$unit = 'hours';
		}
		?>
		<p>
			<label for="kdna_recovery_subject"><strong><?php echo esc_html__( 'Subject line', 'kdna-checkout' ); ?></strong></label>
			<input type="text" id="kdna_recovery_subject" name="kdna_recovery_subject" class="widefat" value="<?php echo esc_attr( $subject ); ?>" placeholder="<?php echo esc_attr__( 'You left something behind', 'kdna-checkout' ); ?>" />
		</p>
		<p>
			<strong><?php echo esc_html__( 'Send this step', 'kdna-checkout' ); ?></strong><br />
			<input type="number" min="1" step="1" name="kdna_recovery_delay_amount" value="<?php echo esc_attr( (string) $amount ); ?>" style="width: 70px;" />
			<select name="kdna_recovery_delay_unit">
				<option value="minutes" <?php selected( $unit, 'minutes' ); ?>><?php echo esc_html__( 'minutes', 'kdna-checkout' ); ?></option>
				<option value="hours" <?php selected( $unit, 'hours' ); ?>><?php echo esc_html__( 'hours', 'kdna-checkout' ); ?></option>
				<option value="days" <?php selected( $unit, 'days' ); ?>><?php echo esc_html__( 'days', 'kdna-checkout' ); ?></option>
			</select>
			<br />
			<span class="description"><?php echo esc_html__( 'after the cart is abandoned.', 'kdna-checkout' ); ?></span>
		</p>
		<p>
			<label for="kdna_recovery_coupon"><strong><?php echo esc_html__( 'Coupon code (optional)', 'kdna-checkout' ); ?></strong></label>
			<input type="text" id="kdna_recovery_coupon" name="kdna_recovery_coupon" class="widefat" value="<?php echo esc_attr( $coupon ); ?>" />
			<span class="description"><?php echo esc_html__( 'Attached to the recovery link and available as {coupon_code}.', 'kdna-checkout' ); ?></span>
		</p>
		<p class="description"><?php echo esc_html__( 'Write the email body in the editor above using the merge tags listed opposite. Publish the step to make it live.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * Render the merge-tags reference box.
	 *
	 * @return void
	 */
	public function render_tags_box() {
		echo '<p>' . esc_html__( 'Use these in the subject and body:', 'kdna-checkout' ) . '</p><ul style="margin:0;">';
		foreach ( KDNA_Checkout_Emails::merge_tags() as $tag ) {
			echo '<li><code>' . esc_html( $tag ) . '</code></li>';
		}
		echo '</ul>';
	}

	/**
	 * Save the step meta.
	 *
	 * @param int     $post_id Step ID.
	 * @param WP_Post $post    Step object.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['kdna_checkout_recovery_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kdna_checkout_recovery_nonce'] ) ), self::NONCE_META ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$subject = isset( $_POST['kdna_recovery_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_recovery_subject'] ) ) : '';
		$amount  = isset( $_POST['kdna_recovery_delay_amount'] ) ? max( 1, absint( wp_unslash( $_POST['kdna_recovery_delay_amount'] ) ) ) : 1;
		$unit    = isset( $_POST['kdna_recovery_delay_unit'] ) ? sanitize_key( wp_unslash( $_POST['kdna_recovery_delay_unit'] ) ) : 'hours';
		if ( ! in_array( $unit, array( 'minutes', 'hours', 'days' ), true ) ) {
			$unit = 'hours';
		}
		$coupon = isset( $_POST['kdna_recovery_coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_recovery_coupon'] ) ) : '';

		update_post_meta( $post_id, self::META_SUBJECT, $subject );
		update_post_meta( $post_id, self::META_DELAY_AMOUNT, $amount );
		update_post_meta( $post_id, self::META_DELAY_UNIT, $unit );
		update_post_meta( $post_id, self::META_COUPON, $coupon );
	}

	/**
	 * Admin list columns for the step CPT.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['kdna_delay']  = __( 'Delay', 'kdna-checkout' );
				$new['kdna_coupon'] = __( 'Coupon', 'kdna-checkout' );
			}
		}
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Step ID.
	 * @return void
	 */
	public function admin_column_content( $column, $post_id ) {
		if ( 'kdna_delay' === $column ) {
			$amount = (int) get_post_meta( $post_id, self::META_DELAY_AMOUNT, true );
			$unit   = (string) get_post_meta( $post_id, self::META_DELAY_UNIT, true );
			echo esc_html( $amount . ' ' . $unit );
		} elseif ( 'kdna_coupon' === $column ) {
			$coupon = (string) get_post_meta( $post_id, self::META_COUPON, true );
			echo $coupon ? esc_html( $coupon ) : '&mdash;';
		}
	}

	/* ================================================================== *
	 * Steps and delays
	 * ================================================================== */

	/**
	 * Delay in seconds for a step.
	 *
	 * @param int $post_id Step ID.
	 * @return int
	 */
	public static function step_delay_seconds( $post_id ) {
		$amount = max( 1, (int) get_post_meta( $post_id, self::META_DELAY_AMOUNT, true ) );
		$unit   = (string) get_post_meta( $post_id, self::META_DELAY_UNIT, true );

		switch ( $unit ) {
			case 'minutes':
				return $amount * MINUTE_IN_SECONDS;
			case 'days':
				return $amount * DAY_IN_SECONDS;
			case 'hours':
			default:
				return $amount * HOUR_IN_SECONDS;
		}
	}

	/**
	 * Published steps in send order (ascending delay, then ID).
	 *
	 * @return array Array of arrays: id, delay, subject, body, coupon.
	 */
	public static function get_ordered_steps() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$steps = array();
		foreach ( $posts as $post ) {
			$steps[] = array(
				'id'      => (int) $post->ID,
				'delay'   => self::step_delay_seconds( $post->ID ),
				'subject' => (string) get_post_meta( $post->ID, self::META_SUBJECT, true ),
				'body'    => (string) $post->post_content,
				'coupon'  => (string) get_post_meta( $post->ID, self::META_COUPON, true ),
			);
		}

		usort(
			$steps,
			function ( $a, $b ) {
				if ( $a['delay'] === $b['delay'] ) {
					return $a['id'] <=> $b['id'];
				}
				return $a['delay'] <=> $b['delay'];
			}
		);

		return $steps;
	}

	/* ================================================================== *
	 * Cron send loop
	 * ================================================================== */

	/**
	 * Self-healing scheduling on the shared 15-minute interval.
	 *
	 * @return void
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( self::CRON_SEND ) ) {
			// Uses the 15-minute interval the capture component registers.
			wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'kdna_checkout_15min', self::CRON_SEND );
		}
	}

	/**
	 * Clear the send loop (plugin deactivation).
	 *
	 * @return void
	 */
	public function clear_events() {
		wp_clear_scheduled_hook( self::CRON_SEND );
	}

	/**
	 * Send each due step to abandoned carts.
	 *
	 * @return void
	 */
	public function run_send_loop() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$steps = self::get_ordered_steps();
		if ( empty( $steps ) ) {
			return;
		}

		global $wpdb;
		$table = KDNA_Checkout_Install::carts_table_name();

		// Abandoned, still subscribed, with an email, and not already
		// through the whole sequence. Batch-limited to keep cron light.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'abandoned' AND unsubscribed = 0 AND email <> '' AND recovery_step_sent < %d ORDER BY updated_at ASC LIMIT 30", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + fixed string.
				count( $steps )
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		$now       = time();
		$now_mysql = current_time( 'mysql', true );

		foreach ( $rows as $row ) {
			$index = (int) $row->recovery_step_sent;
			if ( ! isset( $steps[ $index ] ) ) {
				continue;
			}

			// Stable abandonment reference: set once, from when it was marked.
			$abandoned_at = $row->abandoned_at && '0000-00-00 00:00:00' !== $row->abandoned_at
				? $row->abandoned_at
				: $row->updated_at;

			if ( empty( $row->abandoned_at ) || '0000-00-00 00:00:00' === $row->abandoned_at ) {
				$wpdb->update( $table, array( 'abandoned_at' => $abandoned_at ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
			}

			$due_at = strtotime( $abandoned_at . ' UTC' ) + (int) $steps[ $index ]['delay'];
			if ( $now < $due_at ) {
				continue; // Not due yet.
			}

			$step    = $steps[ $index ];
			$context = self::build_context( $row, $step['coupon'] );

			$subject = wp_strip_all_tags( KDNA_Checkout_Emails::merge( $step['subject'], $context, false ) );
			$body    = wp_kses_post( KDNA_Checkout_Emails::merge( $step['body'], $context, true ) );

			$sent = KDNA_Checkout_Emails::send( $row->email, $subject, $body, $context['unsubscribe'], self::from_headers() );

			if ( $sent ) {
				$wpdb->update(
					$table,
					array(
						'recovery_step_sent' => $index + 1,
						'recovery_last_sent' => $now_mysql,
					),
					array( 'id' => (int) $row->id ),
					array( '%d', '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * From header from the recovery settings, if set.
	 *
	 * @return array
	 */
	public static function from_headers() {
		$email = sanitize_email( (string) get_option( self::OPTION_FROM_EMAIL, '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return array();
		}
		$name = (string) get_option( self::OPTION_FROM_NAME, '' );
		$name = '' !== $name ? $name : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		return array( sprintf( 'From: %s <%s>', $name, $email ) );
	}

	/* ================================================================== *
	 * Merge context
	 * ================================================================== */

	/**
	 * Build the merge context for a captured-cart row.
	 *
	 * @param object $row    Carts table row.
	 * @param string $coupon Optional coupon code for this step.
	 * @return array
	 */
	public static function build_context( $row, $coupon = '' ) {
		$snapshot = json_decode( (string) $row->cart_snapshot, true );
		$items    = isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? $snapshot['items'] : array();

		$total = function_exists( 'wc_price' )
			? wc_price( (float) $row->cart_total, array( 'currency' => (string) $row->currency ) )
			: ( $row->currency . ' ' . number_format( (float) $row->cart_total, 2 ) );

		return array(
			'customer_name'   => self::customer_name( $row->email ),
			'cart_total'      => $total,
			'cart_items_html' => KDNA_Checkout_Emails::cart_items_html( $items, (string) $row->currency ),
			'cart_items_text' => KDNA_Checkout_Emails::cart_items_text( $items ),
			'recovery_link'   => self::recovery_link( $row->cart_token, $coupon ),
			'coupon_code'     => (string) $coupon,
			'store_name'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'unsubscribe'     => self::unsubscribe_link( $row->cart_token ),
		);
	}

	/**
	 * A friendly name for an email: the customer's first name, else the
	 * local part of the address, else a neutral greeting.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function customer_name( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user && $user->first_name ) {
			return $user->first_name;
		}
		$local = strstr( (string) $email, '@', true );
		if ( is_string( $local ) && '' !== $local ) {
			return ucfirst( preg_replace( '/[._\-].*$/', '', $local ) );
		}
		return __( 'there', 'kdna-checkout' );
	}

	/**
	 * The recovery link that restores the cart and lands on the checkout.
	 *
	 * @param string $token  Cart token.
	 * @param string $coupon Optional coupon code.
	 * @return string
	 */
	public static function recovery_link( $token, $coupon = '' ) {
		$args = array( self::VAR_RECOVER => rawurlencode( $token ) );
		if ( '' !== $coupon ) {
			$args[ self::VAR_COUPON ] = rawurlencode( $coupon );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * The unsubscribe link for a token.
	 *
	 * @param string $token Cart token.
	 * @return string
	 */
	public static function unsubscribe_link( $token ) {
		return add_query_arg( array( self::VAR_UNSUBSCRIBE => rawurlencode( $token ) ), home_url( '/' ) );
	}

	/* ================================================================== *
	 * Front-end link endpoints
	 * ================================================================== */

	/**
	 * Handle the recovery and unsubscribe links.
	 *
	 * @return void
	 */
	public function handle_link_endpoints() {
		if ( isset( $_GET[ self::VAR_UNSUBSCRIBE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-authenticated opt-out link.
			$this->handle_unsubscribe( sanitize_text_field( wp_unslash( $_GET[ self::VAR_UNSUBSCRIBE ] ) ) );
			return;
		}

		if ( isset( $_GET[ self::VAR_RECOVER ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-authenticated recovery link.
			$coupon = isset( $_GET[ self::VAR_COUPON ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::VAR_COUPON ] ) ) : '';
			$this->handle_recover( sanitize_text_field( wp_unslash( $_GET[ self::VAR_RECOVER ] ) ), $coupon );
		}
	}

	/**
	 * Restore the saved cart from its token and redirect to checkout.
	 *
	 * @param string $token  Cart token.
	 * @param string $coupon Optional coupon code to apply.
	 * @return void
	 */
	private function handle_recover( $token, $coupon = '' ) {
		if ( '' === $token || ! function_exists( 'WC' ) || ! class_exists( 'KDNA_Checkout_Cart_Capture' ) ) {
			return;
		}

		$row = KDNA_Checkout_Cart_Capture::find_by_token( $token );
		if ( ! $row ) {
			return; // Unknown token: fall through to a normal page load.
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( null === WC()->cart ) {
			return;
		}

		$snapshot = json_decode( (string) $row->cart_snapshot, true );
		$items    = isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? $snapshot['items'] : array();

		WC()->cart->empty_cart();

		foreach ( $items as $item ) {
			$product_id   = (int) ( $item['product_id'] ?? 0 );
			$variation_id = (int) ( $item['variation_id'] ?? 0 );
			$quantity     = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$variation    = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();

			if ( $product_id > 0 ) {
				WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
			}
		}

		// Continue tracking the same row through to purchase.
		if ( null !== WC()->session ) {
			WC()->session->set( KDNA_Checkout_Cart_Capture::SESSION_KEY, $token );
		}

		if ( '' !== $coupon && ! WC()->cart->has_discount( $coupon ) ) {
			WC()->cart->apply_coupon( $coupon );
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Mark every row for this token's email unsubscribed and confirm.
	 *
	 * @param string $token Cart token.
	 * @return void
	 */
	private function handle_unsubscribe( $token ) {
		if ( '' === $token || ! class_exists( 'KDNA_Checkout_Cart_Capture' ) ) {
			return;
		}

		$row = KDNA_Checkout_Cart_Capture::find_by_token( $token );
		if ( $row && '' !== $row->email ) {
			global $wpdb;
			$table = KDNA_Checkout_Install::carts_table_name();
			$wpdb->update( $table, array( 'unsubscribed' => 1 ), array( 'email' => $row->email ), array( '%d' ), array( '%s' ) );
		}

		$message = '<h1>' . esc_html__( 'You have been unsubscribed', 'kdna-checkout' ) . '</h1>'
			. '<p>' . esc_html__( 'You will not receive any more cart reminder emails. You can close this window.', 'kdna-checkout' ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Return to the store', 'kdna-checkout' ) . '</a></p>';

		wp_die(
			wp_kses_post( $message ),
			esc_html__( 'Unsubscribed', 'kdna-checkout' ),
			array( 'response' => 200 )
		);
	}

	/* ================================================================== *
	 * Test send
	 * ================================================================== */

	/**
	 * Send a test of the first step to the current admin.
	 *
	 * @return void
	 */
	public function handle_test_send() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'kdna-checkout' ) );
		}
		check_admin_referer( self::NONCE_TEST );

		$user = wp_get_current_user();
		$to   = $user ? $user->user_email : get_option( 'admin_email' );

		$steps   = self::get_ordered_steps();
		$subject = ! empty( $steps ) && '' !== $steps[0]['subject'] ? $steps[0]['subject'] : __( 'Still thinking it over?', 'kdna-checkout' );
		$body    = ! empty( $steps ) && '' !== trim( wp_strip_all_tags( $steps[0]['body'] ) )
			? $steps[0]['body']
			: '<p>' . __( 'Hi {customer_name}, you left these items in your cart:', 'kdna-checkout' ) . '</p>{cart_items}<p><a href="{recovery_link}">' . __( 'Return to your cart', 'kdna-checkout' ) . '</a></p>';
		$coupon  = ! empty( $steps ) ? $steps[0]['coupon'] : '';

		$context = self::sample_context( $coupon );

		$subject = wp_strip_all_tags( KDNA_Checkout_Emails::merge( $subject, $context, false ) );
		$body    = wp_kses_post( KDNA_Checkout_Emails::merge( $body, $context, true ) );

		$sent = KDNA_Checkout_Emails::send( $to, '[' . __( 'Test', 'kdna-checkout' ) . '] ' . $subject, $body, $context['unsubscribe'], self::from_headers() );

		wp_safe_redirect( add_query_arg( 'kdna_test', $sent ? 'sent' : 'failed', admin_url( 'admin.php?page=kdna-checkout' ) ) );
		exit;
	}

	/**
	 * Sample merge context for the test send.
	 *
	 * @param string $coupon Coupon code.
	 * @return array
	 */
	private static function sample_context( $coupon = '' ) {
		$total = function_exists( 'wc_price' ) ? wc_price( 79.00 ) : '79.00';
		$items = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;">'
			. '<tr><td style="padding:8px 0;font-size:14px;color:#333;">' . esc_html__( 'Sample product', 'kdna-checkout' ) . ' &times; 1</td>'
			. '<td style="padding:8px 0;text-align:right;font-size:14px;color:#333;">' . wp_kses_post( function_exists( 'wc_price' ) ? wc_price( 79.00 ) : '79.00' ) . '</td></tr></table>';

		return array(
			'customer_name'   => __( 'there', 'kdna-checkout' ),
			'cart_total'      => $total,
			'cart_items_html' => $items,
			'cart_items_text' => __( 'Sample product x 1', 'kdna-checkout' ),
			'recovery_link'   => wc_get_checkout_url(),
			'coupon_code'     => (string) $coupon,
			'store_name'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'unsubscribe'     => home_url( '/' ),
		);
	}

	/**
	 * Admin notice after a test send.
	 *
	 * @return void
	 */
	public function maybe_test_notice() {
		if ( ! isset( $_GET['kdna_test'] ) || ! isset( $_GET['page'] ) || 'kdna-checkout' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.
			return;
		}
		$result = sanitize_key( wp_unslash( $_GET['kdna_test'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sent' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test recovery email sent.', 'kdna-checkout' ) . '</p></div>';
		} elseif ( 'failed' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The test email could not be sent. Check your site email configuration.', 'kdna-checkout' ) . '</p></div>';
		}
	}
}
