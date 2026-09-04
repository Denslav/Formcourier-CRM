<?php
use PHPUnit\Framework\TestCase;
final class LoggerPrivacyTest extends TestCase {
    protected function setUp():void{$GLOBALS['formcourier_crm_test_options']=[];$GLOBALS['wpdb']->last_insert_data=[];$GLOBALS['wpdb']->insert_id=0;}
    public function test_email_is_masked_by_default():void{FormCourier_CRM_Logger::add(['email'=>'john@example.com']);$this->assertSame('j***@example.com',$GLOBALS['wpdb']->last_insert_data['email']);}
    public function test_payloads_are_empty_by_default():void{FormCourier_CRM_Logger::add(['request_body'=>['email'=>'john@example.com']]);$this->assertSame('',$GLOBALS['wpdb']->last_insert_data['request_body']);}
    public function test_enabled_payloads_are_masked():void{update_option(FORMCOURIER_CRM_OPTION_NAME,['log_payload_data_enabled'=>'1']);FormCourier_CRM_Logger::add(['request_body'=>['email'=>'john@example.com','phone'=>'+380671234567','firstname'=>'John']]);$body=json_decode($GLOBALS['wpdb']->last_insert_data['request_body'],true);$this->assertSame('***hidden***',$body['email']);$this->assertSame('***hidden***',$body['phone']);$this->assertSame('***hidden***',$body['firstname']);}
    public function test_json_response_strings_are_decoded_and_personal_fields_are_masked():void{
        update_option(FORMCOURIER_CRM_OPTION_NAME,['log_payload_data_enabled'=>'1']);
        FormCourier_CRM_Logger::add([
            'response_body'=>wp_json_encode([
                'properties'=>[
                    'firstname'=>'John',
                    'lastname'=>'Smith',
                    'message'=>'Call me tomorrow',
                    'form_name'=>'Contact form',
                ],
            ]),
        ]);
        $body=json_decode($GLOBALS['wpdb']->last_insert_data['response_body'],true);
        $this->assertSame('***hidden***',$body['properties']['firstname']);
        $this->assertSame('***hidden***',$body['properties']['lastname']);
        $this->assertSame('***hidden***',$body['properties']['message']);
        $this->assertSame('Contact form',$body['properties']['form_name']);
    }

    public function test_composite_and_crm_specific_personal_fields_are_masked():void{
        update_option(FORMCOURIER_CRM_OPTION_NAME,['log_payload_data_enabled'=>'1']);
        FormCourier_CRM_Logger::add(['request_body'=>[
            'person.name'=>'Михайло Поліщук',
            'Last Name'=>'Черненко',
            'Description'=>'Please call me tomorrow',
            'form_name'=>'Contact form',
        ]]);
        $body=json_decode($GLOBALS['wpdb']->last_insert_data['request_body'],true);
        $this->assertSame('***hidden***',$body['person.name']);
        $this->assertSame('***hidden***',$body['Last Name']);
        $this->assertSame('***hidden***',$body['Description']);
        $this->assertSame('Contact form',$body['form_name']);
    }

    public function test_nested_email_and_phone_value_fields_are_masked():void{
        update_option(FORMCOURIER_CRM_OPTION_NAME,['log_payload_data_enabled'=>'1']);
        FormCourier_CRM_Logger::add(['response_body'=>[
            'person'=>[
                'phones'=>[['label'=>'work','value'=>'+380671234567']],
                'emails'=>[['label'=>'work','value'=>'john@example.com']],
            ],
        ]]);
        $body=json_decode($GLOBALS['wpdb']->last_insert_data['response_body'],true);
        $this->assertSame('***hidden***',$body['person']['phones'][0]['value']);
        $this->assertSame('***hidden***',$body['person']['emails'][0]['value']);
        $this->assertSame('work',$body['person']['phones'][0]['label']);
    }

    public function test_log_table_does_not_use_pro_table():void{$this->assertStringNotContainsString('formbridge_crm_logs',FormCourier_CRM_Logger::get_table_name());}
}
