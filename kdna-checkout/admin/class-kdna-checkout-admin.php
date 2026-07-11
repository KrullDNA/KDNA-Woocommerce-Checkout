<?php
/**
 * Settings > KDNA Checkout admin screen.
 *
 * Stage 1 ships the page shell only: Alpine.js is loaded and a
 * "Coming soon" placeholder is shown. Later stages fill the screen in.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers and renders the admin settings screen.
 */
class KDNA_Checkout_Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'kdna-checkout';

	/**
	 * Bundled Alpine.js version.
	 *
	 * @var string
	 */
	const ALPINE_VERSION = '3.14.9';

	/**
	 * Hook suffix returned when the page is registered, used so assets
	 * load only on this screen.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Hook the screen and its assets in.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_options_page(
			__( 'KDNA Checkout', 'kdna-checkout' ),
			__( 'KDNA Checkout', 'kdna-checkout' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue Alpine.js and the admin assets, on this screen only.
	 *
	 * The admin app registers its Alpine components on the alpine:init
	 * event, so it is declared as a dependency of Alpine to guarantee it
	 * executes first.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'kdna-checkout-admin',
			KDNA_CHECKOUT_URL . 'admin/admin.css',
			array(),
			KDNA_CHECKOUT_VERSION
		);

		wp_enqueue_script(
			'kdna-checkout-admin-app',
			KDNA_CHECKOUT_URL . 'admin/admin-app.js',
			array(),
			KDNA_CHECKOUT_VERSION,
			array( 'in_footer' => true )
		);

		wp_enqueue_script(
			'kdna-checkout-alpine',
			KDNA_CHECKOUT_URL . 'admin/vendor/alpine.min.js',
			array( 'kdna-checkout-admin-app' ),
			self::ALPINE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Render the placeholder settings screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kdna-checkout' ) );
		}
		?>
		<div class="wrap kdna-checkout-admin">
			<h1><?php echo esc_html__( 'KDNA Checkout', 'kdna-checkout' ); ?></h1>

			<div class="kdna-checkout-admin__card" x-data="kdnaCheckoutAdmin" x-cloak>
				<h2><?php echo esc_html__( 'Coming soon', 'kdna-checkout' ); ?></h2>
				<p><?php echo esc_html__( 'The KDNA Checkout settings will appear here as the build progresses. Stage 1 (foundation and data layer) is installed and active.', 'kdna-checkout' ); ?></p>
				<p>
					<span class="kdna-checkout-admin__badge" x-show="ready">
						<?php echo esc_html__( 'Admin interface loaded, Alpine.js is running.', 'kdna-checkout' ); ?>
					</span>
					<noscript>
						<?php echo esc_html__( 'JavaScript is disabled, the interactive admin interface needs it enabled.', 'kdna-checkout' ); ?>
					</noscript>
				</p>
			</div>
		</div>
		<?php
	}
}
