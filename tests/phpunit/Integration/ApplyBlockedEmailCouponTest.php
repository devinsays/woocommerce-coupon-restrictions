<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;

class Apply_Blocked_Email_Coupon_Test extends WP_UnitTestCase {

	public $coupon;

	public function setUp() {

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->add_meta_data( 'email_blocked', ['blocked@test.com'], true );
		$coupon->save();
		$this->coupon = $coupon;
	}

	/**
	 * Coupon will apply because no session has been set yet.
	 */
	public function test_coupon_applies_with_no_session() {

		// Get data from setup.
		$coupon = $this->coupon;

		// This should apply because no session has been set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Coupon will fail if customer matches blocked email.
	 */
	public function test_coupon_fails_if_email_is_blocked() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Create a mock customer session.
		$session = array(
			'email' => 'blocked@test.com'
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should not apply because email is blocked.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Coupon will apply if email is not blocked.
	 */
	public function test_coupon_success_if_email_is_not_blocked() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Create a mock customer session.
		$session = array(
			'email' => 'notblocked@test.com'
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should apply because email is not blocked.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}


	public function tearDown() {

		// Reset the customer session data.
		WC()->session->set( 'customer', array() );

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Deletes objects.
		$this->coupon->delete();
	}

}
