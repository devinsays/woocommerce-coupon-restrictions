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

		if ( ! WC_Coupon_Restrictions_Validation::has_enhanced_usage_restrictions( $coupon ) ) {
			WP_CLI::error( __( 'Coupon does not have any enhanced usage restrictions set.', 'woocommerce-coupon-restrictions' ) );
			exit;
		}

		/* translators: %s: usage count of coupon */
		WP_CLI::log( sprintf( __( 'Coupon has been used %d times.', 'woocommerce-coupon-restrictions' ), $usage_count ) );
		WP_CLI::log( '' );

		// This allows us to continue processing an update if it was interrupted.
		$processed_key     = 'wcr_processing_' . $code;
		$processed_value   = get_transient( $processed_key );
		$offset_key        = 'wcr_offset_' . $code;
		$offset_value      = get_transient( $offset_key );
		$last_processed_id = 0;
		$offset            = 0;
		if ( $processed_value ) {
			/* translators: %s: last order processed for WP CLI command. */
			WP_CLI::warning( sprintf( __( 'An update has already been started. The last order id processed was: %d.', 'woocommerce-coupon-restrictions' ), $processed_value ) );
			$answer = $this->ask( sprintf( __( 'Would you like to continue processing from order id %d? [yes/no]', 'woocommerce-coupon-restrictions' ), $processed_value ) );
			if ( 'yes' === trim( $answer ) || 'y' === trim( $answer ) ) {
				$last_processed_id = $processed_value;
				$offset            = $offset_value;
			} else {
				WP_CLI::log( __( 'Data update will be restarted. All order data for coupon will be refreshed.', 'woocommerce-coupon-restrictions' ) );
			}
			WP_CLI::log( '' );
		}

		// Deletes all existing records for the coupon code so table can be refreshed.
		// Only delete the records if we are starting from the beginning.
		if ( $last_processed_id === 0 ) {
			WC_Coupon_Restrictions_Table::delete_records_for_coupon( $code );
		}

		$limit = 100;
		$count = 0;
		while ( true ) {
			$ids = WC_Coupon_Restrictions_Table::get_orders_with_coupon_code( $code, $limit, $offset );
			if ( ! $ids && $count === 0 ) {
				WP_CLI::warning( __( 'No orders available to process.', 'woocommerce-coupon-restrictions' ) );
				break;
			}

			foreach ( $ids as $order_id ) {
				$result = WC_Coupon_Restrictions_Table::maybe_add_record( $order_id );
				if ( $result ) {
					WP_CLI::log( "Record added for order: $order_id" );
				}

				// Update the counter for the loop.
				$last_processed_id = $order_id;
				$offset++;
				$count++;
				set_transient( $processed_key, $last_processed_id, HOUR_IN_SECONDS );
				set_transient( $offset_key, $offset, HOUR_IN_SECONDS );
			}

			if ( count( $ids ) < $limit ) {
				WP_CLI::log( '' );
				WP_CLI::success( __( 'Finished updating verification table.', 'woocommerce-coupon-restrictions' ) );
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
