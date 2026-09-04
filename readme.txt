=== FormCourier CRM ===
Contributors: densslav
Tags: crm, contact form 7, wpforms, hubspot, pipedrive
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Contact Form 7 or WPForms to HubSpot or Pipedrive with visual field mapping and privacy-aware logs.

== Description ==

FormCourier CRM sends mapped Contact Form 7 and WPForms submissions to HubSpot CRM or Pipedrive after a site administrator explicitly enables the integration and saves CRM credentials.

The plugin is useful for contact forms, lead capture forms and appointment forms that need to create or update CRM records without custom integration code.

= Main features =

* Contact Form 7 and WPForms support.
* HubSpot contact-only and Contact + Deal modes.
* Pipedrive person with deal or lead modes.
* Separate global field mapping for every supported form and CRM pair.
* Immediate delivery during form submission.
* Built-in CRM connection testing.
* Local technical logs with masked email addresses.
* Optional masked request and response payloads for troubleshooting.
* Encrypted CRM credentials when PHP OpenSSL is available.
* Suggested Privacy Policy text in WordPress.
* Translation-ready interface using the WordPress.org language-pack system, plus bundled offline documentation.

= Commercial version =

FormCourier CRM may show a contextual **Get FormCourier CRM Pro** link on the plugin settings screen and in the Plugins action links. The link opens https://formcourier.site/, an external commercial website operated by the plugin author. The plugin does not contact this website or send site, user, form or telemetry data to it unless an administrator intentionally follows the link.

= Privacy =

FormCourier CRM does not send telemetry or usage statistics to the plugin author.

The plugin contacts a CRM only after an administrator enables the integration, selects a CRM and saves credentials. The fields sent depend on the administrator's mapping and may include a visitor's name, email address, phone number, message, form ID and form name.

A local technical log may store the form provider, CRM provider, processing result, action and a masked email address. Full request and response payload logging is disabled by default. When enabled, known personal and secret fields are masked before storage.

== External services ==

FormCourier CRM connects directly from the WordPress site to third-party CRM APIs only after a site administrator configures credentials and enables the integration. The plugin author does not receive these requests or form submissions.

* **HubSpot API** - Used to search for, create and update HubSpot contacts and, when Contact + Deal mode is selected, to create an associated deal. On form submission, the plugin sends only the form values that the administrator mapped to HubSpot properties. Depending on the mapping, this may include a visitor's name, email address, phone number, message, form ID and form name. The configured HubSpot private app access token is sent in the HTTP Authorization header with each HubSpot API request. When an administrator runs the connection test, the plugin sends the access token and a synthetic `@example.invalid` email search value to verify API access; no visitor submission data is sent by the connection test. HubSpot Customer Terms of Service: https://legal.hubspot.com/terms-of-service HubSpot Privacy Policy: https://legal.hubspot.com/privacy-policy

* **Pipedrive API** - Used to search for, create and update Pipedrive people and organizations and to create or update deals, leads and notes according to the administrator's selected entity mode and field mapping. On form submission, the plugin sends only the mapped form values and related CRM identifiers required for the selected operation. Depending on the mapping, this may include a visitor's name, email address, phone number, message/note, form ID and form name. The configured Pipedrive API token is sent in the `x-api-token` HTTP header with each Pipedrive API request. When an administrator runs the connection test, the plugin sends the API token to the configured `*.pipedrive.com` account domain and requests the current-user endpoint; no visitor submission data is sent by the connection test. Pipedrive Terms of Service: https://www.pipedrive.com/en/terms-of-service Pipedrive Privacy Notice: https://www.pipedrive.com/en/privacy

No CRM request is made until the administrator intentionally configures the relevant CRM credentials. Form submission data is sent only when the integration is enabled. HubSpot and Pipedrive are third-party services and are not affiliated with or endorsed by FormCourier CRM.

== Installation ==

1. Install FormCourier CRM from the WordPress Plugins screen and activate it.
2. Open **FormCourier CRM** in the WordPress administration menu.
3. Select Contact Form 7 or WPForms and choose HubSpot or Pipedrive.
4. Enter the CRM credentials and run the connection test.
5. Open **Forms & Mapping** and map the actual form fields to CRM fields.
6. Enable the integration and submit a test form.
7. Review **Submission Logs** to confirm the result.

== Frequently Asked Questions ==

= Does the plugin require a license key? =

No. FormCourier CRM is free software and does not require activation with the plugin author.

= Does the plugin send data to the plugin author? =

