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
	 * Coupon codes can be grouped under a shared code with the
	 * wcr_coupon_code_to_store_for_enhanced_usage_limits filter. An order using
	 * more than one coupon from the same group should only count once.
	 */
	public function test_grouped_coupon_codes_store_a_single_record() {
		WC_Coupon_Restrictions_Table::maybe_create_table();

		$coupon2 = WC_Helper_Coupon::create_coupon( 'enhancedcoupon2' );
		$coupon2->update_meta_data( 'usage_limit_per_ip_address', 1 );
		$coupon2->save();

		$order = $this->order;
		$order->apply_coupon( $coupon2 );
		$order->calculate_totals();
		$order->set_customer_ip_address( '192.0.2.10' );
		$order->save();

		// Groups both coupon codes under a single stored code.
		$filter_callback = function () {
			return 'groupedcoupon';
		};
		add_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $filter_callback );

		WC_Coupon_Restrictions_Table::maybe_add_record( $order->get_id() );

		remove_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $filter_callback );

		$records = WC_Coupon_Restrictions_Table::get_records_for_order_id( $order->get_id() );
		$this->assertCount( 1, $records );

		// A single order must not count twice towards the limit.
		$_SERVER['HTTP_X_REAL_IP'] = '192.0.2.10';
		$count = WC_Coupon_Restrictions_Table::get_ip_address_usage( 'groupedcoupon' );
		unset( $_SERVER['HTTP_X_REAL_IP'] );

		$this->assertSame( 1, $count );

		$coupon2->delete();
	}

	/**
	 * An order can be cancelled (e.g. by the hold stock timeout) and then paid for
	 * later by a delayed gateway callback. The cancelled record must not stop a new
	 * record from being stored, otherwise the order never counts towards the limits.
	 */
	public function test_record_stored_again_after_order_is_cancelled() {
		WC_Coupon_Restrictions_Table::maybe_create_table();

		$order = $this->order;
		$order->set_customer_ip_address( '192.0.2.20' );
		$order->save();
		$order_id = $order->get_id();

		WC_Coupon_Restrictions_Table::maybe_add_record( $order_id );

		// Mimics woocommerce_order_status_cancelled.
		WC_Coupon_Restrictions_Table::maybe_update_record_status( $order_id );

		$_SERVER['HTTP_X_REAL_IP'] = '192.0.2.20';
		$cancelled_count = WC_Coupon_Restrictions_Table::get_ip_address_usage( $this->coupon->get_code() );

		// A late payment for the same order.
		$added = WC_Coupon_Restrictions_Table::maybe_add_record( $order_id );

		$count = WC_Coupon_Restrictions_Table::get_ip_address_usage( $this->coupon->get_code() );
		unset( $_SERVER['HTTP_X_REAL_IP'] );

		$this->assertSame( 0, $cancelled_count );
		$this->assertTrue( $added );
		$this->assertSame( 1, $count );
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

		// InnoDB limits an index key to 767 bytes unless large prefixes are
		// enabled. utf8mb4 uses 4 bytes per character, so each composite key
		// needs to stay under 192 characters in total or it fails to create.
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
		$widths  = array();
		foreach ( $columns as $column ) {
			preg_match( '/varchar\((\d+)\)/i', $column->Type, $matches );
			$widths[ $column->Field ] = isset( $matches[1] ) ? (int) $matches[1] : 0;
		}

		$key_lengths = array();
		foreach ( $indexes as $index ) {
			$length = $index->Sub_part ? (int) $index->Sub_part : $widths[ $index->Column_name ];

			$key_lengths[ $index->Key_name ] = ( $key_lengths[ $index->Key_name ] ?? 0 ) + $length;
		}

		foreach ( array( 'coupon_email', 'coupon_ip', 'coupon_address' ) as $key_name ) {
			$this->assertLessThanOrEqual( 191, $key_lengths[ $key_name ], "Index {$key_name} is too long for utf8mb4." );
		}
	}

	public function tear_down() {
		$this->coupon->delete();
		$this->order->delete();

		// Deletes the custom table if it has been created.
		WC_Coupon_Restrictions_Table::delete_table();

		parent::tear_down();
	}
}
