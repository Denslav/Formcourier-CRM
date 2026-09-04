# FormCourier CRM - WordPress.org submission checklist

This file is development documentation and is excluded from the production ZIP.

## Before submission

1. Confirm the WordPress.org username in `Contributors` inside `readme.txt`.
2. Run `composer test`.
3. Run `composer audit`.
4. Install WordPress Coding Standards and run `composer wpcs`.
5. Install the official Plugin Check plugin on the test site.
6. Run Plugin Check from Tools > Plugin Check for `formcourier-crm`.
7. Repeat Plugin Check with runtime checks enabled.
8. Validate `readme.txt` with the official WordPress.org readme validator.
9. Test a clean installation on the minimum supported PHP and WordPress versions.
10. Test WordPress 7.0 and PHP 8.4.
11. Test Contact Form 7 and WPForms with HubSpot and Pipedrive.
12. Confirm no real API credentials or personal data are included in screenshots or archives.

## Suggested Plugin Check command

```bash
wp plugin check formcourier-crm
```

To include runtime checks, follow the current Plugin Check documentation for the installed Plugin Check version.

## WordPress.org SVN layout after approval

```text
/assets/
/tags/1.0.1/
/trunk/
```

Place plugin code directly in `/trunk`, copy the stable release to `/tags/1.0.1`, and place icons, banners and screenshots in the top-level `/assets` directory.