No. The plugin does not send telemetry, form submissions or CRM credentials to the plugin author. Configured form data is sent directly from your WordPress site to HubSpot or Pipedrive.

= Which HubSpot permissions are required? =

Contact-only mode requires contact read and write permissions. Contact + Deal mode also requires permission to create deals.

= Which form fields should I map? =

Use the real Contact Form 7 field names or WPForms field IDs from your form. The bundled documentation includes standard examples for name, email, phone and message fields.

= Are CRM credentials encrypted? =

Yes, when PHP OpenSSL is available. If secure encryption is unavailable, new credentials are not saved.

= Can Lite and Pro be active together? =

Both plugins can be activated, but Lite pauses its delivery hooks while FormCourier CRM Pro is active to prevent duplicate submissions.

= How do I remove stored settings and logs? =

Enable **Delete data on uninstall** before uninstalling the plugin. Otherwise settings and logs are preserved to prevent accidental data loss.

== Screenshots ==

1. General integration settings and the current integration summary.
2. HubSpot and Pipedrive credentials and delivery modes.
3. Visual field mapping for Contact Form 7 and WPForms.
4. Privacy-aware submission logs with masked data.
5. Built-in localized documentation and field mapping examples.

== Changelog ==

= 1.0.1 =
* Added a contextual link to the separately distributed FormCourier CRM Pro commercial offering on the Plugins screen.
* Added a small Pro upgrade section to the Current Integration sidebar card.
* The external commercial site is contacted only when an administrator intentionally clicks the upgrade link.

= 1.0.0 =
* Promoted the fully validated release candidate to the first stable release without changing runtime behavior.
* Confirmed clean Plugin Check results for Plugin Repo, Security, General, Performance and Accessibility.
* Confirmed WordPress Coding Standards, PHP lint, JavaScript syntax and the complete PHPUnit suite.
* Finalized WordPress.org metadata, localized documentation and production packaging.

= 1.0.0-rc1 =
* Promoted the fully validated beta5 codebase to the first release candidate without adding new runtime features.
* Confirmed clean Plugin Check results for Plugin Repo, Security, General, Performance and Accessibility categories.
* Confirmed Contact Form 7 and WPForms delivery to HubSpot and Pipedrive, localized interfaces, privacy-aware logs and Lite/Pro coexistence.
* Corrected the historical beta4 changelog heading and finalized release-candidate metadata and documentation.

= 1.0.0-beta5 =
* Removed the discouraged load_plugin_textdomain() call while preserving bundled translations as a fallback when no WordPress.org language pack exists.
* Prepared custom log-table identifiers with the WordPress %i placeholder before direct database queries.
* Kept prepared values adjacent to get_results() and get_var() calls for Plugin Check analysis.
* Addressed the Plugin Repo warnings reported for beta4.

= 1.0.0-beta4 =
* Fixed corrupted Russian and Ukrainian interface text in compiled MO files.
* Added deterministic PO-to-MO compilation to release checks and builds.
* Added regression tests for MO UTF-8 headers and translated runtime strings.
* Preserved the completed WordPress Coding Standards fixes from beta3.

= 1.0.0-beta3 =
* Completed the remaining WordPress Coding Standards fixes after the beta2 audit.
* Confirmed clean WPCS, PHPUnit and release checks for the release-candidate code path.
* Preserved the validated CRM behavior, translations and Lite/Pro coexistence protection.

= 1.0.0-beta2 =
* Applied WordPress Coding Standards formatting across the runtime PHP files.
* Added complete file, class, property and function documentation.
* Reworked custom log-table SQL handling and documented intentional direct queries.
* Removed direct form POST access from Contact Form 7 email validation.
* Documented read-only administration GET parameters and cryptographic Base64 transport.
* Replaced short ternaries and corrected remaining i18n and parameter documentation issues.

= 1.0.0-beta1 =
* Prepared the plugin metadata and readme for WordPress.org review.
* Expanded external-service and privacy disclosures.
* Added WordPress.org-oriented static release checks and WPCS configuration.
* Limited the Pro conflict notice to relevant administration screens.
* Improved nonce input handling and admin output formatting.
* Excluded Composer vendor files from the plugin-source PHP lint count.

= 1.0.0-alpha4 =
* Completed Russian and Ukrainian translations for the plugin interface and CRM result messages.
* Added localized offline documentation and standard field mapping examples.

== Upgrade Notice ==

= 1.0.1 =
Adds contextual links to the separately distributed FormCourier CRM Pro offering.

= 1.0.0 =
First stable release of FormCourier CRM.
