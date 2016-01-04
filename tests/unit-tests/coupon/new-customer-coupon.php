<?php

namespace WooCommerce\Tests\New_Customer_Coupon;

/**
 * Class New_Customer_Coupon.
 * @package WooCommerce\Tests\New_Customer_Coupon
 */
class New_Customer_Coupon extends \WC_Unit_Test_Case {

	/**
	 * Test returning customer function.
	 */
	public function test_is_returning_customer() {

		// Create a customer
		$email = 'customer@example.org';
		$customer = wc_create_new_customer( $email, $email, 'password' );

		// Create an order and apply it to new customer
		$order = \WC_Helper_Order::create_order();
		update_post_meta( $order->id, '_customer_user', $customer );
		update_post_meta( $order->id, '_billing_email', $email );
		$order->update_status( 'wc-completed' );

		// Set up the New Customer Coupons Class
		$plugin = new \WC_New_Customer_Coupons();

		// Test customer with completed order
		$this->assertTrue( $plugin->is_returning_customer( $email ) );

		// Test customer without completed order
		$this->assertFalse( $plugin->is_returning_customer( 'not@example.org' ) );

		// Delete order
		\WC_Helper_Order::delete_order( $order->id );

		// Delete customer
		wp_delete_user( $email );

	}

	/**
	 * Tests that a new customer can apply a coupon.
	 */
	public function test_new_customer_apply_coupon() {

		// Create a customer
		$email = 'customer@example.org';
		$customer = wc_create_new_customer( $email, $email, 'password' );
		wp_set_current_user( $customer );

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
	 * Tests that an existing customer *can* apply a coupon if
	 * it does *not* have a new_customers_only restriction.
	 */
	public function test_existing_customer_apply_coupon_without_new_customer_restriction() {

		// Create a customer
		$email = 'customer@example.org';
		$customer = wc_create_new_customer( $email, $email, 'password' );
		wp_set_current_user( $customer );

		// Create an order and apply it to new customer
		$order = \WC_Helper_Order::create_order();
		update_post_meta( $order->id, '_customer_user', $customer );
		update_post_meta( $order->id, '_billing_email', $email );
		$order->update_status( 'wc-completed' );

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

		// Delete order
		\WC_Helper_Order::delete_order( $order->id );

		// Delete customer
		wp_delete_user( $email );

	}

	/**
	 * Tests that an existing customer *cannot* apply a coupon if
	 * it *does* have a new_customers_only restriction.
	 */
	public function test_existing_customer_apply_coupon_with_new_customer_restriction() {

		// Create a customer
		$email = 'customer@example.org';
		$customer = wc_create_new_customer( $email, $email, 'password' );
		wp_set_current_user( $customer );

		// Create an order and apply it to new customer
		$order = \WC_Helper_Order::create_order();
		update_post_meta( $order->id, '_customer_user', $customer );
		update_post_meta( $order->id, '_billing_email', $email );
		$order->update_status( 'wc-completed' );

		// Create coupon
		$coupon = \WC_Helper_Coupon::create_coupon();
		update_post_meta( $coupon->id, 'new_customers_only', 'yes' );

		// Add coupon, test return statement
		$this->assertFalse( WC()->cart->add_discount( $coupon->code ) );

		// Test if total amount of coupons is 0
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Remove coupons
		WC()->cart->remove_coupons();

		// Delete coupon
		\WC_Helper_Coupon::delete_coupon( $coupon->id );

		// Delete order
		\WC_Helper_Order::delete_order( $order->id );

		// Delete customer
		wp_delete_user( $email );

	}

}