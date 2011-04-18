<?php

require_once dirname(__FILE__) . '/../technical_analysis.php';

class TechnicalAnalysisTest extends PHPUnit_Framework_TestCase {

	public function test_getUrl() {
		$partnerId = 'abcd';
		$partnerKey = 'ABCdEfGhJkLmNop1qrS2Tv==';

		$ta = new TechnicalAnalysis($partnerId, $partnerKey);

		$result = $ta->getUrl('foobar', 'en');

		$this->assertTrue(is_string($result));
		$this->assertFalse(empty($result));

		$result1 = $ta->getUrl('foobar', 'en');
		$result2 = $ta->getUrl('foobar', 'en');
		$this->assertTrue($result1 == $result2);

		$result1 = $ta->getUrl('foo', 'en');
		$result2 = $ta->getUrl('bar', 'en');
		$this->assertFalse($result1 == $result2);


		$result1 = $ta->getUrl('foobar', 'en');
		$result2 = $ta->getUrl('foobar', 'ru');
		$this->assertFalse($result1 == $result2);
	}
}
?>
