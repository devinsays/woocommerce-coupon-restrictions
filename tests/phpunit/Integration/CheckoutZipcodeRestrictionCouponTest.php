<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;

class Checkout_Zipcode_Restriction_Coupon_Test extends WP_UnitTestCase {

	public $coupon;

	public function setUp() {
		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();

		$coupon->update_meta_data( 'location_restrictions', 'yes' );
		$coupon->update_meta_data( 'address_for_location_restrictions', 'billing' );
		$coupon->save();

		$this->coupon = $coupon;
	}

	/**
	 * Test checkout with single zipcode.
	 */
	public function test_checkout_zipcode_restriction_valid() {
		$coupon = $this->coupon;

		// Apply postcode restriction to zipcode 78703.
		$coupon->update_meta_data( 'postcode_restriction', '78703' );
		$coupon->save();

		// Mock post data.
		$posted = array(
			'billing_postcode' => '78703'
		);

		WC()->cart->apply_coupon( $coupon->get_code() );

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		$posted = array(
			'billing_postcode' => '000000'
		);

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 0 coupons in cart with invalid zipcode.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Test checkout with multiple zipcodes (comma seperated).
	 */
	public function test_checkout_multiple_zipcode_restriction_valid() {
		$coupon = $this->coupon;

		// Apply postcode restriction to zipcode 78703.
		$coupon->update_meta_data( 'postcode_restriction', '78702,78703,78704' );
		$coupon->save();

		// Mock post data.
		$posted = array(
			'billing_postcode' => '78703'
		);

		WC()->cart->apply_coupon( $coupon->get_code() );

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Test checkout with wildcard zipcode restriction.
	 */
	public function test_checkout_wildcode_zipcode_restriction_valid() {
		$coupon = $this->coupon;

		// Apply postcode restriction to zipcode 787*.
		$coupon->update_meta_data( 'postcode_restriction', '787*' );
		$coupon->save();

		// Mock post data.
		$posted = array(
			'billing_postcode' => '78703'
		);

		WC()->cart->apply_coupon( $coupon->get_code() );

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		$posted = array(
			'billing_postcode' => '000000'
		);

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 0 coupons in cart with invalid zipcode.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Test checkout with multiple wildcard zipcode restriction.
	 */
	public function test_checkout_multiple_wildcode_zipcode_restriction_invalid() {
		$coupon = $this->coupon;

		// Apply postcode restriction multiple wildcards.
		$coupon->update_meta_data( 'postcode_restriction', '787*,zip*' );
		$coupon->save();

		// Mock post data.
		$posted = array(
			'billing_postcode' => 'ZIPCODE'
		);

		WC()->cart->apply_coupon( $coupon->get_code() );

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		$posted = array(
			'billing_postcode' => '000000'
		);

		// Run the post checkout validation.
		WC_Coupon_Restrictions()->validation->validate_coupons_after_checkout( $posted );

		// Verifies 0 coupons in cart with invalid zipcode.
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
