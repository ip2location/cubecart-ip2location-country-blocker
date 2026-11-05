<?php

namespace IP2LocationIO;

/**
 * IP2Location.io IP Geolocation module.
 */
class IPGeolocation
{
	private $apiKey = '';

	public function __construct($config)
	{
		if (!isset($config->apiKey)) {
			throw new \Exception('Please provide a valid API key.');
		}

		if (!preg_match('/^[A-Z0-9]{32}$/', $config->apiKey)) {
			throw new \Exception('Please provide a valid API key.');
		}

		$this->apiKey = $config->apiKey;
	}

	/**
	 * Lookup given IP address for an enriched data set.
	 *
	 * @param string $ip
	 * @param string $language
	 *
	 * @return object
	 */
	public function lookup($ip, $language = '')
	{
		$http = new Http();
		$response = $http->get('https://api.ip2location.io/?' . http_build_query([
			'key'            => $this->apiKey,
			'format'         => 'json',
			'ip'             => $ip,
			'lang'           => $language,
			'source'         => 'sdk-php-iplio',
			'source_version' => Configuration::VERSION,
		]));

		if (($json = json_decode($response)) === null) {
			throw new \Exception('IPGeolocation lookup error.', 10005);
		}

		if (isset($json->error)) {
			throw new \Exception($json->error->error_message, $json->error->error_code);
		} else {
			return $json;
		}
	}
}

class_alias('IP2LocationIO\IPGeolocation', 'IP2LocationIO_IPGeolocation');
