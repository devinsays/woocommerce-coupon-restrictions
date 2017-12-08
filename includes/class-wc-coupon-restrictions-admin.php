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
	* Initialize the class.
	*/
	public static function init() {

		// Adds metabox to usage restriction fields.
		add_action( 'woocommerce_coupon_options_usage_restriction', __CLASS__ . '::customer_restrictions' );
		add_action( 'woocommerce_coupon_options_usage_restriction', __CLASS__ . '::location_restrictions' );

		// Saves the metabox.
		add_action( 'woocommerce_coupon_options_save', __CLASS__ . '::coupon_options_save' );

	}

	/**
	 * Adds "new customer" and "existing customer" restriction checkboxes.
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public static function customer_restrictions() {

		echo '<div class="options_group">';

		woocommerce_wp_select(
			array(
				'id' => 'customer_restriction_type',
				'label' => __( 'Purchase history', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Restricts coupon to new customers or existing customers based on purchase history.', 'woocommerce-coupon-restrictions' ),
				'desc_tip' => true,
				'class' => 'select',
				'options' => array(
					'none' => __( 'No restriction', 'woocommerce-coupon-restrictions' ),
					'new' => __( 'New customers only', 'woocommerce-coupon-restrictions' ),
					'existing' => __( 'Existing customers only', 'woocommerce-coupon-restrictions' ),
				),
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

		woocommerce_wp_select(
			array(
				'id' => 'address_for_location_restrictions',
				'label' => __( 'Address', 'woocommerce-coupon-restrictions' ),
				'description' => __( 'Address to use for location restrictions.', 'woocommerce-coupon-restrictions' ),
				'class' => 'select',
				'options' => array(
					'shipping' => __( 'Shipping', 'woocommerce-coupon-restrictions' ),
					'billing' => __( 'Billing', 'woocommerce-coupon-restrictions' ),
				),
			)
		);

		// Country restriction.
		$id = 'country_restriction';
		$title = __( 'Limit Countries', 'woocommerce-coupon-restrictions' );
		$values = get_post_meta( $post->ID, $id, true );
		$description = '';

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
	 * Saves post meta for "new customer" restriction.
	 *
	 * @since  1.3.0
	 * @param $post_id Coupon post ID.
	 * @return void
	 */
	public static function coupon_options_save( $post_id ) {

		// Sanitize customer restriction type meta.
		$customer_restriction_type = isset( $_POST['customer_restriction_type'] ) ? $_POST['customer_restriction_type'] : 'none';
		if ( ! in_array( $customer_restriction_type, array( 'new', 'existing', 'none' ) ) ) {
			$customer_restriction_type = 'none';
		}

		// Sanitize address to use for location restrictions.
		$address_for_location_restrictions = isset( $_POST['address_for_location_restrictions'] ) ? $_POST['address_for_location_restrictions'] : 'shipping';
		if ( 'billing' !== $address_for_location_restrictions ) {
			$address_for_location_restrictions = 'shipping';
		}

		// Sanitize country restriction meta.
		$country_restriction_select = isset( $_POST['country_restriction'] ) ? $_POST['country_restriction'] : array();
		$country_restriction = array_filter( array_map( 'wc_clean', $country_restriction_select ) );

		// Save meta.
		update_post_meta( $post_id, 'customer_restriction_type', $customer_restriction_type );
		update_post_meta( $post_id, 'address_for_location_restrictions', $address_for_location_restrictions );
		update_post_meta( $post_id, 'country_restriction', $country_restriction );

	}
}

WC_Coupon_Restrictions_Admin::init();
