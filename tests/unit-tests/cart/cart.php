<?php

namespace WooCommerce\Tests\Cart;

/**
 * Class Cart.
 * @package WooCommerce\Tests\Cart
 */
class Cart extends \WC_Unit_Test_Case {

	/**
	 * Test cart coupons.
	 */
	public function test_get_coupons() {

		// Create coupon
		$coupon = \WC_Helper_Coupon::create_coupon();

		// Add coupon
		WC()->cart->add_discount( $coupon->code );

		$this->assertEquals( count( WC()->cart->get_coupons() ), 1 );

		// Clean up the cart
		WC()->cart->empty_cart();

		// Remove coupons
		WC()->cart->remove_coupons();

		// Delete coupon
		\WC_Helper_Coupon::delete_coupon( $coupon->id );

	}

}
