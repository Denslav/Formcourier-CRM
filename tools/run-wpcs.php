<?php
/**
 * Run WordPress Coding Standards from the isolated DEV tool directory.
 */

$root = dirname( __DIR__ );
$candidates = [
    $root . '/tools/wpcs/vendor/bin/phpcs',
    $root . '/vendor/bin/phpcs',
];

if ( DIRECTORY_SEPARATOR === '\\' ) {
    $candidates = array_merge(
        [
            $root . '/tools/wpcs/vendor/bin/phpcs.bat',
            $root . '/vendor/bin/phpcs.bat',
        ],
        $candidates
    );
}

$phpcs = '';
foreach ( $candidates as $candidate ) {
    if ( is_file( $candidate ) ) {
        $phpcs = $candidate;
        break;
    }
}

if ( '' === $phpcs ) {
    fwrite(
        STDERR,
        "WordPress Coding Standards are not installed. Run Composer install inside tools/wpcs, then run composer wpcs.\n"
    );
    exit( 2 );
}

$command = escapeshellarg( $phpcs ) . ' --standard=' . escapeshellarg( $root . '/phpcs.xml.dist' );
passthru( $command, $exit_code );
exit( $exit_code );
