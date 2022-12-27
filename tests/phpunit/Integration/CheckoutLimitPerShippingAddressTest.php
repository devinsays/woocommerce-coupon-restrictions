<?php
namespace WooCommerce_Coupon_Restrictions\Tests\Integration;

use WP_UnitTestCase;
use WC_Helper_Coupon;
use WC_Helper_Order;
use WC_Coupon_Restrictions_Table;
use WC_Coupon_Restrictions_Validation_Checkout;

class CheckoutLimitPerShippingAddressTest extends WP_UnitTestCase {
	/** @var WC_Coupon */
	public $coupon;

	/** @var WC_Order */
	public $order;

	public $validation;

	public function setUp() {
		// Create coupon with usage limit and similar emails restriction.
		$coupon = WC_Helper_Coupon::create_coupon();
		$coupon->update_meta_data( 'usage_limit_per_shipping_address', 1 );
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

		// Custom table.
		WC_Coupon_Restrictions_Table::maybe_create_table();
	}

	/**
	 * Validate per address.
	 */
	public function test_usage_limit_per_shipping_address() {
		$address = [
			'shipping_address_1' => '123 Main St',
			'shipping_address_2' => 'Apt 1',
			'shipping_city' => 'Test City',
			'shipping_postcode' => '12345',
		];

		$coupon = $this->coupon;
		$order = $this->order;

		$order->set_shipping_address_1( $address['shipping_address_1'] );
		$order->set_shipping_address_2( $address['shipping_address_2'] );
		$order->set_shipping_city( $address['shipping_city'] );
		$order->set_shipping_postcode( $address['shipping_postcode'] );
		$order->save();

		// Mock post data.
		$posted = $address;

		// Run the post checkout validation with new customer.
		WC()->cart->apply_coupon( $coupon->get_code() );
		$this->validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon is still applied.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );

		// Mimic the hook that gets triggered once the order is created.
		do_action( 'woocommerce_pre_payment_complete', $order->get_id() );

		// Run the post checkout validation.
		WC()->cart->apply_coupon( $coupon->get_code() );
		$this->validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon has been removed.
		$this->assertEquals( 0, count( WC()->cart->get_applied_coupons() ) );

		// Update the coupon to permit 2 usages of same shipping address.
		$coupon->update_meta_data( 'usage_limit_per_shipping_address', 2 );
		$coupon->save();

		// Run the post checkout validation.
		WC()->cart->apply_coupon( $coupon->get_code() );
		$this->validation->validate_coupons_after_checkout( $posted );

		// Verifies coupon is still applied.
		$this->assertEquals( 1, count( WC()->cart->get_applied_coupons() ) );
	}

	public function tearDown() {
		$this->coupon->delete();
		$this->order->delete();

		// Deletes the custom table if it has been created.
		WC_Coupon_Restrictions_Table::delete_table();
	}
}
