=== KDNA Checkout ===
Contributors: krulldna
Tags: woocommerce, checkout, elementor, abandoned cart, cart recovery
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.9
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Streamlined WooCommerce checkout and abandoned-cart recovery, fully styled in Elementor.

== Description ==

KDNA Checkout replaces the plain default WooCommerce checkout with a fast, distraction-free, conversion-focused checkout that is built and styled entirely inside Elementor. It layers on express one-tap payment buttons, a tidy two-column layout, guest checkout by default, fewer fields, optional address autocomplete, an order bump and trust signals, then adds a built-in abandoned-cart recovery system that captures carts and emails customers back with an admin-built email sequence.

The plugin extends native tools rather than rebuilding them: WooCommerce and the official gateway plugins still handle the transaction, tax, shipping and security underneath. The recovery data layer runs quietly in the background, fully separate from the display layer.

Requirements:

* WooCommerce (active), the plugin pauses with an admin notice if WooCommerce is missing.
* Elementor, for the checkout and trust-badge widgets.
* Classic (shortcode) checkout, which is the reliable, controllable base this plugin styles.

Version 1.2.9, feature-complete. The plugin includes:

* Elementor "KDNA Checkout" widget: the native WooCommerce classic checkout reflowed into a two-column layout with a sticky order summary, fully styleable in Elementor.
* Cart strip (mini-cart) with live AJAX quantity/remove, four editing modes and independent desktop/mobile sticky.
* Express payment row that gathers the active gateways' buttons above the form, with a styled divider.
* Guest checkout by default, an optional account checkbox, a drag-to-reorder field editor and inline validation.
* Order bumps (tick-to-add, optional discount) and a customisable trust-signals block plus a standalone trust widget.
* Optional Google Places address autocomplete.
* Abandoned-cart capture and an admin-built, branded recovery email sequence with merge tags, recovery links and unsubscribe.
* HPOS declared, Atomic Elementor markup, assets loaded only where needed, translation-ready (languages/kdna-checkout.pot).

== Installation ==

1. Upload the `kdna-checkout` folder to `/wp-content/plugins/`, or install the ZIP via Plugins > Add New > Upload Plugin.
2. Make sure WooCommerce is installed and active.
3. Activate KDNA Checkout through the Plugins screen.
4. Visit WooCommerce > KDNA Checkout to confirm the admin screen loads.

After every plugin update: regenerate Elementor CSS and data (Elementor > Tools), clear any page cache, then hard refresh the browser.

== Frequently Asked Questions ==

= Does this plugin process payments? =

No. WooCommerce and the official gateway plugins (Stripe, PayPal, Afterpay/Zip) handle the actual transaction, tax, shipping and security. KDNA Checkout reflows and styles the checkout and adds recovery around it.

= What happens to my data if I deactivate the plugin? =

Nothing is deleted on deactivation. All data (captured carts, recovery emails, order bumps and settings) is removed only when the plugin is deleted from the Plugins screen.

== Changelog ==

= 1.2.9 =
* Live editor preview: an optional "Live checkout in the editor" switch (Layout) renders the real WooCommerce checkout in the Elementor editor instead of the skeleton, so you can style against the true front end without saving and previewing each time. You need a product in your cart; if the cart is empty it falls back to the skeleton with a note. Style changes preview live; after a content change, reload the preview.

= 1.2.8 =
* Coupon box: a "Combine into one box" toggle wraps the "Have a coupon?" question and the coupon field in a single box that expands when opened, instead of two separate boxes, with its own background, border, radius and padding controls.

= 1.2.7 =
* Coupon copy: the "Have a coupon?" question, the "Click here to enter your code" link, and the "If you have a coupon code, please apply it below." open message can now each be edited in the Coupon content section (leave blank for the WooCommerce default).

= 1.2.6 =
* Coupon position: new content control to place the "Have a coupon?" section at the top of the checkout (full width, the default), at the top of the billing details, or between the order summary and the payment methods.
* Cart strip quantity buttons: the minus / plus buttons now size to their symbol and padding, so 0 padding gives a tight button; "Button size" is now an optional fixed square (handy for round buttons).

= 1.2.5 =
* Cart strip quantity buttons: added button padding and, in the vertical layout, a spacing control between the plus and minus buttons, plus a control for the gap between the quantity field and the remove (x) button.

= 1.2.4 =
* Cart strip quantity buttons: the mini-cart quantity field now has custom minus / plus buttons with four layout options (minus and plus either side of the field, both on the right, a vertical stepper, or the native browser spinner with no buttons), and full styling controls for the buttons (size, symbol size, colour, background, border, radius and hover) under Cart Strip: Quantity Field.

= 1.2.3 =
* Coupon bar text styling: the "Have a coupon?" prompt text now has its own typography and colour controls, separate from the "Click here to enter your code" link, so the two can be styled independently.

= 1.2.2 =
* Available coupons toggle: a "Show available coupons" switch hides the "Available Coupons" list that the KDNA Ecommerce Suite injects above the checkout, so it can be turned off on this checkout like any other feature with its own widget.
* Coupon bar icon: the little icon on the "Have a coupon?" bar can be kept, swapped for a custom icon, or removed, and its colour and size are styleable in the "Coupon Field" style section.
* Cart strip shrink is now animated: the tile eases to its compact size while the name and quantity fade out, and the page reclaims the freed space in step with the animation instead of jumping. Honours the browser's reduced-motion setting.

