<?php
use PHPUnit\Framework\TestCase;

final class TranslationDocumentationTest extends TestCase {
    private function parsePotMsgids(string $path):array{
        $source=(string)file_get_contents($path);
        preg_match_all('/^msgid "((?:\\\\.|[^"])*)"$/m',$source,$matches);
        $ids=[];
        foreach(array_slice($matches[1],1) as $value){
            $ids[]=stripcslashes($value);
        }
        sort($ids,SORT_STRING);
        return $ids;
    }

    private function parsePo(string $path):array{
        $source=(string)file_get_contents($path);
        preg_match_all('/^msgid "((?:\\\\.|[^"])*)"\nmsgstr "((?:\\\\.|[^"])*)"$/m',$source,$matches,PREG_SET_ORDER);
        $catalog=[];
        foreach($matches as $match){
            $id=stripcslashes($match[1]);
            if(''===$id){continue;}
            $catalog[$id]=stripcslashes($match[2]);
        }
        ksort($catalog,SORT_STRING);
        return $catalog;
    }


    private function parseMo(string $path):array{
        $data=(string)file_get_contents($path);
        $header=unpack('Vmagic/Vrevision/Vcount/Voriginals/Vtranslations/Vhash_size/Vhash_offset',substr($data,0,28));
        $this->assertSame(0x950412de,$header['magic']);
        $catalog=[];
        for($index=0;$index<$header['count'];$index++){
            $original=unpack('Vlength/Voffset',substr($data,$header['originals']+($index*8),8));
            $translation=unpack('Vlength/Voffset',substr($data,$header['translations']+($index*8),8));
            $msgid=substr($data,$original['offset'],$original['length']);
            $msgstr=substr($data,$translation['offset'],$translation['length']);
            $catalog[$msgid]=$msgstr;
        }
        return $catalog;
    }

    public function testRussianAndUkrainianCatalogsCoverTheFullPot():void{
        $root=dirname(__DIR__).'/languages/';
        $ids=$this->parsePotMsgids($root.'formcourier-crm.pot');
        $this->assertGreaterThanOrEqual(140,count($ids));
        foreach(['ru_RU','uk'] as $locale){
            $catalog=$this->parsePo($root.'formcourier-crm-'.$locale.'.po');
            $this->assertSame($ids,array_keys($catalog),$locale.' catalogue must match POT.');
            foreach($catalog as $id=>$translation){
                $this->assertNotEmpty(trim($translation),$locale.' translation is empty for '.$id);
            }
            $this->assertGreaterThan(1000,filesize($root.'formcourier-crm-'.$locale.'.mo'));
        }
    }



    public function testCompiledMoCataloguesUseRealUtf8HeadersAndTranslations():void{
        $root=dirname(__DIR__).'/languages/';
        $expected=[
            'ru_RU'=>[
                'General'=>'Основные',
                'Connect Contact Form 7 or WPForms to HubSpot or Pipedrive.'=>'Подключайте Contact Form 7 или WPForms к HubSpot или Pipedrive.',
            ],
            'uk'=>[
                'General'=>'Загальні',
                'Connect Contact Form 7 or WPForms to HubSpot or Pipedrive.'=>'Підключайте Contact Form 7 або WPForms до HubSpot чи Pipedrive.',
            ],
        ];

        foreach($expected as $locale=>$translations){
            $catalog=$this->parseMo($root.'formcourier-crm-'.$locale.'.mo');
            $this->assertArrayHasKey('',$catalog);
            $this->assertStringContainsString("Content-Type: text/plain; charset=UTF-8\n",$catalog['']);
            $this->assertStringNotContainsString('\\nContent-Type:',$catalog['']);
            foreach($translations as $msgid=>$translation){
                $this->assertSame($translation,$catalog[$msgid]??null,$locale.' MO translation mismatch for '.$msgid);
                $this->assertMatchesRegularExpression('//u',$catalog[$msgid]??'',$locale.' MO translation must be valid UTF-8.');
            }
        }
    }

    public function testRuntimeCatalogTranslatesAdminAndCrmMessages():void{
        $GLOBALS['formcourier_crm_test_locale']='ru_RU';
        $this->assertSame('Основные',__('General','formcourier-crm'));
        $this->assertSame('Формы и сопоставление',__('Forms & Mapping','formcourier-crm'));
        $this->assertSame('Подключайте Contact Form 7 и WPForms к HubSpot или Pipedrive с визуальным сопоставлением полей и журналами с защитой данных.',__('Connect Contact Form 7 and WPForms to HubSpot or Pipedrive with visual field mapping and privacy-aware logs.','formcourier-crm'));
        $this->assertSame('Подключение успешно. API Pipedrive доступен.',__('Connection successful. Pipedrive API is available.','formcourier-crm'));
        $GLOBALS['formcourier_crm_test_locale']='uk';
        $this->assertSame('Загальні',__('General','formcourier-crm'));
        $this->assertSame('Форми та зіставлення',__('Forms & Mapping','formcourier-crm'));
        $this->assertSame('Підключайте Contact Form 7 і WPForms до HubSpot або Pipedrive з візуальним зіставленням полів і журналами із захистом даних.',__('Connect Contact Form 7 and WPForms to HubSpot or Pipedrive with visual field mapping and privacy-aware logs.','formcourier-crm'));
        $this->assertSame('Підключення успішне. API HubSpot доступний, сервісний ключ працює.',__('Connection successful. HubSpot API is available and the Service Key works.','formcourier-crm'));
        $GLOBALS['formcourier_crm_test_locale']='en_US';
    }

    public function testLocalizedDocumentationContainsStandardMappingExamples():void{
        $root=dirname(__DIR__).'/documentation/';
        foreach(['index.html','ru/index.html','uk/index.html'] as $file){
            $this->assertFileExists($root.$file);
            $html=(string)file_get_contents($root.$file);
            $this->assertStringContainsString('1.0.1',$html);
            foreach(['your-name','your-email','your-tel','your-message','person.name','person.email','person.phone','fb_form_id','fb_form_name'] as $example){
                $this->assertStringContainsString($example,$html,$file.' missing '.$example);
            }
        }
    }

    public function testPluginRowMetaOpensDocumentationForCurrentLocale():void{
        $plugin=new FormCourier_CRM_Plugin();
        $GLOBALS['formcourier_crm_test_locale']='ru_RU';
        $this->assertStringContainsString('documentation/ru/index.html',implode('',$plugin->row_meta([],FORMCOURIER_CRM_BASENAME)));
        $GLOBALS['formcourier_crm_test_locale']='uk';
        $this->assertStringContainsString('documentation/uk/index.html',implode('',$plugin->row_meta([],FORMCOURIER_CRM_BASENAME)));
        $GLOBALS['formcourier_crm_test_locale']='en_US';
        $this->assertStringContainsString('documentation/index.html',implode('',$plugin->row_meta([],FORMCOURIER_CRM_BASENAME)));
    }
}
