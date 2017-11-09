<?php

namespace Helpers\Log\Logstash;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class Logstash extends AbstractLogger implements LoggerInterface
{
	const UDP_MAX_SIZE           = 8190;
	const CONNECTION_TRIES_COUNT = 3;
	const MAX_STACK_HISTORY      = 5;

	const DEFAULT_TCP_TIMEOUT_SEC = 5;

	const DEFAULT_CONNECT_USLEEP = 5000;

	const SOCKET_FLUSH_COUNT = 30;
	const KEY_RESOURCE       = 'res';

	const DEFAULT_BUILD   = '00000000';
	const GIT_HASH_SIZE   = 8;
	const MESSAGE_ID_SIZE = 10;

	protected $instanceId;

	/** @var string Differs from project name because of explicit keyword highlighting*/
	protected $facility = 'lgstsh';

	/** @var string */
	private $environment;

	/** @var string */
	private $application;

	/** @var string */
	private $channel;

	/** @var string */
	private $hostname;

	/** @var array */
	private $config;

	/** @var string */
	private $build;

	/** @var array */
	private $socketConnections = [];

	/** @var int */
	private $sequence;

	/** @var int */
	private $maxStackHistorySize;

	/**
	 * host: some.host
	 * proto: [tcp|udp]
	 * port: 37105
	 *
	 * application: some_app     *
	 * customer: some_customer     *
	 * hostname: some.domain.com     *
	 *
	 * @param array $settings
	 * @throws \Exception
	 */
	public function __construct(array $settings)
	{
		if (!extension_loaded('sockets')) {
			throw new \Exception('Extension "sockets" not loaded.');
		}

		$this->config = new LogstashConfig($settings);
		$this->getExtraSettings($settings);

		$this->build = $this->getGitHash();
		$this->environment = $this->getEnvironment();
		$this->instanceId = $this->randomString(self::MESSAGE_ID_SIZE);
		$this->sequence = 0;
	}

	/**
	 * UDP connection should be directly closed and socket nullified.
	 */
	public function __destruct()
	{
		$this->_closeConnection();
	}

	/**
	 * @param string $severity
	 * @param string $message
	 * @param array $context
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function log($severity, $message, array $context = [])
	{
		$this->sequence++;

		$dataSend = [
			LogstashEnum::PARAM_MESSAGE      => $message,
			LogstashEnum::PARAM_SEVERITY     => strtolower($severity),
			LogstashEnum::PARAM_LOG_SEQUENCE => $this->sequence,
		];

		$logData = explode('|', $message, 2);
		if (count($logData) == 2) {
			$dataSend[LogstashEnum::PARAM_LOG_DATA] = $logData[1];
			$dataSend[LogstashEnum::PARAM_MESSAGE] = $logData[0];
		}

		$exc = new \Exception();
		$trace = $exc->getTrace();
		if (isset($trace[1])) {
			$dataSend[LogstashEnum::EXCEPTION_FILE] = self::cutFileName($trace[1]['file']);
			$dataSend[LogstashEnum::EXCEPTION_LINE] = $trace[1]['line'];
		}

		$dataSend = $this->addLogDetails($dataSend, $context);

		$dataRaw = $this->getDataRaw($dataSend);

		$messageTriesCount = 0;

		$tcpExc = NULL;
		$sendCount = 0;

		do {
			if ($this->config->isUdp()) {
				if (strlen($dataRaw) <= self::UDP_MAX_SIZE) {
					$sendCount = socket_sendto(
						$this->_getSocket($this->config),
						$dataRaw,
						strlen($dataRaw),
						0,
						$this->config->getHost(),
						$this->config->getPort());
					$this->_closeConnection($this->config);
				} else {
					throw new \Exception('Too big package.');
				}
			} else {
				try {
					$rv = fwrite($this->_getSocket($this->config), $dataRaw);
					if ($rv !== FALSE && $rv != 0) {
						$sendCount = strlen($dataRaw);
					}
				} catch (\Exception $tcpExc) {
//					print $tcpExc->getMessage();
				}
			}

			if (empty($sendCount)) {
				$this->_closeConnection($this->config);
			}
			$messageTriesCount++;
		} while (empty($sendCount) && $messageTriesCount <= self::CONNECTION_TRIES_COUNT);

		if (!empty($tcpExc)) {
			throw $tcpExc;
		}

		return $sendCount;
	}

	/**
	 * @param LogstashConfig $config
	 * @return mixed
	 * @throws \Exception
	 */
	private function _getSocket(LogstashConfig $config)
	{
		$configKey = json_encode($config->toArray());
		if (isset($this->socketConnections[$configKey])) {
			if ($this->socketConnections[$configKey]['hits'] < self::SOCKET_FLUSH_COUNT) {
				$this->socketConnections[$configKey]['hits']++;
				return $this->socketConnections[$configKey][self::KEY_RESOURCE];
			}
			$this->_closeConnection($config);
		}

		$count = 0;

		$errno = NULL;
		$errstr = NULL;

		while ($count++ < self::CONNECTION_TRIES_COUNT) {
			if ($config->isUdp()) {
				$this->socketConnections[$configKey] = [
					self::KEY_RESOURCE => socket_create(
						AF_INET,
						SOCK_DGRAM,
						SOL_UDP
					),
				];
			} else {
				$this->socketConnections[$configKey] = [
					self::KEY_RESOURCE => fsockopen(
						$config->getHost(),
						$config->getPort(),
						$errno,
						$errstr,
						self::DEFAULT_TCP_TIMEOUT_SEC
					),
				];
			}

			if ($this->socketConnections[$configKey]) {
				break;
			}
			usleep(self::DEFAULT_CONNECT_USLEEP);
		}

		if (empty($this->socketConnections[$configKey])) {
			throw new \Exception('Can\'t establish connection to logger' . (!empty($errstr) ? ': ' . $errstr : ''), 500);
		}

		$this->socketConnections[$configKey]['hits'] = 1;
		return $this->socketConnections[$configKey][self::KEY_RESOURCE];
	}

