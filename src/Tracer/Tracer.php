<?php
namespace Helpers\Tracer;

use Helpers\Statsd\StatsdService;

class Tracer
{
	/** @var array */
	private static $trace;

	/** @var array */
	private static $summaries;

	/** @var StatsdService */
	private static $statsdService;

	/** @var string */
	private static $host;

	/** @var string */
	private static $endpoint;

	private static function init()
	{
		if (self::$trace === NULL) {
			self::$trace = [];
			self::$summaries = [];
		}
	}

	/**
	 * @param string $endpoint
	 */
	public static function start($endpoint)
	{
		self::init();
		self::$trace[] = [
			'e' => $endpoint,
			't' => microtime(TRUE),
		];
	}

	/**
	 * @param string $endpoint
	 */
	public static function increment($endpoint)
	{
		self::init();
		if (!isset(self::$summaries[$endpoint])) {
			self::$summaries[$endpoint] = [
				'c' => 0,
				't' => 0,
			];
		}
		self::$summaries[$endpoint]['c']++;
	}

	/**
	 * @param string|null $expectedName
	 * @return bool
	 * @throws \Exception
	 */
	public static function end($expectedName = NULL)
	{
		self::init();
		$endpoint = count(self::$trace) > 0 ? array_pop(self::$trace) : NULL;

		if ($endpoint === NULL) {
			return FALSE;
		}

		$time = microtime(TRUE) - $endpoint['t'];
		$name = $endpoint['e'];

		if ($expectedName !== NULL && $expectedName !== $name) {
			throw new \Exception('Expected service name: ' . $expectedName . ' not as closed: ' . $name);
		}

		if (!isset(self::$summaries[$name])) {
			self::$summaries[$name] = [
				'c' => 0,
				't' => 0,
			];
		}
		self::$summaries[$name]['c']++;
		self::$summaries[$name]['t'] += $time;
		return TRUE;
	}

	public static function reset()
	{
		self::$trace = NULL;
		self::$summaries = NULL;
		self::$statsdService = NULL;
		self::$host = NULL;
		self::$endpoint = NULL;
	}

	/**
	 * @param bool $asString
	 * @return array|string
	 */
	public static function getTraces($asString = FALSE)
	{
		self::init();
		if ($asString) {
			$string = [];
			foreach (self::$summaries as $endpoint => $stats) {
				$string[] = $endpoint . ' ' . sprintf('%0.3f', $stats['t']) . ' (' . $stats['c'] . ')';
			}
			return implode(', ', $string);
		}
		return self::$summaries;
	}

	/**
	 * @param array $expectedNames
	 */
	public static function endIfOpened($expectedNames = [])
	{
		foreach($expectedNames as $sName) {
			if(sizeof(self::$trace) && self::$trace[sizeof(self::$trace) - 1]['e'] === $sName) {
				self::end($sName);
			}
		}
	}

	public static function endAll()
	{
		self::init();
		while(self::end());
	}

	/**
	 * @param StatsdService $service
	 */
	public static function setStatsdService(StatsdService $service)
	{
		self::$statsdService = $service;
	}

	/**
	 * @param callable|NULL $extractHostname
	 * @param callable|NULL $extractEndpoint
	 */
	public static function handleShutdown(callable $extractHostname = NULL, callable $extractEndpoint = NULL)
	{
		self::init();
		Tracer::endAll();

		$host = NULL;
		if ($extractHostname) {
			$host = call_user_func($extractHostname);
		}

		$endpoint = NULL;
		if ($extractEndpoint) {
			$endpoint = call_user_func($extractEndpoint);
		}

		self::setEndpoint($endpoint);
		self::setHost($host);

		$error = error_get_last();
		if ($error !== NULL) {
			self::increment('error');
		}
		self::reportTraces();
	}

	/**
	 * @param string $host
	 */
	public static function setHost($host)
	{
		self::$host = $host;
	}

	/**
	 * @return string
	 */
	public static function getHost()
	{
		return self::$host;
	}

	/**
	 * @param string $host
	 */
	public static function setEndpoint($endpoint)
	{
		self::$endpoint = $endpoint;
	}

	/**
	 * @return string
	 */
	public static function getEndpoint()
	{
		return self::$endpoint;
	}

	/**
	 * @return void
	 */
	public static function reportTraces()
	{
		self::init();

		if (!self::$statsdService) {
			return;
		}
		$traces = self::getTraces();

		$reportKeys = ['all_hosts'];
		if (!empty(self::$host)) {
			$reportKeys[] = 'by_hosts.' . self::$host;
		}

		if (!empty(self::$endpoint)) {
			$reportKeys[] = 'by_endpoints.' . self::$endpoint;
		}

		foreach ($traces as $metricName => $stats) {
			$metricName = strtolower(preg_replace('/[^\w\-\d]+/', '-', trim($metricName)));
			foreach ($reportKeys as $key) {
				self::$statsdService->count($key . '.' . $metricName, $stats['c']);
				self::$statsdService->timing($key . '.' . $metricName, intval($stats['t'] * 1000));
			}
		}

		$memUsage = sprintf('%0.1f', memory_get_usage() / 1024);

		foreach ($reportKeys as $key) {
			self::$statsdService->gauge($key . '.memory_usage', $memUsage);
		}
	}
}