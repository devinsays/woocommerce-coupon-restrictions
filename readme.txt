=== WooCommerce Coupon Restrictions ===

Contributors: @downstairsdev
Tags: woocommerce, coupon
Requires at least: 4.7.0
Tested up to: 4.9.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 3.3.0
WC tested up to: 3.4.2
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

== Changelog ==

= Development =

* Fix: Display onboarding notice on initial activation.
* Fix: If the session data is blank for country or zipcode, a coupon with location restrictions will stay applied until session or checkout has data to validate it.

= 1.6.0 (06.15.18) =

* Enhancement: Coupon validation now uses stored session data.
* Enhancement: Checkout validation now uses $posted data.
* Update: Additional unit and integration tests.
* Update: Returns a main instance of the class to avoid the need for globals.

= 1.5.0 (05.17.18)=

* Update: Improve coupon validation messages.
* Update: Use "Zip code" as default label.
* Update: Improve customer restriction UX. Use radio buttons rather than select.
* Update: Adds "Location Restrictions" checkbox. Additional options display when checked.
* Update: Country restriction only permits selection of countries that shop currently sells to.
* Update: New onboarding flow that shows where the new coupon options are located.
* Fix: Bug with new customer coupon validation at checkout.

= 1.4.1 (02.15.18)=

* Update: Remove upgrade routine.

= 1.4.0 (12.27.17)=

* Enhancement: Adds option to restrict location based on shipping or billing address.
* Enhancement: Adds option to restrict to postal code or zip code.
* Update: Use WooCommerce order wrapper to fetch orders.
* Update: Organize plugin into multiple classes.
* Update: Upgrade script for sites using earlier version of plugin.
* Update: Unit test framework added.

= 1.3.0 (01.31.17)=

* Enhancement: Adds option to restrict to existing customers.
* Enhancement: Adds option to restrict by shipping country.
* Update: Compatibility updates for WooCommerce 2.7.0.

= 1.2.0 (11.25.16)=

* Update: Compatibility updates for WooCommerce 2.6.8.

= 1.1.0 (12.28.15)=

* Fix: Coupons without the new customer restriction were improperly marked invalid for logged in users.
* Fix: Filter woocommerce_coupon_is_valid in addition to woocommerce_coupon_error.

= 1.0.0 (06.18.15)=

* Initial release.
