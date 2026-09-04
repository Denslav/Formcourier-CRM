<?php
/**
 * Static WordPress.org-oriented checks for FormCourier CRM.
 */

$root = dirname( __DIR__ );

function formcourier_crm_audit_fail( string $message ): void {
    fwrite( STDERR, '[ERROR] ' . $message . PHP_EOL );
    exit( 1 );
}

function formcourier_crm_audit_ok( string $message ): void {
    echo '[OK] ' . $message . PHP_EOL;
}

function formcourier_crm_audit_read( string $path ): string {
    $contents = file_get_contents( $path );

    if ( false === $contents ) {
        formcourier_crm_audit_fail( 'Unable to read ' . $path );
    }

    return $contents;
}

$main   = formcourier_crm_audit_read( $root . '/formcourier-crm.php' );
$readme = formcourier_crm_audit_read( $root . '/readme.txt' );

foreach ( [
    'Plugin Name: FormCourier CRM',
    'Requires at least: 6.2',
    'Requires PHP: 7.4',
    'License: GPLv2 or later',
    'Text Domain: formcourier-crm',
] as $required_header ) {
    if ( false === strpos( $main, $required_header ) ) {
        formcourier_crm_audit_fail( 'Missing plugin header: ' . $required_header );
    }
}

if ( preg_match( '/^Tags:\s*(.+)$/m', $readme, $tag_match ) ) {
    $tags = array_filter( array_map( 'trim', explode( ',', $tag_match[1] ) ) );

    if ( count( $tags ) < 1 || count( $tags ) > 5 ) {
        formcourier_crm_audit_fail( 'readme.txt must contain between 1 and 5 tags.' );
    }
} else {
    formcourier_crm_audit_fail( 'readme.txt Tags header is missing.' );
}

$readme_lines       = preg_split( '/\R/', $readme );
$short_description  = '';
$license_header_seen = false;

foreach ( $readme_lines as $line ) {
    if ( 0 === strpos( $line, 'License URI:' ) ) {
        $license_header_seen = true;
        continue;
    }

    if ( $license_header_seen && '' !== trim( $line ) ) {
        $short_description = trim( $line );
        break;
    }
}

if ( '' === $short_description || strlen( $short_description ) > 150 ) {
    formcourier_crm_audit_fail( 'The readme short description must be present and no longer than 150 characters.' );
}

foreach ( [
    '== External services ==',
    'https://legal.hubspot.com/terms-of-service',
    'https://legal.hubspot.com/privacy-policy',
    'https://www.pipedrive.com/en/terms-of-service',
    'https://www.pipedrive.com/en/privacy',
    '= Privacy =',
    'does not send telemetry',
] as $required_readme_text ) {
    if ( false === strpos( $readme, $required_readme_text ) ) {
        formcourier_crm_audit_fail( 'Required readme disclosure is missing: ' . $required_readme_text );
    }
}

foreach ( [ 'Plugin URI:', 'Update URI:' ] as $forbidden_header ) {
    if ( false !== strpos( $main, $forbidden_header ) ) {
        formcourier_crm_audit_fail( 'Forbidden WordPress.org header found: ' . $forbidden_header );
    }
}

$source = '';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
    if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
        continue;
    }

    $relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );

    if ( 0 === strpos( $relative, 'vendor/' )
        || 0 === strpos( $relative, 'release/' )
        || 0 === strpos( $relative, 'tests/' )
        || 0 === strpos( $relative, 'tools/' )
    ) {
        continue;
    }

    $source .= "\n/* {$relative} */\n" . formcourier_crm_audit_read( $file->getPathname() );
}

foreach ( [
    'license_manager',
    'license server',
    'trial period',
    'remote updater',
    'Update URI:',
    'wp_update_plugins',
    'site_transient_update_plugins',
    'telegram-notifier',
    'utm-tracker',
] as $forbidden_term ) {
    if ( false !== stripos( $source, $forbidden_term ) ) {
        formcourier_crm_audit_fail( 'Forbidden Lite/WordPress.org term found: ' . $forbidden_term );
    }
}

$hubspot  = formcourier_crm_audit_read( $root . '/includes/crm-providers/class-formcourier-crm-hubspot-provider.php' );
$pipedrive = formcourier_crm_audit_read( $root . '/includes/crm-providers/class-formcourier-crm-pipedrive-provider.php' );

foreach ( [ $hubspot, $pipedrive ] as $provider_source ) {
    if ( false === strpos( $provider_source, "'redirection'" ) || false === strpos( $provider_source, "'reject_unsafe_urls'" ) ) {
        formcourier_crm_audit_fail( 'CRM HTTP requests must disable redirects and reject unsafe URLs.' );
    }
}

if ( false === strpos( $pipedrive, "'.pipedrive.com'" ) ) {
    formcourier_crm_audit_fail( 'Pipedrive requests must be restricted to the pipedrive.com host.' );
}

foreach ( [
    'class-formcourier-crm-queue.php',
    'class-formcourier-crm-telegram-notifier.php',
    'class-formcourier-crm-license-manager.php',
    'class-formcourier-crm-utm-tracker.php',
    'class-formcourier-crm-diagnostics-page.php',
] as $forbidden_filename ) {
    $matches = glob( $root . '/includes/**/' . $forbidden_filename );

    if ( ! empty( $matches ) ) {
        formcourier_crm_audit_fail( 'Premium module found in Lite: ' . $forbidden_filename );
    }
}

formcourier_crm_audit_ok( 'Plugin headers' );
formcourier_crm_audit_ok( 'readme.txt metadata and disclosures' );
formcourier_crm_audit_ok( 'External-service HTTP safety' );
formcourier_crm_audit_ok( 'No custom updater, licensing, telemetry or trial code' );
formcourier_crm_audit_ok( 'Lite module boundary' );
