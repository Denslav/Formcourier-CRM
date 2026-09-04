<?php
$root = dirname( __DIR__ );
$mode = $argv[1] ?? 'check';
$version = '1.0.1';
$release_dir = $root . '/release';
$production_name = 'formcourier-crm-' . $version . '.zip';
$development_name = 'formcourier-crm-' . $version . '-dev.zip';

function fail_release( string $message ): void {
    fwrite( STDERR, '[ERROR] ' . $message . "\n" );
    exit( 1 );
}

function relative_path( string $root, string $path ): string {
    return str_replace( '\\', '/', substr( $path, strlen( $root ) + 1 ) );
}

function is_excluded( string $relative, array $exclusions ): bool {
    foreach ( $exclusions as $excluded ) {
        if ( $relative === $excluded || 0 === strpos( $relative, rtrim( $excluded, '/' ) . '/' ) ) {
            return true;
        }
    }
    return false;
}

$main = file_get_contents( $root . '/formcourier-crm.php' );
$readme = file_get_contents( $root . '/readme.txt' );
if ( false === $main || false === $readme ) {
    fail_release( 'Required metadata files are missing.' );
}
preg_match( '/^ \* Version:\s*(.+)$/m', $main, $main_version );
preg_match( '/^Stable tag:\s*(.+)$/m', $readme, $stable_tag );
if ( trim( $main_version[1] ?? '' ) !== $version || trim( $stable_tag[1] ?? '' ) !== $version ) {
    fail_release( 'Plugin version and Stable tag must match ' . $version . '.' );
}
$composer_data = json_decode( (string) file_get_contents( $root . '/composer.json' ), true );
if ( ! is_array( $composer_data ) || ( $composer_data['version'] ?? '' ) !== $version ) {
    fail_release( 'Composer root package version must match ' . $version . '.' );
}
foreach ( [ 'ru_RU', 'uk' ] as $locale ) {
    foreach ( [ 'po', 'mo' ] as $extension ) {
        $translation_file = $root . '/languages/formcourier-crm-' . $locale . '.' . $extension;
        if ( ! is_file( $translation_file ) || 0 === filesize( $translation_file ) ) {
            fail_release( 'Required translation file is missing: ' . basename( $translation_file ) );
        }
    }
}

function parse_catalogue_file( string $path, bool $pot = false ): array {
    $source = file_get_contents( $path );
    if ( false === $source ) {
        fail_release( 'Unable to read translation catalogue: ' . basename( $path ) );
    }
    $catalogue = [];
    if ( $pot ) {
        preg_match_all( '/^msgid "((?:\\\\.|[^"])*)"$/m', $source, $matches );
        foreach ( array_slice( $matches[1], 1 ) as $msgid ) {
            $catalogue[ stripcslashes( $msgid ) ] = '';
        }
    } else {
        preg_match_all( '/^msgid "((?:\\\\.|[^"])*)"\nmsgstr "((?:\\\\.|[^"])*)"$/m', $source, $matches, PREG_SET_ORDER );
        foreach ( $matches as $match ) {
            $msgid = stripcslashes( $match[1] );
            if ( '' === $msgid ) {
                continue;
            }
            $catalogue[ $msgid ] = stripcslashes( $match[2] );
        }
    }
    ksort( $catalogue, SORT_STRING );
    return $catalogue;
}

$pot_catalogue = parse_catalogue_file( $root . '/languages/formcourier-crm.pot', true );
if ( count( $pot_catalogue ) < 140 ) {
    fail_release( 'Translation template unexpectedly contains fewer than 140 strings.' );
}
foreach ( [ 'ru_RU', 'uk' ] as $locale ) {
    $po_catalogue = parse_catalogue_file( $root . '/languages/formcourier-crm-' . $locale . '.po' );
    if ( array_keys( $po_catalogue ) !== array_keys( $pot_catalogue ) ) {
        fail_release( 'Translation catalogue does not match POT: ' . $locale );
    }
    foreach ( $po_catalogue as $msgid => $translation ) {
        if ( '' === trim( $translation ) ) {
            fail_release( 'Empty translation in ' . $locale . ': ' . $msgid );
        }
    }
}

foreach ( [
    'documentation/index.html',
    'documentation/ru/index.html',
    'documentation/uk/index.html',
] as $documentation_file ) {
    $path = $root . '/' . $documentation_file;
    if ( ! is_file( $path ) ) {
        fail_release( 'Localized documentation is missing: ' . $documentation_file );
    }
    $documentation = (string) file_get_contents( $path );
    foreach ( [ '1.0.1', 'your-name', 'your-email', 'your-tel', 'your-message', 'person.name', 'person.email', 'person.phone', 'fb_form_id', 'fb_form_name' ] as $required_text ) {
        if ( false === strpos( $documentation, $required_text ) ) {
            fail_release( 'Documentation example is missing from ' . $documentation_file . ': ' . $required_text );
        }
    }
}
if ( false !== strpos( $main, 'Plugin URI:' ) || false !== strpos( $main, 'Update URI:' ) ) {
    fail_release( 'WordPress.org build must not contain a custom Plugin URI or Update URI.' );
}

