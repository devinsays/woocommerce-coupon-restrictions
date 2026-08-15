<?php
/**
 * WooCommerce Coupon Restrictions - Verification Table.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Table {

	// Name of table.
	public static $table_name = 'wcr_coupon_verification';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// We'll store a record in the verification table if customer uses a coupon with enhanced usage limits.
		// The woocommerce_pre_payment_complete is when if the order does not require a payment.
		// This can happen if the coupon brings the order price to zero.
		// The woocommerce_payment_successful_result filter is used when the order does require a payment.
		add_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_add_record' ), 100 );
		add_filter( 'woocommerce_payment_successful_result', array( $this, 'maybe_add_record_on_payment' ), 100, 2 );

		// This removes the record in the verification table if the order is cancelled.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_update_record_status' ), 10 );
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	/**
	 * Checks if the table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the table schema.
	 *
	 * The ip column is varchar(45) to fit IPv6 addresses.
	 * The lookup indexes match the WHERE clauses used during checkout validation.
	 *
	 * Index prefix lengths are kept short on purpose. InnoDB limits an index key
	 * to 767 bytes unless large prefixes are enabled, and utf8mb4 uses 4 bytes
	 * per character. The composite keys below are 480 bytes at most, which
	 * creates successfully on older MySQL/MariaDB versions and on tables that
	 * still use the COMPACT or REDUNDANT row format.
	 *
	 * @return string
	 */
	public static function get_schema() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE $table_name (
			record_id mediumint(9) NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			coupon_code varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			ip varchar(45) NOT NULL,
			shipping_address varchar(255) NOT NULL,
			UNIQUE KEY record_id (record_id),
			KEY coupon_email (coupon_code(50),email(50),status),
			KEY coupon_ip (coupon_code(50),ip,status),
			KEY coupon_address (coupon_code(50),shipping_address(50),status),
			KEY order_id (order_id)
		) $charset_collate;";
	}

	/**
	 * Checks the table against the current schema.
	 *
	 * dbDelta runs its queries through $wpdb->query() and reports them as
	 * applied whether or not the database accepted them, so the columns and
	 * indexes are read back instead of trusting its return value.
	 *
	 * @return bool
	 */
	public static function schema_is_current() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table_name = self::get_table_name();

		// The ip column needs to accommodate IPv6 addresses.
		$column = $wpdb->get_row(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, 'ip' ),
			ARRAY_A
		);

		if ( ! $column || 'varchar(45)' !== strtolower( $column['Type'] ) ) {
			return false;
		}

		// The lookup indexes used by the checkout validation queries.
		$indexes   = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table_name ) );
		$key_names = wp_list_pluck( (array) $indexes, 'Key_name' );

		foreach ( array( 'coupon_email', 'coupon_ip', 'coupon_address', 'order_id' ) as $key_name ) {
			if ( ! in_array( $key_name, $key_names, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Creates the table if it does not exist.
	 *
	 * @return bool True if the table is available.
	 */
	public static function maybe_create_table() {
		if ( self::table_exists() ) {
			return true;
		}

		return self::create_or_update_table();
	}

	/**
	 * Creates the table or updates its schema to the current version.
	 * Runs on install and upgrade via the plugin upgrade routine.
	 *
	 * @return bool True if the table matches the current schema.
	 */
	public static function create_or_update_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::get_schema() );

		// A store that cannot apply the schema, for example because the
		// database user does not have ALTER privileges, should not be recorded
		// as upgraded. Otherwise the upgrade never runs again and the enhanced
		// usage limits quietly use the old table.
		if ( ! self::schema_is_current() ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'The coupon verification table could not be updated to the current schema. Enhanced usage limits may not be enforced correctly.',
					array( 'source' => 'woocommerce-coupon-restrictions' )
				);
			}

			return false;
		}

		return true;
	}

	/**
	 * Deletes the table.
	 * Currently just used for tests.
	 *
	 * @return void
	 */
	public static function delete_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}

	/**
	 * If a customer uses a coupon with one of the enhanced usage limits we'll store their details.
	 *
	 * @param array $result
	 * @param int   $order_id
	 *
	 * @return array
	 */
	public static function maybe_add_record_on_payment( $result, $order_id ) {
		self::maybe_add_record( $order_id );
		return $result;
	}

	/**
	 * If a coupon with the enhanced usage limits is used we'll store the customer details.
	 *
	 * @param int   $order_id
	 *
	 * @return bool
	 */
	public static function maybe_add_record( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$added         = false;
		$stored_codes  = array();
		$checked_order = false;

		// Stores a record for each coupon with enhanced usage restrictions.
		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			/** @var \WC_Order_Item_Coupon $coupon_item */
			$coupon_code = $coupon_item->get_code();
			$coupon      = new \WC_Coupon( $coupon_code );

			if ( ! WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
				continue;
			}

			// Both payment hooks can fire for the same order in a single request
			// (gateways that call payment_complete() during process_payment).
			// Bail if active records have already been stored for this order.
			// Cancelled records are ignored so an order that is cancelled and
			// then paid for later is still counted towards the usage limits.
			// This runs inside the loop so orders without enhanced usage
			// restrictions do not query the table at all.
			if ( false === $checked_order ) {
				$checked_order = true;

				if ( self::get_records_for_order_id( $order_id, 'active' ) ) {
					return false;
				}
			}

			// This filter can be used to modify the coupon code stored for enhanced restrictions lookup.
			$coupon_code_to_store = apply_filters( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $coupon_code );

			// The filter above is often used to group several coupon codes under a
			// shared code. Only one record per stored code is saved for each order,
			// otherwise a single order counts more than once towards the limits.
			if ( in_array( $coupon_code_to_store, $stored_codes, true ) ) {
				continue;
			}

			$stored_codes[] = $coupon_code_to_store;

			// Store user details.
			if ( self::store_customer_details( $order, $coupon_code_to_store ) ) {
				$added = true;
			}
		}

		return $added;
	}

	/**
	 * Store the details so we can check run usage checks in the future.
	 *
	 * @param \WC_Order $order
	 * @param string    $coupon_code
	 *
	 * @return bool True if the record was stored.
	 */
	protected static function store_customer_details( \WC_Order $order, string $coupon_code ) {
		global $wpdb;

		// Gather the data for each column in the database table.
		$data = array(
			'status'           => 'active',
			'order_id'         => $order->get_id(),
			'coupon_code'      => $coupon_code,
			'email'            => self::get_scrubbed_email( $order->get_billing_email() ),
			'ip'               => $order->get_customer_ip_address(),
			'shipping_address' => self::format_address( $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_city(), $order->get_shipping_postcode() ),
		);

		// Insert data to the table.
		$result = $wpdb->insert(
			self::get_table_name(),
			$data,
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		// A failed insert means the enhanced usage limits will not be
		// enforced for this order, so make sure it is logged.
		if ( false === $result ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					sprintf( 'Could not store coupon verification record for order #%d. Enhanced usage limits will not count this order.', $order->get_id() ),
					array( 'source' => 'woocommerce-coupon-restrictions' )
				);
			}
			return false;
		}

		return true;
	}

	/**
	 * Sets record to cancelled if order with coupon is cancelled.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function maybe_update_record_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// If order does not have any coupons, return early.
		if ( count( $order->get_coupon_codes() ) === 0 ) {
			return;
		}

		$records = self::get_records_for_order_id( $order_id );

		if ( ! $records ) {
			return;
		}

		foreach ( $records as $record ) {
			self::update_record_status( $record->record_id, 'cancelled' );
		}
	}

	/**
	 * Returns all records for a specific order ID.
	 *
	 * @param int    $order_id
	 * @param string $status Optional status to filter by. Returns all statuses if empty.
	 *
	 * @return array Array of records.
	 */
	public static function get_records_for_order_id( $order_id, $status = '' ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( '' !== $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT record_id FROM %i WHERE order_id = %d AND status = %s',
					$table_name,
					$order_id,
					$status
				)
			);
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT record_id FROM %i WHERE order_id = %d',
				$table_name,
				$order_id
			)
		);

		return $results;
	}

	/**
	 * Sets the record status.
	 *
	 * @param int $record_id
	 * @param string $status
	 */
	public static function update_record_status( $record_id, $status = 'active' ) {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->update(
			$table_name,
			array(
				'status' => $status,
			),
			array(
				'record_id' => $record_id,
			),
			array(
				'%s',
			),
			array(
				'%d',
			)
		);
	}

	/**
	 * Deletes all records for a specific coupon in the verification table.
	 *
	 * @param string   $code
	 *
	 * @return void
	 */
	public static function delete_records_for_coupon( $code ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->get_results(
			$wpdb->prepare(
				'DELETE FROM %i WHERE coupon_code = %s',
				$table_name,
				wc_sanitize_coupon_code( $code )
			)
		);
	}

	/**
	 * Check if scrubbed email has been used with coupon previously.
	 *
	 * @param string $coupon_code
	 * @param string $email
	 *
	 * @return int
	 */
	public static function get_similar_email_usage( $coupon_code, $email ) {
		$email = self::get_scrubbed_email( $email );

		global $wpdb;
		$table_name = self::get_table_name();
		$count      = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE coupon_code = %s AND email = %s AND status = %s',
				$table_name,
				$coupon_code,
				$email,
				'active'
			)
		);

		return (int) $count;
	}

	/**
	 * Returns amount of times a scrubbed shipping address has been used with a specific coupon.
	 *
	 * @param string $coupon_code
	 * @param string $email
	 *
	 * @return int $count
	 */
	public static function get_shipping_address_usage( $coupon_code, $posted ) {
		$shipping_address = self::format_address(
			$posted['shipping_address_1'],
			$posted['shipping_address_2'],
			$posted['shipping_city'],
			$posted['shipping_postcode'],
		);

		global $wpdb;
		$table_name = self::get_table_name();
		$count      = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE coupon_code = %s AND shipping_address = %s AND status = %s',
				$table_name,
				$coupon_code,
				$shipping_address,
				'active'
			)
		);

		return (int) $count;
	}

	/**
	 * Returns amount of times an IP address has been used with a specific coupon.
	 *
	 * @param string $coupon_code
	 * @param string $email
	 *
	 * @return int $count
	 */
	public static function get_ip_address_usage( $coupon_code ) {
		$ip = \WC_Geolocation::get_ip_address();

		global $wpdb;
		$table_name = self::get_table_name();
		$count      = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE coupon_code = %s AND ip = %s AND status = %s',
				$table_name,
				$coupon_code,
				$ip,
				'active'
			)
		);

		return (int) $count;
	}

	/**
	 * Keep only English characters and numbers.
	 * If there are any non-English characters, we convert them to the closest English character.
	 *
	 * @param string $address_1
	 * @param string $address_2
	 * @param string $city
	 * @param string $postcode
	 *
	 * @return string|string[]|null
	 */
	public static function format_address( $address_1, $address_2, $city, $postcode ) {
		$address_index = implode(
			'',
			array_map(
				'trim',
				array(
					$address_1,
					$address_2,
					$city,
					$postcode,
				)
			)
		);

		// Remove everything except a-z, A-Z and 0-9.
		$address_index = preg_replace( '/[^a-zA-Z0-9]+/', '', sanitize_title( $address_index ) );
		return strtoupper( $address_index );
	}

	/**
	 * Strip any dots and "+" signs from email.
	 *
	 * @param string $email
	 *
	 * @return string
	 */
	public static function get_scrubbed_email( string $email ) {
		$email = strtolower( trim( $email ) );

		// Malformed emails (no @) are stored as-is.
		if ( false === strpos( $email, '@' ) ) {
			return $email;
		}

		list( $email_name, $email_domain ) = explode( '@', $email, 2 );

		// Let's ignore everything after "+".
		$email_name = explode( '+', $email_name )[0];

		// The dots in Gmail does not matter.
		if ( 'gmail.com' === $email_domain ) {
			$email_name = str_replace( '.', '', $email_name );
		}

		return strtolower( "$email_name@$email_domain" );
	}
}
