<?php
use PHPUnit\Framework\TestCase;
final class CoexistenceTest extends TestCase {
    public function test_lite_main_hook_runs_late():void{$source=file_get_contents(dirname(__DIR__).'/formcourier-crm.php');$this->assertStringContainsString("add_action( 'plugins_loaded', 'formcourier_crm_init', 99 )",$source);}
    public function test_plugin_checks_pro_constant_before_registering_integrations():void{
        $source=file_get_contents(dirname(__DIR__).'/includes/core/class-formcourier-crm-plugin.php');
        $this->assertStringContainsString("defined( 'FORMCOURIER_CRM_PRO_VERSION' )",$source);
        $this->assertStringContainsString("defined( 'FORMBRIDGE_CRM_VERSION' )",$source);
        $this->assertStringContainsString('prevents duplicate form submissions',$source);
    }
    public function test_lite_option_and_table_names_are_isolated():void{$this->assertSame('formcourier_crm_settings',FORMCOURIER_CRM_OPTION_NAME);$this->assertSame('wp_formcourier_crm_logs',FormCourier_CRM_Logger::get_table_name());}
}
