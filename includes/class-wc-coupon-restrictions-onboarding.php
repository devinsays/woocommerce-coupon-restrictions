<?php
/**
 * WooCommerce Coupon Restrictions - Onboarding.
 *
 * @class    WC_Coupon_Restrictions_Onboarding
 * @author   DevPress
 * @package  WooCommerce Coupon Restrictions
 * @license  GPL-2.0+
 * @since    1.5.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly.
}

class WC_Coupon_Restrictions_Onboarding {

	/**
	* Initialize the class.
	*/
	public static function init() {

		// Sets a transient on activation to determine if activation notices should be displayed.
		register_activation_hook( plugin_basename( __FILE__ ), __CLASS__ . '::activation_hook' );

		// Displays a notice on activation.
		add_action( 'admin_notices', __CLASS__ . '::admin_installed_notice' );

		// Sets up onboarding pointers.
		if ( true ) {

			add_action( 'admin_enqueue_scripts', __CLASS__ . '::enqueue_pointer_scripts' );

			// Displays the pointers.
			add_action( 'admin_print_footer_scripts', __CLASS__ . '::pointers' );
		}

	}

	/**
	 * Displays a welcome message. Called when the extension is activated.
	 *
	 * @since 1.5.0
	 */
	public static function activation_hook() {
		// Sets transient data.
		set_transient( 'woocommerce-coupon-restrictions-activated', true, 5 );
	}

	/**
	 * Displays a welcome message. Called when the extension is activated.
	 *
	 * @since 1.5.0
	 */
	public static function admin_installed_notice() {

		if ( get_transient( 'woocommerce-coupon-restrictions-activated' ) ) :
		?>
			<div class="updated notice is-dismissible woocommerce-message" style="border-left-color: #cc99c2">
				<p>
					<?php _e( 'WooCommerce Coupon Restrictions plugin activated.', 'woocommerce-coupon-restrictions' ); ?>
					<a href="<?php echo admin_url( 'post-new.php?post_type=shop_coupon' ); ?>"><?php esc_html_e( 'Create a New Coupon', 'woocommerce-coupon-restrictions' ); ?></a>
				</p>
			</div>
			<?php
		endif;
	}

	/**
	 * Displays the pointers.
	 *
	 * @since 1.5.0
	 */
	public static function enqueue_pointer_scripts() {
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
	}

	/**
	 * Displays the pointers.
	 *
	 * @since 1.5.0
	 */
	public static function pointers() { ?>
		<script>
		jQuery(document).ready( function($) {
			jQuery('#woocommerce-coupon-data .usage_restriction_tab a').trigger('click');

			$('#woocommerce-coupon-data .usage_restriction_options').pointer({
				content: '<h3>Usage Restrictions</h3><p>The new coupon restrictions can be found in this panel.</p>',
				position: 'top',
				close: function() {
				}
			}).pointer('open');
		});
		</script>
	<?php }

}

WC_Coupon_Restrictions_Onboarding::init();
