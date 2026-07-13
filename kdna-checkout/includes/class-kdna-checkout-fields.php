<?php
/**
 * Field optimisation and guest checkout for KDNA Checkout.
 *
 * Applies the widget's field configuration through the
 * woocommerce_checkout_fields filter: show/hide, reorder and relabel
 * the standard checkout fields, optionally combine the name fields,
 * and optionally use placeholders as labels. Also defaults the
 * checkout to guest checkout with an optional "create an account"
 * checkbox.
 *
 * The configuration is persisted (autoload off) when the widget
 * renders, because WooCommerce re-applies the same filter while
 * processing the submitted order over AJAX, where the widget never
 * runs. Fail-safe rules: the email field can never be hidden, fields
 * missing from the configuration fall back to shown, and a hidden
 * country field still submits the store's base country.
 *
 * @package KDNA_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Checkout field configuration.
 */
class KDNA_Checkout_Fields {

	/**
	 * Option storing the active field configuration.
	 *
	 * @var string
	 */
	const OPTION = 'kdna_checkout_fields_config';

	/**
	 * Configurable field keys, in native WooCommerce order.
	 *
	 * @var array
	 */
	const FIELD_KEYS = array(
		'first_name',
		'last_name',
		'company',
		'country',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'phone',
		'email',
		'order_comments',
	);

	/**
	 * Fields that can never be hidden. Email drives the order, the
	 * customer record and (from Stage 10) cart capture.
	 *
	 * @var array
	 */
	const PROTECTED_KEYS = array( 'email' );

	/**
	 * Runtime configuration for the current request.
	 *
	 * @var array|null
	 */
	private static $config = null;

