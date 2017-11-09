<?php

namespace Helpers\Log\Logstash;

class LogstashEnum
{
	const TRAFFIC_INTERNAL = 'internal';
	const TRAFFIC_EXTERNAL = 'external';

	const PARAM_ENVIRONMENT = 'env';

	const PARAM_CHANNEL = 'channel';

	/** @var string general message */
	const PARAM_MESSAGE = 'message';

	/** @var string container for indexed junk stuff */
	const PARAM_LOG_DATA = 'log_data';

	/** @var int position during once session */
	const PARAM_LOG_SEQUENCE = 'log_sequence';

	/** @var string severity */
	const PARAM_SEVERITY = 'severity';

	/** @var int incremental field over all messages */
	const PARAM_INCREMENT = 'increment';

	/** @var string name of application key in logs */
	const PARAM_APPLICATION = 'app';

	/** @var string database name from config */
	const PARAM_DATABASE = 'database';

	/** @var string name of facility key in logs */
	const PARAM_FACILITY = 'facility';

	/** @var string name of git hash */
	const PARAM_BUILD = 'build';

	/** @var mixed unmaped data */
	const PARAM_LOG_PACKAGE = 'logPackage';

	/** @var string sql query that failed */
	const PARAM_SQL = 'sql';

	/** @var string stacktrace for errors */
	const PARAM_STACK = 'stack';

	/** @var int stacktrace items max count */
	const PARAM_STACK_SIZE = 'stack_size';

	/** @var string general description if exists */
	const PARAM_DESCRIPTION = 'description';

	/** @var string host from which message has come */
	const PARAM_HOSTNAME = 'hostname';

	/** @var string pseudounique message ID to identify message in Elastic */
	const PARAM_MESSAGE_ID = 'message_id';

	/** @var string String to identify all session messages from one single instance */
	const PARAM_INSTANCE_ID = 'instance_id';

	/** @var string \Exception the exception to process internally */
	const EXCEPTION_RAW = 'exception';

	/** @var string exception class */
	const EXCEPTION_CLASS = 'exception_class';

	/** @var string file where exception was thrown */
	const EXCEPTION_FILE = 'file';

	/** @var string line where exception was thrown */
	const EXCEPTION_LINE = 'line';

	/** @var string current stack when exception was thrown */
	const EXCEPTION_STACK = 'stack';

	/** @var float time to handle request */
	const REQUEST_TIME = 'request_time';

	/**
	 * User details
	 */
	const PARAM_REMOTE_ADDR      = 'remote_addr';
	const PARAM_USER_AGENT       = 'user_agent';
	const PARAM_REQUEST_URI      = 'request_uri';
	const PARAM_REQUEST_URI_BASE = 'request_uri_base';
	const PARAM_DEVICE_TYPE      = 'device';
	const PARAM_TRAFFIC_TYPE     = 'traffic_type';

	/**
	 * Log extra params
	 */
	const PARAM_LOG_USER_EMAIL        = 'log_email';
	const PARAM_LOG_ID                = 'log_identifier';
	const PARAM_LOG_COUNT             = 'log_count';
	const PARAM_LOG_NAME              = 'log_name';
	const PARAM_LOG_REASON            = 'log_reason';
	const PARAM_LOG_EXCEPTION_MESSAGE = 'log_exception';

}