passthru( 'php ' . escapeshellarg( $root . '/tools/static-audit.php' ), $audit_code );
if ( 0 !== $audit_code ) {
    fail_release( 'WordPress.org static audit failed.' );
}

passthru( 'php ' . escapeshellarg( $root . '/tools/build-pot.php' ), $pot_code );
if ( 0 !== $pot_code ) {
    fail_release( 'POT generation failed.' );
}

passthru( 'php ' . escapeshellarg( $root . '/tools/build-mo.php' ), $mo_code );
if ( 0 !== $mo_code ) {
    fail_release( 'MO compilation failed.' );
}

$php_count = 0;
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    $relative = relative_path( $root, $file->getPathname() );
    if ( $file->isFile()
        && 'php' === strtolower( $file->getExtension() )
        && ! is_excluded( $relative, [ 'release', 'vendor', 'tools/wpcs/vendor' ] )
    ) {
        exec( 'php -l ' . escapeshellarg( $file->getPathname() ) . ' 2>&1', $lint_output, $lint_code );
        if ( 0 !== $lint_code ) {
            fail_release( implode( "\n", $lint_output ) );
        }
        $php_count++;
    }
}
echo '[OK] PHP lint: ' . $php_count . " files\n";

exec( 'node --check ' . escapeshellarg( $root . '/assets/admin.js' ) . ' 2>&1', $js_output, $js_code );
if ( 0 !== $js_code ) {
    fail_release( implode( "\n", $js_output ) );
}
echo "[OK] JavaScript syntax\n";

$phpcs_candidates = [ $root . '/tools/wpcs/vendor/bin/phpcs', $root . '/vendor/bin/phpcs' ];
if ( DIRECTORY_SEPARATOR === '\\' ) {
    array_unshift( $phpcs_candidates, $root . '/tools/wpcs/vendor/bin/phpcs.bat', $root . '/vendor/bin/phpcs.bat' );
}
$phpcs_binary = '';
foreach ( $phpcs_candidates as $phpcs_candidate ) {
    if ( is_file( $phpcs_candidate ) ) {
        $phpcs_binary = $phpcs_candidate;
        break;
    }
}
if ( '' !== $phpcs_binary ) {
    exec( escapeshellarg( $phpcs_binary ) . ' --standard=' . escapeshellarg( $root . '/phpcs.xml.dist' ) . ' 2>&1', $phpcs_output, $phpcs_code );
    if ( 0 !== $phpcs_code ) {
        fail_release( implode( "\n", $phpcs_output ) );
    }
    echo "[OK] WordPress Coding Standards\n";
} else {
    echo "[WARN] WordPress Coding Standards not installed; run composer wpcs in the DEV environment.\n";
}

$phpunit_binary = $root . '/vendor/bin/phpunit';
$test_runner    = is_file( $phpunit_binary ) ? 'phpunit' : 'compatible';
$test_command   = 'phpunit' === $test_runner
    ? escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $phpunit_binary ) . ' -c ' . escapeshellarg( $root . '/phpunit.xml.dist' )
    : escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/minirunner.php' );

exec( $test_command . ' 2>&1', $test_output, $test_code );
if ( 0 !== $test_code ) {
    fail_release( implode( "\n", $test_output ) );
}
echo '[OK] Tests (' . $test_runner . '): ' . trim( implode( ' ', $test_output ) ) . "\n";

$forbidden = [
    'class-formcourier-crm-queue.php',
    'class-formcourier-crm-telegram-notifier.php',
    'class-formcourier-crm-license-manager.php',
    'class-formcourier-crm-utm-tracker.php',
    'class-formcourier-crm-diagnostics-page.php',
];
foreach ( $forbidden as $filename ) {
    $matches = [];
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
        if ( $file->isFile() && $file->getFilename() === $filename ) {
            $matches[] = $file->getPathname();
        }
    }
    if ( $matches ) {
        fail_release( 'Premium module found in Lite: ' . $filename );
    }
}
$hubspot_source = file_get_contents( $root . '/includes/crm-providers/class-formcourier-crm-hubspot-provider.php' );
foreach ( [ 'DEALS_ENDPOINT', 'create_or_update_contact_and_deal', 'create_associated_deal' ] as $required_term ) {
    if ( false === strpos( (string) $hubspot_source, $required_term ) ) {
        fail_release( 'Required HubSpot Contact + Deal code is missing from Lite: ' . $required_term );
    }
}

