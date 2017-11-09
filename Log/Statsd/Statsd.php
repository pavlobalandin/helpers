<?php
namespace Helpers\Log\Statsd;

class Statsd
{
	/**
	 * @var StatsdClient
	 */
	private $client;

	/**
	 * @param StatsdClient $client
	 */
	public function __construct(StatsdClient $client)
	{
		$this->client = $client;
	}

	/**
	 * @param string $key
	 * @param int $sampleRate
	 */
	public function increment($key, $sampleRate = 1)
	{
		$this->client->increment($key, $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $sampleRate
	 */
	public function decrement($key, $sampleRate = 1)
	{
		$this->client->decrement($key, $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $value
	 * @param int $sampleRate
	 */
	public function timing($key, $value, $sampleRate = 1)
	{
		$this->client->timing($key, $value, $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $value
	 * @param int $sampleRate
	 */
	public function count($key, $value, $sampleRate = 1)
	{
		$this->client->count($key, $value, $sampleRate);
	}

	/**
	 * @param string $key
	 * @param int $value
	 */
	public function gauge($key, $value)
	{
		$this->client->gauge($key, $value);
	}

	/**
	 * @param string $namespace
	 */
	public function setNamespace($namespace)
	{
		$this->client->setNamespace($namespace);
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->client->getHost();
	}
}
