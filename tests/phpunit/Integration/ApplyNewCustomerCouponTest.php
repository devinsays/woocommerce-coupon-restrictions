<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Helper_Order;

class Apply_New_Customer_Coupon_Test extends WP_UnitTestCase {
	public $coupon;
	public $customer;
	public $order;

	public function setUp() {
		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'customer_restriction_type', 'new' );
		$this->coupon = $coupon;

		// Create a customer.
		$customer = WC_Helper_Customer::create_customer(
			'customer',
			'password',
			'customer@woo.com'
		);
		$this->customer = $customer;

		// Creates an order and applies it to new customer.
		$order = WC_Helper_Order::create_order( $customer->get_id() );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();
		$this->order = $order;

	}

	/**
	 * Coupon will apply once a session with email has been created,
	 * and email does not match existing customer.
	 */
	public function test_new_customer_restriction_valid() {
		$coupon = $this->coupon;

		// Create a mock customer session.
		$session = array(
			'id' => 0,
			'email' => 'new@woo.com'
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Adds a coupon restricted to new customers.
		// This should return true because customer hasn't yet purchased.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will not apply once a session with email has been created,
	 * and email matches existing customer with purchases.
	 */
	public function new_customer_restriction_invalid_has_account() {
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Create a mock customer session.
		$session = array(
			'id' => 0,
			'email' => $customer->get_email()
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Adds a coupon restricted to new customers.
		// This should return false because customer has purchased.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Checks that "coupon_restrictions_customer_query" setting is working.
	 */
	public function test_coupon_restrictions_customer_query_setting() {
		// Get data from setup.
		$coupon = $this->coupon;

		// Create new order.
		$guest_email = 'guest@woo.com';
		$order = WC_Helper_Order::create_order();
		$order->set_billing_email( $guest_email );
		$order->set_status( 'completed' );
		$order->save();

		// Create a mock customer session.
		$session = array(
			'email' => $guest_email
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Sets coupon_restrictions_customer_query to search accounts only.
		update_option( 'coupon_restrictions_customer_query', 'accounts' );

		// Adds a coupon restricted to new customers.
		// This should return true because customer has a previous guest order
		// but no account.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Sets coupon_restrictions_customer_query to now search guest orders.
		update_option( 'coupon_restrictions_customer_query', 'accounts-orders' );

		// Remove coupon that had just been set.
		WC()->cart->remove_coupons();

		// Adds a coupon restricted to new customers.
		// This should return false because customer has a previous guest order.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		delete_option( 'coupon_restrictions_customer_query' );
	}

	public function tearDown() {
		// Reset the customer session data.
		WC()->session->set( 'customer', array() );

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Deletes objects.
		$this->coupon->delete();
		$this->customer->delete();
		$this->order->delete();
	}
}
