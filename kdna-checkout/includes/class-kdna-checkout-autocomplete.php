<?php
/**
 * Google Places address autocomplete for KDNA Checkout.
 *
 * WooCommerce > KDNA Checkout gains an on/off toggle and a Google API key
 * field (registered through the WordPress Settings API, strictly
 * sanitised). When enabled and a key is present, the Google Places
 * script is registered and the checkout widget declares it as a script
 * dependency, so it loads only where the widget runs. The widget JS
 * then attaches autocomplete to the billing and shipping address
 * fields and populates address line, suburb/city, postcode, state and
 * country correctly for WooCommerce.
 *
 * Fail-safe: feature off or key missing means standard address fields,
 * no script and no errors.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Autocomplete settings and script registration.
 */
class KDNA_Checkout_Autocomplete {

	/**
	 * Option names.
	 *
	 * @var string
	 */
	const OPTION_ENABLED = 'kdna_checkout_autocomplete_enabled';
	const OPTION_API_KEY = 'kdna_checkout_google_api_key';

	/**
	 * Settings group shared by all KDNA Checkout settings.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'kdna_checkout';

	/**
	 * Settings page slug (WooCommerce > KDNA Checkout).
	 *
	 * @var string
	 */
	const SETTINGS_PAGE = 'kdna-checkout';

	/**
	 * Handle for the Google Places script.
	 *
	 * @var string
	 */
	const SCRIPT_HANDLE = 'kdna-checkout-google-places';

	/**
	 * Hook everything in.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Priority 7: after the shared handle registers at 5, so the
		// Places script can depend on it (our init callback must exist
		// before Google calls it).
		add_action( 'wp_enqueue_scripts', array( $this, 'register_places_script' ), 7 );
	}

	/* ================================================================== *
	 * Options
	 * ================================================================== */

	/**
	 * Whether autocomplete is on and usable (toggle on and key present).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' ) && '' !== self::api_key();
	}

	/**
	 * The stored API key.
	 *
	 * @return string
	 */
	public static function api_key() {
		return (string) get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Sanitise the toggle to a strict yes/no.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitise_enabled( $value ) {
		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * Sanitise the API key: Google browser keys only ever contain
	 * letters, digits, underscores and hyphens. Everything else is
	 * stripped and the length capped.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	public static function sanitise_api_key( $key ) {
		$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
		return substr( $key, 0, 128 );
	}

	/* ================================================================== *
	 * Settings UI
	 * ================================================================== */

	/**
	 * Register the settings, section and fields on the shared
	 * WooCommerce > KDNA Checkout page.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitise_enabled' ),
				'default'           => 'no',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitise_api_key' ),
				'default'           => '',
			)
		);

		add_settings_section(
			'kdna_checkout_autocomplete',
			__( 'Address autocomplete', 'kdna-checkout' ),
			array( $this, 'render_section_intro' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			self::OPTION_ENABLED,
			__( 'Google address autocomplete', 'kdna-checkout' ),
			array( $this, 'render_enabled_field' ),
			self::SETTINGS_PAGE,
			'kdna_checkout_autocomplete'
		);

		add_settings_field(
			self::OPTION_API_KEY,
			__( 'Google API key', 'kdna-checkout' ),
			array( $this, 'render_api_key_field' ),
			self::SETTINGS_PAGE,
			'kdna_checkout_autocomplete'
		);
	}

	/**
	 * Section introduction.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		printf(
			'<p>%s</p>',
			esc_html__( 'With this enabled, shoppers start typing their address and pick it from Google suggestions. The street, suburb/city, postcode, state and country fill in automatically. Without a key, the checkout simply uses standard address fields.', 'kdna-checkout' )
		);
	}

	/**
	 * The on/off toggle.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		$enabled = get_option( self::OPTION_ENABLED, 'no' );
		?>
		<label for="<?php echo esc_attr( self::OPTION_ENABLED ); ?>">
			<input type="checkbox"
				id="<?php echo esc_attr( self::OPTION_ENABLED ); ?>"
				name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>"
				value="yes"
				<?php checked( 'yes', $enabled ); ?> />
			<?php echo esc_html__( 'Enable address autocomplete on the checkout', 'kdna-checkout' ); ?>
		</label>
		<p class="description"><?php echo esc_html__( 'Requires a Google API key with the Places API enabled.', 'kdna-checkout' ); ?></p>
		<?php
	}

	/**
	 * The API key field, masked by default with an Alpine.js reveal.
	 *
	 * @return void
	 */
	public function render_api_key_field() {
		?>
		<div class="kdna-checkout-admin__key-field" x-data="kdnaCheckoutSecretField">
			<input
				:type="show ? 'text' : 'password'"
				type="password"
				class="regular-text"
				id="<?php echo esc_attr( self::OPTION_API_KEY ); ?>"
				name="<?php echo esc_attr( self::OPTION_API_KEY ); ?>"
				value="<?php echo esc_attr( self::api_key() ); ?>"
				autocomplete="off"
				spellcheck="false" />
			<button type="button" class="button" @click="show = ! show">
				<span x-show="! show"><?php echo esc_html__( 'Show', 'kdna-checkout' ); ?></span>
				<span x-show="show" x-cloak><?php echo esc_html__( 'Hide', 'kdna-checkout' ); ?></span>
			</button>
		</div>
		<p class="description">
			<?php echo esc_html__( 'Restrict the key to your domain (HTTP referrer restriction) in the Google Cloud console. Browser keys are visible in the Places script address by design, the restriction is what keeps them safe.', 'kdna-checkout' ); ?>
		</p>
		<?php
	}

	/* ================================================================== *
	 * Front end
	 * ================================================================== */

	/**
	 * Register (never blanket-enqueue) the Google Places script. The
	 * checkout widget declares it via get_script_depends(), so it loads
	 * only when the feature is enabled and only on pages containing the
	 * widget.
	 *
	 * @return void
	 */
	public function register_places_script() {
		if ( ! self::is_enabled() ) {
			return; // Fail-safe: no key or switched off means no script at all.
		}

		$src = add_query_arg(
			array(
				'key'       => rawurlencode( self::api_key() ),
				'libraries' => 'places',
				'loading'   => 'async',
				'callback'  => 'kdnaCheckoutPlacesInit',
				'v'         => 'weekly',
			),
			'https://maps.googleapis.com/maps/api/js'
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			$src,
			array( KDNA_Checkout_Assets::HANDLE ),
			null, // No version parameter on Google's URL.
			array(
				'in_footer' => true,
				'strategy'  => 'async',
			)
		);
	}
}
