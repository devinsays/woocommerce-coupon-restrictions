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

		$order_id = 0;
		$limit    = 100;
		$count    = 0;
		$date     = $coupon->get_date_created()->date( 'Y-m-d' );

		while ( true ) {
			WP_CLI::log( sprintf( __( 'Querying order batch starting at order id: %d', 'woocommerce-coupon-restrictions' ), $order_id ) );
			$ids = self::get_order_batch( $limit, $offset, $date );
			if ( ! $ids && $count === 0 ) {
				WP_CLI::warning( __( 'No orders available to process.', 'woocommerce-coupon-restrictions' ) );
				break;
			}

			foreach ( $ids as $order_id ) {
				self::maybe_add_record( $order_id, $code );

				// Updates the counters
				$last_processed_id = $order_id;
				$offset++;
				$count++;
			}

			// We'll update the transient after each batch, so we can continue processing if interrupted.
			set_transient( $processed_key, $last_processed_id, HOUR_IN_SECONDS );
			set_transient( $offset_key, $offset, HOUR_IN_SECONDS );

			if ( count( $ids ) < $limit ) {
				WP_CLI::log( '' );
				WP_CLI::success( __( 'Finished updating verification table.', 'woocommerce-coupon-restrictions' ) );
				break;
			}
		}
	}

	/**
	 * Checks if the order has the coupon code being checked.
	 * If so, it checks if the order already exists in the table.
	 * If not, it adds the record.
	 *
	 * @param int $order_id
	 * @param string   $code
	 *
	 * @return string
	 */
	public function maybe_add_record( $order_id, $code ) {
		$order   = wc_get_order( $order_id );
		$coupons = $order->get_coupon_codes();

		if ( in_array( $code, $coupons, true ) ) {
			$records = WC_Coupon_Restrictions_Table::get_records_for_order_id( $order_id );
			if ( $records ) {
				WP_CLI::log( "Record already exists for order: $order_id" );
			} else {
				$result = WC_Coupon_Restrictions_Table::maybe_add_record( $order_id );
				if ( $result ) {
					WP_CLI::log( "Record added for order: $order_id" );
				}
			}
		}
	}

	/**
	 * Returns an array of orders created after a specific date.
	 *
	 * @param int $limit Limit query to this many orders.
	 * @param int $offset Offset query by this many orders.
	 * @param string $date Date to start querying from.
	 *
	 * @return array
	 */
	public static function get_order_batch( $limit = 100, $offset = 0, $date = '' ) {
		$limit = intval( $limit ) ? intval( $limit ) : 100;

		$args = array(
			'date_created' => '>=' . $date,
			'orderby'      => 'ID',
			'order'        => 'ASC',
			'limit'        => intval( $limit ),
			'offset'       => intval( $offset ),
			'return'       => 'ids',
		);

		$orders = new WC_Order_Query( $args );
		return $orders->get_orders();
	}

	/**
	 * Explainer text to show when the command is run.
	 *
	 * @return void
	 */
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
