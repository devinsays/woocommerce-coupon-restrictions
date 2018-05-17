<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Validation;

class Existing_Customer_Coupon_Test extends \WP_UnitTestCase {

	public $coupon;
	public $customer;

	public function setUp() {

		// Create a customer.
		$customer = WC_Helper_Customer::create_customer();
		$this->customer = $customer;

		// Create a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'customer_restriction_type', 'existing' );
		$this->coupon = $coupon;

		// Set the current customer.
		wp_set_current_user( $customer->get_id() );

	}

	/**
	 * Tests that a coupon with an existing customer restriction can not be applied
	 * to customer without any orders.
	 */
	public function test_existing_customer_restriction_coupon_with_new_customer() {

		// Get data from setup.
		$customer = $this->customer;
		$coupon = $this->coupon;

		// Adds a new customer restricted coupon.
		// This should return false because customer hasn't yet purchased.
		$this->assertFalse( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Tests that a coupon with an existing customer restriction can be applied
	 * to a customer with previous orders.
	 */
	public function test_existing_customer_restriction_coupon_with_existing_customer() {

		// Get data from setup.
		$customer = $this->customer;
		$coupon = $this->coupon;

		// Creates an order and applies it to new customer.
		// This makes the customer a returning customer.
		$order = WC_Helper_Order::create_order( $customer->get_id() );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();

		// Adds coupon, this should now return true.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Clean up.
		$order->delete();

	}

	public function tearDown() {

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Delete coupon and customer.
		$this->coupon->delete();
		$this->customer->delete();

	}

}
