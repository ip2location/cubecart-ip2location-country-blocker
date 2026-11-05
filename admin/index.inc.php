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
 * License:  GPL-3.0 https://www.gnu.org/licenses/quick-guide-gplv3.html
 *
 * IP2Location Country Blocker
 * ========================================
 * Admin configuration screen. Lets the store owner pick the countries to
 * block, the BIN database path, and the action to take (default 403 page,
 * custom page, or redirect to a custom URL).
 */

if (!defined('CC_INI_SET')) {
    die('Access Denied');
}

// The plugins list screen is guarded by the 'maintenance' permission, so
// use the same section here for consistency.
Admin::getInstance()->permissions('maintenance', CC_PERM_READ, true);

// Load the helper class with a path-relative require (the plugin directory is
// not guaranteed to be on the PHP include_path until its hook is registered).
require_once dirname(__FILE__).'/../class.ip2location_country_blocker.php';

// Editing requires the edit permission. Checked before the Module instance
// is created because the Module constructor persists module settings on POST.
if (isset($_POST['module']) && is_array($_POST['module'])) {
    Admin::getInstance()->permissions('maintenance', CC_PERM_EDIT, true);

    // Validate redirect_url before it is persisted by the module save so an
    // unsafe value (e.g. CR/LF header injection or a javascript: URL) never
    // reaches the database.
    if (isset($_POST['module']['redirect_url'])) {
        $GLOBALS['language']->loadDefinitions('ip2location_country_blocker', dirname(__FILE__).'/../language', 'module.definitions.xml');
        $lang['ip2location_country_blocker'] = $GLOBALS['language']->getStrings('ip2location_country_blocker');
        $redirect_url = trim($_POST['module']['redirect_url']);
        if ($redirect_url !== '' && !Ip2LocationCountryBlocker::isValidRedirectUrl($redirect_url)) {
            $GLOBALS['gui']->setError($lang['ip2location_country_blocker']['error_invalid_redirect_url']);
            $_POST['module']['redirect_url'] = ''; // don't persist the unsafe value
        } else {
            $_POST['module']['redirect_url'] = $redirect_url;
        }
    }
}

$module = new Module(__FILE__, $_GET['module'], 'admin/index.tpl', false, false);

/* Load language strings */
$lang['ip2location_country_blocker'] = $GLOBALS['language']->getStrings('ip2location_country_blocker');

/* Default settings (written on first visit so the plugin appears configured) */
if (!is_array($module->_settings)) {
    $defaults = array(
        'status'            => 0,
        'db_path'           => Ip2LocationCountryBlocker::DEFAULT_DB_PATH,
        'blocked_countries' => array(),
        'action'            => '403',
        'message_403'       => '',
        'custom_page'       => '',
        'redirect_url'      => '',
        'use_xff'           => 0,
        'download_token'    => '',
        'db_file_code'      => 'DB1LITEBIN',
        'lookup_source'     => 'bin',
        'api_key'           => '',
    );
    $module->module_settings_save($defaults);
    $module->_settings = $defaults;
}

/* On the very first visit the Module constructor ran its template setup before
   defaults existed, so re-assign the settings to Smarty to populate the form.
   Mirrors Module::_setupTemplate()'s MODULE / SELECT_ / CHECKED_ assignment. */
$base_settings = array();
foreach ($module->_settings as $key => $value) {
    if (is_array($value)) {
        $GLOBALS['smarty']->assign('MODULE_'.strtoupper($key), $value);
    } else {
        $base_settings[$key] = $value;
    }
}
$GLOBALS['smarty']->assign('MODULE', $base_settings);
foreach (array_filter($module->_settings, 'is_scalar') as $setting => $value) {
    $safe_value = str_replace(array('.', '-'), '_', $value);
    $GLOBALS['smarty']->assign('SELECT_'.$setting.'_'.$safe_value, 'selected="selected"');
    $GLOBALS['smarty']->assign('CHECKED_'.$setting.'_'.$safe_value, 'checked="checked"');
}

/* Download / update the BIN database from IP2Location. This is a separate
   form so the module settings are not touched when downloading. */