= 1.2.1 =
* Cart strip shrink no longer stays shrunk while the strip is resting at the top of the page; it now stays full height at the top and collapses only once actually scrolled past.
* New "Shrink after scrolling" control sets how far the strip must scroll before it shrinks.

= 1.2.0 =
* Coupon field toggle: the checkout widget now has a "Show coupon field" switch that hides WooCommerce's native "Have a coupon?" field when you use a separate coupon widget.
* Coupon styling: new "Coupon Field" style section with controls for the "Have a coupon?" bar (background, border, radius, padding, link typography and colour) and the coupon input and Apply button (with a hover state).
* Cart strip shrink-on-scroll: an optional "Shrink while stuck" mode collapses the sticky strip to just the product images as you scroll, then grows back to full height at the top of the page, with a "Shrunk tile size" control under Cart Strip: Container. The page reclaims the freed vertical space while shrunk.
* Billing and shipping now stack vertically in the checkout's first column, so "Deliver to a different address?" sits under the billing address instead of pushing the layout into three columns.
* Admin pages moved under the WooCommerce menu (WooCommerce > KDNA Checkout, Captured Carts, Order Bumps, Recovery Emails), keeping everything together with WooCommerce.

= 1.1.0 =
* New standalone "KDNA Cart Strip" widget: the same mini-cart strip, placeable anywhere (for example a full-width section at the very top of the page), with the same content and style controls and live AJAX quantity/remove.
* Cart strip sticky now works reliably on desktop and mobile: driven by JavaScript (fixed positioning with an in-flow spacer) instead of CSS position: sticky, so it holds even when an Elementor ancestor clips overflow or the strip sits in its own short top section.
* Cart strip tiles are equal height and their quantity fields bottom-align across all tiles, even when one product name wraps onto more lines than its neighbours.
* Cart strip controls refactored into a shared helper so the in-checkout strip and the standalone widget stay identical; existing styling is preserved (same control IDs and selectors).

= 1.0.0 =
* Stage 12: polish and packaging. First public release.
* Accessibility pass: consistent visible keyboard focus ring (:focus-visible) on every interactive element, role="group" on the express row, role="alert" on inline validation errors, ARIA labels throughout, and colour-contrast-friendly muted-text defaults (WCAG AA).
* Conditional-loading fix: the admin stylesheet now also loads on the Captured Carts screen; front-end assets remain widget-only.
* Confirmed every translatable string uses the kdna-checkout text domain; added a translation template (languages/kdna-checkout.pot, 327 strings).
* Verified HPOS compatibility and Elementor Atomic markup (optimised markup on).

= 0.11.0 =
* Stage 11: the admin-built recovery email sequence.
* Recovery-email steps (WooCommerce > Recovery Emails): each stores a subject, a body written in the WordPress editor with merge tags, a delay from cart abandonment, and an optional coupon; add unlimited steps, sent in order of their delay.
* Branded HTML email template (templates/emails/recovery-email.php) with logo, brand colour, button colour and footer controls in WooCommerce > KDNA Checkout, styled to match the checkout.
* Merge tags: {customer_name}, {cart_items}, {cart_total}, {recovery_link}, {coupon_code}, {store_name}, {unsubscribe}. The recovery link restores the exact saved cart from its token and lands the customer on the checkout, applying the step coupon.
* A 15-minute cron loop sends each due step to abandoned carts and records what was sent; the sequence stops immediately when the customer buys (cart flips to recovered) or clicks unsubscribe.
* "Send test to me" button, configurable From name/email, and the capture-consent flag is respected (only captured carts are ever emailed).

= 0.10.0 =
* Stage 10: the abandoned-cart capture data layer, fully separate from the display layer.
* Entering an email on checkout captures it via AJAX into the kdna_checkout_carts table with a unique recovery token, a JSON cart snapshot, the total and currency, and an "active" status; the snapshot refreshes as the cart changes.
* Completing the matching order flips the row to "completed", or "recovered" when it had been abandoned.
* A 15-minute WP Cron sweep marks stale active carts "abandoned" after a configurable idle period (default 60 minutes); a daily sweep auto-purges old rows for privacy (default 90 days, 0 keeps forever).
* Capture-consent mode: an optional shopper opt-in checkbox under the email field; nothing is stored without the tick.
* WooCommerce > Captured Carts admin screen listing email, item count, cart value, status and timestamps, filterable by status and paginated.
* No recovery emails are sent yet, that arrives in Stage 11.

= 0.9.0 =
* Stage 9: optional Google Places address autocomplete.
* WooCommerce > KDNA Checkout gains a real settings form: an autocomplete on/off toggle and a Google API key field (masked by default, Alpine.js show/hide, strictly sanitised).
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
* Order bump editor under WooCommerce > Order Bumps: product search, optional percentage or fixed discount, description, optional image (featured image, falls back to the product image) and which checkout the bump applies to; the entry title is the headline.
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
* WooCommerce > KDNA Checkout placeholder screen running Alpine.js.
* uninstall.php removes the table, custom post type content and all plugin options.
