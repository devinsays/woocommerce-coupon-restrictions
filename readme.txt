=== WooCommerce Coupon Restrictions ===

Contributors: @downstairsdev
Tags: woocommerce, coupon
Requires at least: 4.7.0
Tested up to: 5.2.3
Requires PHP: 5.6
Stable tag: 1.8.0
License: GPLv3 or later License
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 3.3.0
WC tested up to: 3.7.0
Woo: 3200406:6d7b7aa4f9565b8f7cbd2fe10d4f119a

== Description ==

This extension allows you to create coupons with addition restriction options:

New customer restriction: Verifies that the customer does not have an account with completed purchases before applying the coupon. The extension can also verify that a customer doesn't have completed guest orders if when that setting is selected.

Existing customer restriction: Verifies that the custom does have an account with completed purchases before applying a coupon.

User role restriction: Limits a coupon to specific user roles. So if you have custom roles for wholesalers, affiliates, or vips, you can provide coupons that will only work for them.

Country restriction: Allows you to restrict a coupon to specific countries. Restriction can be applied to shipping or billing country.

State restriction: Allows you to restrict a coupon to specific states. Restriction can be applied to shipping or billing country.

Zip code restriction: Allows you to restrict a coupon to specific zip codes or postal codes. Can be applied to the shipping or the billing address.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

== Additional Notes ==

Customers are considered "new customers" if they do not have a user account with completed purchases.

If your site allows guest checkouts, you can also verify if a customer has completed a guest checkout order previously. To enable this, select "Verify new customers by checking against user accounts and all guest orders" under "WooCommerce > Settings > General". However, this setting is not recommended for sites with more than 10,000 orders as this verification query takes additional time to run. Instead, it's recommended to create customer accounts in the background during checkout.

E-mail addresses, zip code, and state restrictions are not case sensitive.

A customer must meet all requirements if multiple restrictions are set. For instance, if a "New Customer" and "Country Restriction" are set, a customer must meet both restrictions in order to checkout with the coupon.

== Compatibility ==

* Requires WooCommerce 3.3.0 or later.
