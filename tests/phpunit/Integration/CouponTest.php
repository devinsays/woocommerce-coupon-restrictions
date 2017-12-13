<?php

namespace DevPress\WooCommerce\CouponRestrictions\Test\Integration;

use WC_Helper_Customer;
use WC_Coupon_Restrictions_Validation;

class CouponTest extends \WP_UnitTestCase {
	protected $coupon;

	public function setUp() {
		parent::setUp();
	}

	/**
	 * Test returning customer function.
	 */
	public function test_is_returning_customer() {

		// Create a customer.
		$customer = \WC_Helper_Customer::create_customer();
		$customer_id = $customer->get_id();

		// Create an order and apply it to new customer.
		$order = \WC_Helper_Order::create_order( $customer_id );
		$order->set_billing_email( $customer->get_email() );
		$order->set_status( 'completed' );
		$order->save();

		// Test customer with completed order.
		$this->assertTrue( \WC_Coupon_Restrictions_Validation::is_returning_customer( $customer->get_email() ) );

		// Test customer without completed order.
		$this->assertFalse( \WC_Coupon_Restrictions_Validation::is_returning_customer( 'not@example.org' ) );

		// Delete order.
		\WC_Helper_Order::delete_order( $order->get_id() );

		// Delete customer
		$customer->delete();

	}

}
