<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;

class Apply_Generic_Coupon_Test extends WP_UnitTestCase {
	/**
	 * Tests that generic coupons can be applied.
	 */
	public function test_apply_coupon() {
		// Create a customer.
		$customer = WC_Helper_Customer::create_customer();
		$customer_id = $customer->get_id();
		wp_set_current_user( $customer_id );

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();

		// Adds a coupon, test return statement.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Test if total amount of coupons is 1.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Clean up.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		$coupon->delete();
		$customer->delete();
	}
}
