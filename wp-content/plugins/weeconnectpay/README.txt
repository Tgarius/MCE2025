=== WeeConnectPay - Clover Payment Gateway for WooCommerce ===
Plugin Name: WeeConnectPay
Description: Integrate Clover Payments with your WooCommerce online store
Tags: clover, payments, weeconnect, e-commerce, gateway
Plugin URI: https://www.weeconnectpay.com/
Author: WeeConnectPay
Contributors: weeconnectpay
Stable Tag: 3.13.12
Requires at least: 5.6
Tested Up To: 6.7.2
Requires PHP: 7.4
Text Domain: weeconnectpay
Domain Path: /languages
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires Plugins: woocommerce
WC requires at least: 3.0.4
WC tested up to: 9.7.0

Accept payments easily and quickly with the Clover online Payment gateway by WeeConnectPay.

== Description ==

WeeConnectPay for WooCommerce is a plugin for taking online payments quickly and easily, thanks to Clover technology. WeeConnectPay seamlessly integrates with your online store on WooCommerce.

<strong>New Feature: Block-based Checkout</strong> - Our plugin now integrates with WooCommerce Checkout Blocks!

Directly on the checkout page, buyers can enter their payment information securely using an iframe provided by Clover, without leaving your website. Alternatively, when available, users can also pay with express checkout payment methods such as Google Pay.




<strong>Benefits:</strong>
- A Whole New Payment Gateway: Take payments quickly and securely with Clover.
- Easier Integration: All of your payment methods in one plugin.
- Lower Overhead: Choose from our flexible transaction fee rates.
- PCI Compliance: A more secure way to take payments.
- Accept Google Pay for fast checkout

