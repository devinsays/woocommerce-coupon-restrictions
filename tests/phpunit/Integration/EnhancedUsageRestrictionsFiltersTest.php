<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Table;
use WC_Coupon_Restrictions_Validation_Checkout;

class EnhancedUsageRestrictionsFiltersTest extends WP_UnitTestCase {

	/** @var WC_Coupon */
	public $coupon1;

	/** @var WC_Coupon */
	public $coupon2;

	/** @var WC_Order */
	public $order1;

	/** @var WC_Order */
	public $order2;

	public $validation;

	public function set_up() {
		parent::set_up();

		// Create test coupons using helper
		$this->coupon1 = WC_Helper_Coupon::create_coupon( 'new-customer-1' );
		$this->coupon1->set_usage_limit_per_user( 1 );
		$this->coupon1->update_meta_data( 'prevent_similar_emails', 'yes' );
		$this->coupon1->update_meta_data( 'usage_limit_per_shipping_address', 1 );
		$this->coupon1->update_meta_data( 'usage_limit_per_ip_address', 1 );
		$this->coupon1->save();

		$this->coupon2 = WC_Helper_Coupon::create_coupon( 'new-customer-2' );
		$this->coupon2->set_usage_limit_per_user( 1 );
		$this->coupon2->update_meta_data( 'prevent_similar_emails', 'yes' );
		$this->coupon2->update_meta_data( 'usage_limit_per_shipping_address', 1 );
		$this->coupon2->update_meta_data( 'usage_limit_per_ip_address', 1 );
		$this->coupon2->save();

		// Create test orders using helper
		$this->order1 = WC_Helper_Order::create_order();
		$this->order1->set_status( 'processing' );
		$this->order1->set_billing_email( 'customer1@example.com' );
		$this->order1->apply_coupon( $this->coupon1 );
		$this->order1->calculate_totals();

		$this->order2 = WC_Helper_Order::create_order();
		$this->order2->set_status( 'processing' );
		$this->order2->set_billing_email( 'customer2@example.com' );
		$this->order2->apply_coupon( $this->coupon2 );
		$this->order2->calculate_totals();

		$this->validation = new WC_Coupon_Restrictions_Validation_Checkout();

		// Ensure table exists
		WC_Coupon_Restrictions_Table::maybe_create_table();
	}

	/**
	 * Test that coupon codes are stored with the filter applied
	 */
	public function test_coupon_storage_with_filter() {
		// Add filter to transform coupon codes for storage
		$filter_callback = function( $coupon_code ) {
			if ( strpos( $coupon_code, 'new-customer-' ) === 0 ) {
				return 'new-customer';
			}
			return $coupon_code;
		};
		add_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $filter_callback );

		// Trigger the storage hook - this should apply the filter
		do_action( 'woocommerce_pre_payment_complete', $this->order1->get_id() );

		// Verify the coupon was stored as "new-customer" not "new-customer-1"
		$count = WC_Coupon_Restrictions_Table::get_similar_email_usage( 'new-customer', 'customer1@example.com' );
		$this->assertEquals( 1, $count );

		// Verify it wasn't stored with the original code
		$count = WC_Coupon_Restrictions_Table::get_similar_email_usage( 'new-customer-1', 'customer1@example.com' );
		$this->assertEquals( 0, $count );

