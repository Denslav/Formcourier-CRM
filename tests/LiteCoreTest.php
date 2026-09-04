<?php
use PHPUnit\Framework\TestCase;
final class LiteCoreTest extends TestCase {
    protected function setUp():void{$GLOBALS['formcourier_crm_test_options']=[];$GLOBALS['formcourier_crm_test_settings_errors']=[];}
    public function test_defaults_are_lite_only():void{$d=FormCourier_CRM_Settings::defaults();$this->assertSame('contact_form_7',$d['form_provider']);$this->assertSame('hubspot',$d['crm_provider']);$this->assertSame('contact',$d['hubspot_submission_mode']);$this->assertSame('appointmentscheduled',$d['hubspot_deal_stage_id']);$this->assertArrayNotHasKey('telegram_enabled',$d);$this->assertArrayNotHasKey('sending_mode',$d);}
    public function test_supported_providers_are_limited():void{$this->assertSame(['hubspot','pipedrive'],array_keys(FormCourier_CRM_Provider_Router::definitions()));}
    public function test_default_mappings_cover_all_pairs():void{$m=FormCourier_CRM_Settings::default_mappings();$this->assertCount(2,$m);$this->assertArrayHasKey('contact_form_7',$m['hubspot']);$this->assertArrayHasKey('wpforms',$m['pipedrive']);}
    public function test_hubspot_contact_deal_settings_are_sanitized():void{
        $s=new FormCourier_CRM_Settings();
        $out=$s->sanitize([
            '_section'=>'crm',
            'hubspot_submission_mode'=>'contact_deal',
            'hubspot_deal_pipeline_id'=>' pipeline-1 ',
            'hubspot_deal_stage_id'=>' stage-2 ',
        ]);
        $this->assertSame('contact_deal',$out['hubspot_submission_mode']);
        $this->assertSame('pipeline-1',$out['hubspot_deal_pipeline_id']);
        $this->assertSame('stage-2',$out['hubspot_deal_stage_id']);
    }
    public function test_current_integration_card_renders_hubspot_mode():void{
        $settings=new FormCourier_CRM_Settings();
        $method=new ReflectionMethod(FormCourier_CRM_Settings::class,'render_current_integration');
        $method->setAccessible(true);
        $values=FormCourier_CRM_Settings::defaults();
        $values['integration_enabled']='1';
        $values['hubspot_submission_mode']='contact_deal';
        ob_start();
        $method->invoke($settings,$values);
        $html=(string)ob_get_clean();
        $this->assertStringContainsString('Current Integration',$html);
        $this->assertStringContainsString('Contact + Deal',$html);
        $this->assertStringContainsString('appointmentscheduled',$html);
        $this->assertStringContainsString('Get FormCourier CRM Pro',$html);
        $this->assertStringContainsString('https://formcourier.site/',$html);
        $this->assertStringContainsString('Opens an external commercial website.',$html);
    }
    public function test_plugin_action_links_include_contextual_pro_offer():void{
        $plugin=new FormCourier_CRM_Plugin();
        $links=$plugin->action_links(['deactivate'=>'<a href="#">Deactivate</a>']);
        $html=implode(' | ',$links);
        $this->assertStringContainsString('Get FormCourier CRM Pro',$html);
        $this->assertStringContainsString('https://formcourier.site/',$html);
        $this->assertStringContainsString('Settings',$html);
        $this->assertStringContainsString('target="_blank"',$html);
    }
    public function test_mapping_maps_values():void{$s=new FormCourier_CRM_Settings();$mapped=$s->map_submission_data(['your-name'=>'Ann','your-email'=>'a@example.com'],wp_json_encode(['your-name'=>'firstname','your-email'=>'email']));$this->assertSame(['firstname'=>'Ann','email'=>'a@example.com'],$mapped);}
    public function test_invalid_mapping_returns_empty_payload():void{$s=new FormCourier_CRM_Settings();$this->assertSame([],$s->map_submission_data(['a'=>'b'],'broken'));}
    public function test_sanitizer_limits_forms_and_crms():void{$s=new FormCourier_CRM_Settings();$out=$s->sanitize(['form_provider'=>'gravityforms','crm_provider'=>'zoho']);$this->assertSame('contact_form_7',$out['form_provider']);$this->assertSame('hubspot',$out['crm_provider']);}
    public function test_mapping_sanitizer_removes_duplicates():void{$s=new FormCourier_CRM_Settings();$out=$s->sanitize(['field_mappings'=>['hubspot'=>['contact_form_7'=>[['form'=>'a','crm'=>'x'],['form'=>'a','crm'=>'y'],['form'=>'b','crm'=>'x'],['form'=>'b','crm'=>'z']]]]]);$this->assertSame(['a'=>'x','b'=>'z'],$out['field_mappings']['hubspot']['contact_form_7']);}
    public function test_mapping_sanitizer_drops_fully_empty_rows():void{
        $s=new FormCourier_CRM_Settings();
        $out=$s->sanitize(['_section'=>'mapping','field_mappings'=>['hubspot'=>['contact_form_7'=>[
            ['form'=>'your-email','crm'=>'email'],
            ['form'=>'   ','crm'=>'  '],
        ]]]]);
        $this->assertSame(['your-email'=>'email'],$out['field_mappings']['hubspot']['contact_form_7']);
        $this->assertSame([],$GLOBALS['formcourier_crm_test_settings_errors']);
    }

