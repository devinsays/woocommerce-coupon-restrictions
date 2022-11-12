<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Coupon;

class Apply_Postcode_Restriction_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;

	public $customer;
	public $session;

	public function setUp() {
		// Creates a customer.
		$customer = WC_Helper_Customer::create_customer();
		$customer->set_billing_postcode( '78703' );
		$customer->set_shipping_postcode( '78703' );
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
	 * Tests applying a coupon with postcode restriction and valid customer.
	 */
	public function test_postcode_restriction_with_valid_customer() {
		$coupon = $this->coupon;
		$coupon->update_meta_data( 'postcode_restriction', '78703' );
		$coupon->save();

		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Tests applying a postcode restriction and non-valid customer.
	 */
	public function test_coupon_country_restriction_with_nonvalid_customer() {
		$coupon = $this->coupon;
		$coupon->update_meta_data( 'postcode_restriction', '000000' );
		$coupon->save();

		$this->assertFalse( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 0 coupons have been applied to cart.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Tests applying a coupon with postcode restriction and valid customer.
	 */
	public function test_valid_postcode_restriction_wildcard() {
		$coupon = $this->coupon;
		$coupon->update_meta_data( 'postcode_restriction', '00000,787*,ALPHAZIP' );
		$coupon->save();

		$this->assertTrue( WC()->cart->apply_coupon( $coupon->get_code() ) );

		// Verifies 1 coupon has been applied to cart.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
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
