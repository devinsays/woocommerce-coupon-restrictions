<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Helper_Order;

class Apply_Existing_Customer_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;

	public function setUp() {
		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->update_meta_data( 'customer_restriction_type', 'existing' );
		$coupon->save();

		$this->coupon = $coupon;
	}

	/**
	 * Coupon will apply because no session has been set yet.
	 */
	public function test_existing_customer_restriction_coupon_applies_with_no_session() {
		$coupon = $this->coupon;

		// Adds a coupon restricted to existing customers.
		// This should return false because customer hasn't yet purchased.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will not apply once a session with email has been created,
	 * and email does not match existing customer.
	 */
	public function test_existing_customer_restriction_with_session_not_valid() {
		$coupon = $this->coupon;

		// Crate a mock customer session.
		$session = array(
			'email' => 'new@woo.com'
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Adds a coupon restricted to existing customers.
		// This should return false because customer hasn't yet purchased.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will not apply once a session with email has been created,
	 * and email does not match existing customer.
	 */
	public function test_existing_customer_restriction_with_session_valid() {
		$coupon = $this->coupon;

		// Create a customer.
		$customer = WC_Helper_Customer::create_customer();
		$this->customer = $customer;

		// Creates an order and applies it to new customer.
		$order = WC_Helper_Order::create_order( $customer->get_id() );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();

		// Crate a mock customer session.
		$session = array(
			'email' => $customer->get_email()
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Adds a coupon restricted to existing customers.
		// This should return true because customer has purchased.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		$order->delete();
	}


	public function tearDown() {
		// Reset the customer session data.
		WC()->session->set( 'customer', array() );

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Deletes the coupon.
		$this->coupon->delete();
	}
}
