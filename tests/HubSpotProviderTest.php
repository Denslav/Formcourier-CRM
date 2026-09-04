<?php
use PHPUnit\Framework\TestCase;
final class HubSpotProviderTest extends TestCase {
    protected function setUp():void{$GLOBALS['formcourier_crm_test_http_requests']=[];$GLOBALS['formcourier_crm_test_http_callback']=null;}
    protected function tearDown():void{$GLOBALS['formcourier_crm_test_http_callback']=null;}
    private function response(int $code,array $body=[]):array{return['response'=>['code'=>$code],'body'=>wp_json_encode($body)];}
    public function test_connection_rejects_empty_token():void{$r=(new FormCourier_CRM_HubSpot_Provider())->test_connection('');$this->assertFalse($r['success']);$this->assertStringContainsString('empty',$r['message']);}
    public function test_connection_accepts_successful_api_response():void{$GLOBALS['formcourier_crm_test_http_callback']=fn()=> $this->response(200,['total'=>0,'results'=>[]]);$r=(new FormCourier_CRM_HubSpot_Provider())->test_connection('token');$this->assertTrue($r['success']);$this->assertSame(200,$r['status_code']);$this->assertCount(1,$GLOBALS['formcourier_crm_test_http_requests']);}
    public function test_contact_requires_email():void{$r=(new FormCourier_CRM_HubSpot_Provider())->create_or_update_contact('token',['firstname'=>'John']);$this->assertFalse($r['success']);$this->assertSame('skipped',$r['action']);}
    public function test_invalid_email_is_rejected_before_http_request():void{$r=(new FormCourier_CRM_HubSpot_Provider())->create_or_update_contact('token',['email'=>'bad']);$this->assertFalse($r['success']);$this->assertCount(0,$GLOBALS['formcourier_crm_test_http_requests']);}
    public function test_search_then_update_flow():void{
        $GLOBALS['formcourier_crm_test_http_callback']=function(string $url,array $args){
            if(false!==strpos($url,'/search'))return $this->response(200,['total'=>1,'results'=>[['id'=>'321']]]);
            if(false!==strpos($url,'/321'))return $this->response(200,['id'=>'321','properties'=>['email'=>'john@example.com']]);
            return $this->response(500,['error'=>'unexpected']);
        };
        $r=(new FormCourier_CRM_HubSpot_Provider())->create_or_update_contact('token',['email'=>'john@example.com','firstname'=>'John']);
        $this->assertTrue($r['success']);$this->assertSame('updated',$r['action']);$this->assertSame('321',(string)$r['contact_id']);$this->assertCount(2,$GLOBALS['formcourier_crm_test_http_requests']);
    }

    public function test_contact_and_deal_flow_creates_associated_deal():void{
        $GLOBALS['formcourier_crm_test_http_callback']=function(string $url,array $args){
            if(false!==strpos($url,'/contacts/search'))return $this->response(200,['total'=>1,'results'=>[['id'=>'321']]]);
            if(false!==strpos($url,'/contacts/321'))return $this->response(200,['id'=>'321','properties'=>['email'=>'john@example.com']]);
            if(false!==strpos($url,'/objects/deals')){
                $body=json_decode((string)($args['body']??''),true);
                $this->assertSame('stage-2',$body['properties']['dealstage']);
                $this->assertSame('pipeline-1',$body['properties']['pipeline']);
                $this->assertSame('321',(string)$body['associations'][0]['to']['id']);
                return $this->response(201,['id'=>'987']);
            }
            return $this->response(500,['error'=>'unexpected']);
        };
        $r=(new FormCourier_CRM_HubSpot_Provider())->create_or_update_contact_and_deal(
            'token',
            ['email'=>'john@example.com','firstname'=>'John','fb_form_name'=>'Contact Form'],
            'pipeline-1',
            'stage-2'
        );
        $this->assertTrue($r['success']);
        $this->assertSame('updated',$r['action']);
        $this->assertSame('321',(string)$r['contact_id']);
        $this->assertSame('987',(string)$r['deal_id']);
        $this->assertStringContainsString('deal created',$r['message']);
        $this->assertCount(3,$GLOBALS['formcourier_crm_test_http_requests']);
    }
}
