<?php
/**
 * Activation and deactivation routines for KDNA Checkout.
 *
 * Creates the captured-carts database table and stores version options.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Install and uninstall helper.
 */
class KDNA_Checkout_Install {

	/**
	 * Database schema version. Bump when the table definition changes.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Unprefixed name of the captured-carts table.
	 *
	 * @var string
	 */
	const CARTS_TABLE = 'kdna_checkout_carts';

	/**
	 * Return the fully prefixed captured-carts table name.
	 *
	 * @return string
	 */
	public static function carts_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::CARTS_TABLE;
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Creates the captured-carts table and records plugin and schema
	 * versions. Safe to run repeatedly, dbDelta only applies differences.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();

		update_option( 'kdna_checkout_version', KDNA_CHECKOUT_VERSION, false );
		update_option( 'kdna_checkout_db_version', self::DB_VERSION, false );

		/**
		 * Fires after KDNA Checkout has activated.
		 *
		 * Later, additive components can hook in here without this file
		 * ever needing to change.
		 */
		do_action( 'kdna_checkout_activated' );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Non-destructive by design: captured data, options and post types are
	 * kept so nothing is lost on a temporary deactivation. Full removal
	 * happens in uninstall.php when the plugin is deleted.
	 *
	 * @return void
	 */
	public static function deactivate() {
		/**
		 * Fires on KDNA Checkout deactivation.
		 *
		 * Later stages hook their scheduled-event clean-up in here so this
		 * completed file never needs modifying.
		 */
		do_action( 'kdna_checkout_deactivated' );
	}

	/**
	 * Create (or update) the captured-carts table.
	 *
	 * Columns:
	 * - id             Row ID.
	 * - cart_token     Unique session/recovery token for the cart.
	 * - email          Customer email captured at checkout.
	 * - cart_snapshot  JSON snapshot of the cart contents.
	 * - cart_total     Cart total at capture time.
	 * - currency       ISO 4217 currency code.
	 * - status         active, abandoned, recovered or completed.
	 * - created_at     Capture timestamp.
	 * - updated_at     Last-touched timestamp.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::carts_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cart_token varchar(64) NOT NULL,
			email varchar(191) NOT NULL DEFAULT '',
			cart_snapshot longtext NULL,
			cart_total decimal(19,4) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY cart_token (cart_token),
			KEY email (email),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
