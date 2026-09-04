<?php

use PHPUnit\Framework\TestCase;

final class PipedriveTypedCustomFieldsTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['formcourier_crm_test_http_requests'] = [];
        $GLOBALS['formcourier_crm_test_http_callback'] = null;
        $GLOBALS['formcourier_crm_test_transients']    = [];
    }

    protected function tearDown(): void {
        $GLOBALS['formcourier_crm_test_http_callback'] = null;
    }

    private function invoke_private( FormCourier_CRM_Pipedrive_Provider $provider, string $method_name, array $arguments = [] ) {
        $method = new ReflectionMethod( $provider, $method_name );
        $method->setAccessible( true );

        return $method->invokeArgs( $provider, $arguments );
    }

    private function http_response( int $status_code, array $body ): array {
        return [
            'response' => [
                'code' => $status_code,
            ],
            'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
        ];
    }

    public function test_explicit_address_mapping_wraps_scalar_value_for_deals_and_leads(): void {
        $provider   = new FormCourier_CRM_Pipedrive_Provider();
        $custom_key = 'f6f16e9b788a4ca51a209f95cc688f9f7111dd0b';
        $payload    = $this->invoke_private(
            $provider,
            'build_payload',
            [
                [
                    'person.email' => 'client@example.com',
                    'custom:address:' . $custom_key => 'Чернівці, вул. Головна, 10',
                ],
            ]
        );

        $prepared = $this->invoke_private(
            $provider,
            'prepare_custom_field_values',
            [ 'example', 'secret-token', $payload ]
        );

        $expected = [
            'value' => 'Чернівці, вул. Головна, 10',
        ];

        $this->assertSame( $expected, $prepared['deal']['custom_fields'][ $custom_key ] );
        $this->assertSame( $expected, $prepared['lead'][ $custom_key ] );
        $this->assertArrayNotHasKey( 'custom_field_types', $prepared );
        $this->assertSame( [], $GLOBALS['formcourier_crm_test_http_requests'] );
    }

    public function test_prepared_address_object_is_sanitized_without_double_wrapping(): void {
        $provider = new FormCourier_CRM_Pipedrive_Provider();
        $value    = $this->invoke_private(
            $provider,
            'format_custom_field_value',
            [
                [
                    'value'       => '<b>Kyiv, Khreshchatyk 1</b>',
                    'locality'    => 'Kyiv',
                    'postal_code' => '01001',
                    'ignored'     => 'not-sent',
                ],
                'address',
            ]
        );

        $this->assertSame(
            [
                'value'       => 'Kyiv, Khreshchatyk 1',
                'locality'    => 'Kyiv',
                'postal_code' => '01001',
            ],
            $value
        );
    }

    public function test_empty_address_value_is_not_sent(): void {
        $provider = new FormCourier_CRM_Pipedrive_Provider();

        $this->assertSame(
            null,
            $this->invoke_private( $provider, 'format_custom_field_value', [ '   ', 'address' ] )
        );
        $this->assertSame(
            null,
            $this->invoke_private( $provider, 'format_custom_field_value', [ [], 'address' ] )
        );
    }

    public function test_regular_custom_field_remains_a_string(): void {
        $provider = new FormCourier_CRM_Pipedrive_Provider();

        $this->assertSame(
            'google',
            $this->invoke_private( $provider, 'format_custom_field_value', [ 'google', 'varchar' ] )
        );
    }

    public function test_address_type_is_detected_from_pipedrive_metadata_and_cached(): void {
        $provider   = new FormCourier_CRM_Pipedrive_Provider();
        $custom_key = 'f6f16e9b788a4ca51a209f95cc688f9f7111dd0b';

        $GLOBALS['formcourier_crm_test_http_callback'] = function ( string $url, array $args ) use ( $custom_key ): array {
            return $this->http_response(
                200,
                [
                    'success' => true,
                    'data'    => [
                        'field_code' => $custom_key,
                        'field_type' => 'address',
                    ],
                ]
            );
        };

        $first_type = $this->invoke_private(
            $provider,
            'get_custom_field_type',
            [ 'example', 'secret-token', $custom_key ]
        );
        $second_type = $this->invoke_private(
            $provider,
            'get_custom_field_type',
            [ 'example', 'secret-token', $custom_key ]
        );

        $this->assertSame( 'address', $first_type );
        $this->assertSame( 'address', $second_type );
        $this->assertCount( 1, $GLOBALS['formcourier_crm_test_http_requests'] );
        $this->assertStringContainsString(
            '/api/v2/dealFields/' . $custom_key,
            $GLOBALS['formcourier_crm_test_http_requests'][0]['url']
        );
    }

    public function test_real_send_flow_converts_existing_hash_mapping_to_address_object(): void {
        $provider   = new FormCourier_CRM_Pipedrive_Provider();
        $custom_key = 'f6f16e9b788a4ca51a209f95cc688f9f7111dd0b';
        $sent_deal  = [];

        $GLOBALS['formcourier_crm_test_http_callback'] = function ( string $url, array $args ) use ( $custom_key, &$sent_deal ): array {
            $method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );

            if ( false !== strpos( $url, '/api/v2/dealFields/' . $custom_key ) ) {
                return $this->http_response(
                    200,
                    [
                        'success' => true,
                        'data'    => [
                            'field_code' => $custom_key,
                            'field_type' => 'address',
                        ],
                    ]
                );
            }

            if ( false !== strpos( $url, '/api/v2/persons/search?' ) ) {
                return $this->http_response(
                    200,
                    [
                        'success' => true,
                        'data'    => [
                            'items' => [],
                        ],
                    ]
                );
            }

            if ( 'POST' === $method && false !== strpos( $url, '/api/v2/persons' ) ) {
                return $this->http_response(
                    201,
                    [
                        'success' => true,
                        'data'    => [
                            'id' => 101,
                        ],
                    ]
                );
            }

            if ( 'GET' === $method && false !== strpos( $url, '/api/v2/deals?' ) ) {
                return $this->http_response(
                    200,
                    [
                        'success' => true,
                        'data'    => [],
                    ]
                );
            }

            if ( 'POST' === $method && false !== strpos( $url, '/api/v2/deals' ) ) {
                $sent_deal = json_decode( (string) ( $args['body'] ?? '' ), true );

                return $this->http_response(
                    201,
                    [
                        'success' => true,
                        'data'    => [
                            'id' => 202,
                        ],
                    ]
                );
            }

            return $this->http_response(
                500,
                [
                    'success' => false,
                    'error'   => 'Unexpected test request.',
                ]
            );
        };

        $result = $provider->create_or_update_contact(
            'example',
            'secret-token',
            [
                'person.name'  => 'Катерина Лисенко',
                'person.email' => 'kateryna@example.com',
                $custom_key    => 'Київ, вул. Хрещатик, 1',
            ],
            'deal'
        );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'created', $result['action'] );
        $this->assertSame(
            [
                'value' => 'Київ, вул. Хрещатик, 1',
            ],
            $sent_deal['custom_fields'][ $custom_key ]
        );
        $this->assertSame( 101, $sent_deal['person_id'] );
        $this->assertCount( 5, $GLOBALS['formcourier_crm_test_http_requests'] );
    }

    public function test_cache_key_does_not_expose_api_token_or_domain(): void {
        $provider = new FormCourier_CRM_Pipedrive_Provider();
        $cache_key = $this->invoke_private(
            $provider,
            'get_custom_field_type_cache_key',
            [
                'private-company',
                'super-secret-token',
                'f6f16e9b788a4ca51a209f95cc688f9f7111dd0b',
            ]
        );

        $this->assertStringNotContainsString( 'super-secret-token', $cache_key );
        $this->assertStringNotContainsString( 'private-company', $cache_key );
        $this->assertStringContainsString( 'formcourier_crm_pd_field_', $cache_key );
    }
}
