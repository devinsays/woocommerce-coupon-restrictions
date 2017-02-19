=== WooCommerce Coupon Restrictions ===

Contributors: @downstairsdev
Tags: woocommerce, coupon
Requires at least: 4.6.1
Tested up to: 4.6.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.6.0
WC tested up to: 2.6.8

== Description ==

This extension allows you to create coupons with addition restriction options: new customers only, existing customers only, and shipping country. If you require addition coupon restrictions, let us know.

New customer restriction: If a customer is logged in, the plugin will verify that they haven't purchased anything from the site before allowing the coupon to be applied. For customers that aren't logged in, the coupon verification runs right before checkout once the e-mail address has been entered.

Existing customer restrictions: Validates that the customer has made a purchase on the site previously before allowing the coupon to be applied.

Shipping country: Allows you limit shipping countries the coupon can be applied to.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

== Additional Notes ==

Customers are considered "new customers" if they have not yet spent money on the site and/or do not have an order that has been marked complete or processing. This can lead to some edge cases where a customer could use the coupon several times before an order is marked complete. Avoid this by setting the "Usage limit per user" to 1.

This extension checks e-mail addresses of existing customers by converting to all lower-case and doing a strict string match. It will only flag e-mail addresses that have an exact match.

== Compatibility ==

* Requires WooCommerce 2.6.0 or later
* Tested to WooCommerce 2.7.0

== Changelog ==

= 1.3.0 =

* Update: Added option to restrict to existing customers.

= 1.2.0 =

* Fix: Compatibility updates for WooCommerce 2.6.8

= 1.1.0 =

* Fix: Coupons without the new customer restriction were improperly marked invalid for logged in users.
* Fix: Filter woocommerce_coupon_is_valid in addition to woocommerce_coupon_error.
* Update: Unit tests added.

= 1.0.0 =

* Initial release.