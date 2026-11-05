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
 * Hook: controller.index
 * Fires on every storefront page request after the user/session are loaded
 * but before the page is rendered, so blocked visitors never see content.
 * Admin requests do not use this trigger and are unaffected.
 */

if (!defined('CC_INI_SET')) {
    die('Access Denied');
}

// Load the helper class with a path-relative require (the plugin directory is
// not guaranteed to be on the PHP include_path).
require_once dirname(__FILE__).'/../class.ip2location_country_blocker.php';

Ip2LocationCountryBlocker::enforceBlock();
