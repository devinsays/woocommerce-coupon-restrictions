<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Customer;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Validation;

class New_Customer_Coupon_Test extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;
	public $customer;
	public $order;
	public $validation;

	public function setUp() {
		// Create a customer.
		$customer = WC_Helper_Customer::create_customer();
		$this->customer = $customer;

		// Creates an order and applies it to new customer.
		$order = WC_Helper_Order::create_order( $customer->get_id() );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();
		$this->order = $order;
	}

	/**
	 * Test returning customer function.
	 */
	public function test_is_returning_customer() {
		// Test should return false because customer hasn't purchased.
		$this->assertFalse( WC_Coupon_Restrictions_Validation::is_returning_customer( 'not@woo.com' ) );

		// Get data from setup.
		$customer = $this->customer;
		wp_set_current_user( $customer->get_id() );

		// Test should return true because customer has a completed order.
		$this->assertTrue( WC_Coupon_Restrictions_Validation::is_returning_customer( $customer->get_email() ) );
	}

	public function tearDown() {
		$this->order->delete();
		$this->customer->delete();
	}
}
