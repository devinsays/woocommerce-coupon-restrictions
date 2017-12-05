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

		// Adds metabox to usage restriction fields
		add_action( 'woocommerce_coupon_options_usage_restriction', __CLASS__ . '::customer_restrictions' );
		add_action( 'woocommerce_coupon_options_usage_restriction', __CLASS__ . '::location_restrictions' );

		// Saves the metabox
		add_action( 'woocommerce_coupon_options_save', __CLASS__ . '::coupon_options_save' );

	}

	/**
	 * Adds "new customer" and "existing customer" restriction checkboxes.
	 *
	 * @return void
	 */
	public static function customer_restrictions() {

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
	 * Adds country restriction.
	 *
	 * @return void
	 */
	public static function location_restrictions() {

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
	 * Saves post meta for "new customer" restriction.
	 *
	 * @return void
	 */
	public static function coupon_options_save( $post_id ) {

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
}

WC_Coupon_Restrictions_Admin::init();
