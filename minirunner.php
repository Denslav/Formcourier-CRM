<?php
namespace PHPUnit\Framework {
    class AssertionFailedError extends \Exception {}
    class SkippedTestError extends \Exception {}
    abstract class TestCase {
        public static int $assertions = 0;
        protected function failNow(string $message): void { throw new AssertionFailedError($message); }
        protected function countAssertion(): void { self::$assertions++; }
        public function markTestSkipped(string $message=''): void { throw new SkippedTestError($message); }
        public function assertSame($expected,$actual,string $message=''): void { $this->countAssertion(); if ($expected !== $actual) $this->failNow($message ?: 'Failed asserting same. Expected '.var_export($expected,true).' got '.var_export($actual,true)); }
        public function assertNotSame($expected,$actual,string $message=''): void { $this->countAssertion(); if ($expected === $actual) $this->failNow($message ?: 'Failed asserting not same.'); }
        public function assertTrue($actual,string $message=''): void { $this->countAssertion(); if ($actual !== true) $this->failNow($message ?: 'Failed asserting true, got '.var_export($actual,true)); }
        public function assertFalse($actual,string $message=''): void { $this->countAssertion(); if ($actual !== false) $this->failNow($message ?: 'Failed asserting false, got '.var_export($actual,true)); }
        public function assertNotEmpty($actual,string $message=''): void { $this->countAssertion(); if (empty($actual)) $this->failNow($message ?: 'Failed asserting not empty.'); }
        public function assertIsArray($actual,string $message=''): void { $this->countAssertion(); if (!is_array($actual)) $this->failNow($message ?: 'Failed asserting array.'); }
        public function assertCount(int $expected,$actual,string $message=''): void { $this->countAssertion(); if (!is_countable($actual) || count($actual)!==$expected) $this->failNow($message ?: 'Failed asserting count '.$expected.', got '.(is_countable($actual)?count($actual):'not countable')); }
        public function assertContains($needle,$haystack,string $message=''): void { $this->countAssertion(); if (!in_array($needle,$haystack,true)) $this->failNow($message ?: 'Failed asserting contains '.var_export($needle,true)); }
        public function assertArrayHasKey($key,$array,string $message=''): void { $this->countAssertion(); if (!is_array($array) || !array_key_exists($key,$array)) $this->failNow($message ?: 'Failed asserting array has key '.var_export($key,true)); }
        public function assertArrayNotHasKey($key,$array,string $message=''): void { $this->countAssertion(); if (is_array($array) && array_key_exists($key,$array)) $this->failNow($message ?: 'Failed asserting array does not have key '.var_export($key,true)); }
        public function assertStringContainsString(string $needle,string $haystack,string $message=''): void { $this->countAssertion(); if (strpos($haystack,$needle)===false) $this->failNow($message ?: 'Failed asserting string contains '.var_export($needle,true)); }
        public function assertStringNotContainsString(string $needle,string $haystack,string $message=''): void { $this->countAssertion(); if (strpos($haystack,$needle)!==false) $this->failNow($message ?: 'Failed asserting string does not contain '.var_export($needle,true)); }
        public function assertMatchesRegularExpression(string $pattern,string $string,string $message=''): void { $this->countAssertion(); if (@preg_match($pattern,$string)!==1) $this->failNow($message ?: 'Failed asserting regex '.$pattern.' matches.'); }
        public function assertLessThan($expected,$actual,string $message=''): void { $this->countAssertion(); if (!($actual < $expected)) $this->failNow($message ?: 'Failed asserting '.$actual.' < '.$expected); }
        public function assertGreaterThan($expected,$actual,string $message=''): void { $this->countAssertion(); if (!($actual > $expected)) $this->failNow($message ?: 'Failed asserting '.$actual.' > '.$expected); }
        public function assertGreaterThanOrEqual($expected,$actual,string $message=''): void { $this->countAssertion(); if (!($actual >= $expected)) $this->failNow($message ?: 'Failed asserting '.$actual.' >= '.$expected); }
        public function assertFileExists(string $filename,string $message=''): void { $this->countAssertion(); if (!file_exists($filename)) $this->failNow($message ?: 'Failed asserting file exists: '.$filename); }
    }
}
namespace {
    chdir(__DIR__);
    require __DIR__.'/tests/bootstrap.php';
    foreach (glob(__DIR__.'/tests/*Test.php') as $file) require_once $file;
    $base='PHPUnit\\Framework\\TestCase';
    $classes=[];
    foreach (get_declared_classes() as $class) if (is_subclass_of($class,$base)) $classes[]=$class;
    sort($classes);
    $passed=$failed=$skipped=0; $failures=[];
    foreach ($classes as $class) {
        $rc=new \ReflectionClass($class);
        if ($rc->isAbstract()) continue;
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!str_starts_with($method->getName(),'test')) continue;
            $datasets=[[]];
            $doc=$method->getDocComment() ?: '';
            if (preg_match('/@dataProvider\s+([A-Za-z0-9_]+)/',$doc,$m)) {
                $provider=$m[1];
                $obj=$rc->newInstanceWithoutConstructor();
                $pm=$rc->getMethod($provider); $pm->setAccessible(true);
                $data=$pm->invoke($obj);
                $datasets=[];
                foreach ($data as $key=>$args) $datasets[(string)$key]=is_array($args)?array_values($args):[$args];
            }
            foreach ($datasets as $dkey=>$args) {
                $obj=$rc->newInstanceWithoutConstructor();
                try {
                    if ($rc->hasMethod('setUp')) { $sm=$rc->getMethod('setUp'); $sm->setAccessible(true); $sm->invoke($obj); }
                    $method->invokeArgs($obj,$args);
                    $passed++;
                } catch (\PHPUnit\Framework\SkippedTestError $e) {
                    $skipped++;
                } catch (\Throwable $e) {
                    $failed++;
                    $label=$class.'::'.$method->getName().($dkey!==0?'['.$dkey.']':'');
                    $failures[]=$label.' => '.get_class($e).': '.$e->getMessage();
                } finally {
                    try { if ($rc->hasMethod('tearDown')) { $tm=$rc->getMethod('tearDown'); $tm->setAccessible(true); $tm->invoke($obj); } } catch (\Throwable $e) {}
                }
            }
        }
    }
    echo "RESULT passed=$passed skipped=$skipped failed=$failed assertions=".\PHPUnit\Framework\TestCase::$assertions."\n";
    foreach ($failures as $f) echo "FAIL $f\n";
    exit($failed?1:0);
}
