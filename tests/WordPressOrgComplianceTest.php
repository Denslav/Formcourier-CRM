<?php

use PHPUnit\Framework\TestCase;

final class WordPressOrgComplianceTest extends TestCase {

    public function test_readme_uses_no_more_than_five_tags(): void {
        $readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
        preg_match( '/^Tags:\s*(.+)$/m', (string) $readme, $matches );
        $tags = array_filter( array_map( 'trim', explode( ',', $matches[1] ?? '' ) ) );

        $this->assertGreaterThanOrEqual( 1, count( $tags ) );
        $this->assertTrue( count( $tags ) <= 5 );
    }

    public function test_readme_documents_external_services_and_privacy(): void {
        $readme = (string) file_get_contents( dirname( __DIR__ ) . '/readme.txt' );

        foreach ( [
            '== External services ==',
            '= Privacy =',
            'https://legal.hubspot.com/terms-of-service',
            'https://legal.hubspot.com/privacy-policy',
            'https://www.pipedrive.com/en/terms-of-service',
            'https://www.pipedrive.com/en/privacy',
            'does not send telemetry',
        ] as $required_text ) {
            $this->assertStringContainsString( $required_text, $readme );
        }
    }

    public function test_plugin_has_no_custom_update_or_plugin_uri(): void {
        $main = (string) file_get_contents( dirname( __DIR__ ) . '/formcourier-crm.php' );

        $this->assertStringNotContainsString( 'Plugin URI:', $main );
        $this->assertStringNotContainsString( 'Update URI:', $main );
    }

    public function test_default_mapping_examples_match_documentation(): void {
        $mappings = FormCourier_CRM_Settings::default_mappings();

        $this->assertSame(
            [
                'your-name'    => 'firstname',
                'your-email'   => 'email',
                'your-tel'     => 'phone',
                'your-message' => 'message',
            ],
            $mappings['hubspot']['contact_form_7']
        );
        $this->assertSame(
            [
                '2' => 'person.name',
                '3' => 'person.email',
                '4' => 'person.phone',
                '6' => 'note',
            ],
            $mappings['pipedrive']['wpforms']
        );
    }

    public function test_wordpress_org_development_files_exist(): void {
        $root = dirname( __DIR__ );

        $this->assertFileExists( $root . '/phpcs.xml.dist' );
        $this->assertFileExists( $root . '/tools/static-audit.php' );
        $this->assertFileExists( $root . '/WORDPRESS_ORG_SUBMISSION.md' );
    }
    public function test_translation_loading_uses_wordpress_org_language_packs(): void {
        $i18n = (string) file_get_contents( dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-i18n.php' );

        $this->assertStringNotContainsString( 'load_plugin_textdomain(', $i18n );
        $this->assertStringNotContainsString( 'load_textdomain(', $i18n );
        $this->assertStringNotContainsString( 'Domain Path:', (string) file_get_contents( dirname( __DIR__ ) . '/formcourier-crm.php' ) );
    }

    public function test_direct_log_queries_escape_table_identifiers(): void {
        $logger    = (string) file_get_contents( dirname( __DIR__ ) . '/includes/core/class-formcourier-crm-logger.php' );
        $uninstall = (string) file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

        $this->assertStringContainsString( 'SELECT * FROM %i', $logger );
        $this->assertStringContainsString( '$wpdb->get_results(', $logger );
        $this->assertStringContainsString( '$wpdb->prepare(', $logger );
        $this->assertStringContainsString( '$wpdb->get_var(', $logger );
        $this->assertStringContainsString( '$wpdb->prepare( \'DROP TABLE IF EXISTS %i\'', $uninstall );
    }

}
