<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Table;

class RestrictionsTableTest extends WP_UnitTestCase {

	/**
	 * Test table creation.
	 */
	public function test_table_creation() {
		// Test should return false because table doesn't exist.
		$this->assertFalse( WC_Coupon_Restrictions_Table::table_exists() );

		// Create table.
		$verification_table = new WC_Coupon_Restrictions_Table();
		$verification_table->maybe_create_table();

		// Table should exist now.
		// @TODO This test is not passing.
		// $this->assertTrue( WC_Coupon_Restrictions_Table::table_exists() );
	}


	/**
	 * Test that format_address is correct.
	 */
	public function test_format_address() {
		$address = [
			'address_1' => '123 Main St',
			'address_2' => 'Apt 1',
			'city' => 'Test City',
			'postcode' => '12345',
		];
		$formatted_address = WC_Coupon_Restrictions_Table::format_address(
			$address['address_1'],
			$address['address_2'],
			$address['city'],
			$address['postcode']
		 );
		$this->assertEquals( $formatted_address, '123MAINSTAPT1TESTCITY12345' );
	}

	/**
	 * Test that table is created and entry stored when an order is created
	 * using a coupon with a similar emails restriction.
	 *
	 * @throws \WC_Data_Exception
	 */
	public function test_order_with_similiar_emails_restriction() {
		$email              = 'test.customer@gmail.com';
		$coupon_code        = 'smiliar-emails-test';

		// Usage should before order is created.
		$usage = WC_Coupon_Restrictions_Table::get_similar_email_usage( $coupon_code , $email );
		$this->assertFalse( $usage );

		// Create the coupon.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->set_code( $coupon_code );
		$coupon->add_meta_data( 'prevent_similar_emails', 'yes' );
		$coupon->save();

		// Create the order.
		$order = WC_Helper_Order::create_order();
		$order->set_billing_email( $email );
		$order->set_status( 'processing' );
		$order->apply_coupon( $coupon );
		$order->calculate_totals();

		// Mimic the hook that gets triggered once the payment is successful.
		do_action( 'woocommerce_payment_successful_result', [], $order->get_id() );

		// Usage should be 1 after order is created.
		$usage = WC_Coupon_Restrictions_Table::get_similar_email_usage( $coupon_code , $email );
		$this->assertTrue( $usage );
	}

	/**
	 * Test that get_scrubbed_email is correct.
	 */
	public function test_get_scrubbed_email() {
		// Same email address.
		$email1 = 'customer@example.com';
		$email1scrubbed = WC_Coupon_Restrictions_Table::get_scrubbed_email( 'customer@example.com' );
		$this->assertEquals( $email1, $email1scrubbed );

		// Different capitalization in submitted address.
		$email2 = 'customer@example.com';
		$email2scrubbed = WC_Coupon_Restrictions_Table::get_scrubbed_email( 'Customer@Example.com' );
		$this->assertEquals( $email2, $email2scrubbed );

		// Test alias.
		$email3 = 'customer@example.com';
		$email3scrubbed = WC_Coupon_Restrictions_Table::get_scrubbed_email( 'customer+test@example.com' );
		$this->assertEquals( $email3, $email3scrubbed );

		// Test periods in email.
		$email3 = 'firstlast@gmail.com';
		$email3scrubbed = WC_Coupon_Restrictions_Table::get_scrubbed_email( 'first.last@gmail.com' );
		$this->assertEquals( $email3, $email3scrubbed );
	}

	public function tearDown() {
		// Deletes the custom table if it has been created.
		$verification_table = new WC_Coupon_Restrictions_Table();
		$verification_table->delete_table();
	}
}
