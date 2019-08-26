<?php

/**
 * Patch to use CleantalkBase/CleantalkSFW as CleantalkSFW_Base
 *
 * @since 5.124.2
 *
 */

// Base classes
require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkBase/CleantalkAPI.php');    // API
require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkBase/CleantalkDB.php');     // Database driver
require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkBase/CleantalkHelper.php'); // Helper
include_once(CLEANTALK_PLUGIN_DIR . "lib/CleantalkBase/CleantalkSFW.php");    // SpamFireWall

require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkDB.php');     // Database class for Wordpress

