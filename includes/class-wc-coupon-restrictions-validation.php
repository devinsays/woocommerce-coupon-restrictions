<?php
/**
 * WooCommerce Coupon Restrictions - Validation.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    2.0.0
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
	 * @param WC_Coupon $coupon
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
	 * @param WC_Coupon $coupon
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

		$user_roles = (array) $user->roles;

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

		$state_array = self::comma_separated_string_to_array( $state_restriction );

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

		$postcode_array = self::comma_separated_string_to_array( $postcode_restriction );

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
	 * Checks if coupon has enhanced usage restrictions set.
	 *
	 * @param WC_Coupon $coupon
	 * @return boolean
	 */
	public static function has_enhanced_usage_restrictions( $coupon ) {
		$meta = array(
			'prevent_similar_emails',
			'usage_limit_per_shipping_address',
			'usage_limit_per_ip_address',
		);

		foreach ( $meta as $key ) {
			if ( $coupon->get_meta( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert string textarea to normalized array with uppercase.
	 *
	 * @param string $string
	 * @return array $values
	 */
	public static function comma_separated_string_to_array( $string ) {
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
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" is only valid for new customers.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'existing-customer' ) {
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'role-restriction' ) {
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" is not valid with your customer role.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'country' ) {
			$address_type     = self::get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[ $address_type ];
			/* translators: %1$s: Coupon code, %2$s: Address type (shipping or billing) */
			return sprintf( __( 'Sorry, coupon code "%1$s" is not valid in your %2$s country.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		if ( $key === 'state' ) {
			$address_type     = self::get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[ $address_type ];
			/* translators: %1$s: Coupon code, %2$s: Address type (shipping or billing) */
			return sprintf( __( 'Sorry, coupon code "%1$s" is not valid in your %2$s state.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		if ( $key === 'zipcode' ) {
			$address_type     = self::get_address_type_for_restriction( $coupon );
			$i8n_address_type = $i8n_address[ $address_type ];
			/* translators: %1$s: Coupon code, %2$s: Address type (shipping or billing) */
			return sprintf( __( 'Sorry, coupon code "%1$s" is not valid in your %2$s zip code.', 'woocommerce-coupon-restrictions' ), $coupon->get_code(), $i8n_address_type );
		}

		// By default we validate all the enhanced restrictions together and display a single generic validate message.
		// However, unique validation messages per enhanced restriction can be displayed by using the filter.
		$combine_enhanced_restriction_validation = apply_filters( 'wcr_combine_enhanced_restrictions_validation', true );
		if ( $combine_enhanced_restriction_validation && in_array(
			$key,
			array(
				'similar-email-usage',
				'usage-limit-per-shipping-address',
				'usage-limit-per-ip-address',
			),
			true
		) ) {
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" usage limit exceeded.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'similar-email-usage' ) {
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" usage limit exceeded for this email.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'usage-limit-per-shipping-address' ) {
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" usage limit exceeded for this address.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		if ( $key === 'usage-limit-per-ip-address' ) {
			/* translators: %s: Coupon code */
			return sprintf( __( 'Sorry, coupon code "%s" usage limit exceeded for this IP address.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
		}

		// The $key should always find a match.
		// But we'll return a default message just in case.
		/* translators: %s: Coupon code */
		return sprintf( __( 'Sorry, coupon code "%s" is not valid.', 'woocommerce-coupon-restrictions' ), $coupon->get_code() );
	}

	/**
	 * Validates all coupon restrictions for checkout.
	 * Returns array of validation errors, or empty array if all valid.
	 *
	 * @since 2.3.0
	 *
	 * @param array $checkout_data Normalized checkout data with keys:
	 *   - billing_email, billing_country, billing_state, billing_postcode
	 *   - shipping_country, shipping_state, shipping_postcode
	 *   - shipping_address_1, shipping_address_2, shipping_city
	 * @param array $coupon_codes Array of coupon codes to validate
	 * @return array Associative array of errors: [ 'coupon_code' => 'error_message' ]
	 */
	public static function validate_checkout( $checkout_data, $coupon_codes ) {
		$errors = array();

		foreach ( $coupon_codes as $code ) {
			$coupon = new WC_Coupon( $code );

			// Skip invalid coupons.
			$discounts = new WC_Discounts( WC()->cart );
			if ( ! wc_coupons_enabled() || ! $discounts->is_coupon_valid( $coupon ) ) {
				continue;
			}

			$email = strtolower( $checkout_data['billing_email'] ?? '' );

			// New customer restriction.
			if ( ! self::new_customer_restriction( $coupon, $email ) ) {
				$errors[ $code ] = self::message( 'new-customer', $coupon );
				continue;
			}

			// Existing customer restriction.
			if ( ! self::existing_customer_restriction( $coupon, $email ) ) {
				$errors[ $code ] = self::message( 'existing-customer', $coupon );
				continue;
			}

			// Role restriction.
			if ( ! self::role_restriction( $coupon, $email ) ) {
				$errors[ $code ] = self::message( 'role-restriction', $coupon );
				continue;
			}

			// Location restrictions.
			$location_error = self::validate_location_restrictions( $coupon, $checkout_data );
			if ( $location_error ) {
				$errors[ $code ] = $location_error;
				continue;
			}

			// Enhanced usage restrictions.
			if ( self::has_enhanced_usage_restrictions( $coupon ) ) {
				$enhanced_error = self::validate_enhanced_usage_restrictions( $coupon, $code, $checkout_data );
				if ( $enhanced_error ) {
					$errors[ $code ] = $enhanced_error;
					continue;
				}
			}
		}

		return $errors;
	}

	/**
	 * Validates location restrictions for a coupon.
	 *
	 * @since 2.3.0
	 *
	 * @param WC_Coupon $coupon
	 * @param array $checkout_data Normalized checkout data
	 * @return string|null Error message if validation fails, null if valid
	 */
	public static function validate_location_restrictions( $coupon, $checkout_data ) {
		// If location restrictions aren't set, coupon is valid.
		if ( 'yes' !== $coupon->get_meta( 'location_restrictions' ) ) {
			return null;
		}

		// Get the address type used for location restrictions (billing or shipping).
		$address = self::get_address_type_for_restriction( $coupon );

		// Defaults in case no conditions are met.
		$country_validation = true;
		$state_validation   = true;
		$zipcode_validation = true;

		if ( 'shipping' === $address ) {
			if ( isset( $checkout_data['shipping_country'] ) && '' !== $checkout_data['shipping_country'] ) {
				$country_validation = self::country_restriction( $coupon, $checkout_data['shipping_country'] );
			}

			if ( isset( $checkout_data['shipping_state'] ) && '' !== $checkout_data['shipping_state'] ) {
				$state_validation = self::state_restriction( $coupon, $checkout_data['shipping_state'] );
			}

			if ( isset( $checkout_data['shipping_postcode'] ) && '' !== $checkout_data['shipping_postcode'] ) {
				$zipcode_validation = self::postcode_restriction( $coupon, $checkout_data['shipping_postcode'] );
			}
		}

		if ( 'billing' === $address ) {
			if ( isset( $checkout_data['billing_country'] ) && '' !== $checkout_data['billing_country'] ) {
				$country_validation = self::country_restriction( $coupon, $checkout_data['billing_country'] );
			}

			if ( isset( $checkout_data['billing_state'] ) && '' !== $checkout_data['billing_state'] ) {
				$state_validation = self::state_restriction( $coupon, $checkout_data['billing_state'] );
			}

			if ( isset( $checkout_data['billing_postcode'] ) && '' !== $checkout_data['billing_postcode'] ) {
				$zipcode_validation = self::postcode_restriction( $coupon, $checkout_data['billing_postcode'] );
			}
		}

		if ( false === $country_validation ) {
			return self::message( 'country', $coupon );
		}

		if ( false === $state_validation ) {
			return self::message( 'state', $coupon );
		}

		if ( false === $zipcode_validation ) {
			return self::message( 'zipcode', $coupon );
		}

		return null;
	}

	/**
	 * Validates enhanced usage restrictions for a coupon.
	 *
	 * @since 2.3.0
	 *
	 * @param WC_Coupon $coupon
	 * @param string $code Coupon code
	 * @param array $checkout_data Normalized checkout data
	 * @return string|null Error message if validation fails, null if valid
	 */
	public static function validate_enhanced_usage_restrictions( $coupon, $code, $checkout_data ) {
		// Default behavior is to return a generic "usage limit exceeded" message if any of the enhanced restrictions fail.
		$combine_enhanced_restriction_validation = apply_filters( 'wcr_combine_enhanced_restrictions_validation', true );

		// Similar emails restriction.
		$coupon_usage_limit = $coupon->get_usage_limit_per_user();
		if ( $coupon_usage_limit && 'yes' === $coupon->get_meta( 'prevent_similar_emails' ) ) {
			$email       = $checkout_data['billing_email'] ?? '';
			$lookup_code = apply_filters( 'wcr_validate_similar_emails_restriction_lookup_code', $code );
			$count       = WC_Coupon_Restrictions_Table::get_similar_email_usage( $lookup_code, $email );

			if ( $count >= $coupon_usage_limit ) {
				return self::message( 'similar-email-usage', $coupon );
			}
		}

		// Usage limit per shipping address.
		$shipping_limit = $coupon->get_meta( 'usage_limit_per_shipping_address' );
		if ( $shipping_limit ) {
			$lookup_code = apply_filters( 'wcr_validate_usage_limit_per_shipping_address_lookup_code', $code );
			$count       = WC_Coupon_Restrictions_Table::get_shipping_address_usage( $lookup_code, $checkout_data );

			if ( $count >= $shipping_limit ) {
				return self::message( 'usage-limit-per-shipping-address', $coupon );
			}
		}

		// Usage limit per IP address.
		$ip_limit = $coupon->get_meta( 'usage_limit_per_ip_address' );
		if ( $ip_limit ) {
			$lookup_code = apply_filters( 'wcr_validate_usage_limit_per_ip_address_lookup_code', $code );
			$count       = WC_Coupon_Restrictions_Table::get_ip_address_usage( $lookup_code );

			if ( $count >= $ip_limit ) {
				return self::message( 'usage-limit-per-ip-address', $coupon );
			}
		}

		return null;
	}
}
