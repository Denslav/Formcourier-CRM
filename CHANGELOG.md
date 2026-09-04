# Changelog

## 1.0.1

- Added a contextual **Get FormCourier CRM Pro** action link to the Plugins screen.
- Added a compact Pro upgrade callout to the Current Integration sidebar card.
- Documented that the commercial website is contacted only after an administrator intentionally clicks an upgrade link.

## 1.0.0

- Promoted the fully validated release candidate to the first stable release without changing runtime behavior.
- Confirmed clean Plugin Check results for Plugin Repo, Security, General, Performance and Accessibility.
- Confirmed WordPress Coding Standards, PHP lint, JavaScript syntax and the complete PHPUnit suite.
- Finalized WordPress.org metadata, localized documentation and production packaging.

## 1.0.0-rc1

- Promoted the fully validated beta5 codebase to the first release candidate without adding new runtime features.
- Confirmed clean Plugin Check results for Plugin Repo, Security, General, Performance and Accessibility categories.
- Confirmed Contact Form 7 and WPForms delivery to HubSpot and Pipedrive, localized interfaces, privacy-aware logs and Lite/Pro coexistence.
- Corrected the historical beta4 changelog heading and finalized release-candidate metadata and documentation.

## 1.0.0-beta5

- Removed the discouraged `load_plugin_textdomain()` call while preserving bundled translations as a fallback.
- Prepared custom log-table identifiers with the WordPress `%i` placeholder before direct database queries.
- Kept prepared values adjacent to database execution calls for Plugin Check analysis.
- Addressed all five Plugin Repo warnings reported for beta4.

## 1.0.0-beta4

- Fixed corrupted Russian and Ukrainian text caused by malformed newline escapes in compiled MO headers.
- Added deterministic PO-to-MO compilation to every release check and build.
- Added regression tests that parse the compiled MO files and verify real UTF-8 headers and translations.
- Preserved the completed WordPress Coding Standards fixes from beta3.

## 1.0.0-beta3

- Completed the remaining WordPress Coding Standards fixes after the beta2 audit.
- Confirmed clean WPCS, PHPUnit and release checks for the release-candidate code path.
- Preserved the validated CRM behavior, translations and Lite/Pro coexistence protection.

## 1.0.0-beta2

- Applied WordPress Coding Standards formatting across all runtime PHP files.
- Added complete file, class, property and function PHPDoc coverage.
- Reworked custom log-table SQL preparation and documented intentional direct database operations.
- Removed direct `$_POST` access from Contact Form 7 email validation in favor of the Contact Form 7 submission API.
- Documented read-only administration query parameters and cryptographic Base64 transport exceptions.
- Replaced short ternaries and corrected remaining i18n, parameter and provider documentation findings.
- Preserved the validated Lite feature boundary and existing CRM behavior.

## 1.0.0-beta1

- Prepared `readme.txt` metadata, privacy disclosures and external-service documentation for WordPress.org review.
- Reduced directory tags to five generic discovery tags and kept the short description within the directory limit.
- Added WordPress.org-oriented static release checks and submission documentation.
- Added an isolated WordPress Coding Standards tool configuration and PHPCS ruleset.
- Limited the Lite/Pro conflict notice to the Plugins screen and FormCourier CRM administration pages.
- Improved nonce input handling, administrator redirects and log-page output structure.
- Added log pagination and explicit submit button types.
- Updated new-install default mappings to match the documented Contact Form 7 and WPForms examples.
- Excluded Composer vendor directories from plugin-source PHP lint and release archives.
- Added WordPress.org compliance regression tests.

## 1.0.0-alpha3

- Added HubSpot Contact only and Contact + Deal modes.
- Added optional HubSpot deal pipeline and deal stage settings.
- Added a Current Integration information card for HubSpot and Pipedrive.
- Added Russian and Ukrainian translations for the WordPress Privacy Policy suggestion.
- Declared the Composer root package version to remove the root-version warning.

## 1.0.0-alpha2

- Expanded privacy masking for composite CRM keys, alternate field names, descriptions, and nested email/phone values.
- Removed automatically rendered empty mapping rows.
- Empty mapping pairs are ignored, while incomplete pairs block mapping changes and show an error.
- Added client-side validation for incomplete mapping pairs.
- Added regression tests for privacy masking and mapping validation.

## 1.0.0-alpha1

- Created a separate GPL-compatible FormCourier CRM codebase for WordPress.org preparation.
- Added Contact Form 7 and WPForms integrations.
- Added HubSpot contact synchronization and Pipedrive deal or lead synchronization.
- Added global visual field mapping for every supported form and CRM pair.
- Added immediate CRM delivery, connection testing and privacy-aware submission logs.
- Added encrypted CRM credential storage with fail-closed OpenSSL handling.
- Added WordPress Privacy Policy suggestion text and external-service disclosures.
- Added coexistence protection that pauses Lite delivery hooks while FormCourier CRM Pro is active.
- Physically excluded premium queue, retry, UTM, Telegram, diagnostics, licensing and updater modules.
- Added section-safe settings saving so one settings tab cannot reset another tab.
- Removed HubSpot deal-creation code from the Lite provider.
- Added deterministic DEV and production release builds with automated package-boundary checks.
