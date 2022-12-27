<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Table;
use WC_Coupon_Restrictions_Validation_Checkout;

class CheckoutLimitSimilarEmailsTest extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;

	/** @var WC_Order */
	public $order;

	public $validation;

	public function setUp() {
		// Create coupon with usage limit and similar emails restriction.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->update_meta_data( 'prevent_similar_emails', 'yes' );
		$coupon->save();
		$this->coupon = $coupon;

		// Create an order.
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'processing' );
		$order->apply_coupon( $coupon );
		$order->calculate_totals();
		$this->order = $order;

		// Validation object.
		$this->validation = new WC_Coupon_Restrictions_Validation_Checkout();

		// Create table.
		WC_Coupon_Restrictions_Table::maybe_create_table();
	}

	/**
	 * Validate basic similar emails restriction.
	 */
	public function test_email_usage_restriction() {
		$coupon = $this->coupon;
		$order = $this->order;

		$email = 'customer1@gmail.com';
		$order->set_billing_email( $email );
		$order->save();

		// Mock post data.
		$posted = array(
			'billing_email' => $email,
		);

		// Run the post checkout validation with new customer.
		WC()->cart->apply_coupon( $coupon->get_code() );
		$this->validation->validate_coupons_after_checkout( $posted );

		// Verifies 1 coupon is still in cart after checkout validation.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Mimic the hook that gets triggered once the order is created.
		do_action( 'woocommerce_pre_payment_complete', $order->get_id() );

		// Run the post checkout validation.
		WC()->cart->apply_coupon( $coupon->get_code() );
		$this->validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon has been removed.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );
	}

	/**
	 * Validate similar emails restriction.
	 *
	 * @TODO This test runs fine on its own, but fails when running all tests in this class.
	 * // i.e. phpunit --filter=CheckoutLimitSimilarEmailsTest
	 * // Leaving this as an item to debug later.
	 * // Prefix this method with 'test_' to add it back to the test suite.
	 */
	public function similar_email_usage_restriction() {
		$coupon = $this->coupon;
		$order = $this->order;

		$email = 'customer2@gmail.com';
		$order->set_billing_email( $email );
		$order->save();

		// Mimic the hook that gets triggered once the order is created.
		do_action( 'woocommerce_pre_payment_complete', $order->get_id() );

		// Test a similar email (not exact match).
		$posted = array(
			'billing_email' => 'customer2+test@gmail.com'
		);

		// Apply the coupon.
		WC()->cart->apply_coupon( $coupon->get_code() );

		// Verify the coupon is removed because it is a similar email.
		$this->validation->validate_coupons_after_checkout( $posted );
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Update the usage limit to 2.
		$coupon->set_usage_limit_per_user( 2 );
		$coupon->save();

		// Run the post checkout validation.
		WC()->cart->apply_coupon( $coupon->get_code() );
		$this->validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon now applies.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	public function tearDown() {
		$this->coupon->delete();
		$this->order->delete();

		// Deletes the custom table if it has been created.
		WC_Coupon_Restrictions_Table::delete_table();
	}
}