if (isset($_POST['download_db'])) {
    $dl_token = (isset($_POST['download_token'])) ? trim($_POST['download_token']) : '';
    $dl_code  = (isset($_POST['db_file_code'])) ? trim($_POST['db_file_code']) : '';
    if ($dl_token === '' || $dl_code === '') {
        $GLOBALS['gui']->setError($lang['ip2location_country_blocker']['error_download_missing']);
    } else {
        $dl_result = Ip2LocationCountryBlocker::downloadDatabase($dl_token, $dl_code, $module->_settings['db_path']);
        // Remember the token and file code so they persist for next time
        $GLOBALS['config']->set('ip2location_country_blocker', 'download_token', $dl_token);
        $GLOBALS['config']->set('ip2location_country_blocker', 'db_file_code', $dl_code);
        // On success the database is installed under the package's original
        // filename (e.g. IPV6-COUNTRY.BIN), so point the BIN database path
        // setting at it.
        $module->_settings['download_token'] = $dl_token;
        $module->_settings['db_file_code']   = $dl_code;
        if ($dl_result['success'] && !empty($dl_result['db_path'])) {
            $GLOBALS['config']->set('ip2location_country_blocker', 'db_path', $dl_result['db_path']);
            $module->_settings['db_path'] = $dl_result['db_path'];
        }
        // Reflect the saved values in the form immediately
        $GLOBALS['smarty']->assign('MODULE', array_merge((array)$GLOBALS['smarty']->getTemplateVars('MODULE'), array(
            'download_token' => $dl_token,
            'db_file_code'   => $dl_code,
            'db_path'        => $module->_settings['db_path'],
        )));
        if ($dl_result['success']) {
            $GLOBALS['gui']->setNotify(sprintf($lang['ip2location_country_blocker']['notify_download_ok'], $dl_result['file_size']));
        } else {
            $GLOBALS['gui']->setError($dl_result['message']);
        }
    }
}

/* The storefront hook is registered automatically by CubeCart (HookLoader)
   when the module is saved with Status enabled — it is declared in this
   plugin's config.xml under <hooks>, so no manual registration is needed. */

/* Validate the configured BIN database file */
$db_path = Ip2LocationCountryBlocker::resolveDbPath($module->_settings['db_path']);
$db_ok   = is_file($db_path) && is_readable($db_path);

if (!$db_ok) {
    $GLOBALS['gui']->setError(sprintf($lang['ip2location_country_blocker']['error_db_missing'], $db_path));
}

/* Validate the IP2Location.io API key format when the API source is selected */
$lookup_source = (isset($module->_settings['lookup_source'])) ? $module->_settings['lookup_source'] : 'bin';
$api_key       = (isset($module->_settings['api_key'])) ? trim($module->_settings['api_key']) : '';
if ($lookup_source === 'api' && $api_key !== '' && !preg_match('/^[A-Z0-9]{32}$/', $api_key)) {
    $GLOBALS['gui']->setError($lang['ip2location_country_blocker']['error_api_key_format']);
}

/* Test lookup: show the admin's own detected country to confirm the database works */
$test_country = Ip2LocationCountryBlocker::detectCountry($module->_settings);
$test_ip      = Ip2LocationCountryBlocker::getClientIp(!empty($module->_settings['use_xff']));

/* Build the country list with pre-computed selected flags (Smarty 4 in this
   CubeCart build has no in_array modifier, so do the comparison in PHP). */
$blocked     = (isset($module->_settings['blocked_countries']) && is_array($module->_settings['blocked_countries']))
    ? array_map('strtoupper', $module->_settings['blocked_countries'])
    : array();
$countries   = $GLOBALS['db']->select('CubeCart_geo_country', array('iso', 'name'), 'status > 0', array('name' => 'ASC'));
$country_list = array();
if (is_array($countries)) {
    foreach ($countries as $country) {
        $country['selected'] = in_array(strtoupper($country['iso']), $blocked, true);
        $country_list[]      = $country;
    }
}

/* CubeCart core shows the "Module settings ... saved." notice itself, so no
   extra notification is added here. */

$display_db_path = (!empty($module->_settings['db_path'])) ? $module->_settings['db_path'] : Ip2LocationCountryBlocker::DEFAULT_DB_PATH;
$template_vars = array(
    'COUNTRIES'        => $country_list,
    'DB_PATH'          => htmlspecialchars($display_db_path, ENT_QUOTES, 'UTF-8'),
    'DB_OK'            => $db_ok,
    'TEST_COUNTRY'     => ($test_country !== false) ? $test_country : $lang['ip2location_country_blocker']['country_unknown'],
    'TEST_IP'          => $test_ip,
    'DB_LITE_URL'      => 'https://lite.ip2location.com/database-download',
    'BLOCKED_COUNT'    => count($blocked),
);

$module->assign_to_template($template_vars, false);
$module->fetch();
$page_content = $module->display();
