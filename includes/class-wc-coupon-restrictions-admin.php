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
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'customer_restrictions' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'role_restrictions' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'location_restrictions' ), 10, 2 );

		// Saves the metabox.
		add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save'  ), 10, 2 );

	}

	/**
	 * Adds "new customer" and "existing customer" restriction checkboxes.
	 *
	 * @since  1.3.0
	 *
	 * @param int $coupon_id
	 * @param object $coupon
	 * @return void
	 */
	public static function customer_restrictions( $coupon_id, $coupon ) {

		echo '<div class="options_group">';

		$value = esc_attr( $coupon->get_meta( 'customer_restriction_type', true ) );

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
	 * Adds role restriction select box.
	 *
	 * @since  1.8.0
	 *
	 * @param int $coupon_id
	 * @param object $coupon
	 * @return void
	 */
	public static function role_restrictions( $coupon_id, $coupon ) {

		echo '<div class="options_group">';

		$id = 'role_restriction';
		$title = __( 'User role restriction', 'woocommerce-coupon-restrictions' );
		$values = $coupon->get_meta( $id, true );
		$description = '';

		echo '<p class="form-field ' . $id . '_only_field">';

			$selections = array();
			if ( ! empty( $values ) ) {
				$selections = $values;
			}

			// An array of all roles.
			$roles = array_reverse( get_editable_roles() );
			?>
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $title ); ?>
			</label>
			<select multiple="multiple" name="<?php echo esc_attr( $id ); ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose roles&hellip;', 'woocommerce-coupon-restrictions' ); ?>" aria-label="<?php esc_attr_e( 'Role', 'woocommerce-coupon-restrictions' ) ?>" class="wc-enhanced-select">
				<?php
				foreach ( $roles as $id => $role ) {
					$selected = in_array( $id, $selections );
					$role_name = translate_user_role( $role['name'] );

					echo '<option value="' . esc_attr( $id ) . '" ' . selected( $selected, true, false ) . '>' . esc_html( $role_name ) . '</option>';
				}
				?>
			</select>
			<?php
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Adds country restriction.
	 *
	 * @since  1.3.0
	 *
	 * @param int $id
	 * @param object $coupon
	 * @return void
	 */
	public static function location_restrictions( $coupon_id, $coupon ) {

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
		$values = $coupon->get_meta( $id, true );

		echo '<p class="form-field ' . $id . '_only_field">';

			$selections = array();
			if ( ! empty( $values ) ) {
				$selections = $values;
			}

			// An array of all countries.
			$countries = WC()->countries->get_countries();

			// An array of countries the shop sells to.
			// Calls the global instance for PHP5.6 compatibility.
			$shop_countries = WC_Coupon_Restrictions()->admin->shop_countries();
			?>
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $title ); ?>
			</label>
			<select multiple="multiple" name="<?php echo esc_attr( $id ); ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose countries&hellip;', 'woocommerce-coupon-restrictions' ); ?>" aria-label="<?php esc_attr_e( 'Country', 'woocommerce-coupon-restrictions' ) ?>" class="wc-enhanced-select">
				<?php
				foreach ( $countries as $key => $val ) {

					// If country has been saved, it will display even if shop doesn't currently sell there.
					$selected = in_array( $key, $selections );

					// Any country that shop sells to should appear as a selectable option.
					$allowed = in_array( $key, $shop_countries );

					// Output the options.
					if ( $selected ||  $allowed ) {
						echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, true, false ) . '>' . esc_html( $val ) . '</option>';
					}
				}
				?>
			</select>
			<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Select any country that your store currently sells to.', 'woocommerce-coupon-restrictions' ); ?>"></span>
			<div class="wcr-field-options" style="margin-left: 162px;">
			<button type="button" class="button button-secondary" aria-label="Adds all the countries that the store sells to in the restricted field.">Add All</button>
			</div>
			<?php
		echo '</p>';
		
		// State restrictions
		woocommerce_wp_textarea_input(
			array(
				'label'   => __( 'Restrict to specific states', 'woocommerce-coupon-restrictions' ),
				'description'    => __( 'Use the two digit state codes. Comma separate to specify multiple states.', 'woocommerce-coupon-restrictions' ),
				'desc_tip' => true,
				'id'      => 'state_restriction',
				'type'    => 'textarea',
			)
		);

		// Postcode / Zip Code restrictions
		woocommerce_wp_textarea_input(
			array(
				'label'   => __( 'Restrict to specific zip codes', 'woocommerce-coupon-restrictions' ),
				'description'    => __( 'Comma separate to list multiple zip codes. Wildcards (*) can be used to match portions of zip codes.', 'woocommerce-coupon-restrictions' ),
				'desc_tip' => true,
				'id'      => 'postcode_restriction',
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
		$countries = WC()->countries->get_countries();

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

		// Strip line breaks and extra whitespace before returning.
		$js = preg_replace( "/\s+/", " ", $js );
		return trim( $js );
	}

	/**
	 * Saves post meta for custom coupon meta.
	 *
	 * @since  1.3.0
	 * @param int $coupon_id
	 * @param object $coupon
	 *
	 * @return void
	 */
	public static function coupon_options_save( $coupon_id, $coupon ) {

		// Sanitize customer restriction type meta.
		$id = 'customer_restriction_type';
		$customer_restriction_type = isset( $_POST[$id] ) ? $_POST[$id] : 'none';
		if ( ! in_array( $customer_restriction_type, array( 'new', 'existing', 'none' ) ) ) {
			$customer_restriction_type = 'none';
		}
		
		// Sanitize role restriction meta.
		$id = 'role_restriction';
		$role_restriction_select = isset( $_POST[$id] ) ? $_POST[$id] : array();
		$role_restriction = array_filter( array_map( 'wc_clean', $role_restriction_select ) );

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
		
		// Sanitize state restriction meta.
		$id = 'state_restriction';
		$state_restriction = isset( $_POST[$id] ) ? $_POST[$id] : '';
		$state_restriction = self::sanitize_comma_seperated_textarea( $state_restriction );

		// Sanitize postcode restriction meta.
		$id = 'postcode_restriction';
		$postcode_restriction = isset( $_POST[$id] ) ? $_POST[$id] : '';
		$postcode_restriction = self::sanitize_comma_seperated_textarea( $postcode_restriction );

		// Save meta.
		$coupon->update_meta_data( 'customer_restriction_type', $customer_restriction_type );
		$coupon->update_meta_data( 'role_restriction', $role_restriction );
		$coupon->update_meta_data( 'location_restrictions', $location_restrictions );
		$coupon->update_meta_data( 'address_for_location_restrictions', $address_for_location_restrictions );
		$coupon->update_meta_data( 'country_restriction', $country_restriction );
		$coupon->update_meta_data( 'state_restriction', $state_restriction );
		$coupon->update_meta_data( 'postcode_restriction', $postcode_restriction );
		$coupon->save_meta_data();
	}
	
	/**
	 * Sanitizes comma seperated textarea.
	 *
	 * @since  1.7.1
	 * @param string $textarea
	 *
	 * @return string
	 */
	public static function sanitize_comma_seperated_textarea( $textarea ) {
		
		// Trim whitespace.
		$textarea = trim( $textarea );

		if ( '' !== $textarea ) {
			// Convert comma separated list into array for sanitization.
			$items = explode( ',', $textarea );
			$items = array_unique( array_map( 'trim', $items ) ); // Trim whitespace
			$items = array_unique( array_map( 'esc_textarea', $items ) ); // Sanitize values
			$textarea = implode(', ', $items ); // Convert back to comma separated string
		}
		
		return $textarea;
	}

}
