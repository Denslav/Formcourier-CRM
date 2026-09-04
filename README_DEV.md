# FormCourier CRM development

## Install the PHPUnit dependencies

From the plugin DEV directory:

```bash
composer install
```

## Run PHPUnit

```bash
composer test
```

## Run the WordPress.org static audit

```bash
composer static-audit
```

## Install WordPress Coding Standards

The WPCS dependencies are isolated from the plugin PHPUnit lock file:

```bash
composer install --working-dir=tools/wpcs
```

Then run:

```bash
composer wpcs
```

## Validate the release

```bash
composer release:check
```

The release check runs metadata checks, translation checks, the WordPress.org static audit, PHP lint, JavaScript syntax, PHPUnit and the Lite module-boundary audit. WPCS is also run when its isolated dependencies are installed.

## Build DEV and production ZIP files

```bash
composer release:build
```

## Run the official Plugin Check

Install and activate the official Plugin Check plugin on the WordPress test site. Open **Tools > Plugin Check**, select `formcourier-crm`, and run all available checks.

A WP-CLI static check can also be started with:

```bash
wp plugin check formcourier-crm
```

Use the instructions for the installed Plugin Check version when runtime checks are required.
