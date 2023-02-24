<?php
/**
 * WooCommerce Coupon Restrictions - CLI.
 *
 * Command line interface for coupon restrictions.
 *
 * Usage: wp wcr refresh_enhanced_usage_limits_table
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_CLI {
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

		if ( ! \WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
			WP_CLI::error( 'Coupon does not have any enhanced usage restrictions set.' );
			exit;
		}

		WP_CLI::success( "Coupon has been used $usage_count times." );

		$orders = $this->get_orders_with_coupon_code( $code );
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			WC_Coupon_Restrictions_Table::maybe_add_record( $order_id );
			WP_CLI::log( "Record added for order: $order_id" );
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
	public static function get_orders_with_coupon_code( $code ) {
		$coupon = new WC_Coupon( $code );
		$date   = $coupon->get_date_created()->date( 'Y-m-d' );

		// Query is restricted to orders created after the coupon was created.
		// This limitation makes the query much more performant (less orders to query).
		// But there can be rare edge cases where a coupon was applied to an earlier order.
		$args = array(
			'date_created' => '>=' . $date,
			'meta_query'   => array(
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
