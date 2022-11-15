<?php
/**
 * WooCommerce Coupon Restrictions - Verification Table.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    1.9.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Verification_Table {

	// Name of table.
	public static $table_name = 'wcr_coupon_verification';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_payment_successful_result', array( $this, 'maybe_store_customer_details' ), 999, 2 );
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	public function maybe_create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// Only create the table if it does not exist yet.
		if ( $wpdb->get_var( "show tables like '{$table_name}'" ) == $table_name ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			coupon_code varchar(20) NOT NULL,
			order_id bigint(20) UNSIGNED NOT NULL,
			email varchar(255) NOT NULL,
			ip varchar(15) NOT NULL,
			shipping_address varchar(255) NOT NULL,
			payment_method varchar(15) NOT NULL,
			payment_identifier varchar(20) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * If a customer uses a coupon with one of the enhanced usage limits we'll store their details.
	 *
	 * @param array $result
	 * @param int   $order_id
	 *
	 * @return array
	 */
	public function maybe_store_customer_details( $result, $order_id ) {
		$order = wc_get_order( $order_id );

		// The order needs to be in processing.
		if ( ! $order->has_status( 'processing' ) ) {
			return $result;
		}

		// Check all the coupons.
		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			/** @var \WC_Order_Item_Coupon $coupon_item */

			$coupon = new \WC_Coupon( $coupon_item->get_code() );

			if ( WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
				// Store user details.
				$this->store_customer_details( $order, $coupon_item->get_code() );
				break;
			}
		}

		return $result;
	}

	/**
	 * Store the details so we can check run fraud verification in the future.
	 *
	 * @param \WC_Order $order
	 * @param string    $coupon_code
	 */
	protected function store_customer_details( \WC_Order $order, string $coupon_code ) {
		global $wpdb;

		// Gather the data for each column in the database table.
		$data = array(
			'email'              => self::format_email( $order->get_billing_email() ),
			'shipping_address'   => self::format_address( $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_postcode() ),
			'payment_method'     => str_replace( 'braintree_', '', $order->get_payment_method() ), // credit_card or paypal
			'payment_identifier' => $order->get_meta( '_wc_braintree_credit_card_account_four' ),
			'ip'                 => $order->get_customer_ip_address(),
			'coupon_code'        => $coupon_code,
			'order_id'           => $order->get_id(),
		);

		// Insert data to the table.
		$wpdb->insert(
			self::get_table_name(),
			$data,
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);
	}

	/**
	 * Keep only English characters and numbers.
	 * If there are any non-English characters, we convert them to the closest English character.
	 *
	 * @param string $shipping_address_1
	 * @param string $shipping_address_2
	 * @param string $shipping_postcode
	 *
	 * @return string|string[]|null
	 */
	public static function format_address( $shipping_address_1, $shipping_address_2, $shipping_postcode ) {
		$address_index = implode(
			'',
			array_map(
				'trim',
				array(
					$shipping_address_1,
					$shipping_address_2,
					$shipping_postcode,
				)
			)
		);

		// Remove everything except a-z, A-Z and 0-9.
		$address_index = preg_replace( '/[^a-zA-Z0-9]+/', '', sanitize_title( $address_index ) );

		return strtoupper( $address_index );
	}

	/**
	 * Strip email from any dots and "+" signs.
	 *
	 * @param string $get_billing_email
	 *
	 * @return string
	 */
	public static function format_email( string $get_billing_email ) {
		list( $email_name, $email_domain ) = explode( '@', strtolower( trim( $get_billing_email ) ) );

		// Let's ignore everything after "+".
		$email_name = explode( '+', $email_name )[0];

		// The dots in Gmail do not matter.
		if ( 'gmail.com' === $email_domain ) {
			$email_name = str_replace( '.', '', $email_name );
		}

		return strtoupper( "$email_name@$email_domain" );
	}

}
