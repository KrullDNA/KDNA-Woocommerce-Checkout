=== KDNA Checkout ===
Contributors: krulldna
Tags: woocommerce, checkout, elementor, abandoned cart, cart recovery
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Streamlined WooCommerce checkout and abandoned-cart recovery, fully styled in Elementor.

== Description ==

KDNA Checkout replaces the plain default WooCommerce checkout with a fast, distraction-free, conversion-focused checkout that is built and styled entirely inside Elementor. It layers on express one-tap payment buttons, a tidy two-column layout, guest checkout by default, fewer fields, optional address autocomplete, an order bump and trust signals, then adds a built-in abandoned-cart recovery system that captures carts and emails customers back with an admin-built email sequence.

The plugin extends native tools rather than rebuilding them: WooCommerce and the official gateway plugins still handle the transaction, tax, shipping and security underneath. The recovery data layer runs quietly in the background, fully separate from the display layer.

Requirements:

* WooCommerce (active), the plugin pauses with an admin notice if WooCommerce is missing.
* Elementor, for the checkout widget (arrives in a later build stage).

Current build stage: Stage 1 of 12, foundation and data layer.

* Plugin scaffold with HPOS compatibility declared and a WooCommerce-active guard.
* Captured-carts database table created on activation.
* Recovery email and order bump custom post types registered (admin-only).
* Settings > KDNA Checkout page shell running Alpine.js with a placeholder.
* Clean uninstall that removes the table, custom post type content and options.

== Installation ==

1. Upload the `kdna-checkout` folder to `/wp-content/plugins/`, or install the ZIP via Plugins > Add New > Upload Plugin.
2. Make sure WooCommerce is installed and active.
3. Activate KDNA Checkout through the Plugins screen.
4. Visit Settings > KDNA Checkout to confirm the admin screen loads.

After every plugin update: regenerate Elementor CSS and data (Elementor > Tools), clear any page cache, then hard refresh the browser.

== Frequently Asked Questions ==

= Does this plugin process payments? =

No. WooCommerce and the official gateway plugins (Stripe, PayPal, Afterpay/Zip) handle the actual transaction, tax, shipping and security. KDNA Checkout reflows and styles the checkout and adds recovery around it.

= What happens to my data if I deactivate the plugin? =

Nothing is deleted on deactivation. All data (captured carts, recovery emails, order bumps and settings) is removed only when the plugin is deleted from the Plugins screen.

== Changelog ==

= 0.1.0 =
* Stage 1: foundation and data layer.
* Main plugin file with HPOS compatibility declaration and WooCommerce-active guard.
* Activation creates the captured-carts table (kdna_checkout_carts).
* Registered kdna_recovery_email and kdna_order_bump custom post types (admin-only).
* Settings > KDNA Checkout placeholder screen running Alpine.js.
* uninstall.php removes the table, custom post type content and all plugin options.
