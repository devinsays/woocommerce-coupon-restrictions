<?php
/**
 * WooCommerce Coupon Restrictions - Verification Table.
 *
 * @class    WC_Coupon_Restrictions_Verification_Table
 * @author   DevPress
 * @package  WooCommerce Coupon Restrictions
 * @license  GPL-2.0+
 * @since    1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Coupon_Restrictions_Verification_Table {

	// Name of table.
	public static $table_name = 'wcr_coupon_verification';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->maybe_create_table();
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

}