	/**
	 * Breaks connection and reset socketConnection param for proper function handling.
	 */
	private function _closeConnection(LogstashConfig $config = NULL)
	{
		if (!empty($config)) {
			$configKeys = [json_encode($config->toArray())];
		} else {
			$configKeys = array_keys($this->socketConnections);
		}

		foreach ($configKeys as $key) {
			if (!empty($this->socketConnections[$key])) {
				$keyData = json_decode($key, TRUE);
				if ($keyData[LogstashConfig::KEY_PROTO] === LogstashConfig::PROTO_TCP) {
					fclose($this->socketConnections[$key][self::KEY_RESOURCE]);
				} else {
					socket_close($this->socketConnections[$key][self::KEY_RESOURCE]);
				}
			}
			$this->socketConnections[$key] = NULL;
		}
	}

	/**
	 * @param array $dataSend
	 * @param array $context
	 * @return array
	 */
	private function addLogDetails(array $dataSend, array $context = [])
	{
		$dataSend[LogstashEnum::PARAM_APPLICATION] = $this->application;
		$dataSend[LogstashEnum::PARAM_BUILD] = $this->build;
		$dataSend[LogstashEnum::PARAM_FACILITY] = $this->facility;
		$dataSend[LogstashEnum::PARAM_MESSAGE_ID] = $this->randomString(self::MESSAGE_ID_SIZE);
		$dataSend[LogstashEnum::PARAM_INSTANCE_ID] = $this->instanceId;

		if (!empty($this->environment)) {
			$dataSend[LogstashEnum::PARAM_ENVIRONMENT] = $this->environment;
		}

		$dataSend = array_merge($dataSend, $this->getSystemInfo());
		$dataSend = array_merge($dataSend, $this->getClientInfo());

		if (!empty($this->channel)) {
			$dataSend[LogstashEnum::PARAM_CHANNEL] = $this->channel;
		}

		if (!empty($this->hostname)) {
			$dataSend[LogstashEnum::PARAM_HOSTNAME] = $this->hostname;
		}

		if (!empty($context[LogstashEnum::EXCEPTION_RAW])
			&& $context[LogstashEnum::EXCEPTION_RAW] instanceof \Exception
		) {
			$dataSend[LogstashEnum::EXCEPTION_STACK] = self::cleanupStackTrace($context[LogstashEnum::EXCEPTION_RAW], $this->maxStackHistorySize);
			unset($context[LogstashEnum::EXCEPTION_RAW]);
		}

		foreach ($context as $key => $value) {
			if (!isset($dataSend[$key]) && $value !== NULL) {
				$dataSend[$key] = $value;
			}
		}

		return $dataSend;
	}

	/**
	 * @param array $dataSend
	 * @return string
	 */
	private function getDataRaw(array $dataSend)
	{
		return json_encode($dataSend) . "\n";
	}

	/**
	 * Returns git hash of selected branch.
	 *
	 * @param string $branchName
	 * @return string
	 */
	protected function getGitHash($branchName = NULL)
	{
		$hash = self::DEFAULT_BUILD;
		if (getenv('GIT_HEAD')) {
			$hash = substr(getenv('GIT_HEAD'), 0, self::GIT_HASH_SIZE);
		}

		if (!defined('APP_ROOT_DIR')) {
			return $hash;
		}

		if (file_exists(APP_ROOT_DIR . '/githash')) {
			$hash = substr(trim(file_get_contents(APP_ROOT_DIR . '/githash')), 0, self::GIT_HASH_SIZE);
			// automatic hash read if available
		} elseif (is_dir(APP_ROOT_DIR . '/.git')) {
			$headsData = file_get_contents(APP_ROOT_DIR . '/.git/HEAD');
			if (empty($branchName) && preg_match('/^ref:.*\\/([^\\/]+)$/', $headsData, $matches)) {
				// return \n from the end
				$branchName = trim($matches[1]);
			}
			$hashFound = FALSE;
			if (!empty($branchName)) {
				$branchesData = @file(APP_ROOT_DIR . '/.git/FETCH_HEAD');
				if (!empty($branchesData)) {
					foreach ($branchesData as $line) {
						if (strpos($line, 'branch \'' . $branchName . '\'') !== FALSE) {
							if (preg_match('/^([0-9a-f]*)/i', $line, $matches)) {
								$hash = substr($matches[1], 0, self::GIT_HASH_SIZE);
								$hashFound = TRUE;
								break;
							}
						}
					}
				}
			}
			if (!$hashFound && file_exists(APP_ROOT_DIR . '/.git/ORIG_HEAD')) {
				$hash = substr(trim(file_get_contents(APP_ROOT_DIR . '/.git/ORIG_HEAD')), 0, self::GIT_HASH_SIZE);
			}
		}
		return $hash;
	}

