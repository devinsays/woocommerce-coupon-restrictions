<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation;

class Checkout_Role_Restriction_Coupon_Test extends WP_UnitTestCase {

	public $coupon;
	public $customer;

	public function setUp() {

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'role_restriction', ['administrator'] );
		$this->coupon = $coupon;
		
		// Creates a customer.
		$customer = WC_Helper_Customer::create_customer();
		$this->customer = $customer;

	}

	/**
	 * Coupon will be removed because user role does not match restriction.
	 */
	public function test_coupon_removed_if_role_restriction_invalid() {

		// Get data from setup.
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Set role that does not match coupon.
		$user = new \WP_User( $customer->get_id() );
		$user->set_role('subscriber');
		
		// Create a mock customer session.
		$posted = array(
			'billing_email' => $customer->get_email()
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon has been removed.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

	}
	
	/**
	 * Coupon will be remain applied because user role does match restriction.
	 */
	public function test_coupon_applies_if_role_restriction_valid() {

		// Get data from setup.
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Set role that does not match coupon.
		$user = new \WP_User( $customer->get_id() );
		$user->set_role('administrator');
		
		// Create a mock customer session.
		$posted = array(
			'billing_email' => $customer->get_email()
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon remains applied.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}


	public function tearDown() {

		// Removes the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Deletes objects.
		$this->coupon->delete();
		$this->customer->delete();

	}

}
