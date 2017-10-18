<?php
/**
 * Plugin Name: WooCommerce Coupon Restrictions
 * Plugin URI: http://github.com/devinsays/woocommerce-coupon-restrictions
 * Description: Allows for additional coupon restrictions. Coupons can be restricted to new customers, existing customers, or by country.
 * Version: 1.3.0
 * Author: DevPress
 * Author URI: https://devpress.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-coupon-restrictions
 * Domain Path: /languages
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WC_Coupon_Restrictions' ) ) :

	class WC_Coupon_Restrictions {

		/**
		* Construct the plugin.
		*/
		public function __construct() {

			// Load translations
			load_plugin_textdomain( 'woocommerce-coupon-restrictions', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );

			// Fire up the plugin!
			add_action( 'plugins_loaded', array( $this, 'init' ) );

		}

		/**
		* Initialize the plugin.
		*/
		public function init() {

			// Adds metabox to usage restriction fields
			add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'customer_restrictions' ) );
			add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'location_restrictions' ) );

			// Saves the metabox
			add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save' ) );

			// Validates coupons before checkout if customer is logged in
			add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupons' ), 10, 2 );

			// Validates coupons again during checkout validation
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_customer_coupons' ), 1 );

		}

		/**
		 * Adds "new customer" and "existing customer" restriction checkboxes
		 *
		 * @return void
		 */
		public function customer_restrictions() {

			echo '<div class="options_group">';

			woocommerce_wp_checkbox(
				array(
					'id' => 'new_customers_only',
					'label' => __( 'New customers only', 'woocommerce-coupon-restrictions' ),
					'description' => __( 'Verifies customer e-mail address <b>has not</b> been used previously.', 'woocommerce-coupon-restrictions' )
				)
			);

			woocommerce_wp_checkbox(
				array(
					'id' => 'existing_customers_only',
					'label' => __( 'Existing customers only', 'woocommerce-coupon-restrictions' ),
					'description' => __( 'Verifies customer e-mail address has been used previously.', 'woocommerce-coupon-restrictions' )
				)
			);

			echo '</div>';

		}

		/**
		 * Adds country restriction
		 *
		 * @return void
		 */
		public function location_restrictions() {

			global $post;

			$id = 'shipping_country_restriction';
			$title = __( 'Limit Countries (Shipping)', 'woocommerce-coupon-restrictions' );
			$values = get_post_meta( $post->ID, $id, true );
			$description = '';

			echo '<div class="options_group">';
			echo '<p class="form-field ' . $id . '_only_field">';

				$selections = array();
				if ( ! empty( $values ) ) {
					$selections = $values;
				}
				$countries = WC()->countries->countries;
				asort( $countries );
				?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $title ); ?>
				</label>
				<select multiple="multiple" name="<?php echo esc_attr( $id ); ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose countries&hellip;', 'woocommerce-coupon-restrictions' ); ?>" aria-label="<?php esc_attr_e( 'Country', 'woocommerce-coupon-restrictions' ) ?>" class="wc-enhanced-select">
					<?php
						if ( ! empty( $countries ) ) {
							foreach ( $countries as $key => $val ) {
								echo '<option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $selections ), true, false ) . '>' . $val . '</option>';
							}
						}
					?>
				</select>

				<?php
			echo '</p>';
			echo '</div>';
		}

		/**
		 * Saves post meta for "new customer" restriction
		 *
		 * @return void
		 */
		public function coupon_options_save( $post_id ) {

			// Sanitize meta
			$new_customers_only = isset( $_POST['new_customers_only'] ) ? 'yes' : 'no';
			$existing_customers_only = isset( $_POST['existing_customers_only'] ) ? 'yes' : 'no';
			$shipping_country_restriction_select = isset( $_POST['shipping_country_restriction'] ) ? $_POST['shipping_country_restriction'] : array();
			$shipping_country_restriction = array_filter( array_map( 'wc_clean', $shipping_country_restriction_select ) );

			// Save meta
			update_post_meta( $post_id, 'new_customers_only', $new_customers_only );
			update_post_meta( $post_id, 'existing_customers_only', $existing_customers_only );
			update_post_meta( $post_id, 'shipping_country_restriction', $shipping_country_restriction );

		}

		/**
		 * Validates coupon when added (if possible due to log in state)
		 *
		 * @return void
		 */
		public function validate_coupons( $valid, $coupon ) {

			// If coupon already marked invalid, no sense in moving forward.
			if ( ! $valid ) {
				return $valid;
			}

			// Can't validate e-mail at this point unless customer is logged in.
			if ( ! is_user_logged_in() ) {
				return $valid;
			}

			// Validate new customer restriction
			$new_customers_restriction = $coupon->get_meta( 'new_customers_only', true );
			if ( 'yes' == $new_customers_restriction ) {
				$valid = $this->validate_new_customer_coupon();
			}

			// Validate existing customer restriction
			$existing_customers_restriction = $coupon->get_meta( 'existing_customers_only', true );
			if ( 'yes' == $existing_customers_restriction ) {
				$valid = $this->validate_existing_customer_coupon();
			}

			return $valid;

		}

		/**
		 * If user is logged in, validates new customer coupon
		 *
		 * @return void
		 */
		public function validate_new_customer_coupon() {

			// If current customer is an existing customer, return false
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( $customer->get_is_paying_customer() ) {
				add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_new_customer_restriction' ), 10, 2 );
				return false;
			}

			return true;
		}

		/**
		 * If user is logged in, validates existing cutomer coupon
		 *
		 * @return void
		 */
		public function validate_existing_customer_coupon() {

			// If current customer is not an existing customer, return false
			$current_user = wp_get_current_user();
			$customer = new WC_Customer( $current_user->ID );

			if ( ! $customer->get_is_paying_customer() ) {
				add_filter( 'woocommerce_coupon_error', array( $this, 'validation_message_existing_customer_restriction' ), 10, 2 );
				return false;
			}

			return true;
		}

		/**
		 * Applies new customer coupon error message
		 *
		 * @return $err error message
		 */
		function validation_message_new_customer_restriction( $err, $err_code ) {

			// Alter the validation message if coupon has been removed
			if ( 100 == $err_code ) {
				// Validation message
				$msg = __( 'Coupon removed. This coupon is only valid for new customers.', 'woocommerce-coupon-restrictions' );
				$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
			}

			// Return validation message
			return $err;
		}

		/**
		 * Applies existing customer coupon error message
		 *
		 * @return $err error message
		 */
		function validation_message_existing_customer_restriction( $err, $err_code ) {

			// Alter the validation message if coupon has been removed
			if ( 100 == $err_code ) {
				// Validation message
				$msg = __( 'Coupon removed. This coupon is only valid for existing customers.', 'woocommerce-coupon-restrictions' );
				$err = apply_filters( 'woocommerce-coupon-restrictions-removed-message', $msg );
			}

			// Return validation message
			return $err;
		}

		/**
		 * Check user coupons (now that we have billing email). If a coupon is invalid, add an error.
		 *
		 * @param array $posted
		 */
		public function check_customer_coupons( $posted ) {

			if ( ! empty( WC()->cart->applied_coupons ) ) {

				foreach ( WC()->cart->applied_coupons as $code ) {

					$coupon = new WC_Coupon( $code );

					if ( $coupon->is_valid() ) {

						// Check if coupon is restricted to new customers.
						$new_customers_restriction = $coupon->get_meta( 'new_customers_only', true );

						if ( 'yes' === $new_customers_restriction ) {
							$this->check_new_customer_coupon_checkout( $coupon, $code );
						}

						// Check if coupon is restricted to existing customers.
						$existing_customers_restriction = $coupon->get_meta( 'existing_customers_only', true );
						if ( 'yes' === $existing_customers_restriction ) {
							$this->check_existing_customer_coupon_checkout( $coupon, $code );
						}

						// Check country restrictions
						$shipping_country_restriction = $coupon->get_meta( 'shipping_country_restriction', true );
						if ( ! empty( $shipping_country_restriction ) ) {
							$this->check_shipping_country_restriction_checkout( $coupon, $code );
						}

					}
				}
			}
		}

		/**
		 * Validates new customer coupon on checkout
		 *
		 * @param object $coupon
		 * @param string $code
		 */
		public function check_new_customer_coupon_checkout( $coupon, $code ) {

			// Validation message
			$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for new customers.', 'woocommerce-coupon-restrictions' ), $code );

			// Check if order is for returning customer
			if ( is_user_logged_in() ) {

				// If user is logged in, we can check for paying_customer meta.
				$current_user = wp_get_current_user();
				$customer = new WC_Customer( $current_user->ID );

				if ( $customer->get_is_paying_customer() ) {
					$this->remove_coupon( $coupon, $code, $msg );
				}

			} else {

				// If user is not logged in, we can check against previous orders.
				$email = strtolower( $_POST['billing_email'] );
				if ( $this->is_returning_customer( $email ) ) {
					$this->remove_coupon( $coupon, $code, $msg );
				}

			}
		}

		/**
		 * Validates existing customer coupon on checkout
		 *
		 * @param object $coupon
		 * @param string $code
		 */
		public function check_existing_customer_coupon_checkout( $coupon, $code ) {

			// Validation message
			$msg = sprintf( __( 'Coupon removed. Code "%s" is only valid for existing customers.', 'woocommerce-coupon-restrictions' ), $code );

			// Check if order is for returning customer
			if ( is_user_logged_in() ) {

				// If user is logged in, we can check for paying_customer meta.
				$current_user = wp_get_current_user();
				$customer = new WC_Customer( $current_user->ID );

				if ( ! $customer->get_is_paying_customer() ) {
					$this->remove_coupon( $coupon, $code, $msg );
				}

			} else {

				// If user is not logged in, we can check against previous orders.
				$email = strtolower( $_POST['billing_email'] );
				if ( ! $this->is_returning_customer( $email ) ) {
					$this->remove_coupon( $coupon, $code, $msg );
				}

			}
		}

		/**
		 * Validates country restrictions on checkout
		 *
		 * @param object $coupon
		 * @param string $code
		 */
		public function check_shipping_country_restriction_checkout( $coupon, $code ) {

			// Validation message
			$msg = sprintf( __( 'Coupon removed. Code "%s" is not valid in your shipping country.', 'woocommerce-coupon-restrictions' ), $code );

			if ( isset( $_POST['shipping_country'] ) ) {
				// Get shipping country if it exists
				$country = esc_textarea( $_POST['shipping_country'] );
			} elseif ( isset( $_POST['billing_country'] ) ) {
				// Some sites don't have separate billing vs shipping option
				// In that case we use the billing_country
				$country = esc_textarea( $_POST['billing_country'] );
			} else {
				// Fallback if we can't determine shipping or billing country
				$country = '';
			}

			// Get the allowed countries from coupon meta
			$allowed_countries = $coupon->get_meta( 'shipping_country_restriction', true );

			if ( ! in_array( $country, $allowed_countries ) ) {
				$this->remove_coupon( $coupon, $code, $msg );
			}

		}

		/**
		 * Removes coupon and displays validation message
		 *
		 * @param object $coupon
		 * @param string $code
		 */
		public function remove_coupon( $coupon, $code, $msg ) {

			// Filter to change validation text
			$msg = apply_filters( 'woocommerce-coupon-restrictions-removed-message-with-code', $msg, $code, $coupon );

			// Remove the coupon
			WC()->cart->remove_coupon( $code );

			// Throw a notice to stop checkout
			wc_add_notice( $msg, 'error' );

			// Flag totals for refresh
			WC()->session->set( 'refresh_totals', true );

		}

		/**
		 * Checks if e-mail address has been used previously for a purchase.
		 *
		 * @returns boolean
		 */
		public function is_returning_customer( $email ) {

			$customer_orders = get_posts( array(
				'post_type'   => 'shop_order',
				'meta_key'    => '_billing_email',
				'post_status' => 'publish',
				'post_status' => array( 'wc-processing', 'wc-completed' ),
				'meta_value'  => $email,
				'numberposts' => 1,
				'cache_results' => false,
				'no_found_rows' => true,
				'fields' => 'ids',
			) );

			// If there is at least one other order by billing e-mail
			if ( 1 === count( $customer_orders ) ) {
				return true;
			}

			// Otherwise there should not be any orders
			return false;
		}

	}

	new WC_Coupon_Restrictions();

endif;