		remove_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $filter_callback );
	}

	/**
	 * Test similar email lookup with filter
	 */
	public function test_similar_email_lookup_with_filter() {
		// First, trigger storage for order1 to create a record
		do_action( 'woocommerce_pre_payment_complete', $this->order1->get_id() );

		// Add lookup filter to transform "new-customer-2" lookups to "new-customer-1"
		$filter_callback = function( $coupon_code ) {
			if ( $coupon_code === 'new-customer-2' ) {
				return 'new-customer-1';
			}
			return $coupon_code;
		};
		add_filter( 'wcr_validate_similar_emails_restriction_lookup_code', $filter_callback );

		$posted = [
			'billing_email' => 'customer1@example.com', // Same email as order1
			'shipping_address_1' => '456 Different St',
			'shipping_address_2' => '',
			'shipping_city' => 'Different City',
			'shipping_postcode' => '67890'
		];

		// Apply coupon2 to cart
		WC()->cart->apply_coupon( $this->coupon2->get_code() );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// This should remove the coupon because "new-customer-2" looks up against "new-customer-1"
		// and finds 1 existing usage with same email (limit is 1)
		$this->validation->validate_coupons_after_checkout( $posted );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		remove_filter( 'wcr_validate_similar_emails_restriction_lookup_code', $filter_callback );
	}

	/**
	 * Test shipping address lookup with filter
	 */
	public function test_shipping_address_lookup_with_filter() {
		// Set same shipping address for both orders
		$address = [
			'shipping_address_1' => '123 Main St',
			'shipping_address_2' => '',
			'shipping_city' => 'Test City',
			'shipping_postcode' => '12345'
		];

		$this->order1->set_shipping_address_1( $address['shipping_address_1'] );
		$this->order1->set_shipping_address_2( $address['shipping_address_2'] );
		$this->order1->set_shipping_city( $address['shipping_city'] );
		$this->order1->set_shipping_postcode( $address['shipping_postcode'] );
		$this->order1->save();

		// First, trigger storage for order1 to create a record
		do_action( 'woocommerce_pre_payment_complete', $this->order1->get_id() );

		// Add lookup filter to transform "new-customer-2" lookups to "new-customer-1"
		$filter_callback = function( $coupon_code ) {
			if ( $coupon_code === 'new-customer-2' ) {
				return 'new-customer-1';
			}
			return $coupon_code;
		};
		add_filter( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', $filter_callback );

		$posted = array_merge( $address, [
			'billing_email' => 'different@example.com'
		]);

		// Apply coupon2 to cart
		WC()->cart->apply_coupon( $this->coupon2->get_code() );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// This should remove the coupon because "new-customer-2" looks up against "new-customer-1"
		// and finds 1 existing usage at this address (limit is 1)
		$this->validation->validate_coupons_after_checkout( $posted );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		remove_filter( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', $filter_callback );
	}

	/**
	 * Test IP address lookup with filter
	 */
	public function test_ip_address_lookup_with_filter() {
		$ip = '208.67.220.220';

		// Set same IP for both orders
		$this->order1->set_customer_ip_address( $ip );
		$this->order1->save();

		// First, trigger storage for order1 to create a record
		do_action( 'woocommerce_pre_payment_complete', $this->order1->get_id() );

		// Set IP address for current request
		$_SERVER['HTTP_X_REAL_IP'] = $ip;

		// Add lookup filter to transform "new-customer-2" lookups to "new-customer-1"
		$filter_callback = function( $coupon_code ) {
			if ( $coupon_code === 'new-customer-2' ) {
				return 'new-customer-1';
			}
			return $coupon_code;
		};
		add_filter( 'wcr_validate_usage_limit_per_ip_address_lookup_code', $filter_callback );

		$posted = [
			'billing_email' => 'different@example.com',
			'shipping_address_1' => '456 Different St',
			'shipping_address_2' => '',
			'shipping_city' => 'Different City',
			'shipping_postcode' => '67890'
		];

		// Apply coupon2 to cart
		WC()->cart->apply_coupon( $this->coupon2->get_code() );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// This should remove the coupon because "new-customer-2" looks up against "new-customer-1"
		// and finds 1 existing usage from this IP (limit is 1)
		$this->validation->validate_coupons_after_checkout( $posted );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		remove_filter( 'wcr_validate_usage_limit_per_ip_address_lookup_code', $filter_callback );
		unset( $_SERVER['HTTP_X_REAL_IP'] );
	}

	/**
	 * Test complete workflow: storage filter + all lookup filters together
	 */
	public function test_complete_filter_workflow() {
		// Define filter callback that transforms numbered variants to base code
		$transform_callback = function( $coupon_code ) {
			if ( strpos( $coupon_code, 'new-customer-' ) === 0 ) {
				return 'new-customer';
			}
			return $coupon_code;
		};

		// Add all filters at once to simulate real-world usage
		add_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $transform_callback );
		add_filter( 'wcr_validate_similar_emails_restriction_lookup_code', $transform_callback );
		add_filter( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', $transform_callback );
		add_filter( 'wcr_validate_usage_limit_per_ip_address_lookup_code', $transform_callback );

		// Step 1: First customer uses "new-customer-1" - should store as "new-customer"
		do_action( 'woocommerce_pre_payment_complete', $this->order1->get_id() );

		// Verify storage: should be stored as "new-customer" due to storage filter
		$count = WC_Coupon_Restrictions_Table::get_similar_email_usage( 'new-customer', 'customer1@example.com' );
		$this->assertEquals( 1, $count );

		// Step 2: Second customer tries to use "new-customer-2" with same email
		$this->order2->set_billing_email( 'customer1@example.com' ); // Same email as order1
		$this->order2->save();

		$posted = [
			'billing_email' => 'customer1@example.com',
			'shipping_address_1' => '789 Another St',
			'shipping_address_2' => '',
			'shipping_city' => 'Another City',
			'shipping_postcode' => '54321'
		];

		// Apply coupon2 to cart
		WC()->cart->apply_coupon( $this->coupon2->get_code() );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Should fail - "new-customer-2" looks up as "new-customer" via lookup filter
		// and finds existing usage with same email
		$this->validation->validate_coupons_after_checkout( $posted );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Clean up filters
		remove_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $transform_callback );
		remove_filter( 'wcr_validate_similar_emails_restriction_lookup_code', $transform_callback );
		remove_filter( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', $transform_callback );
		remove_filter( 'wcr_validate_usage_limit_per_ip_address_lookup_code', $transform_callback );
	}

	/**
	 * Test that filters don't affect non-matching coupon codes
	 */
	public function test_filters_dont_affect_other_coupons() {
		// Add filter that only affects "new-customer-*" codes
		$filter_callback = function( $coupon_code ) {
			if ( strpos( $coupon_code, 'new-customer-' ) === 0 ) {
				return 'new-customer';
			}
			return $coupon_code;
		};
		add_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $filter_callback );

		// Create a different coupon type
		$other_coupon = WC_Helper_Coupon::create_coupon( 'summer-sale' );
		$other_coupon->update_meta_data( 'prevent_similar_emails', 'yes' );
		$other_coupon->save();

		// Create order with the other coupon
		$other_order = WC_Helper_Order::create_order();
		$other_order->set_status( 'processing' );
		$other_order->set_billing_email( 'test@example.com' );
		$other_order->apply_coupon( $other_coupon );
		$other_order->calculate_totals();

		// Trigger storage
		do_action( 'woocommerce_pre_payment_complete', $other_order->get_id() );

		// Verify it was stored with original code (not transformed)
		$count = WC_Coupon_Restrictions_Table::get_similar_email_usage( 'summer-sale', 'test@example.com' );
		$this->assertEquals( 1, $count );

		// Verify it wasn't stored as "new-customer"
		$count = WC_Coupon_Restrictions_Table::get_similar_email_usage( 'new-customer', 'test@example.com' );
		$this->assertEquals( 0, $count );

		// Clean up
		$other_coupon->delete();
		$other_order->delete();
		remove_filter( 'wcr_coupon_code_to_store_for_enhanced_usage_limits', $filter_callback );
	}

	public function tear_down() {
		// Clean up test data
		WC_Coupon_Restrictions_Table::delete_table();

		// Delete test coupons and orders
		$this->coupon1->delete();
		$this->coupon2->delete();
		$this->order1->delete();
		$this->order2->delete();

		parent::tear_down();
	}
}