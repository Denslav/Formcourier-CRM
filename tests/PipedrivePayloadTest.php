<?php

use PHPUnit\Framework\TestCase;

final class PipedrivePayloadTest extends TestCase {

    private function build_payload( array $properties ): array {
        $provider = new FormCourier_CRM_Pipedrive_Provider();
        $method   = new ReflectionMethod( $provider, 'build_payload' );
        $method->setAccessible( true );

        return $method->invokeArgs( $provider, [ $properties ] );
    }

    public function test_deal_custom_fields_are_nested_in_custom_fields_object(): void {
        $custom_key = 'bab02eab6d823099f694eb167433010cc399a6bd';

        $payload = $this->build_payload(
            [
                'person.name'  => 'Мирослава Климчук',
                'person.email' => 'myroslava@example.com',
                $custom_key    => 'google',
                'custom:68030c33d0472c99d6a2a4b842674d9c7d365970' => 'cpc',
            ]
        );

        $this->assertSame( 'google', $payload['deal']['custom_fields'][ $custom_key ] );
        $this->assertSame( 'cpc', $payload['deal']['custom_fields']['68030c33d0472c99d6a2a4b842674d9c7d365970'] );
        $this->assertArrayNotHasKey( $custom_key, $payload['deal'] );
        $this->assertSame( 'google', $payload['lead'][ $custom_key ] );
    }

    public function test_person_payload_contains_email_and_generated_name(): void {
        $payload = $this->build_payload(
            [
                'person.email' => 'client@example.com',
                'person.phone' => '+380 67 123 45 67',
            ]
        );

        $this->assertSame( 'client@example.com', $payload['person']['emails'][0]['value'] );
        $this->assertSame( '+380 67 123 45 67', $payload['person']['phones'][0]['value'] );
        $this->assertSame( 'client@example.com', $payload['person']['name'] );
    }
}
