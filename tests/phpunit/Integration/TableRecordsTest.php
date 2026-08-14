<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Table;

class TableRecordsTest extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;

	/** @var WC_Order */
	public $order;

	public function set_up() {
		parent::set_up();

		// Create coupon with an enhanced usage restriction.
		$coupon = WC_Helper_Coupon::create_coupon( 'enhancedcoupon' );
		$coupon->update_meta_data( 'usage_limit_per_ip_address', 1 );
		$coupon->save();
		$this->coupon = $coupon;

		// Create an order with the coupon applied.
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'processing' );
		$order->apply_coupon( $coupon );
		$order->calculate_totals();
		$this->order = $order;
	}

	/**
	 * Both payment hooks can fire for a single order (gateways that call
	 * payment_complete() during process_payment). Only one record should be stored.
	 */
	public function test_no_duplicate_records_when_both_payment_hooks_fire() {
		WC_Coupon_Restrictions_Table::maybe_create_table();
		$order_id = $this->order->get_id();

		// Mimic woocommerce_pre_payment_complete.
		WC_Coupon_Restrictions_Table::maybe_add_record( $order_id );

		// Mimic woocommerce_payment_successful_result in the same request.
		WC_Coupon_Restrictions_Table::maybe_add_record_on_payment( array(), $order_id );

		$records = WC_Coupon_Restrictions_Table::get_records_for_order_id( $order_id );
		$this->assertCount( 1, $records );
	}

	/**
	 * An order with multiple enhanced-restriction coupons should store a record per coupon.
	 */
	public function test_record_stored_for_each_enhanced_coupon_on_order() {
		WC_Coupon_Restrictions_Table::maybe_create_table();

		$coupon2 = WC_Helper_Coupon::create_coupon( 'enhancedcoupon2' );
		$coupon2->update_meta_data( 'usage_limit_per_shipping_address', 1 );
		$coupon2->save();

		$order = $this->order;
		$order->apply_coupon( $coupon2 );
		$order->calculate_totals();

		WC_Coupon_Restrictions_Table::maybe_add_record( $order->get_id() );

		$records = WC_Coupon_Restrictions_Table::get_records_for_order_id( $order->get_id() );
		$this->assertCount( 2, $records );

		$coupon2->delete();
	}

	/**
	 * IPv6 addresses (up to 45 chars) must be stored intact so usage lookups match.
	 */
	public function test_ipv6_address_stored_and_counted() {
		WC_Coupon_Restrictions_Table::maybe_create_table();

		$ipv6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';

		$order = $this->order;
		$order->set_customer_ip_address( $ipv6 );
		$order->save();

		WC_Coupon_Restrictions_Table::maybe_add_record( $order->get_id() );

		// Validation-time lookup uses WC_Geolocation::get_ip_address().
		$_SERVER['HTTP_X_REAL_IP'] = $ipv6;
		$count = WC_Coupon_Restrictions_Table::get_ip_address_usage( $this->coupon->get_code() );
		unset( $_SERVER['HTTP_X_REAL_IP'] );

		$this->assertSame( 1, $count );
	}

	/**
	 * If the record cannot be stored (e.g. table missing), maybe_add_record
	 * should report failure rather than true.
	 */
	public function test_maybe_add_record_returns_false_when_insert_fails() {
		global $wpdb;

		// Remove the table. The test framework rewrites DROP TABLE queries to
		// DROP TEMPORARY TABLE, so bypass that to drop the real table created
		// by the plugin upgrade routine during bootstrap.
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		WC_Coupon_Restrictions_Table::delete_table();
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$this->assertFalse( WC_Coupon_Restrictions_Table::table_exists() );

		$suppress = $wpdb->suppress_errors( true );
		$result   = WC_Coupon_Restrictions_Table::maybe_add_record( $this->order->get_id() );
		$wpdb->suppress_errors( $suppress );

		$this->assertFalse( $result );
	}

	/**
	 * The verification table needs indexes for its checkout-time lookup queries.
	 */
	public function test_verification_table_has_lookup_indexes() {
		global $wpdb;

		WC_Coupon_Restrictions_Table::maybe_create_table();
		$table_name = WC_Coupon_Restrictions_Table::get_table_name();

		$indexes   = $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );
		$key_names = wp_list_pluck( $indexes, 'Key_name' );

		$this->assertContains( 'coupon_email', $key_names );
		$this->assertContains( 'coupon_ip', $key_names );
		$this->assertContains( 'coupon_address', $key_names );
		$this->assertContains( 'order_id', $key_names );
	}

	public function tear_down() {
		$this->coupon->delete();
		$this->order->delete();

		// Deletes the custom table if it has been created.
		WC_Coupon_Restrictions_Table::delete_table();

		parent::tear_down();
	}
}