$form_provider_files = glob( $root . '/includes/form-providers/*.php' ) ?: [];
$crm_provider_files  = glob( $root . '/includes/crm-providers/*.php' ) ?: [];
if ( 2 !== count( $form_provider_files ) || 2 !== count( $crm_provider_files ) ) {
    fail_release( 'Lite must contain exactly two form providers and two CRM providers.' );
}

echo "[OK] Lite module boundary\n";

if ( 'check' === $mode ) {
    exit( 0 );
}
if ( 'build' !== $mode ) {
    fail_release( 'Unknown mode. Use check or build.' );
}
if ( ! is_dir( $release_dir ) && ! mkdir( $release_dir, 0777, true ) && ! is_dir( $release_dir ) ) {
    fail_release( 'Unable to create release directory.' );
}

$common_exclusions = [ '.git', '.github', '.idea', '.vscode', 'release', 'vendor', 'tools/wpcs/vendor', '.phpunit.result.cache', '.phpunit.cache', '.DS_Store', 'Thumbs.db' ];
$production_exclusions = array_merge( $common_exclusions, [ 'tests', 'tools', 'languages', 'composer.json', 'composer.lock', 'phpunit.xml.dist', 'phpcs.xml.dist', 'minirunner.php', 'README_DEV.md', 'WORDPRESS_ORG_SUBMISSION.md' ] );
$development_exclusions = $common_exclusions;

function build_zip( string $root, string $zip_path, array $exclusions ): array {
    @unlink( $zip_path );
    $files = [];
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
        if ( ! $file->isFile() ) {
            continue;
        }
        $relative = relative_path( $root, $file->getPathname() );
        if ( is_excluded( $relative, $exclusions ) ) {
            continue;
        }
        $files[ $relative ] = $file->getPathname();
    }
    ksort( $files, SORT_STRING );

    if ( class_exists( 'ZipArchive' ) ) {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            fail_release( 'Unable to create ' . $zip_path );
        }
        foreach ( $files as $relative => $path ) {
            $archive_name = 'formcourier-crm/' . $relative;
            $zip->addFile( $path, $archive_name );
            if ( method_exists( $zip, 'setMtimeName' ) ) {
                $zip->setMtimeName( $archive_name, 946684800 );
            }
        }
        $zip->close();
    } else {
        $stage = sys_get_temp_dir() . '/formcourier-crm-release-' . bin2hex( random_bytes( 6 ) );
        $stage_root = $stage . '/formcourier-crm';
        if ( ! mkdir( $stage_root, 0777, true ) && ! is_dir( $stage_root ) ) {
            fail_release( 'Unable to create temporary release directory.' );
        }
        foreach ( $files as $relative => $path ) {
            $destination = $stage_root . '/' . $relative;
            $directory = dirname( $destination );
            if ( ! is_dir( $directory ) && ! mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
                fail_release( 'Unable to create release subdirectory.' );
            }
            if ( ! copy( $path, $destination ) ) {
                fail_release( 'Unable to stage ' . $relative );
            }
            touch( $destination, 946684800 );
        }
        $command = 'cd ' . escapeshellarg( $stage ) . ' && find formcourier-crm -type f -print | LC_ALL=C sort | zip -X -q ' . escapeshellarg( $zip_path ) . ' -@';
        exec( $command, $zip_output, $zip_code );
        if ( 0 !== $zip_code || ! is_file( $zip_path ) ) {
            fail_release( 'Unable to create ZIP with the system zip command.' );
        }
        exec( 'rm -rf ' . escapeshellarg( $stage ) );
    }

    return [
        'file' => basename( $zip_path ),
        'sha256' => hash_file( 'sha256', $zip_path ),
        'size_bytes' => filesize( $zip_path ),
        'entry_count' => count( $files ),
    ];
}

$development = build_zip( $root, $release_dir . '/' . $development_name, $development_exclusions );
$production = build_zip( $root, $release_dir . '/' . $production_name, $production_exclusions );

$manifest = [
    'plugin' => 'formcourier-crm',
    'package_version' => $version,
    'stable_version' => $version,
    'db_version' => '1.0.0',
    'option_name' => 'formcourier_crm_settings',
    'test_runner' => $test_runner,
    'archives' => [ 'development' => $development, 'production' => $production ],
];
$manifest_path = $release_dir . '/formcourier-crm-' . $version . '-manifest.json';
file_put_contents( $manifest_path, json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
echo '[OK] Built ' . $development_name . ' (' . $development['entry_count'] . " files)\n";
echo '[OK] Built ' . $production_name . ' (' . $production['entry_count'] . " files)\n";
echo '[OK] Manifest ' . basename( $manifest_path ) . "\n";
