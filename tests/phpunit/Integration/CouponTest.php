<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation;

class CouponTest extends \WP_UnitTestCase {
	protected $coupon;

	public function setUp() {
		parent::setUp();
	}

	/**
	 * Test returning customer function.
	 */
	public function test_is_returning_customer() {

		// Create a customer.
		$customer = \WC_Helper_Customer::create_customer();
		$customer_id = $customer->get_id();

		// Create an order and apply it to new customer.
		$order = \WC_Helper_Order::create_order( $customer_id );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();

		// Test customer with completed order.
		$this->assertTrue( \WC_Coupon_Restrictions_Validation::is_returning_customer( $customer->get_email() ) );

		// Test customer without completed order.
		$this->assertFalse( \WC_Coupon_Restrictions_Validation::is_returning_customer( 'not@example.org' ) );

		// Clean up.
		$order->delete();
		$customer->delete();

	}


	/**
	 * Tests that coupons can be applied.
	 */
	public function test_apply_coupon() {

		// Create a customer.
		$customer = \WC_Helper_Customer::create_customer();
		$customer_id = $customer->get_id();
		wp_set_current_user( $customer_id );

		// Create coupon.
		$coupon = \WC_Helper_Coupon::create_coupon();

		// Add coupon, test return statement.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Test if total amount of coupons is 1.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Clean up.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		$customer->delete();
		$coupon->delete();

	}

	/**
	 * Tests that a coupon with a new customer restriction cannot be applied
	 * to an existing customer.
	 */
	public function test_new_customer_restriction_type() {

		// Create a customer.
		$customer = \WC_Helper_Customer::create_customer();
		$customer_id = $customer->get_id();
		wp_set_current_user( $customer_id );

		// Create coupon.
		$coupon = \WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'customer_restriction_type', 'new' );

		// Add coupon, test return statement.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Test if total amount of coupons is 0.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Remove the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Create an order and apply it to new customer.
		$order = \WC_Helper_Order::create_order( $customer_id );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();

		// Add coupon, test return statement.
		$this->assertFalse( WC()->cart->add_discount( $coupon->get_code() ) );

		// Test if total amount of coupons is 0.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Clean up.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		$order->delete();
		$coupon->delete();

	}

}