    public function test_incomplete_mapping_row_blocks_mapping_changes():void{
        $saved=FormCourier_CRM_Settings::default_mappings();
        $GLOBALS['formcourier_crm_test_options'][FORMCOURIER_CRM_OPTION_NAME]=['field_mappings'=>$saved];
        $s=new FormCourier_CRM_Settings();
        $out=$s->sanitize(['_section'=>'mapping','field_mappings'=>['hubspot'=>['contact_form_7'=>[
            ['form'=>'your-email','crm'=>''],
        ]]]]);
        $this->assertSame($saved,$out['field_mappings']);
        $this->assertCount(1,$GLOBALS['formcourier_crm_test_settings_errors']);
        $this->assertSame('formcourier_crm_incomplete_mapping',$GLOBALS['formcourier_crm_test_settings_errors'][0]['code']);
    }

    public function test_mapping_page_does_not_render_automatic_empty_rows():void{
        $settings=new FormCourier_CRM_Settings();
        $method=new ReflectionMethod(FormCourier_CRM_Settings::class,'render_mapping');
        $method->setAccessible(true);
        ob_start();
        $method->invoke($settings,FormCourier_CRM_Settings::defaults());
        $html=(string)ob_get_clean();
        $this->assertStringNotContainsString('value=""', $html);
        $this->assertStringContainsString('data-crm="hubspot"', $html);
        $this->assertStringContainsString('formcourier-crm-add-row', $html);
    }

