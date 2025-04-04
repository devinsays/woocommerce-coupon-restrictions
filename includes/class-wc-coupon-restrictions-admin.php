<?php
/**
 * WooCommerce Coupon Restrictions - Admin.
 *
 * @package  WooCommerce Coupon Restrictions
 * @since    1.3.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Coupon_Restrictions_Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Enqueues javascript.
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		// Adds additional usage restriction fields.
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'customer_restrictions' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'role_restrictions' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'location_restrictions' ), 10, 2 );

		// Adds additional usage limit fields.
		add_action( 'woocommerce_coupon_options_usage_limit', array( $this, 'usage_limits' ), 10, 2 );

		// Saves the meta fields.
		add_action( 'woocommerce_coupon_options_save', array( $this, 'coupon_options_save' ), 10, 2 );
	}

	/**
	 * Adds "new customer" and "existing customer" restriction radio buttons.
	 *
	 * @since  1.3.0
	 *
	 * @param int $coupon_id
	 * @param WC_Coupon $coupon
	 * @return void
	 */
	public static function customer_restrictions( $coupon_id, $coupon ) {
		echo '<div class="options_group">';

		$value = esc_attr( $coupon->get_meta( 'customer_restriction_type', true ) );

		// Default to none if no value has been saved.
		$value = $value ? $value : 'none';

		woocommerce_wp_radio(
			array(
				'id'          => 'customer_restriction_type',
				'label'       => __( 'Customer restrictions', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Restricts coupon to specific customers based on purchase history.', 'woocommerce-coupon-restrictions' ),
				'desc_tip'    => true,
				'class'       => 'select',
				'options'     => array(
					'none'     => __( 'Default (no restriction)', 'woocommerce-coupon-restrictions' ),
					'new'      => __( 'New customers only', 'woocommerce-coupon-restrictions' ),
					'existing' => __( 'Existing customers only', 'woocommerce-coupon-restrictions' ),
				),
				'value'       => $value,
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
	 * @param WC_Coupon $coupon
	 * @return void
	 */
	public static function role_restrictions( $coupon_id, $coupon ) {
		$id    = 'role_restriction';
		$title = __( 'User role restriction', 'woocommerce-coupon-restrictions' );

		// Gets the selected roles (if any) from the coupon.
		$values     = $coupon->get_meta( $id, true );
		$selections = array();
		if ( ! empty( $values ) && is_array( $values ) ) {
			$selections = $values;
		}

		// An array of all roles.
		$roles = array_reverse( get_editable_roles() );

		// Adds a fabricated role for "Guest" to allow guest checkouts.
		$roles['woocommerce-coupon-restrictions-guest'] = array(
			'name' => __( 'Guest (No User Account)', 'woocommerce-coupon-restrictions' ),
		);
		?>
		<div class="options_group">
			<p class="form-field <?php echo $id; ?>_only_field">
				<label for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $title ); ?>
				</label>
				<select multiple="multiple" name="<?php echo $id; ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose roles&hellip;', 'woocommerce-coupon-restrictions' ); ?>" aria-label="<?php esc_attr_e( 'Role', 'woocommerce-coupon-restrictions' ); ?>" class="wc-enhanced-select">
				<?php
				foreach ( $roles as $id => $role ) {
					$selected  = in_array( $id, $selections );
					$role_name = translate_user_role( $role['name'] );

					echo '<option value="' . $id . '" ' . selected( $selected, true, false ) . '>' . esc_html( $role_name ) . '</option>';
				}
				?>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds country restriction.
	 *
	 * @since  1.3.0
	 *
	 * @param int $coupon_id
	 * @param WC_Coupon $coupon
	 * @return void
	 */
	public static function location_restrictions( $coupon_id, $coupon ) {
		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => 'location_restrictions',
				'label'       => __( 'Use location restrictions', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Display and enable location restriction options.', 'woocommerce-coupon-restrictions' ),
			)
		);
		?>

		<div class="woocommerce-coupon-restrictions-locations" style="display:none;">

		<?php
		woocommerce_wp_select(
			array(
				'id'      => 'address_for_location_restrictions',
				'label'   => __( 'Address for location restrictions', 'woocommerce-coupon-restrictions' ),
				'class'   => 'select',
				'options' => array(
					'shipping' => __( 'Shipping', 'woocommerce-coupon-restrictions' ),
					'billing'  => __( 'Billing', 'woocommerce-coupon-restrictions' ),
				),
			)
		);

		// Country restriction.
		$id    = 'country_restriction';
		$title = __( 'Restrict to specific countries', 'woocommerce-coupon-restrictions' );

		// Gets the selected countries (if any) from the coupon.
		$values     = $coupon->get_meta( $id, true );
		$selections = array();
		if ( ! empty( $values ) && is_array( $values ) ) {
			$selections = $values;
		}

		// An array of all countries.
		$countries = WC()->countries->get_countries();

		// An array of countries the shop sells to.
		$shop_countries = self::shop_countries();
		?>
		<p class="form-field <?php echo $id; ?>_only_field">
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $title ); ?>
		</label>
		<select id="wccr-restricted-countries" multiple="multiple" name="<?php echo esc_attr( $id ); ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose countries&hellip;', 'woocommerce-coupon-restrictions' ); ?>" aria-label="<?php esc_attr_e( 'Country', 'woocommerce-coupon-restrictions' ); ?>" class="wc-enhanced-select">
			<?php
			foreach ( $countries as $key => $val ) {

				// If country has been saved, it will display even if shop doesn't currently sell there.
				$selected = in_array( $key, $selections );

				// Any country that shop sells to should appear as a selectable option.
				$allowed = in_array( $key, $shop_countries );

				// Output the options.
				if ( $selected || $allowed ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, true, false ) . '>' . esc_html( $val ) . '</option>';
				}
			}
			?>
			</select>
			<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Select any country that your store currently sells to.', 'woocommerce-coupon-restrictions' ); ?>"></span>
			<div class="wcr-field-options" style="margin-left: 162px;">
				<button id="wccr-add-all-countries" type="button" class="button button-secondary" aria-label="<?php esc_attr_e( 'Adds all the countries that the store sells to in the restricted field.', 'woocommerce-coupon-restrictions' ); ?>">
					<?php echo esc_html_e( 'Add All Countries', 'woocommerce-coupon-restrictions' ); ?>
				</button>
				<button id="wccr-clear-all-countries" type="button" class="button button-secondary" aria-label="<?php esc_attr_e( 'Clears all restricted country selections.', 'woocommerce-coupon-restrictions' ); ?>">
					<?php echo esc_html_e( 'Clear', 'woocommerce-coupon-restrictions' ); ?>
				</button>
			</div>
		</p>
		<?php

		// State restrictions
		woocommerce_wp_textarea_input(
			array(
				'label'       => __( 'Restrict to specific states', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Use the two digit state codes. Comma separate to specify multiple states.', 'woocommerce-coupon-restrictions' ),
				'desc_tip'    => true,
				'id'          => 'state_restriction',
				'type'        => 'textarea',
			)
		);

		// Postcode / Zip Code restrictions
		woocommerce_wp_textarea_input(
			array(
				'label'       => __( 'Restrict to specific zip codes', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Comma separate to list multiple zip codes. Wildcards (*) can be used to match portions of zip codes.', 'woocommerce-coupon-restrictions' ),
				'desc_tip'    => true,
				'id'          => 'postcode_restriction',
				'type'        => 'textarea',
			)
		);

		echo '</div>'; // .woocommerce-coupon-restrictions-locations
		echo '</div>'; // .options-group

		wp_enqueue_script( 'wccr-admin' );
	}

	/**
	 * Adds additional usage restrictions.
	 *
	 * @since  1.7.0
	 *
	 * @param int $coupon_id
	 * @param WC_Coupon $coupon
	 * @return void
	 */
	public static function usage_limits( $coupon_id, $coupon ) {
		// Description on how the enhanced usage limits work.
		$label       = __( 'Enhanced Usage Limits', 'woocommerce-coupon-restrictions' );
		$description = __( 'Enhanced usage limits should be set when the coupon is first created. WooCommerce will verify against previous orders made with a coupon that has enhanced usage restrictions.', 'woocommerce-coupon-restrictions' );
		/* translators: %s: link to WooCommerce Coupon Restrictions documentation. */
		$link = sprintf( __( 'Please read <a href="%1$s">the documentation</a> for more information.', 'woocommerce-coupon-restrictions' ), 'https://woocommerce.com/document/woocommerce-coupon-restrictions/#section-5' );
		echo '<p class="form-field">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo esc_html( $description );
		echo ' ' . wp_kses( $link, array( 'a' => array( 'href' => array() ) ) );
		echo '</p>';

		$value = esc_attr( $coupon->get_meta( 'prevent_similar_emails', true ) );
		woocommerce_wp_checkbox(
			array(
				'id'          => 'prevent_similar_emails',
				'label'       => __( 'Prevent similar emails', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Many email services ignore periods and anything after a "+". Check this box to prevent customers from using a similar email address to exceed the usage limit per user.', 'woocommerce-coupon-restrictions' ),
				'value'       => wc_bool_to_string( $value ),
			)
		);

		// Usage limit per shipping address.
		$value = $coupon->get_meta( 'usage_limit_per_shipping_address' );
		woocommerce_wp_text_input(
			array(
				'id'                => 'usage_limit_per_shipping_address',
				'label'             => __( 'Usage limit per shipping address', 'woocommerce-coupon-restrictions' ),
				'placeholder'       => esc_attr__( 'Unlimited usage', 'woocommerce-coupon-restrictions' ),
				'description'       => __( 'How many times this coupon can be used with the same shipping address.', 'woocommerce-coupon-restrictions' ),
				'desc_tip'          => true,
				'class'             => 'short',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 1,
					'min'  => 0,
				),
				'value'             => $value ? intval( $value ) : '',
			)
		);

		// Usage limit per IP address.
		$value = $coupon->get_meta( 'usage_limit_per_ip_address' );
		woocommerce_wp_text_input(
			array(
				'id'                => 'usage_limit_per_ip_address',
				'label'             => __( 'Usage limit per IP address', 'woocommerce-coupon-restrictions' ),
				'placeholder'       => esc_attr__( 'Unlimited usage', 'woocommerce-coupon-restrictions' ),
				'description'       => __( 'How many times this coupon can be used with the same IP address.', 'woocommerce-coupon-restrictions' ),
				'desc_tip'          => true,
				'class'             => 'short',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 1,
					'min'  => 0,
				),
				'value'             => $value ? intval( $value ) : '',
			)
		);
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
			$shop_countries       = array_diff_key( $countries, $all_except_countries );
			return $shop_countries;
		}

		// Returns all countries if above conditions are not met.
		return $countries;
	}

	/**
	 * Saves post meta for custom coupon meta.
	 *
	 * @since  1.3.0
	 * @param int $coupon_id
	 * @param WC_Coupon $coupon
	 *
	 * @return void
	 */
	public static function coupon_options_save( $coupon_id, $coupon ) {
		// Customer restriction type.
		$id                        = 'customer_restriction_type';
		$customer_restriction_type = $_POST[ $id ] ?? '';
		if ( in_array( $customer_restriction_type, array( 'new', 'existing' ) ) ) {
			$coupon->update_meta_data( $id, $customer_restriction_type );
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Role restriction.
		$id                      = 'role_restriction';
		$role_restriction_select = $_POST[ $id ] ?? array();
		$role_restriction        = array_filter( array_map( 'wc_clean', $role_restriction_select ) );
		if ( $role_restriction ) {
			$coupon->update_meta_data( $id, $role_restriction );
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Location restrictions.
		$id                    = 'location_restrictions';
		$location_restrictions = isset( $_POST[ $id ] ) ? 'yes' : 'no';
		if ( 'yes' === $location_restrictions ) {
			$coupon->update_meta_data( $id, $location_restrictions );
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Address for location restrictions.
		$id                                = 'address_for_location_restrictions';
		$address_for_location_restrictions = isset( $_POST[ $id ] ) ? $_POST[ $id ] : 'shipping';
		if ( 'billing' === $address_for_location_restrictions ) {
			$coupon->update_meta_data( $id, $address_for_location_restrictions );
		} else {
			// Shipping is used as default if no meta exists.
			$coupon->delete_meta_data( $id );
		}

		// Country restriction.
		$id                         = 'country_restriction';
		$country_restriction_select = $_POST[ $id ] ?? array();
		$country_restriction        = array_filter( array_map( 'wc_clean', $country_restriction_select ) );
		if ( $country_restriction ) {
			$coupon->update_meta_data( $id, $country_restriction );
		} else {
			$coupon->delete_meta_data( $id );
		}

		// State restriction.
		$id                = 'state_restriction';
		$state_restriction = $_POST[ $id ] ?? '';
		$state_restriction = self::sanitize_comma_seperated_textarea( $state_restriction );
		if ( $state_restriction ) {
			$coupon->update_meta_data( $id, $state_restriction );
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Postcode restriction.
		$id                   = 'postcode_restriction';
		$postcode_restriction = $_POST[ $id ] ?? '';
		$postcode_restriction = self::sanitize_comma_seperated_textarea( $postcode_restriction );
		if ( $postcode_restriction ) {
			$coupon->update_meta_data( $id, $postcode_restriction );
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Track whether an enhanced usage restriction is set.
		$enhanced_usage_restriction = false;

		// Prevent similar emails.
		$id                     = 'prevent_similar_emails';
		$prevent_similar_emails = isset( $_POST[ $id ] ) ? 'yes' : 'no';
		if ( 'yes' === $prevent_similar_emails ) {
			$coupon->update_meta_data( $id, $prevent_similar_emails );
			$enhanced_usage_restriction = true;
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Usage limit per shipping address.
		$id                       = 'usage_limit_per_shipping_address';
		$usage_limit_per_shipping = absint( $_POST[ $id ] );
		if ( $usage_limit_per_shipping > 0 ) {
			$coupon->update_meta_data( $id, $usage_limit_per_shipping );
			$enhanced_usage_restriction = true;
		} else {
			$coupon->delete_meta_data( $id );
		}

		// Usage limit per IP address.
		$id                         = 'usage_limit_per_ip_address';
		$usage_limit_per_ip_address = absint( $_POST[ $id ] );
		if ( $usage_limit_per_ip_address > 0 ) {
			$coupon->update_meta_data( $id, $usage_limit_per_ip_address );
			$enhanced_usage_restriction = true;
		} else {
			$coupon->delete_meta_data( $id );
		}

		// If an enhanced usage restiction is set,
		// make sure the custom restrictions table is available.
		if ( $enhanced_usage_restriction ) {
			WC_Coupon_Restrictions_Table::maybe_create_table();
		}

		// Save meta data.
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
	public static function sanitize_comma_seperated_textarea( $textarea = '' ) {
		// Trim whitespace.
		$textarea = trim( $textarea );

		if ( '' !== $textarea ) {
			// Convert comma separated list into array for sanitization.
			$items    = explode( ',', $textarea );
			$items    = array_unique( array_map( 'trim', $items ) ); // Trim whitespace
			$items    = array_unique( array_map( 'esc_textarea', $items ) ); // Sanitize values
			$textarea = implode( ', ', $items ); // Convert back to comma separated string
		}

		return $textarea;
	}

	/**
	 * Registers javascript.
	 *
	 * @return void
	 */
	public function scripts() {
		$file = esc_url( WC_Coupon_Restrictions::plugin_asset_path() . 'assets/admin.js' );
		wp_register_script(
			'wccr-admin',
			$file,
			array( 'jquery' ),
			'1.8.6',
			true
		);
	}

}
