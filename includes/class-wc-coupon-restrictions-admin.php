<?php
/**
 * WooCommerce Coupon Restrictions - Admin.
 *
 * @class    WC_Coupon_Restrictions_Admin
 * @author   DevPress
 * @package  WooCommerce Coupon Restrictions
 * @license  GPL-2.0+
 * @since    1.3.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly.
}

class WC_Coupon_Restrictions_Admin {

	/**
	* Init the class.
	*/
	public function init() {

		// Adds metabox to usage restriction fields.
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'customer_restrictions' ) );
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'location_restrictions' ) );

		// Saves the metabox.
		add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save'  ) );

	}

	/**
	 * Adds "new customer" and "existing customer" restriction checkboxes.
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public static function customer_restrictions() {

		echo '<div class="options_group">';

		global $post;
		$value = get_post_meta( $post->ID, 'customer_restriction_type', true );

		// Default to none if no value has been saved.
		$value = $value ? $value : 'none';

		woocommerce_wp_radio(
			array(
				'id' => 'customer_restriction_type',
				'label' => __( 'Customer restrictions', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Restricts coupon to specific customers based on purchase history.', 'woocommerce-coupon-restrictions' ),
				'desc_tip' => true,
				'class' => 'select',
				'options' => array(
					'none' => __( 'Default (no restriction)', 'woocommerce-coupon-restrictions' ),
					'new' => __( 'New customers only', 'woocommerce-coupon-restrictions' ),
					'existing' => __( 'Existing customers only', 'woocommerce-coupon-restrictions' ),
				),
				'value' => $value
			)
		);

		echo '</div>';

	}

	/**
	 * Adds country restriction.
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public static function location_restrictions() {

		global $post;

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id' => 'location_restrictions',
				'label' => __( 'Use location restrictions', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Displays and enables location restriction options.', 'woocommerce-coupon-restrictions' )
			)
		);
		?>

		<div class="woocommerce-coupon-restrictions-locations" style="display:none;">

		<?php
		woocommerce_wp_select(
			array(
				'id' => 'address_for_location_restrictions',
				'label' => __( 'Address for location restrictions', 'woocommerce-coupon-restrictions' ),
				'class' => 'select',
				'options' => array(
					'shipping' => __( 'Shipping', 'woocommerce-coupon-restrictions' ),
					'billing' => __( 'Billing', 'woocommerce-coupon-restrictions' ),
				),
			)
		);

		// Country restriction.
		$id = 'country_restriction';
		$title = __( 'Restrict to specific countries', 'woocommerce-coupon-restrictions' );
		$values = get_post_meta( $post->ID, $id, true );
		$description = '';

		echo '<p class="form-field ' . $id . '_only_field">';

			$selections = array();
			if ( ! empty( $values ) ) {
				$selections = $values;
			}

			// An array of all countries.
			$countries = WC()->countries->countries;
			asort( $countries );

			// An array of countries the shop sells to.
			// Calls the global instance for PHP5.6 compatibility.
			$shop_countries = WC_Coupon_Restrictions()->admin->shop_countries();
			?>
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $title ); ?>
			</label>
			<select multiple="multiple" name="<?php echo esc_attr( $id ); ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose countries&hellip;', 'woocommerce-coupon-restrictions' ); ?>" aria-label="<?php esc_attr_e( 'Country', 'woocommerce-coupon-restrictions' ) ?>" class="wc-enhanced-select">
				<?php
					if ( ! empty( $countries ) ) {
						foreach ( $countries as $key => $val ) {

							// If country has been saved, it will display even if shop doesn't currently sell there.
							$selected = in_array( $key, $selections );

							// Any country that shop sells to should appear as a selectable option.
							$allowed = in_array( $key, $shop_countries );

							// Output the options.
							if ( $selected ||  $allowed ) {
								echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, true, false ) . '>' . $val . '</option>';
							}
						}
					}
				?>
			</select>
			<span class="woocommerce-help-tip" data-tip="<?php _e( "Select any country that your store currently sells to.", 'woocommerce-coupon-restrictions' ); ?>">
			<?php
		echo '</p>';

		// Postcode / ZIP restrictions
		$id = 'postcode_restriction';
		woocommerce_wp_textarea_input(
			array(
				'label'   => __( 'Restrict to specific zip codes', 'woocommerce-coupon-restrictions' ),
				'description'    => __( 'You can list multiple zip codes or postcodes (comma separated).', 'woocommerce-coupon-restrictions' ),
				'desc_tip' => true,
				'id'      => $id,
				'type'    => 'textarea',
			)
		);

		echo '</div>'; // .woocommerce-coupon-restrictions-locations
		echo '</div>'; // .options-group

		// Calls the global instance for PHP5.6 compatibility.
		$js = WC_Coupon_Restrictions()->admin->location_restrictions_admin_js();

		// Enqueue the inline script.
		wc_enqueue_js( $js );

	}

	/**
	 * Returns an array of countries the shop sells to.
	 *
	 * @since  1.5.0
	 * @return array $shop_countries
	 */
	public static function shop_countries() {

		// An array of all countries.
		$countries = WC()->countries->countries;

		// We just need the array keys.
		$countries = array_keys( $countries );

		// This option is set in the WooCommerce settings.
		// Possible values are: all, all_except_countries, specific.
		$allowed_countries = get_option( 'woocommerce_allowed_countries' );

		if ( 'specific' === $allowed_countries ) {
			$shop_countries = get_option( 'woocommerce_specific_allowed_countries' );
			return $shop_countries;
		}

		if ( 'all_except_countries' === $allowed_countries ) {
			$all_except_countries = get_option( 'woocommerce_all_except_countries' );
			$shop_countries = array_diff_key( $countries, $all_except_countries );
			return $shop_countries;
		}

		// Returns all countries if above conditions are not met.
		return $countries;

	}

	/**
	 * Outputs javascript to be enqueued on the coupon admin screen.
	 *
	 * @since  1.5.0
	 * @return void
	 */
	public static function location_restrictions_admin_js() {
		$js = "
			var location_restrictions_group = document.querySelector('.woocommerce-coupon-restrictions-locations');
			var location_restrictions_cb = document.querySelector('#location_restrictions');
			if ( location_restrictions_cb.checked ) {
				location_restrictions_group.removeAttribute('style');
			}
			location_restrictions_cb.addEventListener( 'change', function() {
				if ( this.checked ) {
					location_restrictions_group.removeAttribute('style');
				} else {
					location_restrictions_group.style.display = 'none';
				}
			});
		";
		return $js;
	}

	/**
	 * Saves post meta for custom coupon meta.
	 *
	 * @since  1.3.0
	 * @param $post_id Coupon post ID.
	 * @return void
	 */
	public static function coupon_options_save( $post_id ) {

		// Sanitize customer restriction type meta.
		$id = 'customer_restriction_type';
		$customer_restriction_type = isset( $_POST[$id] ) ? $_POST[$id] : 'none';
		if ( ! in_array( $customer_restriction_type, array( 'new', 'existing', 'none' ) ) ) {
			$customer_restriction_type = 'none';
		}

		// Sanitize location restrictions checkbox.
		$id = 'location_restrictions';
		$location_restrictions = isset( $_POST[$id] ) ? 'yes' : 'no';

		// Sanitize address to use for location restrictions.
		$id = 'address_for_location_restrictions';
		$address_for_location_restrictions = isset( $_POST[$id] ) ? $_POST[$id] : 'shipping';
		if ( 'billing' !== $address_for_location_restrictions ) {
			$address_for_location_restrictions = 'shipping';
		}

		// Sanitize country restriction meta.
		$id = 'country_restriction';
		$country_restriction_select = isset( $_POST[$id] ) ? $_POST[$id] : array();
		$country_restriction = array_filter( array_map( 'wc_clean', $country_restriction_select ) );

		// Sanitize postcode restriction meta.
		$id = 'postcode_restriction';
		$postcode_restriction = isset( $_POST[$id] ) ? $_POST[$id] : '';
		if ( '' !== $postcode_restriction ) {
			// Trim whitespace.
			$postcode_restriction = trim( $postcode_restriction );
			// Convert comma separated list into array for sanitization.
			$postcode_array = explode( ',', $postcode_restriction );
			$postcode_array = array_unique( array_map( 'trim', $postcode_array ) ); // Trim whitespace
			$postcode_array = array_unique( array_map( 'esc_textarea', $postcode_array ) ); // Sanitize values
			$postcode_restriction = implode(', ', $postcode_array ); // Convert back to comma separated string
		}

		// Save meta.
		update_post_meta( $post_id, 'customer_restriction_type', $customer_restriction_type );
		update_post_meta( $post_id, 'location_restrictions', $location_restrictions );
		update_post_meta( $post_id, 'address_for_location_restrictions', $address_for_location_restrictions );
		update_post_meta( $post_id, 'country_restriction', $country_restriction );
		update_post_meta( $post_id, 'postcode_restriction', $postcode_restriction );

	}
}
