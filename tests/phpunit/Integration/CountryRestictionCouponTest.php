<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use DevPress\WooCommerce\CouponLinks\Test\Framework\MockSession;
use WC_Helper_Customer;
use WC_Helper_Coupon;
use WC_Coupon_Restrictions_Validation;

class Country_Restriction_Test extends \WP_UnitTestCase {

	/**
	 * Tests the country restriction (billing address).
	 */
	public function test_country_restriction_billing() {

		// Create a customer.
		$customer = \WC_Helper_Customer::create_customer();
		$customer->set_billing_country( 'US' );
		$customer->save();
		$customer_id = $customer->get_id();
		wp_set_current_user( $customer_id );

		// Create coupon.
		$coupon = \WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->get_id(), 'address_for_location_restrictions', 'billing' );
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'US' ) );

		// Adds a country restricted coupon.
		// This should return true because customer billing is in US.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Remove the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Updates country restriction, coupon now restricted to two countries.
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'US', 'CA' ) );

		// This should return true because customer billing is in US.
		$this->assertTrue( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Remove the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		// Updates country restriction, coupon now restricted to Canada.
		update_post_meta( $coupon->get_id(), 'country_restriction', array( 'CA' ) );

		// Adds a country restricted coupon.
		// This should return false because customer billing is in US.
		$this->assertFalse( WC()->cart->add_discount( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Clean up.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		$customer->delete();
		$coupon->delete();

	}

}
