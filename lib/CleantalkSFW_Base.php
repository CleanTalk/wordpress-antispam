<?php

/**
 * Patch to use CleantalkBase/CleantalkSFW as CleantalkSFW_Base
 *
 * @since 5.124.2
 *
 */

// Base classes
require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/API.php');    // API
require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/DB.php');     // Database driver
require_once(CLEANTALK_PLUGIN_DIR . 'lib/Cleantalk/Antispam/Helper.php'); // Helper
include_once(CLEANTALK_PLUGIN_DIR . "lib/Cleantalk/Antispam/SFW.php");    // SpamFireWall

require_once(CLEANTALK_PLUGIN_DIR . 'lib/CleantalkDB.php');     // Database class for Wordpress