    public function test_general_tab_save_preserves_crm_credentials_and_mappings():void{
        $encrypted=FormCourier_CRM_Encryption::encrypt('secret-token');
        $mappings=FormCourier_CRM_Settings::default_mappings();
        $GLOBALS['formcourier_crm_test_options'][FORMCOURIER_CRM_OPTION_NAME]=[
            'integration_enabled'=>'1',
            'form_provider'=>'wpforms',
            'crm_provider'=>'pipedrive',
            'hubspot_access_token'=>$encrypted,
            'pipedrive_company_domain'=>'company.pipedrive.com',
            'pipedrive_api_token'=>$encrypted,
            'field_mappings'=>$mappings,
            'log_payload_data_enabled'=>'1',
        ];
        $out=(new FormCourier_CRM_Settings())->sanitize([
            '_section'=>'general',
            'integration_enabled'=>'1',
            'form_provider'=>'contact_form_7',
            'crm_provider'=>'hubspot',
        ]);
        $this->assertSame($encrypted,$out['hubspot_access_token']);
        $this->assertSame($encrypted,$out['pipedrive_api_token']);
        $this->assertSame('company.pipedrive.com',$out['pipedrive_company_domain']);
        $this->assertSame($mappings,$out['field_mappings']);
        $this->assertSame('0',$out['log_payload_data_enabled']);
        $this->assertArrayNotHasKey('_section',$out);
    }
    public function test_crm_tab_save_preserves_general_and_mapping_settings():void{
        $mappings=FormCourier_CRM_Settings::default_mappings();
        $encrypted=FormCourier_CRM_Encryption::encrypt('saved-token');
        $GLOBALS['formcourier_crm_test_options'][FORMCOURIER_CRM_OPTION_NAME]=[
            'integration_enabled'=>'1',
            'form_provider'=>'wpforms',
            'crm_provider'=>'pipedrive',
            'hubspot_access_token'=>$encrypted,
            'field_mappings'=>$mappings,
        ];
        $out=(new FormCourier_CRM_Settings())->sanitize([
            '_section'=>'crm',
            'hubspot_access_token'=>'',
            'pipedrive_company_domain'=>'new-company.pipedrive.com',
            'pipedrive_entity_type'=>'lead',
        ]);
        $this->assertSame('1',$out['integration_enabled']);
        $this->assertSame('wpforms',$out['form_provider']);
        $this->assertSame('pipedrive',$out['crm_provider']);
        $this->assertSame($encrypted,$out['hubspot_access_token']);
        $this->assertSame($mappings,$out['field_mappings']);
        $this->assertSame('new-company.pipedrive.com',$out['pipedrive_company_domain']);
        $this->assertSame('lead',$out['pipedrive_entity_type']);
    }
    public function test_mapping_tab_save_preserves_general_and_crm_settings():void{
        $encrypted=FormCourier_CRM_Encryption::encrypt('saved-token');
        $GLOBALS['formcourier_crm_test_options'][FORMCOURIER_CRM_OPTION_NAME]=[
            'integration_enabled'=>'1',
            'form_provider'=>'wpforms',
            'crm_provider'=>'hubspot',
            'hubspot_access_token'=>$encrypted,
            'pipedrive_company_domain'=>'company.pipedrive.com',
        ];
        $out=(new FormCourier_CRM_Settings())->sanitize([
            '_section'=>'mapping',
            'field_mappings'=>[
                'hubspot'=>[
                    'contact_form_7'=>[
                        ['form'=>'your-email','crm'=>'email'],
                    ],
                ],
            ],
        ]);
        $this->assertSame('1',$out['integration_enabled']);
        $this->assertSame('wpforms',$out['form_provider']);
        $this->assertSame($encrypted,$out['hubspot_access_token']);
        $this->assertSame('company.pipedrive.com',$out['pipedrive_company_domain']);
        $this->assertSame(['your-email'=>'email'],$out['field_mappings']['hubspot']['contact_form_7']);
    }
    public function test_dispatcher_accepts_optional_submission_context():void{
        $settings=new FormCourier_CRM_Settings();
        $router=new FormCourier_CRM_Provider_Router($settings);
        $dispatcher=new FormCourier_CRM_CRM_Dispatcher($router);
        $result=$dispatcher->create_or_update_contact([],['form_id'=>'10']);
        $this->assertFalse($result['success']);
    }

    public function test_submission_meta_adds_form_fields():void{$data=FormCourier_CRM_Submission_Meta::append_form_data([],12,'Contact');$this->assertSame('12',$data['form_id']);$this->assertSame('Contact',$data['form_name']);}
    public function test_email_extraction():void{$this->assertSame('john@example.com',FormCourier_CRM_Submission_Meta::extract_email(['your-email'=>'john@example.com']));}
    public function test_dispatcher_rejects_empty_payload():void{$settings=new FormCourier_CRM_Settings();$router=new FormCourier_CRM_Provider_Router($settings);$d=new FormCourier_CRM_CRM_Dispatcher($router);$this->assertFalse($d->create_or_update_contact([])['success']);}
    public function test_logger_table_is_isolated():void{$this->assertSame('wp_formcourier_crm_logs',FormCourier_CRM_Logger::get_table_name());}
    public function test_privacy_content_is_registered():void{FormCourier_CRM_Privacy::add_privacy_policy_content();$this->assertNotEmpty($GLOBALS['formcourier_crm_test_privacy_policy_content']);}
    public function test_plugin_row_meta_is_scoped():void{$p=new FormCourier_CRM_Plugin();$this->assertSame(['x'],array_values($p->row_meta(['x'],'other/plugin.php')));$links=$p->row_meta([],FORMCOURIER_CRM_BASENAME);$this->assertStringContainsString('documentation/index.html',implode('',$links));}
}
