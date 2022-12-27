<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Validation_Checkout;

class Checkout_New_Customer_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;

	public function setUp() {
		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->update_meta_data( 'customer_restriction_type', 'new' );
		$coupon->save();
		$this->coupon = $coupon;

	}

	/**
	 * Coupon will apply because $posted data contains a new customer.
	 */
	public function test_new_customer_restriction_with_checkout_valid() {
		$coupon = $this->coupon;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Mock the posted data.
		$posted = array(
			'billing_email' => 'new@woo.com'
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Coupon will be removed because $posted data contains an existing customer.
	 */
	public function test_new_customer_restriction_with_checkout_not_valid() {
		// Create a customer.
		$customer = WC_Helper_Customer::create_customer();

		// Creates an order and applies it to new customer.
		$order = WC_Helper_Order::create_order( $customer->get_id() );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();

		// Applies the coupon. This should apply since no session is set.
		// Creates a coupon.
		$coupon = $this->coupon;
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Mock the posted data.
		$posted = array(
			'billing_email' => $customer->get_email()
		);

		// Run the post checkout validation.
		// Coupon will be removed from cart because customer has previous purchases.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * If customer has previous guest order and coupon_restrictions_customer_query
	 * is set to 'accounts-orders', checkout should fail.
	 */
	public function test_customer_has_previous_guest_order() {
		$coupon = $this->coupon;

		// Email to use for this test.
		$email = 'customer@woo.com';

		// Creates a new guest order.
		$order = WC_Helper_Order::create_order();
		$order->set_billing_email( $email );
		$order->set_status( 'completed' );
		$order->save();

		// Create a customer.
		WC_Helper_Customer::create_customer( 'customer', 'password', $email );

		// Adds a coupon restricted to new customers.
		// This should return true because customer doesn't have any purchases applied to their account.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Mock the posted data.
		$posted = array(
			'billing_email' => $email
		);

		// Run the post checkout validation.
		// Coupon will not be removed because coupon_restrictions_customer_query
		// is set to 'acccounts' by default.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		update_option( 'coupon_restrictions_customer_query', 'accounts-orders' );

		// Run the post checkout validation now with
		// coupon_restrictions_customer_query set to 'accounts-orders'.
		// Coupon will be removed this time.
		$validation->validate_coupons_after_checkout( $posted );

		delete_option( 'coupon_restrictions_customer_query' );
		$order->delete();
	}


	public function tearDown() {
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		$this->coupon->delete();
	}

}
