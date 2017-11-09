<?php
namespace Helpers\Statsd;

class StatsdSocket
	implements Connection
{
	const PROTO_UDP = 'udp';
	const PROTO_TCP = 'tcp';

	/** @var string */
	protected $_host;

	/** @var int */
	protected $_port;

	/** @var string */
	protected $_proto;

	/** @var resource */
	protected $_socket;

	/** @var bool */
	protected $_forceSampling = FALSE;

	/**
	 * StatsdSocket constructor.
	 * @param string $host
	 * @param int $port
	 * @param string $proto
	 * @throws \Exception
	 */
	public function __construct($host = 'localhost', $port = 8125, $proto = self::PROTO_UDP)
	{
		if (!in_array($proto, array(self::PROTO_UDP, self::PROTO_TCP))) {
			throw new \Exception('Invalid protocol specified.');
		}
		$this->_host = (string) $host;
		$this->_port = (int) $port;
		$this->_proto = $proto;
		$this->_socket = @fsockopen(sprintf($this->_proto . '://%s', $this->_host), $this->_port);
	}

	/**
	 * @param string $message
	 * @return bool
	 */
	public function send($message)
	{
		if (0 != strlen($message) && $this->_socket) {
			return (bool) @fwrite($this->_socket, $message . ($this->_proto === self::PROTO_TCP ? "\n" : ''));
		}
		return TRUE;
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->_host;
	}

	/**
	 * @return int
	 */
	public function getPort()
	{
		return $this->_port;
	}

	/**
	 * @return string
	 */
	public function getProto()
	{
		return $this->_proto;
	}

	/**
	 * @return bool
	 */
	public function forceSampling()
	{
		return (bool) $this->_forceSampling;
	}
}
