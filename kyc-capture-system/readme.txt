=== KYC Capture System ===
Contributors: yourusername
Tags: kyc, customer capture, crm, loyalty, birthday reminders
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular Know Your Customer (KYC) system for small businesses — capture customer details, track birthdays, manage loyalty, and more.

== Description ==

**KYC Capture System** is a lightweight, modular customer data plugin built for small and medium businesses. On first install, an onboarding wizard guides you to select your business type (Bakery, Retail, Service, or Professional Services), and only the features you actually need are loaded — keeping your site fast.

= Core Features (always available) =

* Floating quick-capture popup (name + phone) with GDPR consent checkbox
* Full profile page via `[kyc_form]` shortcode
* Customer list table in admin with search, sort, and CSV export
* WooCommerce silent sync — auto-imports customers on checkout
* Webhook integration for Zapier, Make, and other automation tools

= Optional Modules (selected at setup) =

* **Birthday & Anniversary Reminders** — Daily email reminders for upcoming customer celebrations
* **Family Social Graph** — Link family members by phone number; mutual relationship confirmation
* **Customer Tags** — Categorise customers (VIP, Wedding Client, Bulk Order, etc.)
* **Customer Preferences & Notes** — Store order preferences, allergen notes, and flavour choices
* **Loyalty Points / Visit Counter** — Track repeat visits and reward loyal customers
* **Referral Tracking** — Track how customers found you and who referred them
* **WooCommerce & Webhook Integration** — Auto-sync and fire webhooks on every capture

= Privacy & GDPR =

* Explicit consent checkbox required before any personal data is stored
* Full integration with WordPress Personal Data Export and Erasure tools (Tools > Export/Erase Personal Data)
* All data stored locally in your own WordPress database — no data sent to any external server unless you configure a Webhook URL

= External Services =

If you configure a **Webhook URL** in Settings, customer data (name, phone, email) will be sent to that URL on each new capture. This data will be received by the external service you specify (e.g. Zapier, Make). You are responsible for ensuring that service's terms of use permit receipt of personal data, and for disclosing this use of data in your own privacy policy.

No other external connections are made by this plugin.

== Installation ==

1. Upload the `kyc-capture-system` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Complete the **Onboarding Wizard** that launches automatically on first activation
4. Add the `[kyc_form]` shortcode to any page to show the full customer profile form
5. Configure settings at **Customer Data > Settings**

== Frequently Asked Questions ==

= Does this plugin send my customer data anywhere? =

No — all data is stored in your own WordPress database. The only exception is if you configure a Webhook URL in Settings, in which case new customer data is sent to the URL you specify (e.g. Zapier).

= Is this GDPR compliant? =

The plugin provides tools to help with GDPR compliance (consent checkbox, data export, data erasure). You remain responsible for your own privacy policy and compliance obligations.

= Can I choose which features to use? =

Yes. The onboarding wizard lets you select your business type and the features are automatically configured. You can change active features any time at **Customer Data > ⚙ Features**.

= Will this work without WooCommerce? =

Yes. WooCommerce integration is optional and only activates if you select the **Integrations** module and WooCommerce is installed.

= Can I reset the onboarding wizard? =

Yes — visit **Admin > Customer Data > ⚙ Features** to change your active modules, or navigate directly to `wp-admin/admin.php?page=kyc-onboarding`.

== Screenshots ==

1. The floating KYC button and quick-capture popup
2. Full customer profile page (frontend)
3. Customer Data admin list table with tags and loyalty points
4. Onboarding wizard — select your business type
5. Birthday & Anniversary upcoming dates page

== Changelog ==

= 3.0.0 =
* Complete modular rewrite — modules load only when selected
* New: Onboarding wizard with business presets
* New: Tags, Loyalty Points, Order Preferences, and Referral Tracking modules
* New: Family Social Graph with mutual link confirmation
* Security: Column whitelist on all DB update operations
* Security: httponly + SameSite cookie flags
* Security: GDPR eraser null-format bug fixed
* Performance: Registry, presets, and file_exists() results cached per request
* Performance: Admin/public classes loaded only in their respective contexts

= 2.0.0 =
* Added Birthday & Anniversary Reminder system
* Added Family Social Graph (linked contacts)
* Added WooCommerce silent sync
* Added Zapier/Make webhook integration
* GDPR personal data export and erasure

= 1.0.0 =
* Initial release — quick-capture popup, full profile page, admin list table

== Upgrade Notice ==

= 3.0.0 =
Major modular rewrite. On first load after upgrade the database will be automatically migrated. No data is lost. You will be prompted to confirm your active modules.

