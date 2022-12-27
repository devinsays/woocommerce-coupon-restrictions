<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;

class Apply_Country_Restriction_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;
	public $customer;
	public $session;

	public function setUp() {
		// Creates a customer.
		$customer = WC_Helper_Customer::create_customer();
		$customer->set_billing_country( 'US' );
		$customer->set_shipping_country( 'US' );
		$customer->save();
		$this->customer = $customer;

		// Sets the customer session.
		$session = array(
			'country' 				 => $customer->get_billing_country(),
			'state' 				 => $customer->get_billing_state(),
			'postcode' 				 => $customer->get_billing_postcode(),
			'city'					 => $customer->get_billing_city(),
			'address' 				 => $customer->get_billing_address(),
			'shipping_country' 		 => $customer->get_shipping_country(),
			'shipping_state' 		 => $customer->get_shipping_state(),
			'shipping_postcode' 	 => $customer->get_shipping_postcode(),
			'shipping_city'			 => $customer->get_shipping_city(),
			'shipping_address'		 => $customer->get_shipping_address(),
		);
		WC_Helper_Customer::set_customer_details( $session );

		// Creates a coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->update_meta_data( 'location_restrictions', 'yes' );
		$coupon->update_meta_data( 'address_for_location_restrictions', 'billing' );
		$coupon->save();

		$this->coupon = $coupon;

		// Set the current customer.
		wp_set_current_user( $customer->get_id() );
	}

	/**
	 * Tests applying a coupon with country restriction and valid customer.
	 */
	public function test_coupon_country_restriction_with_valid_customer() {
		$coupon = $this->coupon;

		// Apply country restriction to single country "US"
		$coupon->update_meta_data( 'country_restriction', array( 'US' ) );
		$coupon->save();

		// Adds a country restricted coupon.
		// This should return true because customer billing is in US.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Tests applying a coupon with a two country restriction and valid customer.
	 */
	public function test_coupon_two_country_restriction_with_valid_customer() {
		$coupon = $this->coupon;

		// Apply country restiction to two countries "US" and "CA"
		$coupon->update_meta_data( 'country_restriction', array( 'US', 'CA' ) );
		$coupon->save();

		// This should return true because customer billing is in US.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Tests applying a coupon with a two country restriction and non-valid customer.
	 */
	public function test_coupon_country_restriction_with_nonvalid_customer() {
		$coupon = $this->coupon;

		// Apply country restriction single country "CA".
		$coupon->update_meta_data( 'country_restriction', array( 'CA' ) );
		$coupon->save();

		// Adds a country restricted coupon.
		// This should return false because customer billing is in US.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Tests applying a coupon with a country location requirement.
	 *
	 * The customer doesn't meet coupon location requirements,
	 * but the location restriction option is not checked,
	 * so coupon should be valid.
	 */
	public function test_location_restrictions_should_apply() {
		// Coupon can only be used in CA.
		$coupon = $this->coupon;
		$coupon->update_meta_data( 'country_restriction', array( 'CA' ) );

		// Location restriction is not checked.
		$coupon->update_meta_data( 'location_restrictions', 'no' );

		$coupon->save();

		// Adds a country restricted coupon.
		// This should be valid since location restrictions are not being checked.
		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies the coupon has not been added to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Tests applying a coupon with a country location requirement.
	 *
	 * locale_filter_matches customer doesn't meet coupon location requirements,
	 * and location restriction option is checked,
	 * so the coupon should not apply.
	 */
	public function test_location_restrictions_should_not_apply() {
		// Coupon can only be used in CA.
		$coupon = $this->coupon;
		$coupon->update_meta_data( 'country_restriction', array( 'CA' ) );

		// Location restriction is checked.
		$coupon->update_meta_data( 'location_restrictions', 'yes' );

		// Adds a country restricted coupon.
		// This should fail because customer doesn't meet requirements,
		// and location restrictions are checked.
		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies the coupon has not been added to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	public function tearDown() {
		// Reset the customer session data.
		WC()->session->set( 'customer', array() );

		// Remove the coupons from the cart.
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();

		$this->customer->delete();
		$this->coupon->delete();
	}
}
