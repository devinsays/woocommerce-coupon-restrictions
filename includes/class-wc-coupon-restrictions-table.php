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
		add_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_store_customer_details' ), 999, 1 );
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

		if ( $wpdb->get_var( "show tables like '{$table_name}'" ) === $table_name ) {
			return true;
		}

		return false;
	}

	/**
	 * Creates the table if it does not exist.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		if ( self::table_exists() ) {
			return;
		}

		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			coupon_code varchar(20) NOT NULL,
			email varchar(255) NOT NULL,
			ip varchar(15) NOT NULL,
			shipping_address varchar(255) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Deletes the table.
	 * Currently just used for tests.
	 *
	 * @return void
	 */
	public static function delete_table() {
		if ( ! self::table_exists() ) {
			return;
		}

		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE {$table_name}" );
	}

	/**
	 * If a customer uses a coupon with one of the enhanced usage limits we'll store their details.
	 *
	 * @param array $result
	 * @param int   $order_id
	 *
	 * @return array
	 */
	public static function maybe_store_customer_details( $order_id ) {
		error_log( 'maybe_store_customer_details' );
		$order = wc_get_order( $order_id );

		// Check all the coupons.
		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			/** @var \WC_Order_Item_Coupon $coupon_item */
			$coupon = new \WC_Coupon( $coupon_item->get_code() );

			if ( WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
				// Store user details.
				self::store_customer_details( $order, $coupon_item->get_code() );
				break;
			}
		}
	}

	/**
	 * Store the details so we can check run usage checks in the future.
	 *
	 * @param \WC_Order $order
	 * @param string    $coupon_code
	 */
	protected static function store_customer_details( \WC_Order $order, string $coupon_code ) {
		global $wpdb;

		// Gather the data for each column in the database table.
		$data = array(
			'order_id'         => $order->get_id(),
			'coupon_code'      => $coupon_code,
			'email'            => self::get_scrubbed_email( $order->get_billing_email() ),
			'ip'               => $order->get_customer_ip_address(),
			'shipping_address' => self::format_address( $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_city(), $order->get_shipping_postcode() ),
		);

		// Insert data to the table.
		$wpdb->insert(
			self::get_table_name(),
			$data,
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Check if scrubbed email has been used with coupon previously.
	 *
	 * @param \WC_Coupon $coupon
	 * @param string $email
	 *
	 * @return int
	 */
	public static function get_similar_email_usage( $coupon_code, $email ) {
		$email = self::get_scrubbed_email( $email );

		global $wpdb;
		$table_name = self::get_table_name();
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE coupon_code = %s AND email = %s",
				$coupon_code,
				$email
			)
		);

		if ( ! $results ) {
			return 0;
		}

		return count( $results );
	}

	/**
	 * Returns amount of times a scrubbed shipping address has been used with a specific coupon.
	 *
	 * @param \WC_Coupon $coupon
	 * @param string $email
	 *
	 * @return int $count
	 */
	public static function get_shipping_address_usage( $coupon, $coupon_code, $posted ) {
		$shipping_address = self::format_address(
			$posted['shipping_address_1'],
			$posted['shipping_address_2'],
			$posted['shipping_city'],
			$posted['shipping_postcode'],
		);

		global $wpdb;
		$table_name = self::get_table_name();
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE coupon_code = %s AND shipping_address = %s",
				$coupon_code,
				$shipping_address
			)
		);

		return ( count( $results ) );
	}

	/**
	 * Returns amount of times an IP address has been used with a specific coupon.
	 *
	 * @param \WC_Coupon $coupon
	 * @param string $email
	 *
	 * @return int $count
	 */
	public static function get_ip_address_usage( $coupon, $coupon_code ) {
		$ip = \WC_Geolocation::get_ip_address();

		global $wpdb;
		$table_name = self::get_table_name();
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE coupon_code = %s AND ip = %s",
				$coupon_code,
				$ip
			)
		);

		return ( count( $results ) );
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
		list( $email_name, $email_domain ) = explode( '@', strtolower( trim( $email ) ) );

		// Let's ignore everything after "+".
		$email_name = explode( '+', $email_name )[0];

		// The dots in Gmail does not matter.
		if ( 'gmail.com' === $email_domain ) {
			$email_name = str_replace( '.', '', $email_name );
		}

		return strtolower( "$email_name@$email_domain" );
	}
}
