<?php

/**
 * CubeCart v6
 * ========================================
 * CubeCart is a registered trade mark of CubeCart Limited
 * Copyright CubeCart Limited 2026. All rights reserved.
 * UK Private Limited Company No. 5323904
 * ========================================
 * Web:   https://www.cubecart.com
 * Email:  hello@cubecart.com
 * License:  GPL-3.0 https://www.gnu.org/licenses/quick-guide-gplv3.html.
 *
 * IP2Location Country Blocker
 * ========================================
 * Core helper class. Detects the visitor's origin country with the bundled
 * IP2Location library and blocks the request when the country is on the
 * store owner's block list.
 */
if (!defined('CC_INI_SET')) {
	exit('Access Denied');
}

/**
 * Register a small autoloader for the bundled libraries:
 *   IP2Location\    -> includes/  (IP2Location BIN database library)
 *   IP2LocationIO\  -> includes/ip2locationio/  (IP2Location.io API SDK)
 */
spl_autoload_register(function ($class) {
	if (strpos($class, 'IP2LocationIO\\') === 0) {
		$file = __DIR__ . '/includes/ip2locationio/' . str_replace('\\', '/', substr($class, strlen('IP2LocationIO\\'))) . '.php';
		if (is_file($file)) {
			require $file;
		}
	} elseif (strpos($class, 'IP2Location\\') === 0) {
		$file = __DIR__ . '/includes/' . str_replace('\\', '/', substr($class, strlen('IP2Location\\'))) . '.php';
		if (is_file($file)) {
			require $file;
		}
	}
});

