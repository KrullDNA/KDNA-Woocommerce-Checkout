=== KDNA Checkout ===
Contributors: krulldna
Tags: woocommerce, checkout, elementor, abandoned cart, cart recovery
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Streamlined WooCommerce checkout and abandoned-cart recovery, fully styled in Elementor.

== Description ==

KDNA Checkout replaces the plain default WooCommerce checkout with a fast, distraction-free, conversion-focused checkout that is built and styled entirely inside Elementor. It layers on express one-tap payment buttons, a tidy two-column layout, guest checkout by default, fewer fields, optional address autocomplete, an order bump and trust signals, then adds a built-in abandoned-cart recovery system that captures carts and emails customers back with an admin-built email sequence.

The plugin extends native tools rather than rebuilding them: WooCommerce and the official gateway plugins still handle the transaction, tax, shipping and security underneath. The recovery data layer runs quietly in the background, fully separate from the display layer.

Requirements:

* WooCommerce (active), the plugin pauses with an admin notice if WooCommerce is missing.
* Elementor, for the checkout widget (arrives in a later build stage).

Current build stage: Stage 9 of 12, Google Places address autocomplete.

* KDNA widget category and KDNA Checkout widget registered with Elementor (Atomic architecture).
* The widget renders the native WooCommerce classic shortcode checkout in a two-column grid, customer details left, sticky order summary card right, stacking to one column on mobile with the summary above or below per a control.
* A clean placeholder shows in the Elementor editor instead of a live checkout.
* Widget CSS/JS load only on pages containing the widget.
* Plus everything from Stage 1: HPOS declaration, WooCommerce guard, captured-carts table, admin-only CPTs, settings page shell, clean uninstall.

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

= 0.9.0 =
* Stage 9: optional Google Places address autocomplete.
* Settings > KDNA Checkout gains a real settings form: an autocomplete on/off toggle and a Google API key field (masked by default, Alpine.js show/hide, strictly sanitised).
* When enabled with a key, typing in the address field suggests full addresses; selecting one fills street, suburb/city, postcode, state and country correctly for WooCommerce (billing and shipping, country set before state so WooCommerce can rebuild the state field).
* The Places script loads only when the feature is enabled and only on pages containing the checkout widget, via the widget script dependencies.
* Fail-safe: feature off, key missing or Google unreachable means standard address fields with no errors.

= 0.8.0 =
* Stage 8: trust signals block.
* Trust block inside the checkout widget: payment-method icons (Visa, Mastercard, American Express, PayPal, Apple Pay, Google Pay) plus a secure-checkout reassurance message.
* Each default badge toggles on/off individually; custom badge images can be uploaded (for example Afterpay, Zip or an SSL seal).
* Positionable: in the order summary card below the pay button (default), below the customer details, or full width below the checkout, always outside the AJAX fragments so it survives totals refreshes.
* Full Style controls: alignment, icon size/spacing/colour, message typography and colour, message position and spacing, background, full border group, radius, box-shadow, padding and margin.
* New standalone "KDNA Trust Badges" Elementor widget with the identical controls, placeable anywhere on the page.

= 0.7.0 =
* Stage 7: order bumps.
* Order bump editor under Settings > Order Bumps: product search, optional percentage or fixed discount, description, optional image (featured image, falls back to the product image) and which checkout the bump applies to; the entry title is the headline.
* Tick-to-add bump box above the pay button, showing the original and discounted price.
* Ticking adds the product at the discounted price via AJAX with no reload and updates the totals; unticking removes it cleanly.
* Fail-safe: default unticked and full price; deleted, unpublished, unbuyable or out-of-stock bump products never render or discount; discounts never compound across recalculations.
* Elementor Style controls for the bump box: background, full border group, radius, box-shadow, padding, margin, headline/description/price typography and colour, checkbox accent and size, image size and radius.
* The bump ID is recorded on the order line item for traceability.

= 0.6.0 =
* Stage 6: field optimisation and guest checkout.
* Guest checkout by default with an optional, controllable "create an account" checkbox near the end of the form.
* Drag-to-reorder checkout field editor in Elementor: show/hide, reorder and relabel the standard fields via the woocommerce_checkout_fields filter.
* Optional combined full-name field (the order still receives first and last name) and optional placeholders-as-labels (labels stay available to screen readers).
* Lightweight inline validation: required, email, postcode (country-aware) and phone errors show per field as the shopper goes.
* Fail-safe: the email field can never be hidden, deleted rows fall back to shown, and a hidden country field still submits the store base country.

= 0.5.0 =
* Stage 5: the express payment row.
* Express buttons output by the active gateways (Apple Pay, Google Pay, PayPal Express, Stripe Link, Afterpay/Zip) are gathered into a row at the top of the checkout, above the form.
* Styled "or pay with card below" divider with editable text.
* Fail-safe: the row only appears while at least one gateway button is actually visible; an inactive gateway leaves no gap and no error.
* Content toggles for the row and the divider; Style controls for the container (background, border, radius, shadow, padding, margin, button gap) and the divider (line colour and thickness, text typography and colour, spacing).
* Filters kdna_checkout_express_selectors / kdna_checkout_express_hide_selectors and the kdna_checkout_express_buttons action for extending gateway coverage.

= 0.4.0 =
* Stage 4: the cart strip (mini-cart) at the very top of the checkout.
* Square product tiles (image, name, editable quantity, remove) in a sideways-scrolling row with the cart subtotal on the right.
* Live AJAX updates: quantity edits and removals refresh the strip and the order summary with no page reload; a tidy empty state when the last item goes.
* Item controls modes: Full (default), Subtle, Edit toggle and Locked.
* Separate sticky-on-desktop and sticky-on-mobile toggles.
* Full Elementor style controls for the strip container, tiles, product image, quantity field, remove button, edit link and subtotal.

= 0.3.0 =
* Stage 3: the complete Elementor Style tab for the KDNA Checkout widget.
* Grouped style sections: Columns & Spacing, Headings, Field Labels, Input Fields, Order Summary Card, Order Summary Text & Totals, Pay Button.
* Full box coverage everywhere: background, complete border group, separate border-radius, box-shadow, padding and margin.
* Distinct, controllable input focus state and pay button hover state (hover styling also applies on keyboard focus).
* Optional pay button icon with position, spacing and size controls, persisted across WooCommerce AJAX refreshes.
* Responsive controls for the column layout and all spacing.
* All generated CSS scoped to the widget instance, so two checkouts can be styled independently.

= 0.2.0 =
* Stage 2: Elementor checkout widget (structure).
* KDNA Elementor widget category and KDNA Checkout widget, Atomic architecture compliant.
* Two-column CSS grid reflow of the native classic shortcode checkout with a sticky order summary card.
* Mobile single-column stacking with an above/below order summary control.
* Elementor editor placeholder, no live checkout runs in the editor.
* Front-end assets registered centrally and enqueued only where the widget is used.

= 0.1.0 =
* Stage 1: foundation and data layer.
* Main plugin file with HPOS compatibility declaration and WooCommerce-active guard.
* Activation creates the captured-carts table (kdna_checkout_carts).
* Registered kdna_recovery_email and kdna_order_bump custom post types (admin-only).
* Settings > KDNA Checkout placeholder screen running Alpine.js.
* uninstall.php removes the table, custom post type content and all plugin options.
