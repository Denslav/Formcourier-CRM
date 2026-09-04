<?php
use PHPUnit\Framework\TestCase;
final class PackageStructureTest extends TestCase {
    public function test_main_metadata():void{$source=file_get_contents(dirname(__DIR__).'/formcourier-crm.php');$this->assertStringContainsString('Plugin Name: FormCourier CRM',$source);$this->assertStringContainsString('Text Domain: formcourier-crm',$source);$this->assertStringContainsString('Requires PHP: 7.4',$source);}
    public function test_premium_modules_are_physically_absent():void{$root=dirname(__DIR__);foreach(['class-formcourier-crm-queue.php','class-formcourier-crm-telegram-notifier.php','class-formcourier-crm-license-manager.php','class-formcourier-crm-utm-tracker.php','class-formcourier-crm-diagnostics-page.php'] as $file){$matches=glob($root.'/includes/**/'.$file);$this->assertSame([], $matches, $file);}}
    public function test_only_two_form_provider_files_exist():void{$files=glob(dirname(__DIR__).'/includes/form-providers/*.php');$this->assertCount(2,$files);}
    public function test_only_two_crm_provider_files_exist():void{$files=glob(dirname(__DIR__).'/includes/crm-providers/*.php');$this->assertCount(2,$files);}
    public function test_hubspot_lite_provider_contains_contact_and_deal_support():void{
        $source=file_get_contents(dirname(__DIR__).'/includes/crm-providers/class-formcourier-crm-hubspot-provider.php');
        foreach(['DEALS_ENDPOINT','create_or_update_contact_and_deal','create_associated_deal','handle_deal_response','dealname'] as $required){
            $this->assertStringContainsString($required,$source);
        }
    }
    public function test_privacy_translation_files_exist():void{
        $root=dirname(__DIR__).'/languages/';
        foreach(['formcourier-crm-ru_RU.po','formcourier-crm-ru_RU.mo','formcourier-crm-uk.po','formcourier-crm-uk.mo'] as $file){
            $this->assertFileExists($root.$file);
        }
        $ru=file_get_contents($root.'formcourier-crm-ru_RU.po');
        $uk=file_get_contents($root.'formcourier-crm-uk.po');
        $this->assertStringContainsString('Этот рекомендуемый текст', $ru);
        $this->assertStringContainsString('Цей рекомендований текст', $uk);
    }
    public function test_logger_schema_uses_dbdelta_compatible_primary_key():void{
        $source=file_get_contents(dirname(__DIR__).'/includes/core/class-formcourier-crm-logger.php');
        $this->assertStringContainsString('PRIMARY KEY  (id)',$source);
    }

    public function test_readme_external_services_are_disclosed():void{$readme=file_get_contents(dirname(__DIR__).'/readme.txt');$this->assertStringContainsString('= External services =',$readme);$this->assertStringContainsString('HubSpot API',$readme);$this->assertStringContainsString('Pipedrive API',$readme);$this->assertStringContainsString('Terms of Service',$readme);}
    public function test_stable_tag_matches_version():void{$readme=file_get_contents(dirname(__DIR__).'/readme.txt');$main=file_get_contents(dirname(__DIR__).'/formcourier-crm.php');preg_match('/Stable tag:\s*([^\r\n]+)/',$readme,$s);preg_match('/Version:\s*([^\r\n]+)/',$main,$v);$this->assertSame(trim($v[1]),trim($s[1]));}
    public function test_pro_upgrade_link_is_contextual_and_external():void{
        $plugin=file_get_contents(dirname(__DIR__).'/includes/core/class-formcourier-crm-plugin.php');
        $settings=file_get_contents(dirname(__DIR__).'/includes/core/class-formcourier-crm-settings.php');
        $main=file_get_contents(dirname(__DIR__).'/formcourier-crm.php');
        $this->assertStringContainsString("FORMCOURIER_CRM_PRO_URL', 'https://formcourier.site/'",$main);
        $this->assertStringContainsString('Get FormCourier CRM Pro',$plugin);
        $this->assertStringContainsString('Get FormCourier CRM Pro',$settings);
        $this->assertStringContainsString('Opens an external commercial website.',$settings);
    }
}