	/**
	 * @return string
	 */
	private function getEnvironment()
	{
		if (defined('APP_ROOT_DIR') && file_exists(APP_ROOT_DIR . '/environment')) {
			return trim(file_get_contents(APP_ROOT_DIR . '/environment'));
		}
	}

	/**
	 * @return array
	 */
	private function getSystemInfo()
	{
		$sysInfo = [];

		$sysInfo['mem_max'] = sprintf('%0.1f', memory_get_peak_usage() / (1024 * 1024));
		$sysInfo['mem_abs'] = sprintf('%0.1f', memory_get_usage() / (1024 * 1024));
		$sysInfo['pid'] = getmypid();

		return $sysInfo;
	}

	/**
	 * @return array
	 */
	private function getClientInfo()
	{
		$clientInfo = [];
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$clientInfo[LogstashEnum::PARAM_REMOTE_ADDR] = $_SERVER['REMOTE_ADDR'];
			$clientInfo[LogstashEnum::PARAM_TRAFFIC_TYPE] = $this->getTrafficType($_SERVER['REMOTE_ADDR']);
		}

		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$clientInfo[LogstashEnum::PARAM_USER_AGENT] = $_SERVER['HTTP_USER_AGENT'];
		}

		if (!empty($_SERVER['REQUEST_URI'])) {
			$clientInfo[LogstashEnum::PARAM_REQUEST_URI] = $_SERVER['REQUEST_URI'];
			$base = $this->getRequestUriBase($_SERVER['REQUEST_URI']);
			if ($base) {
				$clientInfo[LogstashEnum::PARAM_REQUEST_URI_BASE] = $base;
			}
		}

		return $clientInfo;
	}

	/**
	 * Generates alphanumeric string of determined size.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	protected function randomString($length)
	{
		$string = implode('', array_merge(range(0, 9), range('a', 'z'), range('A', 'Z')));
		return substr(str_shuffle($string), 0, $length);
	}

	/**
	 * @param string $remoteAddr
	 * @return string
	 */
	private function getTrafficType($remoteAddr) {
		if (preg_match('/^192\.168/', $remoteAddr)) {
			return LogstashEnum::TRAFFIC_INTERNAL;
		}
		if (preg_match('/^10\./', $remoteAddr)) {
			return LogstashEnum::TRAFFIC_INTERNAL;
		}
		return LogstashEnum::TRAFFIC_EXTERNAL;
	}

	/**
	 * @param $uri
	 * @return null
	 */
	private function getRequestUriBase($uri) {
		$urlParts = parse_url($uri);

		if (!empty($urlParts['path'])) {
			return $urlParts['path'];
		}
		return NULL;
	}

	/**
	 * @param \Exception $exception
	 * @return null|string
	 */
	public static function cleanupStackTrace(\Exception $exception, $historySize = self::MAX_STACK_HISTORY) {
		$stack = explode("\n", $exception->getTraceAsString());
		if (empty($stack)) {
			return NULL;
		}

		$messages = [];
		$lines = 0;
		foreach ($stack as $item) {
			$lines++;
			$messages[] = self::cutFileName($item, 0);
			if ($lines >= $historySize) {
				break;
			}
		}

		return implode("\n", $messages);
	}

	/**
	 * @param $fileName
	 * @param int $start
	 * @return bool|string
	 */
	private static function cutFileName($fileName, $start = 1)
	{
		if (defined('APP_ROOT_DIR')) {
			$fileName = substr(str_replace(APP_ROOT_DIR, '', $fileName), $start);
		}
		return $fileName;
	}

	/**
	 * @param array $settings
	 */
	private function getExtraSettings(array $settings)
	{
		$this->application = $settings[LogstashEnum::PARAM_APPLICATION];

		if (isset($settings[LogstashEnum::PARAM_CHANNEL])) {
			$this->channel = $settings[LogstashEnum::PARAM_CHANNEL];
		}

		if (isset($settings[LogstashEnum::PARAM_HOSTNAME])) {
			$this->hostname = $settings[LogstashEnum::PARAM_HOSTNAME];
		}

		if (isset($settings[LogstashEnum::PARAM_STACK_SIZE])) {
			$this->maxStackHistorySize = $settings[LogstashEnum::PARAM_STACK_SIZE];
		}
	}
}