You can see more information on our [website](https://weeconnectpay.com)

== Installation ==

<strong>Minimum Requirements</strong>
WooCommerce >= 3.0.4

<strong>Further minimum requirements for WooCommerce:</strong>
PHP 7.2 or greater
PHP Module INTL enabled
MySQL 5.6 or greater
WordPress 5.6 or greater
Permalink URL structure to be set to anything other than 'plain'
WP Memory limit of 64 MB or greater (128 MB or higher is preferred)

<strong>Additional Plugin Requirements</strong>
WordPress database table prefix must be properly set and not left empty

In addition, you need an approved business account with Clover.
You can submit a request through WeeConnectPay.

<strong>Automatic Installation</strong>
This is the easiest way to install the WeeConnectPay plugin:

1. Log into your WordPress installation.
2. On the left menu bar, select "Plugins", then "Add New".
3. Search for "WeeConnectPay". In cases where several plugins are listed, check if "WeeConnectPay" is the plugin author.
4. Click "Install Now" and wait until WordPress confirms the successful installation.
5. Activate the plugin. You can find the settings here: WooCommerce > Settings > Checkout > Clover Integration - WeeConnectPay.
6. Log into your Clover dashboard to connect WooCommerce with your Clover Account.

<strong>Important:</strong> You need WooCommerce 3.0.4 or higher to use WeeConnectPay. Otherwise, the settings page of the plugin won't be available. You will get a notification in your WordPress backend if you don't use the correct WooCommerce version.

<strong>Manual Installation</strong>
In case the automatic installation doesn't work:

1. Download the plugin from here via the Download button.
2. Unpack the archive and load the folder via FTP into the directory wp-content/plugins of your WordPress installation.
3. Go to Plugins => Installed plugins and click Activate on WeeConnectPay for WooCommerce.

== Frequently Asked Questions ==


=I installed a previous version of WooCommerce. Will I be able to use WeeConnectPay for WooCommerce?=
No, the plugin is only compatible with WooCommerce versions >= 3.0.4 and we advise you to update to one of these versions. But don't forget to make a backup of your installation first. For making a backup use our free WordPress backup plugin BackWPup.

=Am I able to use WeeConnectPay for multiple ecommerce shops?=
Yes, you can use the WeeConnectPay plugin on various online stores with WooCommerce.

=With WeeConnectPay for WooCommerce, which payment methods can I integrate into my shop?=
With our plugin, you can integrate both credit and debit card payment options.

=Can I use WeeConnectPay for digital products?=
Yes!

=Does WeeConnectPay allow subscription payments?=
You must have a subscription plugin on your online store for WeeConnectPay to take subscription payments. It is also possible to manage subscription payments on the Clover web portal.

=Does the vendor get their money directly no matter the used payment option?=
Vendors receive their money the next morning. Banks are closed on weekends or holidays, so you will receive your money the next working day.

=Does WeeConnectPay work for international transactions too?=
At this time WeeConnectPay can only be used by a Canadian bank account holder. But their buyers can buy and pay all over the world.

=How can I disable the WeeConnectPay Checkout button?=
The WeeConnectPay Checkout button is available in the following places:
– Single Product Page
– Cart Page
– Mini Cart

For each of these, you can decide to enable/disable the button.
To do so just go into WooCommerce > Settings > Checkout Settings.

On this page it is possible to select whether you want to see the buttons.

If you don't want to have Express Checkout Gateway enabled you can disable it from the same page.




== Screenshots ==

1. Securely authenticate the payment gateway directly through Clover.
2. Offer a secure on-site payment method to your customers, without the usual PCI compliance burden.
3. Intuitive real-time validation for payment fields.
4. See what is happening with Clover, as well as the relevant order and payment IDs, directly in the WooCommerce order notes.
5. Supports full and partial refunds directly in the WooCommerce order.
6. Clover generates receipts for each order in your Clover dashboard. They are updated with each payment and refund.

== Changelog ==
= 3.13.12 =
* Enhanced WeeConnectPay payment charges display with a dedicated meta box for improved visibility and organization of transaction details

= 3.13.11 =
* Updated gateway name from "Clover Integration" to "Clover via WeeConnectPay" for clarity
* Updated logger to use a dedicated log file in the plugin's logs directory
* Improved logging reliability by not depending on WordPress debug settings
* Added fallback to WordPress error_log for critical errors

= 3.13.10 =
* Updated failed payment order notes to show more information
* Fixed a bug that could happen preventing failed order notes from being displayed under certain circumstances

= 3.13.6 =
* Fixed a generic plugin error that could affect plugin activation or checkout

= 3.13.5 =
* Fixed Google Pay with Gift Cards
* Added warning message to inform merchants that when an order contains gift cards or mixed payment methods (gift cards + credit cards), they must process full refunds for each transaction separately through the WeeConnectPay Charges section.

= 3.13.4 =
* Enhanced security measures for payment processing
* Improved transaction reliability and error handling
* Optimized checkout performance for high-traffic stores

= 3.13.1 =
* Various bug fixes and optimizations to support multi-charge orders with custom tenders
* Enhanced support for gift card and loyalty card payment processing
* Improved handling of multiple payment methods in a single order
* Optimized order processing workflow for custom tender transactions

= 3.13.0 =
* Added a debug option
* Updated the frequency and quality of log messages
* Added a debug log viewer in the plugin settings page

= 3.12.5 =
* Added additional information for transactions being displayed in the order edit page -- This information changes nothing for your PCI-DSS compliance and allows for custom and/or automated fraud prevention for merchants
* Added a way to refund the full amount of a transaction
* Added additional functions for developers through the gift card helper class to support custom payment and refund flows

= 3.12.1 =
* Added gift card partial payment support

= 3.11.5 =
* Updated the state tracking for the front-end to prevent other plugins

= 3.11.4 =
* Updated the Clover SDK Tokenization logic to handle less than ideal environments

= 3.11.3 =
* Updated Clover SDK initialisation for WooCommerce Blocks - This should fix iframe placeholder translation issues

= 3.11.2 =
* Updated plugin to show more information about the mismatched amounts when checkout amounts would not match -- This is mostly to help us help the merchants figure out which plugin is conflicting and to help us add support for these plugins faster

= 3.11.1 =
* Added the following requirements: WordPress database must be properly set and not empty
* Updated plugin to show when an authentication was successful instead of simply showing the settings as a landing page

= 3.11.0 =
* Updated plugin to log the merchant out if the WeeConnectPay API key is invalidated or expired -- We now email your Clover Employee (Also to the Clover Merchant Owner If they are not the same) to notify you and give you a link to reconnect

= 3.10.7 =
* Added clickable Clover receipt links to WooCommerce order notes
* Updated the permalinks dependency notice to include localized clickable instruction to resolve the issue
* Updated plugin name to include WooCommerce in the Plugin Directory
* Updated requests timeout to help with the refresh token implementation

= 3.10.0 =
* Added support for order fees

= 3.9.0 =
* Added card brand as a column in the WooCommerce Orders listing page. This feature is available in both Classic and Blocks checkout. It is also available for both the HPOS and legacy data storage modes.
* Added card brand in the order notes as well

= 3.8.0 =
* Added High Performance Order Storage (HPOS) compatibility

= 3.7.12 =
* Updated plugin to declare compatibility with WooCommerce Checkout blocks, as well as tested up to version and minimum supported versions detectable by WooCommerce
* Updated compatibility values detected by WordPress
* Additional changes not included in the plugin, related to the pipeline / build process

= 3.7.9 =
* Updated pipeline to include folders needed for the WordPress SVN

= 3.7.5 =
* Fixed a bug where the edit page for block checkout showed that the gateway did not support block-based checkout

= 3.7.3 =
* Updated certain pre-authentication values to be trimmed from extra spaces in order for them to pass the validation in the event extra spaces are added by another plugin or theme

= 3.7.0 =
* Added WooCommerce Blocks Support
* Fixed an issue that prevented unmet dependencies from displaying upon activation and instead displayed a fatal error
* Added workaround for the 'Pro' theme which was preventing the Clover SDK from detecting its environment
* Updated the temporary workaround for refunds to also include shipping fees. This is still related to the bug introduced by an undocumented Clover API breaking change that could affect refunds
* Updated the logic for validation before payment processing for WooCommerce Blocks Checkout (This change does not apply to Classic Checkout)

= 3.6.4 =
* Added a temporary workaround (Exact refund validation) to prevent a bug introduced by an undocumented Clover API breaking change that could affect refunds.

= 3.6.0 =
* Feature: Google reCAPTCHA is now supported as an additional security measure for merchants
* Added other security related options to detect automated transactions

= 3.5.2 =
* The plugin should now be fully translated in French Canadian. (Added fr_CA translation files)

= 3.5.1 =
* Added more information in the order notes (with improved formatting!) from the Clover response when the payment fails.
* Updated some strings to better fit the format of different languages for translators

= 3.5.0 =
* Feature: WeeConnectPay will now detect if the Clover API key has been invalidated and require re-authenticating when it happens in order to re-generate a new Clover API key. This scenario only happens when any API calls to Clover would fail otherwise, due to the key not being valid anymore.
* Updated fraud analysis new feature notice to include a link to the integration settings and added a title in the settings.

= 3.4.0 =
* Updated WeeConnectPay to comply with a new security requirement by Clover; Sending the IP address of the customer to Clover with the payment request. This change is required by Clover before Sept 1st, 2023, to continue processing payments.

= 3.3.1 =
* Removed debug logging before releasing new feature

= 3.3.0 =
* Feature: Introducing order notes as a fraud risk assessment tool for WeeConnectPay. Evaluates mismatches in shipping/billing or billing/credit card postal codes

= 3.2.6 =
* Added SiteGround Optimizer support

= 3.2.5 =
* Updated plugin to check for PHP intl module

= 3.2.4 =
* Fixed php warning

= 3.2.3 =
* Updated plugin to only display standard payment fields if Google Pay does not load properly
* Fixed payment fields not showing when Google Pay did not load properly

= 3.2.2 =
* Updated plugin activation to validate prerequisites before attempting anything else
* Updated plugin activation to display validation errors for pre-authentication in the admin panel

= 3.2.1 =
* Updated plugin admin options to gracefully display an error if something goes wrong with the requests. (IE: Expired or invalid SSL certificate)

= 3.2.0 =
* Feature: WeeConnectPay will now suggest autocompleting the credit card information if the browser has access to it

= 3.1.2 =
* Updated settings and authentication verification failure to reset database values to what a fresh installation would be. This should allow for seamless installation through FTP

= 3.1.0 =
* Improved the authentication verification to detect database exports/imports that create issue due to partially invalid data
* Feature: WeeConnectPay will now log you out of the integration and clean the database entries if the authentication data is not valid. This allows the integration to fetch valid data to authenticate properly afterwards.

= 3.0.7 =
* Removed (Secured by clover) from the gateway label to better fit our updated style
* Fix CVV placeholder being too high when the gateway is not open by default (French-specific bug)
* Improved CSS compatibility with FF, IE, Opera, Safari

= 3.0.6 =
* Added secured by footer on the gateway
* Added credit card logos on the gateway header
* Updated CSS to be sturdier to themes style collisions

= 3.0.5 =
* Added support for a wide range of WooCommerce order item types

= 3.0.4 =
* Added an error notice on checkout when totals do not match due to unsupported discount or gift card plugins

= 3.0.3 =
* Updated CSS rules for Google Pay button
* Added fallback for Google Pay button required values

= 3.0.0 =
* Improved payment processing flow

= 2.5.7 =
* Update singleton design to prevent PHP8 from throwing warnings

= 2.5.6 =
* Update plugin display name
* Improve order notes for some more edge cases

= 2.5.5 =
* Improved logic and order notes to gracefully handle edge cases in the unlikely event of a Clover service interruption

= 2.5.5 =
* Updated CSS rules for Google Pay button
* Added fallback for Google Pay button required values

= 2.5.4 =
* Fix cache-busting for seamless updates when scripts or styles are modified as part of an update

= 2.5.3 =
* Add specific CSS to try and prevent themes from breaking the card tokenization methods separator

= 2.5.2 =
* Update tested-up-to to 6.0.2

= 2.5.1 =
* Add min-height and width for Google Pay with higher specificity to prevent themes breaking the button layout
* Add separator for the 2 card tokenization methods - Clover iframe OR Google Pay

= 2.5.0 =
* Feature: Add Google Pay card tokenization support. This allows customer to use Google Wallet to provide a tokenized card for the payment with Clover instead of typing the card in your website.
* Fix: No longer prevents users from clicking elements on the page where the error fields would normally appear.

= 2.4.9 =
* Fix: Plugin no longer attempts to charge when the order total amount is 0 or less

= 2.4.2 =
* Add minimum required WordPress and PHP versions to plugin header

= 2.4.1 =
* Fix: A Clover customer creation bug when some fields were null. All edge cases should now be covered and allow a customer to be created properly regardless of available data given by WooCommerce.

= 2.4.0 =
* Feature: Add customer creation and link customer data to orders (Data available in the merchant Clover dashboard)

= 2.3.0 =
* Feature: Add support for multiple employees authenticating with the plugin
* Change refund payload to support API changes in relation to the above feature

= 2.2.7 =
* Fix: Callback URL not being properly constructed when filters were used to add query parameters to `home_url()`

= 2.2.6 =
* Add stronger specificity for payment fields CSS to avoid themes overriding our styles or vice-versa
* Fix: A display bug where the credit card field would extend past the rest of the fields on the bottom row
* Fix: A display bug where the payment fields overflowed and displayed a scrollbar

= 2.2.5 =
* Change element IDs to be mounted by the Clover SDK to prevent accidental mounting of our payment fields by other plugins

= 2.2.4 =
* Change default gateway title to contain "(Secured by Clover)"

= 2.2.3 =
* Fix: Disabled a check for an unused value in the plugin that determines if the gateway is ready

= 2.2.0 =
* Feature: Allow searching WooCommerce orders by Clover Order ID or Clover Payment ID
* Fix: Add warning when trying to refund non-positive numbers

= 2.1.1 =
* Feature: Settings page now displays the authenticated Clover merchant name and ID

= 2.0.11 =
* Fix gateway fields sometimes not being displayed when WooCommerce was updating checkout at the same time as changing gateways

= 2.0.10 =
* Change client-side logic to wait for DOM to be done loading

= 2.0.9 =
* Add plugin dependency: Permalink URL structure to be set to anything other than 'plain'
* Change payment callback URL to construct dynamically using permalink settings

= 2.0.8 =
* WordPress Plugin Directory Team compliance changes

= 2.0.7 =
* WordPress Plugin Directory Team compliance changes

= 2.0.6 =
* Fix gateway trying to process order when missing required "county" field for orders with shipping addresses in GB

= 2.0.4 =
* Add proper character escaping for some data
* Fix admin CSS file name

= 2.0.3 =
* Update the splash screen register button with the proper URL

= 2.0.2 =
* Update build process to include payment fields -- No change to the actual plugin end-result

= 2.0.0 =
* Update plugin payment processing front-end and order creation

= 1.4.10 =
* Update plugin and gateway descriptions

= 1.4.9 =
* Fix: Gateway not being detected by WooCommerce when determining the current gateway by gateway ordering

= 1.4.8 =
* Fix: Autoloader no longer loads non-class files to prevent other plugins from accessing them.

= 1.4.7 =
* Fix: Dependency collisions with other plugins
* Update uninstallation logic and plugin description

= 1.4.5 =
* Hotfix: Remove unused top-level menu

= 1.4.4 =
* Bugfix: WooCommerce no longer attempts to load the gateway if dependencies are not met.

= 1.4.3 =
* Updated minimum required WooCommerce version from 3.0.0 to 3.0.4

= 1.4.2 =
* Remove logging

= 1.4 =
* First stable beta release

== Upgrade Notice ==

= 3.4.0 =
Action Required by Sept 1, 2023: Merchants, please update your WeeConnectPay WooCommerce gateway. This essential update aligns with Clover's IP security enhancement, ensuring uninterrupted payment processing. Your cooperation is much appreciated! 🚀

= 3.3 =
WeeConnectPay added a new feature! Upgrade now and get the power of enhanced order notes to stay informed about billing and shipping zip/postal code mismatches during checkout with WeeConnectPay! 🚀💼

= 2.0 =
WeeConnectPay has revamped the payment process in version 2.0. This includes faster checkout and the overall stability is increased.


