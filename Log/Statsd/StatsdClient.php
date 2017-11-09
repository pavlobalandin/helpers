<?php
namespace Helpers\Log\Statsd;

class StatsdClient
{
	const ROOT_NAMESPACE = 'projects';

	/** @var StatsdSocket */
	protected $_connection;

	/** @var array */
	protected $_timings = array();

	/** @var array */
	protected $_memoryProfiles = array();

	/** @var string */
	protected $_namespace = '';

	/**
	 * @param Connection $connection
	 * @param string $namespace
	 */
	public function __construct(Connection $connection, $namespace = '')
	{
		$this->_connection = $connection;
		$this->_namespace = (string) self::ROOT_NAMESPACE . '.' . $namespace;
	}

	/**
	 * @param string $key
	 * @param int $sampleRate
	 *
	 * @return void
	 */
	public function increment($key, $sampleRate = 1)
	{
		$this->count($key, 1, $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $sampleRate
	 */
	public function decrement($key, $sampleRate = 1)
	{
		$this->count($key, -1, $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $value
	 * @param int $sampleRate
	 */
	public function count($key, $value, $sampleRate = 1)
	{
		$this->_send($key, (int) $value, 'c', $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $value the timing in ms
	 * @param int $sampleRate
	 *
	 * @return void
	 */
	public function timing($key, $value, $sampleRate = 1)
	{
		$this->_send($key, (int) $value, 'ms', $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $value
	 */
	public function gauge($key, $value)
	{
		$this->_send($key, (int) $value, 'g', 1);
	}

	/**
	 * @param string $key
	 * @param int $value
	 * @param string $type
	 * @param int $sampleRate
	 */
	protected function _send($key, $value, $type, $sampleRate)
	{
		if (0 != strlen($this->_namespace)) {
			$key = strtolower(sprintf('%s.%s', $this->_namespace, $key));
		}

		$message = sprintf('%s:%d|%s', $key, $value, $type);
		$sampledData = '';

		if ($sampleRate < 1) {
			$sample = mt_rand() / mt_getrandmax();

			if ($sample <= $sampleRate || $this->_connection->forceSampling()) {
				$sampledData = sprintf('%s|@%s', $message, $sampleRate);
			}
		} else {
			$sampledData = $message;
		}

		$this->_connection->send($sampledData);
	}

	/**
	 * @param string $namespace
	 */
	public function setNamespace($namespace)
	{
		$this->_namespace = (string) self::ROOT_NAMESPACE . '.' . $namespace;
	}

	/**
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->_namespace;
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->_connection->getHost();
	}
}
