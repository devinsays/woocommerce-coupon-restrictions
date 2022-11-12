<?php
/**
 * WooCommerce Coupon Restrictions - Validation.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    1.9.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Validation {

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
	 * @param WC_Coupon $coupon
	 * @param string $email
	 * @return boolean
	 */
	public static function new_customer_restriction( $coupon, $email ) {
		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

		if ( 'new' === $customer_restriction_type ) {
			// If customer has purchases, coupon is not valid.
			if ( self::is_returning_customer( $email ) ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Returns whether coupon address restriction applies to 'shipping' or 'billing'.
	 *
	 * @param WC_Coupon $coupon
	 * @return string
	 */
	public static function get_address_type_for_restriction( $coupon ) {
		$address_type = $coupon->get_meta( 'address_for_location_restrictions', true );
		if ( 'billing' === $address_type ) {
			return 'billing';
		}

		return 'shipping';
	}

		/**
	 * Validates existing customer restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public static function existing_customer_restriction( $coupon, $email ) {
		$customer_restriction_type = $coupon->get_meta( 'customer_restriction_type', true );

		// If customer has purchases, coupon is valid.
		if ( 'existing' === $customer_restriction_type ) {
			if ( ! self::is_returning_customer( $email ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates role restrictions.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param object $coupon
	 * @param string $email
	 * @return boolean
	 */
	public static function role_restriction( $coupon, $email ) {
		// Returns an array with all the restricted roles.
		$restricted_roles = $coupon->get_meta( 'role_restriction', true );

		// If there are no restricted roles, coupon is valid.
		if ( ! $restricted_roles ) {
			return true;
		}

		// Checks if there is an account associated with the $email.
		$user = get_user_by( 'email', $email );

		// If user account does not exist and guest role is permitted, return true.
		if ( ! $user && in_array( 'woocommerce-coupon-restrictions-guest', $restricted_roles ) ) {
			return true;
		}

		// If user account does not exist and guest role not permitted, coupon is invalid.
		if ( ! $user ) {
			return false;
		}

		$user_meta  = get_userdata( $user->ID );
		$user_roles = $user_meta->roles;

		// If any the user roles do not match the restricted roles, coupon is invalid.
		if ( ! array_intersect( $user_roles, $restricted_roles ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates state restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $state
	 * @return boolean
	 */
	public static function state_restriction( $coupon, $state ) {
		// Get the allowed states from coupon meta.
		$state_restriction = $coupon->get_meta( 'state_restriction', true );

		// If $state_restriction has not been set, coupon remains valid.
		if ( ! $state_restriction ) {
			return true;
		}

		$state_array = self::comma_seperated_string_to_array( $state_restriction );

		if ( ! in_array( strtoupper( $state ), $state_array ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates postcode restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $postcode
	 * @return boolean
	 */
	public static function postcode_restriction( $coupon, $postcode ) {
		// Get the allowed postcodes from coupon meta.
		$postcode_restriction = $coupon->get_meta( 'postcode_restriction', true );

		// If $postcode_restriction has not been set, coupon remains valid.
		if ( ! $postcode_restriction ) {
			return true;
		}

		$postcode_array = self::comma_seperated_string_to_array( $postcode_restriction );

		// Wildcard check.
		if ( strpos( $postcode_restriction, '*' ) !== false ) {
			foreach ( $postcode_array as $restricted_postcode ) {
				if ( strpos( $restricted_postcode, '*' ) !== false ) {
					if ( fnmatch( $restricted_postcode, $postcode ) ) {
						return true;
					}
				}
			}
		}

		// Standard check.
		if ( ! in_array( strtoupper( $postcode ), $postcode_array ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates country restriction.
	 * Returns true if customer meets $coupon criteria.
	 *
	 * @param WC_Coupon $coupon
	 * @param string $country
	 * @return boolean
	 */
	public static function country_restriction( $coupon, $country ) {
		// Get the allowed countries from coupon meta.
		$allowed_countries = $coupon->get_meta( 'country_restriction', true );

		// If $allowed_countries has not been set, coupon remains valid.
		if ( ! $allowed_countries ) {
			return true;
		}

		// If the customer country is not in allowed countries, return false.
		if ( ! in_array( $country, $allowed_countries ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert string textarea to normalized array with uppercase.
	 *
	 * @param string $string
	 * @return array $values
	 */
	public static function comma_seperated_string_to_array( $string ) {
		// Converts string to array.
		$values = explode( ',', $string );
		$values = array_map( 'trim', $values );

		// Converts values to uppercase so comparison is not case sensitive.
		$values = array_map( 'strtoupper', $values );

		return $values;
	}

	/**
	 * Returns the validation message.
	 *
	 * @param string $key
	 * @param WC_Coupon $coupon
	 * @return string
	 */
	public static function message( $key, $coupon ) {
		$i8n_address = array(
			'shipping' => __( 'shipping', 'woocommerce-coupon-restrictions' ),
			'billing'  => __( 'billing', 'woocommerce-coupon-restrictions' ),
		);

		if ( $key === 'new-customer' ) {
			return sprintf( __( 'Sorry, coupon code "%s" is only valid for new customers.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'existing-customer' ) {
			return sprintf( __( 'Sorry, coupon code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'role-restriction' ) {
			return sprintf( __( 'Sorry, coupon code "%s" is not valid with your customer role.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'country' ) {
			$address_type     = self::get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[ $address_type ];
			return sprintf( __( 'Sorry, coupon code "%1$s" is not valid in your %2$s country.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		if ( $key === 'state' ) {
			$address_type     = self::get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[ $address_type ];
			return sprintf( __( 'Sorry, coupon code "%1$s" is not valid in your %2$s state.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		if ( $key === 'zipcode' ) {
			$address_type     = self::get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[ $address_type ];
			return sprintf( __( 'Sorry, coupon code "%1$s" is not valid in your %2$s zip code.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		// The $key should always find a match.
		// But we'll return a default message just in case.
		return sprintf( __( 'Sorry, coupon code "%s" is not valid.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
	}

}
