<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Unit;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Validation;

class Checkout_Existing_Customer_Coupon_Test extends WP_UnitTestCase {

	public $coupon;

	public function setUp() {

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'customer_restriction_type', 'existing' );
		$this->coupon = $coupon;

	}

	/**
	 * Coupon will not apply because $posted data contains a new customer.
	 */
	public function test_existing_customer_restriction_with_checkout_not_valid() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Mock the posted data.
		$posted = array(
			'billing_email' => 'new@woo.com'
		);

		// Run the post checkout validation.
		WC_Coupon_Restrictions_Validation::validate_coupons_after_checkout( $posted );

		// Verifies 0 coupons are still in cart after checkout validation.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Coupon will be valid because $posted data contains an existing customer.
	 */
	public function test_existing_customer_restriction_checkout_not_valid() {

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
		WC_Coupon_Restrictions_Validation::validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}


	public function tearDown() {

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Deletes the coupon.
		$this->coupon->delete();

	}

}
