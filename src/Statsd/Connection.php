<?php
namespace Helpers\Statsd;

interface Connection
{
	/**
	 * @param string $message
	 * @return bool
	 */
	public function send($message);

	/**
	 * @return bool
	 */
	public function forceSampling();
}
