<?php
use PHPUnit\Framework\TestCase;
final class EncryptionTest extends TestCase {
    protected function setUp():void{$GLOBALS['formcourier_crm_encryption_test_failures']=[];}
    protected function tearDown():void{$GLOBALS['formcourier_crm_encryption_test_failures']=[];}
    public function test_sensitive_keys_are_lite_only():void{$this->assertSame(['hubspot_access_token','pipedrive_api_token'],FormCourier_CRM_Encryption::get_sensitive_option_keys());}
    public function test_round_trip():void{if(!FormCourier_CRM_Encryption::is_available())$this->markTestSkipped('OpenSSL unavailable');$plain='secret-token';$enc=FormCourier_CRM_Encryption::encrypt($plain);$this->assertNotSame($plain,$enc);$this->assertTrue(FormCourier_CRM_Encryption::is_encrypted($enc));$this->assertSame($plain,FormCourier_CRM_Encryption::decrypt($enc));}
    public function test_existing_encrypted_value_is_not_double_encrypted():void{if(!FormCourier_CRM_Encryption::is_available())$this->markTestSkipped('OpenSSL unavailable');$enc=FormCourier_CRM_Encryption::encrypt('secret');$this->assertSame($enc,FormCourier_CRM_Encryption::encrypt_if_needed($enc));}
    public function test_plain_value_is_returned_by_decrypt_for_legacy_migration():void{$this->assertSame('legacy',FormCourier_CRM_Encryption::decrypt('legacy'));}
    public function test_placeholder_is_lite_specific():void{$this->assertSame('__FORMCOURIER_CRM_SECRET_SAVED__',FormCourier_CRM_Encryption::get_placeholder());$this->assertTrue(FormCourier_CRM_Encryption::is_placeholder('__FORMCOURIER_CRM_SECRET_SAVED__'));}
    public function test_encrypt_fails_closed_when_random_bytes_fails():void{if(!FormCourier_CRM_Encryption::is_available())$this->markTestSkipped('OpenSSL unavailable');$GLOBALS['formcourier_crm_encryption_test_failures']['random_bytes']=true;$this->assertSame('',FormCourier_CRM_Encryption::encrypt('secret'));}
    public function test_encrypt_fails_closed_when_openssl_is_unavailable():void{$GLOBALS['formcourier_crm_encryption_test_failures']['openssl_unavailable']=true;$this->assertSame('',FormCourier_CRM_Encryption::encrypt('secret'));}
}
