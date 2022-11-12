<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;

class Apply_Role_Restriction_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;
	public $customer;
	public $session;

	public function setUp() {
		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->update_meta_data( 'role_restriction', ['administrator'] );
		$coupon->save();

		$this->coupon = $coupon;

		// Creates a customer.
		$customer = WC_Helper_Customer::create_customer();
		$this->customer = $customer;
	}

	/**
	 * Coupon will apply because no session has been set yet.
	 */
	public function test_coupon_applies_with_no_session() {
		$coupon = $this->coupon;

		// Adds a role restricted coupon.
		// This should apply because no session has been set.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupons have been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will fail if restriction does not match.
	 */
	public function test_coupon_fails_if_restrictions_do_not_match() {
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Set role that does not match coupon.
		$user = new \WP_User( $customer->get_id() );
		$user->set_role('subscriber');

		// Create a mock customer session.
		$session = array(
			'email' => $customer->get_email()
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should not apply because custom role and restriction do not match.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will apply if restrictions match.
	 */
	public function test_coupon_success_if_restrictions_match() {
		$coupon = $this->coupon;
		$customer = $this->customer;

		// Set role that does match coupon.
		$user = new \WP_User( $customer->get_id() );
		$user->set_role('administrator');

		// Create a mock customer session.
		$session = array(
			'email' => $customer->get_email()
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should apply because customer role and restriction match.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );
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

		// Create a mock customer session.
		$session = array(
			'email' => 'guest@testing.dev'
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should apply because customer does not have an account
		// and the role restriction allows guests.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon will not apply because customer is guest and guest role is not permitted.
	 */
	public function test_coupon_fails_if_guest_and_guest_role_not_set() {
		// Get data from setup for coupon restricted to administrators (no guest role).
		$coupon = $this->coupon;

		// Create a mock customer session.
		$session = array(
			'email' => 'guest@testing.dev'
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Coupon should not apply because customer does not have an account
		// and the role restriction does not allow guests.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
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
	}

}
