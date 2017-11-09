<?php
namespace Helpers\Log\Logstash;

class LogstashConfig
{
	const KEY_HOST  = 'host';
	const KEY_PORT  = 'port';
	const KEY_PROTO = 'proto';

	const PROTO_UDP = 'udp';
	const PROTO_TCP = 'tcp';

	/** @var string */
	private $host;

	/** @var int */
	private $port;

	/** @var string */
	private $proto;

	/**
	 * LogstashConfig constructor.
	 * @param array $settings
	 * @throws \Exception
	 */
	public function __construct(array $settings)
	{
		if (empty($settings[self::KEY_HOST])) {
			throw new \Exception('Host is not set or empty.');
		}

		if (empty($settings[self::KEY_PROTO]) || !in_array($settings[self::KEY_PROTO], [self::PROTO_TCP, self::PROTO_UDP])) {
			throw new \Exception('Protocol is invalid.');
		}

		if (empty($settings[self::KEY_PORT]) || !is_numeric($settings[self::KEY_PORT])) {
			throw new \Exception('Port is invalid.');
		}

		$this->host = $settings[self::KEY_HOST];
		$this->port = $settings[self::KEY_PORT];
		$this->proto = $settings[self::KEY_PROTO];
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}

	/** @return int */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * @return bool
	 */
	public function isUdp()
	{
		return strtolower($this->proto) === self::PROTO_UDP;
	}

	/**
	 * @return array
	 */
	public function toArray()
	{
		return [
			self::KEY_HOST => $this->host,
			self::KEY_PORT => $this->port,
			self::KEY_PROTO => $this->proto,
		];
	}
}