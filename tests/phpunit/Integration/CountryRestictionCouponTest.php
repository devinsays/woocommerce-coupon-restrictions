<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use DevPress\WooCommerce\CouponLinks\Test\Framework\MockSession;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation;

class Country_Restriction_Test extends \WP_UnitTestCase {

	public $coupon;
	public $customer;

	public function setUp() {

		// Create a customer.
		$customer = \WC_Helper_Customer::create_customer();
		$customer->set_billing_country( 'US' );
		$customer->save();
		$this->customer = $customer;

		// Create a coupon.
		$coupon = \WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'location_restrictions', 'yes' );
		update_post_meta( $coupon->get_id(), 'address_for_location_restrictions', 'billing' );
		$this->coupon = $coupon;

		// Set the current customer.
		wp_set_current_user( $customer->get_id() );

	}

	/**
	 * Tests coupon with country restriction and valid customer.
	 */
	public function test_coupon_country_restriction_with_valid_customer() {

		$customer = $this->customer;
		$coupon = $this->coupon;

		// Apply country restriction to single country "US"
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'US' ) );

		// Adds a country restricted coupon.
		// This should return true because customer billing is in US.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Tests coupon with two country restrictions and valid customer.
	 */
	public function test_coupon_two_country_restriction_with_valid_customer() {

		$customer = $this->customer;
		$coupon = $this->coupon;

		// Apply country restiction to two countries "US" and "CA"
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'US', 'CA' ) );

		// This should return true because customer billing is in US.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Tests coupon with one country restrictions and non-valid customer.
	 */
	public function test_coupon_country_restriction_with_nonvalid_customer() {

		$customer = $this->customer;
		$coupon = $this->coupon;

		// Apply country restriction single country "CA"
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'CA' ) );

		// Adds a country restricted coupon.
		// This should return false because customer billing is in US.
		$this->assertFalse( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

	}

	/**
	 * Tests that location_restiction checkbox setting is working as intended.
	 * If location_restiction is not checked, location restrictions should not apply.
	 */
	public function test_location_restriction_setting() {

		$customer = $this->customer;
		$coupon = $this->coupon;

		// Location restriction is not checked.
		update_post_meta( $coupon->get_id(), 'location_restrictions', 'no' );

		// Apply country restriction single country "CA"
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'CA' ) );

		// Adds a country restricted coupon.
		// This should return false because customer billing is in US.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

	}

	public function tearDown() {

		// Remove the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		$this->customer->delete();
		$this->coupon->delete();

	}

}
