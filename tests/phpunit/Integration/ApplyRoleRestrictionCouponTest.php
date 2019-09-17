<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Helper_Order;

class Apply_Role_Restriction_Coupon_Test extends WP_UnitTestCase {

	public $coupon;

	public function setUp() {

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'role_restriction', ['administrator'] );
		$this->coupon = $coupon;

	}

	/**
	 * Coupon will apply because no session has been set yet.
	 */
	public function test_coupon_applies_with_no_session() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Adds a role restricted coupon.
		// This should apply because no session has been set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Coupon will apply if user has correct role.
	 */
	public function test_existing_customer_restriction_with_session_valid() {

		// Get data from setup.
		$coupon = $this->coupon;

		// Create a customer.
		$customer = WC_Helper_Customer::create_customer();
		$customer->set_role('subscriber');

		// Crate a mock customer session.
		$session = array(
			'email' => $customer->get_email()
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should not apply because custom role and restriction do not match.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
		
		// Change the user role to one that matches restriction.
		$customer->set_role('administrator');
		
		// Coupon should apply because customer role and restriction match.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		$customer->delete();

	}


	public function tearDown() {
		// Deletes the coupon.
		$this->coupon->delete();
	}

}
