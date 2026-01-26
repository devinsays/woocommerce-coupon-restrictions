<?php
/**
 * WooCommerce Coupon Restrictions - Block Checkout Validation.
 *
 * Validates coupon restrictions during block-based checkout.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.3.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Validation_Block_Checkout {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Validates coupons during block checkout order processing.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'validate_coupons' ), 10 );
	}

	/**
	 * Validates coupon restrictions when block checkout order is processed.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When validation fails.
	 */
	public function validate_coupons( $order ) {
		$coupon_codes = $order->get_coupon_codes();

		if ( empty( $coupon_codes ) ) {
			return;
		}

		$checkout_data = $this->get_checkout_data_from_order( $order );
		$errors        = WC_Coupon_Restrictions_Validation::validate_checkout( $checkout_data, $coupon_codes );

		if ( ! empty( $errors ) ) {
			// Get first error (block checkout shows one error at a time).
			$message = reset( $errors );
			$code    = key( $errors );
			$coupon  = new WC_Coupon( $code );

			// Apply filter for custom message modification.
			$message = apply_filters( 'woocommerce_coupon_restrictions_removed_message_with_code', $message, $code, $coupon );

			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'woocommerce_coupon_restrictions_validation_failed',
				esc_html( $message ),
				400
			);
		}
	}

	/**
	 * Extracts checkout data from the order object.
	 *
	 * @param WC_Order $order The order object.
	 * @return array Normalized checkout data for validation.
	 */
	private function get_checkout_data_from_order( $order ) {
		return array(
			'billing_email'      => $order->get_billing_email(),
			'billing_country'    => $order->get_billing_country(),
			'billing_state'      => $order->get_billing_state(),
			'billing_postcode'   => $order->get_billing_postcode(),
			'shipping_country'   => $order->get_shipping_country(),
			'shipping_state'     => $order->get_shipping_state(),
			'shipping_postcode'  => $order->get_shipping_postcode(),
			'shipping_address_1' => $order->get_shipping_address_1(),
			'shipping_address_2' => $order->get_shipping_address_2(),
			'shipping_city'      => $order->get_shipping_city(),
		);
	}
}
