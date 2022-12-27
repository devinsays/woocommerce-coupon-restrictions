<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation_Checkout;

class Checkout_Role_Restriction_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
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
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Set role that does not match coupon.
		$user = new \WP_User( $customer->get_id() );
		$user->set_role('subscriber');

		// Creates mock checkout data.
		$posted = array(
			'billing_email' => $customer->get_email()
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon has been removed.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will remain applied because user role matches restriction.
	 */
	public function test_coupon_applies_if_role_restriction_valid() {
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Set role that does not match coupon.
		$user = new \WP_User( $customer->get_id() );
		$user->set_role('administrator');

		// Creates mock checkout data.
		$posted = array(
			'billing_email' => $customer->get_email()
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon remains applied.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will apply because customer is guest and guest role is permitted.
	 */
	public function test_coupon_success_if_guest_and_guest_role_set() {
		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->update_meta_data( 'role_restriction', ['woocommerce-coupon-restrictions-guest'] );
		$coupon->save();

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Creates mock checkout data.
		$posted = array(
			'email' => 'guest@testing.dev'
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon remains applied.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will not apply because customer is guest and guest role is not permitted.
	 */
	public function test_coupon_fails_if_guest_and_guest_role_not_set() {
		// Get data from setup for coupon restricted to administrators (no guest role).
		$coupon = $this->coupon;

		// Applies the coupon. This should apply since no session is set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Creates mock checkout data.
		$posted = array(
			'email' => 'guest@testing.dev'
		);

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon removed because customer does not meet restriction.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}


	public function tearDown() {
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		$this->coupon->delete();
		$this->customer->delete();
	}
}
