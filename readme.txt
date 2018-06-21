=== WooCommerce Coupon Restrictions ===

Contributors: @downstairsdev
Tags: woocommerce, coupon
Requires at least: 4.7.0
Tested up to: 4.9.4
Requires PHP: 5.6
Stable tag: 1.6.1
License: GPLv3 or later License
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 3.3.0
WC tested up to: 3.4.3
Woo: 3200406:6d7b7aa4f9565b8f7cbd2fe10d4f119a

== Description ==

This extension allows you to create coupons with addition restriction options:

New customer restriction: If a customer is logged in, the plugin will verify that they haven't completed an order from the site before allowing the coupon to be applied. For customers that aren't logged in, the coupon verification runs after an email address has been added during checkout.

Existing customer restrictions: Validates that the customer has made a purchase on the site previously before allowing the coupon to be applied.

Country restriction: Allows you to restrict a coupon to specific countries. Restriction can be applied to shipping or billing country.

Zip code restriction: Allows you to restrict a coupon to specific zip codes or postal codes. Can be applied to the shipping or the billing address.

The plugin is fully translatable. Developers can also use filters to modify any notices displayed during coupon validation.

== Additional Notes ==

Customers are considered “new customers” if they have not yet completed an order on the site.

Customers are still considered “new customers” while their first order is “processing”. To ensure a new customer can only use a specific coupon coupon once, set the “Usage limit per user” on the coupon to 1.

Users who have an account on the site, but no completed purchases, are still considered new customers.

E-mail addresses restrictions are not case sensitive, but otherwise require an exact match.

Zip code or postcode restrictions are not case sensitive.

A customer must meet all requirements if multiple restrictions are set. For instance, if a "New Customer" and "Country Restriction" are set, a customer must meet both restrictions in order to checkout with the coupon.

== Compatibility ==

* Requires WooCommerce 3.3.0 or later.
