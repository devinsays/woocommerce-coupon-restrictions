<?php
/**
 * WooCommerce Coupon Restrictions - CLI.
 *
 * Command line interface for coupon restrictions.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_CLI {
	// Usage: wp wcr refresh_enhanced_usage_limits_table
	public function refresh_enhanced_usage_limits_table() {
		$this->explainer_text();
		$code = $this->ask( 'Coupon code to update data for:' );

		$coupon = new WC_Coupon( $code );
		if ( ! $coupon ) {
			WP_CLI::error( 'Coupon not found.' );
			exit;
		}

		$usage_count = $coupon->get_usage_count();
		if ( ! $usage_count ) {
			WP_CLI::error( 'Coupon has not been used for any orders.' );
			exit;
		}

		WP_CLI::success( "Coupon has been used $usage_count times." );

		$orders = $this->get_orders_with_coupon_code( $code );
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			WP_CLI::log( "Order ID: $order_id" );
		}
	}

	public function explainer_text() {
		WP_CLI::log( '' );
		WP_CLI::log( 'This command updates the coupon restrictions verification table.' );
		WP_CLI::log( 'This can be run if enhanced usage limits have been added to an existing coupon.' );
		WP_CLI::log( 'After the update, enhanced usage restriction verifications will work for future checkouts.' );
		WP_CLI::log( '' );
	}

	/**
	 * Ask a question and returns input.
	 *
	 * @param string $question
	 * @param bool   $case_sensitive
	 *
	 * @return string
	 */
	public function ask( $question, $case_sensitive = false ) {
		fwrite( STDOUT, trim( $question ) . ' ' );
		$answer = trim( fgets( STDIN ) );
		$answer = $case_sensitive ? $answer : strtolower( $answer );
		return $answer;
	}

	/**
	 * Returns an array of orders that used a specific coupon code.
	 *
	 * @param string $code
	 *
	 * @return array
	 */
	public function get_orders_with_coupon_code( $code ) {
		$args = array(
			'meta_query' => array(
				array(
					'key'     => '_coupon_code',
					'value'   => $code,
					'compare' => '=',
				),
			),
		);

		$orders = new WC_Order_Query( $args );
		return $orders->get_orders();
	}

}

WP_CLI::add_command( 'wcr', 'WC_Coupon_Restrictions_CLI' );
