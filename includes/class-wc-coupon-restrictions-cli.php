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
		$code = $this->ask( __( 'Coupon code to update data for:', 'woocommerce-coupon-restrictions' ) );

		$coupon = new WC_Coupon( $code );
		if ( ! $coupon ) {
			WP_CLI::error( __( 'Coupon not found.', 'woocommerce-coupon-restrictions' ) );
			exit;
		}

		$usage_count = $coupon->get_usage_count();
		if ( ! $usage_count ) {
			WP_CLI::error( __( 'Coupon has not been used for any orders.', 'woocommerce-coupon-restrictions' ) );
			exit;
		}

		if ( ! \WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
			WP_CLI::error( __( 'Coupon does not have any enhanced usage restrictions set.', 'woocommerce-coupon-restrictions' ) );
			exit;
		}

		/* translators: %s: usage count of coupon */
		WP_CLI::success( sprintf( __( 'Coupon has been used %d times.', 'woocommerce-coupon-restrictions' ), $usage_count ) );

		$orders = \WC_Coupon_Restrictions_Table::get_orders_with_coupon_code( $code );

		if ( ! $orders ) {
			WP_CLI::error( __( 'Could not query any orders for coupon.', 'woocommerce-coupon-restrictions' ) );
		}

		// Deletes all existing records for the coupon code so table can be refreshed.
		\WC_Coupon_Restrictions_Table::delete_records_for_coupon( $code );

		$limit             = 100;
		$last_processed_id = 0;
		while ( true ) {
			$orders = \WC_Coupon_Restrictions_Table::get_orders_with_coupon_code( $code, $last_processed_id, $limit );
			\WC_Coupon_Restrictions_Table::bulk_add_records( $orders );

			if ( count( $orders ) < $limit ) {
				WP_CLI::log( __( 'Finished updating verification table.', 'woocommerce-coupon-restrictions' ) );
				break;
			}
		}
	}

	public function explainer_text() {
		WP_CLI::log( '' );
		WP_CLI::log( __( 'This command updates the coupon restrictions verification table.', 'woocommerce-coupon-restrictions' ) );
		WP_CLI::log( __( 'This can be run if enhanced usage limits have been added to an existing coupon.', 'woocommerce-coupon-restrictions' ) );
		WP_CLI::log( __( 'After the update, enhanced usage restriction verifications will work for future checkouts.', 'woocommerce-coupon-restrictions' ) );
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

}

WP_CLI::add_command( 'wcr', 'WC_Coupon_Restrictions_CLI' );
