<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation_Checkout;

class Checkout_Country_Restriction_Coupon_Test extends WP_UnitTestCase {
	/* var WC_Coupon */
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
	 * Coupon is valid because coupon is restricted to US,
	 * and customer billing_country is US.
	 */
	public function test_checkout_country_restriction_with_valid_customer() {
		$coupon = $this->coupon;

		// Apply country restriction to single country "US".
		$coupon->update_meta_data( 'country_restriction', array( 'US' ) );
		$coupon->save();

		// Mock post data.
		$posted = array(
			'billing_country' => 'US'
		);

		// Adds a country restricted coupon.
		// This will apply because no validation runs if a session is not set.
		WC()->cart->apply_coupon( $coupon->get_code() );

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Coupon is not valid because coupon is restricted to US,
	 * and customer billing_country is CA.
	 */
	public function test_checkout_country_restriction_with_not_valid_customer() {
		$coupon = $this->coupon;

		// Apply country restriction to single country "US"
		$coupon->update_meta_data( 'country_restriction', array( 'US' ) );
		$coupon->save();

		// Mock post data.
		$posted = array(
			'billing_email' => 'customer@woo.com',
			'billing_country' => 'CA'
		);

		// Adds a country restricted coupon.
		// This will apply because no validation runs if a session is not set.
		WC()->cart->apply_coupon( $coupon->get_code() );

		// Run the post checkout validation.
		$validation = new WC_Coupon_Restrictions_Validation_Checkout();
		$validation->validate_coupons_after_checkout( $posted );

		// Verifies 0 coupons are still in cart after checkout validation.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	public function tearDown() {
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		$this->coupon->delete();
	}
}