if (!class_exists('Ip2LocationCountryBlocker', false)) {
	class Ip2LocationCountryBlocker
	{
		/**
		 * Config name (matches the plugin folder name) used as the
		 * CubeCart_config row name.
		 */
		public const MODULE = 'ip2location_country_blocker';

		/**
		 * Default relative path to the IP2Location BIN database shipped
		 * with this plugin. Can be overridden in the admin settings.
		 */
		public const DEFAULT_DB_PATH = 'modules/plugins/ip2location_country_blocker/data/IP2LOCATION-LITE-DB1.BIN';

		/**
		 * Read the stored module settings.
		 *
		 * @return array
		 */
		public static function getSettings()
		{
			$settings = $GLOBALS['config']->get(self::MODULE);
			if (!is_array($settings)) {
				$settings = [];
			}
			return $settings;
		}

		/**
		 * Resolve the configured BIN path to an absolute filesystem path.
		 * Relative paths are resolved against the store root.
		 *
		 * @param string $db_path
		 *
		 * @return string
		 */
		public static function resolveDbPath($db_path = '')
		{
			if (empty($db_path)) {
				$db_path = self::DEFAULT_DB_PATH;
			}
			// Absolute on *nix (starts with /) or Windows (starts with drive letter)
			if (!preg_match('#^(/|[a-zA-Z]:[\\\\/])#', $db_path)) {
				$db_path = CC_ROOT_DIR . '/' . $db_path;
			}
			return str_replace('\\', '/', $db_path);
		}

		/**
		 * Determine the visitor's IP address. When $use_xff is enabled the
		 * first valid address from the X-Forwarded-For header is used, which
		 * is useful when the store sits behind a proxy or load balancer.
		 *
		 * @param bool $use_xff
		 *
		 * @return string
		 */
		public static function getClientIp($use_xff = false)
		{
            return '8.8.8.8';

			if ($use_xff && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
					$ip = trim($ip);
					if (filter_var($ip, FILTER_VALIDATE_IP)) {
						return $ip;
					}
				}
			}
			return (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		}

		/**
		 * Look up the ISO country code for an IP address using the bundled
		 * IP2Location library.
		 *
		 * @param string $ip
		 * @param string $db_path Absolute path to the BIN database
		 *
		 * @return string|false ISO country code, or false on error/unknown
		 */
		public static function lookupCountry($ip, $db_path)
		{
			if (!filter_var($ip, FILTER_VALIDATE_IP)) {
				return false;
			}
			if (!is_file($db_path) || !is_readable($db_path)) {
				self::log('Database file not found or not readable: ' . $db_path);
				return false;
			}
			try {
				$db = new \IP2Location\Database($db_path, \IP2Location\Database::FILE_IO);
				$result = $db->lookup($ip, [\IP2Location\Database::COUNTRY_CODE]);
			} catch (\Exception $e) {
				self::log('IP2Location lookup error: ' . $e->getMessage());
				return false;
			}
			if (!is_array($result) || empty($result['countryCode'])) {
				return false;
			}
			$country = strtoupper($result['countryCode']);
			// '-' is what the library returns for IP ranges with no data
			return ($country === '-') ? false : $country;
		}

		/**
		 * Detect the visitor's origin country, caching the result in the
		 * session so the source is only queried once per visitor. The lookup
		 * source is either the local IP2Location BIN database ('bin') or the
		 * IP2Location.io web API ('api').
		 *
		 * @param array $settings
		 *
		 * @return string|false
		 */
		public static function detectCountry($settings)
		{
			$source = (isset($settings['lookup_source']) && $settings['lookup_source'] === 'api') ? 'api' : 'bin';
			$ip = self::getClientIp(!empty($settings['use_xff']));

			// Cache per IP (and per source, so switching sources re-detects)
			$cache_key = 'ip2loc_' . ($source === 'api' ? 'a' : 'b') . '_' . md5($ip);
			$cached = (isset($GLOBALS['session'])) ? $GLOBALS['session']->get($cache_key, 'ip2location', null) : null;
			if ($cached !== null) {
				return $cached;
			}

			if ($source === 'api') {
				$api_key = (isset($settings['api_key'])) ? trim($settings['api_key']) : '';
				$country = (!empty($api_key)) ? self::lookupCountryApi($ip, $api_key) : false;
			} else {
				$db_path = self::resolveDbPath($settings['db_path'] ?? '');
				$country = self::lookupCountry($ip, $db_path);
			}

			if ($country !== false && isset($GLOBALS['session'])) {
				$GLOBALS['session']->set($cache_key, $country, 'ip2location');
			}
			return $country;
		}

		/**
		 * Look up the ISO country code for an IP address through the
		 * IP2Location.io web API using the bundled ip2location-io-php SDK.
		 *
		 * @param string $ip
		 * @param string $api_key
		 *
		 * @return string|false
		 */
		public static function lookupCountryApi($ip, $api_key)
		{
			if (!filter_var($ip, FILTER_VALIDATE_IP) || trim($api_key) === '') {
				return false;
			}
			try {
				$config = new \IP2LocationIO\Configuration($api_key);
				$geo = new \IP2LocationIO\IPGeolocation($config);
				$result = $geo->lookup($ip);
			} catch (\Exception $e) {
				self::log('IP2Location.io API lookup error: ' . $e->getMessage());
				return false;
			}
			if (!is_object($result) || !isset($result->country_code)) {
				return false;
			}
			$country = strtoupper($result->country_code);
			// '-' is what the API returns for ranges with no data
			return ($country === '' || $country === '-') ? false : $country;
		}

		/**
		 * Decide whether a detected country is on the block list.
		 *
		 * @param string|false $country
		 * @param mixed        $blocked Countries stored in the settings
		 *
		 * @return bool
		 */
		public static function isBlocked($country, $blocked)
		{
			if (empty($country) || empty($blocked)) {
				return false;
			}
			$blocked = (is_array($blocked)) ? $blocked : [$blocked];
			$blocked = array_map('strtoupper', $blocked);
			return in_array(strtoupper($country), $blocked, true);
		}

		/**
		 * Enforce the block: called from the controller.index hook on every
		 * storefront request.
		 */
		public static function enforceBlock()
		{
			$settings = self::getSettings();
			if (empty($settings['status'])) {
				return;
			}

			$blocked = $settings['blocked_countries'] ?? [];
			if (empty($blocked)) {
				return;
			}

			$country = self::detectCountry($settings);
			if (self::isBlocked($country, $blocked)) {
				self::blockVisitor($settings, $country);
			}
		}

		/**
		 * Validate a redirect URL before it is used in a Location: header.
		 * Allows http/https absolute URLs, protocol-relative (//host/path) and
		 * store-relative paths. Rejects header injection (CR/LF/control
		 * characters) and non-http schemes such as javascript:, data: or
		 * vbscript:.
		 *
		 * @param string $url
		 * @return bool
		 */
		public static function isValidRedirectUrl($url)
		{
			$url = trim($url);
			if ($url === '') {
				// Empty is fine - blockVisitor falls back to the 403 page
				return true;
			}
			// Reject header injection and control characters
			if (preg_match('/[\r\n\x00-\x1F\x7F]/', $url)) {
				return false;
			}
			// Absolute URL: only the http/https scheme is allowed
			if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
				return (bool)preg_match('#^https?://#i', $url);
			}
			// Protocol-relative URLs resolve to http(s) in the browser
			if (substr($url, 0, 2) === '//') {
				return true;
			}
			// Store-relative path: must start with /, ./ or ../ (a real path),
			// or index.php (CubeCart convention). Bare words like "xx" are
			// rejected because they are not usable redirect targets.
			return (bool)preg_match('!^(/|\.{1,2}/|index\.php)[a-zA-Z0-9_./~?#&%+=\-:@]*$!', $url);
		}

		/**
		 * Perform the configured block action (403 / custom page / redirect)
		 * and terminate the request.
		 *
		 * @param array  $settings
		 * @param string $country  Detected ISO country code
		 */
		public static function blockVisitor($settings, $country)
		{
			$action = $settings['action'] ?? '403';
			self::log("Blocking visitor from {$country} (action: {$action})");

			if ($action === 'redirect') {
				$url = isset($settings['redirect_url']) ? trim($settings['redirect_url']) : '';
				// Only redirect to a validated, safe URL; otherwise fall back
				// to the 403 page (defence in depth).
				if (!empty($url) && self::isValidRedirectUrl($url)) {
					header('Location: ' . $url, true, 302);
					exit;
				}
				$action = '403';
			}

			if ($action === 'page') {
				$content = $settings['custom_page'] ?? '';
				if (!empty($content)) {
					header('HTTP/1.1 403 Forbidden');
					header('Status: 403 Forbidden');
					echo $content;
					exit;
				}
				$action = '403';
			}

			// 403 (default)
			$message = $settings['message_403'] ?? '';
			if (empty($message)) {
				$message = 'Access denied';
			}
			header('HTTP/1.1 403 Forbidden');
			header('Status: 403 Forbidden');
			echo self::render403Page($message);
			exit;
		}

		/**
		 * Build a minimal, self-contained 403 error page.
		 *
		 * @param string $message
		 *
		 * @return string
		 */
		public static function render403Page($message)
		{
			$message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
			return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 Forbidden</title>
</head>
<body style="margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;color:#333;">
<div style="max-width:520px;margin:10% auto;padding:40px;background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.12);text-align:center;">
<h1 style="font-size:64px;margin:0 0 8px;color:#c0392b;font-weight:700;">403</h1>
<p style="font-size:18px;margin:0 0 16px;color:#555;">' . htmlspecialchars('Forbidden', ENT_QUOTES, 'UTF-8') . '</p>
<p style="font-size:14px;line-height:1.6;color:#777;">' . $message . '</p>
</div>
</body>
</html>';
		}

		/**
		 * Perform an HTTP GET request with cURL.
		 *
		 * @param string      $url
		 * @param int         $timeout
		 * @param string|null $dest_file Optional file to stream the body into
		 *
		 * @return string|bool Body string, or true/false when writing to a file
		 */
		public static function httpGet($url, $timeout = 120, $dest_file = null)
		{
			if (!function_exists('curl_init')) {
				self::log('cURL extension is required.');
				return false;
			}
			$ch = curl_init($url);
			$fp = ($dest_file !== null) ? @fopen($dest_file, 'wb') : null;
			curl_setopt_array($ch, [
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_CONNECTTIMEOUT => 30,
				CURLOPT_USERAGENT      => 'CubeCart/IP2Location-Country-Blocker',
			]);
			if ($fp) {
				curl_setopt($ch, CURLOPT_FILE, $fp);
			} else {
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			}
			$body = curl_exec($ch);
			$error = curl_error($ch);
			curl_close($ch);
			if ($fp) {
				fclose($fp);
			}
			if ($body === false) {
				self::log('cURL GET failed: ' . $error);
				return false;
			}
			return ($dest_file !== null) ? true : $body;
		}

		/**
		 * Perform an HTTP POST request with url-encoded fields using cURL.
		 * When $dest_file is provided the response body is streamed directly
		 * to that file instead of being returned as a string.
		 *
		 * @param string      $url
		 * @param array       $fields
		 * @param int         $timeout
		 * @param string|null $dest_file Optional file to stream the body into
		 *
		 * @return string|bool Body string, or true/false when writing to a file
		 */
		public static function httpPost($url, $fields = [], $timeout = 120, $dest_file = null)
		{
			if (!function_exists('curl_init')) {
				self::log('cURL extension is required.');
				return false;
			}
			$ch = curl_init($url);
			$fp = ($dest_file !== null) ? @fopen($dest_file, 'wb') : null;
			if ($dest_file !== null && !$fp) {
				return false;
			}
			curl_setopt_array($ch, [
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => http_build_query($fields),
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_CONNECTTIMEOUT => 30,
				CURLOPT_USERAGENT      => 'CubeCart/IP2Location-Country-Blocker',
			]);
			if ($fp) {
				curl_setopt($ch, CURLOPT_FILE, $fp);
			} else {
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			}
			$body = curl_exec($ch);
			$error = curl_error($ch);
			curl_close($ch);
			if ($fp) {
				fclose($fp);
			}
			if ($body === false) {
				self::log('cURL POST failed: ' . $error);
				return false;
			}
			return ($dest_file !== null) ? true : $body;
		}

		/**
		 * Verify a download token and file code with IP2Location and obtain
		 * the expected size of the archive. Mirrors IP2Location's official
		 * download helper (ip2location_bin_download.php).
		 *
		 * @param string $token
		 * @param string $file_code e.g. DB1LITEBIN
		 *
		 * @return array {ok, size, message}
		 */
		public static function verifyDownload($token, $file_code)
		{
			$token = trim($token);
			$code = trim($file_code);
			if ($token === '') {
				return ['ok' => false, 'size' => 0, 'message' => 'Missing IP2Location download token.'];
			}
			if ($code === '' || !preg_match('/^[A-Za-z0-9]+$/', $code)) {
				return ['ok' => false, 'size' => 0, 'message' => 'Invalid IP2Location database file code.'];
			}
			$response = self::httpPost('https://www.ip2location.com/download-info', [
				'token'   => $token,
				'package' => $code,
			]);
			if ($response === false) {
				return ['ok' => false, 'size' => 0, 'message' => 'Could not reach the IP2Location download server.'];
			}
			$data = explode(';', $response);
			$status = (isset($data[0])) ? strtoupper(trim($data[0])) : '';
			switch ($status) {
				case 'OK':
					$size = (isset($data[3]) && is_numeric($data[3])) ? (int)$data[3] : 0;
					return ['ok' => true, 'size' => $size, 'message' => ''];
				case 'EXPIRED':
					$date = (isset($data[1])) ? trim($data[1]) : '';
					return ['ok' => false, 'size' => 0, 'message' => 'The download account expired on ' . $date . '. Renew your subscription at ip2location.com.'];
				case 'NOPERMISSION':
					return ['ok' => false, 'size' => 0, 'message' => 'This token/file code cannot download the database (permission issue).'];
				default:
					return ['ok' => false, 'size' => 0, 'message' => 'IP2Location rejected the download request. Check your token and file code.'];
			}
		}

		/**
		 * Download the BIN database from IP2Location and install it over the
		 * configured database file. The existing file is only replaced after
		 * the downloaded archive passes an integrity check and the extracted
		 * BIN validates as a real IP2Location database.
		 *
		 * @param string $token
		 * @param string $file_code e.g. DB1LITEBIN
		 * @param string $db_path   Configured path of the BIN database
		 *
		 * @return array {success, message, file_size}
		 */
		public static function downloadDatabase($token, $file_code, $db_path)
		{
			@set_time_limit(300);
			$target = self::resolveDbPath($db_path);
			$target_dir = dirname($target);

			if (!is_dir($target_dir) || !is_writable($target_dir)) {
				return ['success' => false, 'message' => 'The database folder is not writable: ' . $target_dir, 'file_size' => 0];
			}

			// 1. Verify the token/file code and get the expected archive size
			$info = self::verifyDownload($token, $file_code);
			if (!$info['ok']) {
				return ['success' => false, 'message' => $info['message'], 'file_size' => 0];
			}

			// 2. Download the ZIP archive (POST, matching IP2Location's official
			//    ip2location_bin_download.php helper)
			$tmp_dir = $target_dir . '/ip2loc_dl_' . str_replace('.', '', uniqid('', true));
			if (!@mkdir($tmp_dir, 0755, true)) {
				return ['success' => false, 'message' => 'Could not create a temporary folder for the download.', 'file_size' => 0];
			}
			$archive = $tmp_dir . '/db.zip';
			if (!self::httpPost('https://www.ip2location.com/download', ['token' => trim($token), 'file' => trim($file_code)], 180, $archive)) {
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'Download failed. The IP2Location server did not return any data.', 'file_size' => 0];
			}

			// 3. Basic sanity check: the archive must not be empty. An exact
			//    size match against download-info is deliberately NOT required
			//    — IP2Location regenerates the ZIP on every request (entry
			//    timestamps vary), so the reported size can differ by a few KB
			//    even for a perfectly good download. The archive's actual
			//    content is fully validated in the install step below.
			$dl_size = (int)filesize($archive);
			if ($dl_size === 0) {
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'Download failed. The IP2Location server returned an empty file.', 'file_size' => 0];
			}

			// 4. Extract, validate and install the BIN from the ZIP archive
			$install = self::installDatabaseArchive($archive, $target);
			if ($install['success'] && !empty($install['path'])) {
				// Provide a store-relative path so the admin can point the
				// "IP2Location BIN database path" setting at the new file.
				// realpath() normalises any .. segments on both sides.
				$abs = str_replace('\\', '/', realpath($install['path']));
				$root = str_replace('\\', '/', realpath(CC_ROOT_DIR));
				$install['db_path'] = ($root !== '' && strpos($abs, $root . '/') === 0)
					? substr($abs, strlen($root) + 1)
					: str_replace('\\', '/', $install['path']);
			}
			self::rrmdir($tmp_dir);
			if (!$install['success']) {
				$install['file_size'] = $dl_size;
			}
			return $install;
		}

		/**
		 * Verify that a file looks like a genuine IP2Location BIN database by
		 * inspecting its 512-byte metadata header. The header layout is
		 * documented at https://www.ip2location.com/development/binary-file-format:
		 *   byte 0      database type/version (1..25)
		 *   byte 1      column count
		 *   bytes 2-4   database date (year-2000, month, day)
		 *   bytes 5-8   IPv4 range count (unsigned 32-bit LE)
		 *   bytes 13-16 IPv6 range count (unsigned 32-bit LE).
		 *
		 * @param string $file
		 *
		 * @return bool
		 */
		public static function isValidBinFile($file)
		{
			if (!is_file($file) || filesize($file) < 512) {
				return false;
			}
			$fp = @fopen($file, 'rb');
			if (!$fp) {
				return false;
			}
			$headers = fread($fp, 512);
			fclose($fp);
			if (strlen($headers) < 512) {
				return false;
			}
			$b = unpack('C*', $headers);
			$type = $b[1]; // database type/version
			$cols = $b[2]; // column count
			$year = 2000 + $b[3];
			$month = $b[4];
			$day = $b[5];
			$ipv4 = unpack('V', substr($headers, 5, 4))[1];
			$ipv6 = unpack('V', substr($headers, 13, 4))[1];

			if ($type < 1 || $type > 26) {
				return false;
			}
			if ($cols < 1 || $cols > 32) {
				return false;
			}
			if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
				return false;
			}
			if ($ipv4 == 0 && $ipv6 == 0) {
				return false;
			}
			return true;
		}

		/**
		 * Install a BIN database from a local ZIP archive into the directory
		 * of the target file. The installed file keeps the original .BIN
		 * filename from the package (e.g. IPV6-COUNTRY.BIN) and is only
		 * written once the archive opens, contains a .BIN entry, and that
		 * entry validates as an IP2Location database.
		 *
		 * @param string $archive_path Path to the ZIP archive
		 * @param string $target       Absolute path whose directory receives the BIN
		 *
		 * @return array {success, message, file_size, path}
		 */
		public static function installDatabaseArchive($archive_path, $target)
		{
			if (!is_file($archive_path)) {
				return ['success' => false, 'message' => 'The database archive could not be found.', 'file_size' => 0];
			}
			if (!class_exists('ZipArchive')) {
				return ['success' => false, 'message' => 'The PHP ZipArchive extension is required to install the database.', 'file_size' => 0];
			}
			$tmp_dir = dirname($archive_path) . '/ip2loc_extract_' . str_replace('.', '', uniqid('', true));
			if (!@mkdir($tmp_dir, 0755, true)) {
				return ['success' => false, 'message' => 'Could not create a temporary folder to extract the database.', 'file_size' => 0];
			}

			$zip = new \ZipArchive();
			if ($zip->open($archive_path) !== true) {
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'The downloaded file is not a valid IP2Location archive. Check your token and file code.', 'file_size' => 0];
			}
			$bin_name = null;
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$name = $zip->getNameIndex($i);
				if (strtoupper(substr($name, -4)) === '.BIN') {
					$bin_name = $name;
					break;
				}
			}
			if ($bin_name === null || $zip->extractTo($tmp_dir) !== true) {
				$zip->close();
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'The archive did not contain an extractable BIN database file.', 'file_size' => 0];
			}
			$zip->close();

			$extracted_bin = $tmp_dir . '/' . $bin_name;
			if (!is_file($extracted_bin)) {
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'The BIN file could not be extracted from the archive.', 'file_size' => 0];
			}

			// Validate it really is an IP2Location BIN database before
			// replacing any working file
			if (!self::isValidBinFile($extracted_bin)) {
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'The downloaded file is not a valid IP2Location BIN database.', 'file_size' => 0];
			}
			try {
				$test = new \IP2Location\Database($extracted_bin);
				unset($test);
			} catch (\Exception $e) {
				self::rrmdir($tmp_dir);
				return ['success' => false, 'message' => 'The downloaded file is not a valid IP2Location BIN database: ' . $e->getMessage(), 'file_size' => 0];
			}

			// Install using the package's original BIN filename (e.g.
			// IPV6-COUNTRY.BIN) into the target directory, so the file on
			// disk matches what IP2Location ships in the package.
			$final = dirname($target) . '/' . basename($bin_name);
			$installed = false;
			if (@rename($extracted_bin, $final)) {
				$installed = true;
			} elseif (@copy($extracted_bin, $final)) {
				@unlink($extracted_bin);
				$installed = true;
			}
			self::rrmdir($tmp_dir);

			if (!$installed) {
				return ['success' => false, 'message' => 'Could not install the database file at: ' . $final, 'file_size' => 0];
			}
			return ['success' => true, 'message' => 'Database updated successfully.', 'file_size' => (int)filesize($final), 'path' => $final];
		}

		/**
		 * Recursively delete a directory tree.
		 *
		 * @param string $dir
		 */
		public static function rrmdir($dir)
		{
			if (!is_dir($dir)) {
				return;
			}
			$items = glob($dir . '/*');
			if (is_array($items)) {
				foreach ($items as $item) {
					if (is_dir($item)) {
						self::rrmdir($item);
					} else {
						@unlink($item);
					}
				}
			}
			@rmdir($dir);
		}

		/**
		 * Write a message to the CubeCart debug log (shown when debug mode
		 * or log writing is enabled).
		 *
		 * @param string $message
		 */
		private static function log($message)
		{
			if (isset($GLOBALS['debug']) && is_object($GLOBALS['debug'])) {
				$GLOBALS['debug']->debugMessage('IP2Location Country Blocker: ' . $message);
			}
		}
	}
}
