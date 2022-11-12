<?php
/**
 * WooCommerce Coupon Restrictions - Helpers.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    1.9.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Helpers {

	/**
	 * Checks if e-mail address has been used previously for a purchase.
	 *
	 * @param string $email of customer
	 * @return boolean
	 */
	public static function is_returning_customer( $email ) {

		// Checks if there is an account associated with the $email.
		$user = get_user_by( 'email', $email );

		// If there is a user account, we can check if customer is_paying_customer.
		if ( $user ) {
			$customer = new WC_Customer( $user->ID );
			if ( $customer->get_is_paying_customer() ) {
				return true;
			}
		}

		// If there isn't a user account or user account ! is_paying_customer
		// we can check against previous guest orders.
		// Store admin must opt-in to this because of performance concerns.
		$option = get_option( 'coupon_restrictions_customer_query', 'accounts' );
		if ( 'accounts-orders' === $option ) {

			// This query can be slow on sites with a lot of orders.
			// @todo Check if 'customer' => '' improves performance.
			$customer_orders = wc_get_orders(
				array(
					'status' => array( 'wc-processing', 'wc-completed' ),
					'email'  => $email,
					'limit'  => 1,
					'return' => 'ids',
				)
			);

			// If there is at least one order, customer is returning.
			if ( 1 === count( $customer_orders ) ) {
				return true;
			}
		}

		// If we've gotten to this point, the customer must be new.
		return false;
	}

	/**
	 * Validates new customer restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public static function validate_new_customer_restriction( $coupon, $email ) {
		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

		if ( 'new' === $customer_restriction_type ) {
			// If customer has purchases, coupon is not valid.
			if ( self::is_returning_customer( $email ) ) {
				return false;
			}
		}

		return true;
	}
}
