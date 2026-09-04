<?php
/**
 * Build GNU MO catalogues from the plugin PO files.
 */

$root = dirname( __DIR__ );

/**
 * Decode one PO quoted string.
 *
 * @param string $value Quoted PO value.
 * @return string
 */
function formcourier_crm_po_decode( string $value ): string {
    $value = trim( $value );

    if ( strlen( $value ) < 2 || '"' !== $value[0] || '"' !== $value[ strlen( $value ) - 1 ] ) {
        throw new RuntimeException( 'Invalid PO string: ' . $value );
    }

    return stripcslashes( substr( $value, 1, -1 ) );
}

/**
 * Parse a PO catalogue.
 *
 * The Lite catalogues use singular msgid/msgstr entries. Continuation lines are
 * supported so the standard PO header is decoded to real newline characters.
 *
 * @param string $path PO file path.
 * @return array<string,string>
 */
function formcourier_crm_parse_po( string $path ): array {
    $lines = file( $path, FILE_IGNORE_NEW_LINES );

    if ( false === $lines ) {
        throw new RuntimeException( 'Unable to read ' . basename( $path ) );
    }

    $catalogue = array();
    $msgid     = null;
    $msgstr    = null;
    $mode      = null;

    $flush = static function () use ( &$catalogue, &$msgid, &$msgstr, &$mode ): void {
        if ( null !== $msgid && null !== $msgstr ) {
            $catalogue[ $msgid ] = $msgstr;
        }

        $msgid  = null;
        $msgstr = null;
        $mode   = null;
    };

    foreach ( $lines as $line ) {
        $trimmed = trim( $line );

        if ( '' === $trimmed ) {
            $flush();
            continue;
        }

        if ( 0 === strpos( $trimmed, '#' ) ) {
            continue;
        }

        if ( preg_match( '/^msgid\s+(".*")$/', $trimmed, $match ) ) {
            $flush();
            $msgid = formcourier_crm_po_decode( $match[1] );
            $mode  = 'msgid';
            continue;
        }

        if ( preg_match( '/^msgstr\s+(".*")$/', $trimmed, $match ) ) {
            $msgstr = formcourier_crm_po_decode( $match[1] );
            $mode   = 'msgstr';
            continue;
        }

        if ( preg_match( '/^(".*")$/', $trimmed, $match ) ) {
            $fragment = formcourier_crm_po_decode( $match[1] );

            if ( 'msgid' === $mode && null !== $msgid ) {
                $msgid .= $fragment;
            } elseif ( 'msgstr' === $mode && null !== $msgstr ) {
                $msgstr .= $fragment;
            }
        }
    }

    $flush();
    ksort( $catalogue, SORT_STRING );

    return $catalogue;
}

/**
 * Compile a catalogue to GNU MO bytes.
 *
 * @param array<string,string> $catalogue Translation catalogue.
 * @return string
 */
function formcourier_crm_compile_mo( array $catalogue ): string {
    ksort( $catalogue, SORT_STRING );

    $originals    = array_keys( $catalogue );
    $translations = array_values( $catalogue );
    $count        = count( $catalogue );

    $original_table_offset    = 28;
    $translation_table_offset = $original_table_offset + ( $count * 8 );
    $original_data_offset     = $translation_table_offset + ( $count * 8 );

    $original_data = '';
    $original_rows = '';
    $offset        = $original_data_offset;

    foreach ( $originals as $original ) {
        $length         = strlen( $original );
        $original_rows .= pack( 'V2', $length, $offset );
        $original_data .= $original . "\0";
        $offset        += $length + 1;
    }

    $translation_data_offset = $original_data_offset + strlen( $original_data );
    $translation_data        = '';
    $translation_rows        = '';
    $offset                  = $translation_data_offset;

    foreach ( $translations as $translation ) {
        $length            = strlen( $translation );
        $translation_rows .= pack( 'V2', $length, $offset );
        $translation_data .= $translation . "\0";
        $offset           += $length + 1;
    }

    $header = pack(
        'V7',
        0x950412de,
        0,
        $count,
        $original_table_offset,
        $translation_table_offset,
        0,
        0
    );

    return $header . $original_rows . $translation_rows . $original_data . $translation_data;
}

/**
 * Read one MO catalogue for build-time validation.
 *
 * @param string $path MO file path.
 * @return array<string,string>
 */
function formcourier_crm_parse_mo( string $path ): array {
    $data = file_get_contents( $path );

    if ( false === $data || strlen( $data ) < 28 ) {
        throw new RuntimeException( 'Invalid MO file: ' . basename( $path ) );
    }

    $header = unpack( 'Vmagic/Vrevision/Vcount/Voriginals/Vtranslations/Vhash_size/Vhash_offset', substr( $data, 0, 28 ) );

    if ( ! is_array( $header ) || 0x950412de !== $header['magic'] ) {
        throw new RuntimeException( 'Invalid MO magic: ' . basename( $path ) );
    }

    $catalogue = array();

    for ( $index = 0; $index < $header['count']; $index++ ) {
        $original_row = unpack( 'Vlength/Voffset', substr( $data, $header['originals'] + ( $index * 8 ), 8 ) );
        $translation_row = unpack( 'Vlength/Voffset', substr( $data, $header['translations'] + ( $index * 8 ), 8 ) );

        if ( ! is_array( $original_row ) || ! is_array( $translation_row ) ) {
            throw new RuntimeException( 'Invalid MO string table: ' . basename( $path ) );
        }

        $original    = substr( $data, $original_row['offset'], $original_row['length'] );
        $translation = substr( $data, $translation_row['offset'], $translation_row['length'] );
        $catalogue[ $original ] = $translation;
    }

    return $catalogue;
}

foreach ( array( 'ru_RU', 'uk' ) as $locale ) {
    $po_path = $root . '/languages/formcourier-crm-' . $locale . '.po';
    $mo_path = $root . '/languages/formcourier-crm-' . $locale . '.mo';
    $catalogue = formcourier_crm_parse_po( $po_path );

    if ( ! isset( $catalogue[''] ) ) {
        throw new RuntimeException( 'PO header is missing: ' . basename( $po_path ) );
    }

    if ( false === strpos( $catalogue[''], "Content-Type: text/plain; charset=UTF-8\n" ) ) {
        throw new RuntimeException( 'PO UTF-8 header is invalid: ' . basename( $po_path ) );
    }

    $bytes = formcourier_crm_compile_mo( $catalogue );

    if ( false === file_put_contents( $mo_path, $bytes ) ) {
        throw new RuntimeException( 'Unable to write ' . basename( $mo_path ) );
    }

    $compiled = formcourier_crm_parse_mo( $mo_path );

    if ( $compiled !== $catalogue ) {
        throw new RuntimeException( 'Compiled MO catalogue does not match PO: ' . $locale );
    }

    if ( false !== strpos( $compiled[''], '\\nContent-Type:' ) ) {
        throw new RuntimeException( 'MO header contains escaped newlines: ' . $locale );
    }

    foreach ( $compiled as $msgid => $translation ) {
        if ( 1 !== preg_match( '//u', $msgid ) || 1 !== preg_match( '//u', $translation ) ) {
            throw new RuntimeException( 'MO catalogue contains invalid UTF-8: ' . $locale );
        }
    }

    echo '[OK] Built ' . basename( $mo_path ) . ': ' . count( $compiled ) . " entries\n";
}
