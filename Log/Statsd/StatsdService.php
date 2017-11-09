<?php
namespace Helpers\Log\Statsd;

use Helpers\Log\Tracer\Tracer;

class StatsdService
{
	/** @var StatsdService */
	private static $instance;

	/** @var Statsd */
	private static $statsd;

	protected function __construct()
	{
	}

	/**
	 * @return StatsdService
	 */
	public static function getInstance()
	{
		if (self::$instance === NULL) {
			self::$instance = new StatsdService();
		}
		return self::$instance;
	}

	/**
	 * @param string $connectionString
	 * @throws \Exception
	 */
	public function setConnection($connectionString) {
		list($proto, $connectionStringRest) = explode(':', $connectionString, 2);
		$connectionStringRest = preg_replace('/^\\/\\//', '', $connectionStringRest);
		list($host, $path) = explode('/', $connectionStringRest);
		list($ipAddress, $port) = explode(':', $host, 2);

		$path = preg_replace('/[^A-Za-z\\.]/', '_', preg_replace('/\s+/', ' ', trim($path)));

		if (!is_numeric($port)) {
			throw new \Exception('Stats port is not integer.');
		}

		if (!preg_match('/\d+\\.\d+\\.\d+\\.\d+$/', $ipAddress)) {
			throw new \Exception('Stats IP is not valid.');
		}

		if (empty($path)) {
			throw new \Exception('Stats path is not valid.');
		}

		$socket = new StatsdSocket($ipAddress, $port, $proto);
		$client = new StatsdClient($socket, $path);

		self::$statsd = new Statsd($client);
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 * @throws \Exception
	 */
	public function __call($name, array $arguments)
	{
		if (empty(self::$statsd)) {
			return;
		}

		if (!in_array($name, array('increment', 'decrement', 'gauge', 'count', 'timing'))) {
			throw new \Exception('Method "' . $name . '" not exist.');
		}

		call_user_func_array(array(self::$statsd, $name), $arguments);
	}

	/**
	 * @param string $endpoint
	 */
	public static function attach($endpoint)
	{
		if (!defined('PHPUNIT_TESTSUITE')) {
			$statsdService = self::getInstance();
			$statsdService->setConnection($endpoint);
			Tracer::setStatsdService($statsdService);
			register_shutdown_function(
				['\Helpers\Log\Tracer\Tracer', 'handleShutdown'],
				function () {
					return preg_replace('/[^a-z]/', '_', strtolower(gethostname()));
				},
				function () {
					$method = $_SERVER['REQUEST_METHOD'] . '-';

					if (!class_exists('\Phalcon\Di')) {
						return $method . 'no-di';
					}

					$diInstance = \Phalcon\Di::getDefault();
					/** @var \Phalcon\Mvc\Router $diRouter */
					$diRouter = $diInstance->getShared('router');
					if (empty($diRouter)) {
						return $method . 'no-router';
					}

					$endpoint = $method
						. $diRouter->getModuleName() . '-'
						. $diRouter->getControllerName() . '-'
						. $diRouter->getActionName();

					$endpoint = strtolower(preg_replace('/[^\w\-\d]+/', '-', trim($endpoint)));
					$endpoint = preg_replace('/\-+/', '-', $endpoint);

					return $endpoint;
				}
			);
		}
	}

}