	/**
	 * Hook the filters in.
	 */
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_fields' ), 20 );
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'registration_required' ), 20 );
		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'registration_enabled' ), 20 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'fill_posted_defaults' ), 20 );

		// Priority 6: attach the inline-validation strings to the shared
		// handle registered at priority 5, only where the widget loads.
		add_action( 'wp_enqueue_scripts', array( $this, 'localise_script' ), 6 );
	}

	/**
	 * Localise the inline validation messages.
	 *
	 * @return void
	 */
	public function localise_script() {
		wp_localize_script(
			KDNA_Checkout_Assets::HANDLE,
			'kdnaCheckoutFields',
			array(
				'required' => __( 'This field is required.', 'kdna-checkout' ),
				'email'    => __( 'Please enter a valid email address.', 'kdna-checkout' ),
				'postcode' => __( 'Please enter a valid postcode.', 'kdna-checkout' ),
				'phone'    => __( 'Please enter a valid phone number.', 'kdna-checkout' ),
			)
		);
	}

	/**
	 * Store the widget's field configuration for this request and
	 * persist it for AJAX order processing.
	 *
	 * @param array $raw Raw configuration from the widget settings.
	 * @return void
	 */
	public static function set_config( array $raw ) {
		$config       = self::sanitise_config( $raw );
		self::$config = $config;

		if ( get_option( self::OPTION ) !== $config ) {
			update_option( self::OPTION, $config, false );
		}
	}

	/**
	 * The active configuration, from runtime or storage.
	 *
	 * @return array|null Null when the widget has never configured fields.
	 */
	private static function get_config() {
		if ( null !== self::$config ) {
			return self::$config;
		}

		$stored = get_option( self::OPTION );
		if ( is_array( $stored ) && ! empty( $stored['enabled'] ) ) {
			self::$config = $stored;
			return self::$config;
		}

		return null;
	}

	/**
	 * Sanitise a raw configuration.
	 *
	 * Unknown keys are dropped, duplicates collapse to the first row,
	 * protected fields are forced visible, and any standard field
	 * missing from the list is appended as shown (fail-safe: deleting a
	 * repeater row never removes data WooCommerce expects).
	 *
	 * @param array $raw Raw configuration.
	 * @return array
	 */
	public static function sanitise_config( array $raw ) {
		$fields = array();
		$seen   = array();

		foreach ( (array) ( $raw['fields'] ?? array() ) as $row ) {
			$key = sanitize_key( $row['key'] ?? '' );
			if ( ! in_array( $key, self::FIELD_KEYS, true ) || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$show = ! empty( $row['show'] );
			if ( in_array( $key, self::PROTECTED_KEYS, true ) ) {
				$show = true;
			}

			$fields[] = array(
				'key'   => $key,
				'show'  => $show,
				'label' => sanitize_text_field( $row['label'] ?? '' ),
			);
		}

		foreach ( self::FIELD_KEYS as $key ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$fields[] = array(
					'key'   => $key,
					'show'  => true,
					'label' => '',
				);
			}
		}

		return array(
			'enabled'                => true,
			'create_account'         => ! empty( $raw['create_account'] ),
			'combine_names'          => ! empty( $raw['combine_names'] ),
			'placeholders_as_labels' => ! empty( $raw['placeholders_as_labels'] ),
			'fields'                 => $fields,
		);
	}

	/**
	 * Guest checkout by default: registration is never forced.
	 *
	 * @param bool $required Whether registration is required.
	 * @return bool
	 */
	public function registration_required( $required ) {
		return self::get_config() ? false : $required;
	}

	/**
	 * The optional "create an account" checkbox, controllable from the
	 * widget.
	 *
	 * @param bool $enabled Whether checkout registration is enabled.
	 * @return bool
	 */
	public function registration_enabled( $enabled ) {
		$config = self::get_config();
		if ( ! $config ) {
			return $enabled;
		}
		return ! empty( $config['create_account'] );
	}

	/**
	 * Apply show/hide, order, labels, combined names and
	 * placeholders-as-labels to the checkout fields.
	 *
	 * @param array $fields WooCommerce checkout fields by section.
	 * @return array
	 */
	public function filter_fields( $fields ) {
		$config = self::get_config();
		if ( ! $config || ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( $config['fields'] as $index => $row ) {
			$key      = $row['key'];
			$priority = ( $index + 1 ) * 10;

			$section  = 'order_comments' === $key ? 'order' : 'billing';
			$full_key = 'order_comments' === $key ? 'order_comments' : 'billing_' . $key;

			if ( isset( $fields[ $section ][ $full_key ] ) ) {
				if ( ! $row['show'] && ! in_array( $key, self::PROTECTED_KEYS, true ) ) {
					unset( $fields[ $section ][ $full_key ] );
				} else {
					$fields[ $section ][ $full_key ]['priority'] = $priority;
					if ( '' !== $row['label'] ) {
						$fields[ $section ][ $full_key ]['label'] = $row['label'];
					}
				}
			}

			// Mirror onto the matching shipping field so hiding or
			// reordering billing address parts keeps both sections tidy.
			$shipping_key = 'shipping_' . $key;
			if ( isset( $fields['shipping'][ $shipping_key ] ) ) {
				if ( ! $row['show'] ) {
					unset( $fields['shipping'][ $shipping_key ] );
				} else {
					$fields['shipping'][ $shipping_key ]['priority'] = $priority;
					if ( '' !== $row['label'] ) {
						$fields['shipping'][ $shipping_key ]['label'] = $row['label'];
					}
				}
			}
		}

		if ( $config['combine_names'] ) {
			$fields = $this->combine_name_fields( $fields, $config );
		}

		if ( $config['placeholders_as_labels'] ) {
			$fields = $this->placeholders_as_labels( $fields );
		}

		return $fields;
	}

	/**
	 * Combine first and last name into a single full-width field. The
	 * submitted value is split back into first/last in
	 * fill_posted_defaults() so the order data stays complete.
	 *
	 * @param array $fields Checkout fields.
	 * @param array $config Active configuration.
	 * @return array
	 */
	private function combine_name_fields( array $fields, array $config ) {
		$custom_label = '';
		foreach ( $config['fields'] as $row ) {
			if ( 'first_name' === $row['key'] ) {
				$custom_label = $row['label'];
				break;
			}
		}
		$label = '' !== $custom_label ? $custom_label : __( 'Full name', 'kdna-checkout' );

		foreach ( array( 'billing', 'shipping' ) as $section ) {
			unset( $fields[ $section ][ $section . '_last_name' ] );

			$first = $section . '_first_name';
			if ( isset( $fields[ $section ][ $first ] ) ) {
				$fields[ $section ][ $first ]['label'] = $label;
				$fields[ $section ][ $first ]['class'] = array( 'form-row-wide' );
			}
		}

		return $fields;
	}

	/**
	 * Use each field's label as its placeholder and hide the label
	 * visually while keeping it for screen readers.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	private function placeholders_as_labels( array $fields ) {
		foreach ( $fields as $section => $section_fields ) {
			if ( ! is_array( $section_fields ) ) {
				continue;
			}
			foreach ( $section_fields as $key => $args ) {
				if ( empty( $args['label'] ) ) {
					continue;
				}
				$fields[ $section ][ $key ]['placeholder'] = wp_strip_all_tags( $args['label'] );

				$label_class   = (array) ( $args['label_class'] ?? array() );
				$label_class[] = 'screen-reader-text';

				$fields[ $section ][ $key ]['label_class'] = array_unique( $label_class );
			}
		}

		return $fields;
	}

	/**
	 * Fail-safe posted data: split a combined name back into first and
	 * last, and give a hidden country field the store's base country so
	 * tax and shipping still resolve.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public function fill_posted_defaults( $data ) {
		$config = self::get_config();
		if ( ! $config || ! is_array( $data ) ) {
			return $data;
		}

		if ( $config['combine_names'] ) {
			foreach ( array( 'billing', 'shipping' ) as $section ) {
				$first_key = $section . '_first_name';
				$last_key  = $section . '_last_name';

				if ( ! empty( $data[ $first_key ] ) && empty( $data[ $last_key ] ) ) {
					$parts = preg_split( '/\s+/', trim( (string) $data[ $first_key ] ) );
					if ( count( $parts ) > 1 ) {
						$data[ $last_key ]  = array_pop( $parts );
						$data[ $first_key ] = implode( ' ', $parts );
					} else {
						$data[ $last_key ] = '';
					}
				}
			}
		}

		foreach ( $config['fields'] as $row ) {
			if ( 'country' === $row['key'] && ! $row['show'] && function_exists( 'WC' ) && WC()->countries ) {
				$base = WC()->countries->get_base_country();
				if ( empty( $data['billing_country'] ) ) {
					$data['billing_country'] = $base;
				}
				if ( isset( $data['shipping_country'] ) && '' === $data['shipping_country'] ) {
					$data['shipping_country'] = $base;
				}
			}
		}

		return $data;
	}
}
