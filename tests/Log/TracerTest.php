<?php
namespace Helpers\Log\Tracer;

class TracerTest extends \PHPUnit_Framework_TestCase
{
	const GLOBAL_METRIC = 'some-metric';
	private $tracesReported = [];

	private $hostCalled;
	private $endpointCalled;

	public function setUp()
	{
		parent::setUp();
		Tracer::reset();
		$this->tracesReported = [];
	}

	public function tearDown()
	{
		parent::tearDown();
		Tracer::reset();
	}

	public function testShutdown()
	{
		$this->hostCalled = FALSE;
		$this->endpointCalled = FALSE;
		Tracer::handleShutdown(
			function() {
				$this->hostCalled = TRUE;
				return 'some host 1';
			},
			function() {
				$this->endpointCalled = TRUE;
				return 'some endpoint 1';
			}
		);

		$this->assertTrue($this->hostCalled);
		$this->assertTrue($this->endpointCalled);
		$this->assertEquals('some host 1', Tracer::getHost());
		$this->assertEquals('some endpoint 1', Tracer::getEndpoint());

		Tracer::reset();
		$this->assertNull(Tracer::getHost());
		$this->assertNull(Tracer::getEndpoint());
	}

	public function testIncrements()
	{
		Tracer::increment('some endpoint');
		Tracer::increment('some endpoint');
		Tracer::increment('some endpoint');
		Tracer::start('some endpoint');
		Tracer::end();

		$traces = Tracer::getTraces();
		$this->assertEquals(4, $traces['some endpoint']['c']);
	}

	public function testGeneral()
	{
		Tracer::start('some branch');
		Tracer::end('some branch');
		Tracer::start('some another branch');
		Tracer::end();

		Tracer::start('key');
		Tracer::start('key');

		Tracer::start('subkey');
		Tracer::start('subkey');

		Tracer::start('key');
		Tracer::end();

		Tracer::end();
		Tracer::end();

		Tracer::end();
		Tracer::end();


		$traces = Tracer::getTraces();

		$this->assertEquals(1, $traces['some branch']['c']);
		$this->assertEquals(1, $traces['some another branch']['c']);
		$this->assertEquals(3, $traces['key']['c']);
		$this->assertEquals(2, $traces['subkey']['c']);

		$this->assertArrayHasKey('t', $traces['subkey']);
		$this->assertTrue(is_float($traces['subkey']['t']));
		$this->assertTrue(is_float($traces['key']['t']));
	}

	public function testStringOutput()
	{
		Tracer::start('some br');
		Tracer::start('another br');
		Tracer::end();
		Tracer::end();

		$traces = Tracer::getTraces(TRUE);
		$this->assertRegExp('/^another br \d+\D\d+ \(1\), some br \d+\D\d+ \(1\)$/', $traces);
	}

	/**
	 * @dataProvider providerStatsdReporting

	 * @param string $metric
	 * @param bool $statsdInit
	 * @param string $host
	 * @param string $endpoint
	 * @param array $expectedTracesReported
	 */
	public function testStatsdReporting($metric, $statsdInit, $host, $endpoint, array $expectedTracesReported)
	{
		$statsd = $this->getStatsdService();
		$statsd->expects($this->exactly(isset($expectedTracesReported['count']) ? count($expectedTracesReported['count']) : 0))
			->method('count')
			->will($this->returnCallback(function($key, $value) {
				$this->tracesReported['count'][$key] = $value;
			}));
		$statsd->expects($this->exactly(isset($expectedTracesReported['timing']) ? count($expectedTracesReported['timing']) : 0))
			->method('timing')
			->will($this->returnCallback(function($key, $value) {
				$this->tracesReported['timing'][$key] = $value;
			}));

		$statsd->expects($this->exactly(isset($expectedTracesReported['gauge']) ? count($expectedTracesReported['gauge']) : 0))
			->method('gauge')
			->will($this->returnCallback(function($key, $value) {
				$this->tracesReported['gauge'][$key] = $value;
			}));

		Tracer::start($metric);
		Tracer::end();

		if ($statsdInit) {
			Tracer::setStatsdService($statsd);
		}

		if ($host) {
			Tracer::setHost($host);
		}

		if ($endpoint) {
			Tracer::setEndpoint($endpoint);
		}

		Tracer::reportTraces();
		$this->assertEquals(array_keys($expectedTracesReported), array_keys($this->tracesReported));

		foreach ($expectedTracesReported as $metric => $endpointMetrics) {
			$this->assertEquals($endpointMetrics, array_keys($this->tracesReported[$metric]));
		}
	}

	/**
	 * @return array
	 */
	public function providerStatsdReporting()
	{
		return [
			'no reporting' => [self::GLOBAL_METRIC, FALSE, NULL, NULL, []],
			'basic reporting' => [self::GLOBAL_METRIC, TRUE, NULL, NULL, [
				'count' => [
					'all_hosts.' . self::GLOBAL_METRIC,
				],
				'timing' => [
					'all_hosts.' . self::GLOBAL_METRIC,
				],
				'gauge' => [
					'all_hosts.memory_usage',
				],
			]],
			'endpoint reporting' => [self::GLOBAL_METRIC, TRUE, NULL, 'some-endpoint', [
				'count' => [
					'all_hosts.' . self::GLOBAL_METRIC,
					'by_endpoints.some-endpoint.' . self::GLOBAL_METRIC,
				],
				'timing' => [
					'all_hosts.' . self::GLOBAL_METRIC,
					'by_endpoints.some-endpoint.' . self::GLOBAL_METRIC,
				],
				'gauge' => [
					'all_hosts.memory_usage',
					'by_endpoints.some-endpoint.memory_usage',
				],
			]],
			'host reporting' => [self::GLOBAL_METRIC, TRUE, 'some-host', 'some-endpoint', [
				'count' => [
					'all_hosts.' . self::GLOBAL_METRIC,
					'by_hosts.some-host.' . self::GLOBAL_METRIC,
					'by_endpoints.some-endpoint.' . self::GLOBAL_METRIC,
				],
				'timing' => [
					'all_hosts.' . self::GLOBAL_METRIC,
					'by_hosts.some-host.' . self::GLOBAL_METRIC,
					'by_endpoints.some-endpoint.' . self::GLOBAL_METRIC,
				],
				'gauge' => [
					'all_hosts.memory_usage',
					'by_hosts.some-host.memory_usage',
					'by_endpoints.some-endpoint.memory_usage',
				],
			]],

			'metric whitening check' => [
				'sOm3 . \\-_spec@fic', TRUE, NULL, NULL, [
					'count' => [
						'all_hosts.' . 'som3--_spec-fic',
					],
					'timing' => [
						'all_hosts.' . 'som3--_spec-fic',
					],
					'gauge' => [
						'all_hosts.memory_usage',
					],
				]
			]
		];
	}

	private function getStatsdService()
	{
		$mock = $this->getMockBuilder('\Helpers\Log\Statsd\StatsdService')
			->disableOriginalConstructor()
			->setMethods(['count', 'increment', 'gauge', 'timing'])
			->getMock();

		return $mock;
	}
}