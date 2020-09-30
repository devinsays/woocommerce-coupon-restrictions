<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation;

class Checkout_Blocked_Email_Test extends WP_UnitTestCase {

	public $coupon;

	public function setUp() {

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->add_meta_data( 'email_blocked', ['blocked@test.com'], true );
		$coupon->save();
		$this->coupon = $coupon;

	}

	/**
	 * Coupon should apply because customer is not blocked.
	 */
	public function test_coupon_applies_if_email_valid() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Mock the posted data.
		$posted = array(
			'billing_email' => 'notblocked@test.com'
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Coupon should not apply because customer is blocked.
	 */
	public function test_coupon_does_not_apply_if_email_blocked() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Mock the posted data.
		$posted = array(
			'billing_email' => 'blocked@test.com'
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 0 coupon in cart after checkout validation.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}


	public function tearDown() {

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Deletes the coupon.
		$this->coupon->delete();

	}

}
