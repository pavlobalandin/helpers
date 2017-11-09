<?php
namespace Helpers\Log\Statsd;
use \PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class StatsdTest extends \PHPUnit_Framework_TestCase
{
	public function testIncrement()
	{
		$key = 'some';
		$sampleRate = 2;
		$statsd1 = new Statsd($this->addExpectIncrementDecrement($this->getStatsdClient(), 'increment', $key, $sampleRate));
		$statsd1->increment($key, $sampleRate);
		$statsd2 = new Statsd($this->addExpectIncrementDecrement($this->getStatsdClient(), 'increment', $key, 1));
		$statsd2->increment($key);
	}

	public function testDecrement()
	{
		$key = 'another';
		$sampleRate = 2;
		$statsd1 = new Statsd($this->addExpectIncrementDecrement($this->getStatsdClient(), 'decrement', $key, $sampleRate));
		$statsd1->decrement($key, $sampleRate);
		$statsd2 = new Statsd($this->addExpectIncrementDecrement($this->getStatsdClient(), 'decrement', $key, 1));
		$statsd2->decrement($key);
	}

	public function testCount()
	{
		$key = 'any';
		$value = 'val';
		$sampleRate = 3;
		$statsd1 = new Statsd($this->addExpectCount($this->getStatsdClient(), 'count', $key, $value, $sampleRate));
		$statsd1->count($key, $value, $sampleRate);
		$statsd2 = new Statsd($this->addExpectCount($this->getStatsdClient(), 'count', $key, $value, 1));
		$statsd2->count($key, $value);
	}

	/**
	 * @param \PHPUnit_Framework_MockObject_MockObject $mock
	 * @param string $method
	 * @param mixed $param1
	 * @param int $param2
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	private function addExpectIncrementDecrement(MockObject $mock, $method, $param1, $param2 = NULL)
	{
		$mock->expects($this->exactly(1))
			->method($method)
			->with(
				$this->equalTo($param1),
				$this->equalTo($param2)
			);
		return $mock;
	}

	/**
	 * @param \PHPUnit_Framework_MockObject_MockObject $mock
	 * @param string $method
	 * @param mixed $param1
	 * @param mixed $param2
	 * @param int $param3
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	private function addExpectCount(MockObject $mock, $method, $param1, $param2, $param3 = NULL)
	{
		$mock->expects($this->exactly(1))
			->method($method)
			->with(
				$this->equalTo($param1),
				$this->equalTo($param2),
				$this->equalTo($param3)
			);
		return $mock;
	}

	/**
	 * @return \Helpers\Log\Statsd\StatsdClient
	 */
	private function getStatsdClient()
	{
		return $this->getMockBuilder('\Helpers\Log\Statsd\StatsdClient')
			->disableOriginalConstructor()
			->setMethods(array('increment', 'decrement', 'count'))
			->getMock();
	}
}
