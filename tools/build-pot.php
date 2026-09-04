<?php
$root = dirname( __DIR__ );
$strings = [];
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
        continue;
    }
    $relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
    if ( 0 === strpos( $relative, 'tests/' ) || 0 === strpos( $relative, 'tools/' ) ) {
        continue;
    }
    $source = file_get_contents( $file->getPathname() );
    if ( false === $source ) {
        continue;
    }
    preg_match_all(
        '/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*([\'\"])(.*?)\1\s*,\s*([\'\"])formcourier-crm\3/s',
        $source,
        $matches,
        PREG_SET_ORDER
    );
    foreach ( $matches as $match ) {
        $msgid = stripcslashes( $match[2] );
        if ( '' !== $msgid ) {
            $strings[ $msgid ] = true;
        }
    }
}
$main_file = $root . '/formcourier-crm.php';
$main_source = is_file( $main_file ) ? file_get_contents( $main_file ) : false;
if ( false !== $main_source ) {
    foreach ( [ 'Plugin Name', 'Description' ] as $header_name ) {
        if ( preg_match( '/^ \* ' . preg_quote( $header_name, '/' ) . ':\s*(.+)$/m', $main_source, $header_match ) ) {
            $header_value = trim( $header_match[1] );
            if ( '' !== $header_value ) {
                $strings[ $header_value ] = true;
            }
        }
    }
}
ksort( $strings, SORT_STRING );
$header = <<<'POT'
msgid ""
msgstr ""
"Project-Id-Version: FormCourier CRM 1.0.1\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/formcourier-crm/\n"
"POT-Creation-Date: 2026-07-25 00:00+0000\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: formcourier-crm\n"

POT;
$output = $header;
foreach ( array_keys( $strings ) as $msgid ) {
    $escaped = addcslashes( $msgid, "\\\"\n\r\t" );
    $output .= 'msgid "' . $escaped . '"' . "\n" . 'msgstr ""' . "\n\n";
}
$file = $root . '/languages/formcourier-crm.pot';
file_put_contents( $file, $output );
echo '[OK] Built ' . basename( $file ) . ': ' . count( $strings ) . " strings\n";
