<?php

namespace WooCommerce\Tests\New_Customer_Coupon;

/**
 * Class New_Customer_Coupon.
 * @package WooCommerce\Tests\New_Customer_Coupon
 */
class New_Customer_Coupon extends \WC_Unit_Test_Case {

	/**
	 * Test new customer apply coupon
	 */
	public function test_new_customer_apply_coupon() {

		// Create a customer
		$customer = \WC_Helper_Customer::create_mock_customer();

		// Create coupon
		$coupon = \WC_Helper_Coupon::create_coupon();

		// Add coupon, test return statement
		$this->assertTrue( WC()->cart->add_discount( $coupon->code ) );

		// Test if total amount of coupons is 1
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Remove coupons
		WC()->cart->remove_coupons();

		// Delete coupon
		\WC_Helper_Coupon::delete_coupon( $coupon->id );

	}

	/**
	 * Test existing customer apply coupon
	 */
	public function test_existing_customer_apply_coupon() {

		// Create a customer
		$customer = \WC_Helper_Customer::create_customer();
		wp_set_current_user( $customer );

		// Create an order
		$order = \WC_Helper_Order::create_order();
		$order->update_status( 'completed' );
		update_post_meta( $order->id, '_customer_user', $customer );

		// Create coupon
		$coupon = \WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->id, 'new_customers_only', 'yes' );

		// Add coupon, test return statement
		$this->assertTrue( WC()->cart->add_discount( $coupon->code ) );

		// Test if total amount of coupons is 0
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Remove coupons
		WC()->cart->remove_coupons();

		// Delete coupon
		\WC_Helper_Coupon::delete_coupon( $coupon->id );

	}


}
