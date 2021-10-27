<?php

namespace Cleantalk\Common;

use Exception;

class Schema
{
    /**
     * Schema table prefix
     */
    private static $schemaTablePrefix = 'cleantalk_';

    /**
     * Structure of schema
     *
     * @var array
     */
    private static $structureSchemas = array(
        'sfw' => array(
            'id' => 'INT NOT NULL AUTO_INCREMENT',
            'network' => 'INT unsigned NOT NULL',
            'mask' => 'INT unsigned NOT NULL',
            'status' => 'TINYINT NOT NULL DEFAULT 0',
            'source' => 'TINYINT NULL DEFAULT NULL',
            '__indexes' => 'PRIMARY KEY (`id`), INDEX (  `network` ,  `mask` )',
            '__createkey' => 'INT unsigned primary KEY AUTO_INCREMENT FIRST'
        ),
        'ua_bl' => array(
            'id' => 'INT NOT NULL',
            'ua_template' => 'VARCHAR(255) NULL DEFAULT NULL',
            'ua_status' => 'TINYINT NULL DEFAULT NULL',
            '__indexes' => 'PRIMARY KEY ( `id` ), INDEX ( `ua_template` )',
            '__createkey' => 'INT unsigned primary KEY FIRST'
        ),
        'sfw_logs' => array(
            'id' => 'VARCHAR(40) NOT NULL',
            'ip' => 'VARCHAR(15) NOT NULL',
            'status' => 'ENUM(\'PASS_SFW\',\'DENY_SFW\',\'PASS_SFW__BY_WHITELIST\',\'PASS_SFW__BY_COOKIE\',\'DENY_ANTICRAWLER\',\'PASS_ANTICRAWLER\',\'DENY_ANTICRAWLER_UA\',\'PASS_ANTICRAWLER_UA\',\'DENY_ANTIFLOOD\',\'PASS_ANTIFLOOD\',\'DENY_ANTIFLOOD_UA\',\'PASS_ANTIFLOOD_UA\') NULL DEFAULT NULL',
            'all_entries' => 'INT NOT NULL',
            'blocked_entries' => 'INT NOT NULL',
            'entries_timestamp' => 'INT NOT NULL',
            'ua_id' => 'INT NULL DEFAULT NULL',
            'ua_name' => 'VARCHAR(1024) NOT NULL',
            'source' => 'TINYINT NULL DEFAULT NULL',
            'network' => 'VARCHAR(20) NULL DEFAULT NULL',
            'first_url' => 'VARCHAR(100) NULL DEFAULT NULL',
            'last_url' => 'VARCHAR(100) NULL DEFAULT NULL',
            '__indexes' => 'PRIMARY KEY (`id`)',
            '__createkey' => 'VARCHAR(40) NOT NULL primary KEY FIRST'
        ),
        'ac_log' => array(
            'id' => 'VARCHAR(40) NOT NULL',
            'ip' => 'VARCHAR(40) NOT NULL',
            'ua' => 'VARCHAR(40) NOT NULL',
            'entries' => 'INT DEFAULT 0',
            'interval_start' => 'INT NOT NULL',
            '__indexes' => 'PRIMARY KEY (`id`)',
            '__createkey' => 'VARCHAR(40) NOT NULL primary KEY FIRST'
        ),
        'sessions' => array(
            'id' => 'VARCHAR(64) NOT NULL',
            'name' => 'VARCHAR(40) NOT NULL',
            'value' => 'TEXT NULL DEFAULT NULL',
            'last_update' => 'DATETIME NULL DEFAULT NULL',
            '__indexes' => 'PRIMARY KEY (`name`(40), `id`(64))',
            '__createkey' => 'VARCHAR(64) NOT NULL primary KEY FIRST'
        ),
        'spamscan_logs' => array(
            'id' => 'INT NOT NULL AUTO_INCREMENT',
            'scan_type' => 'VARCHAR(11) NOT NULL',
            'start_time' => 'DATETIME NOT NULL',
            'finish_time' => 'DATETIME NOT NULL',
            'count_to_scan' => 'INT DEFAULT NULL',
            'found_spam' => 'INT DEFAULT NULL',
            'found_bad' => 'INT DEFAULT NULL',
            '__indexes' => 'PRIMARY KEY (`id`)',
            '__createkey' => 'INT unsigned primary KEY AUTO_INCREMENT FIRST'
        ),
    );

    /**
     * Return $schemaTablePrefix
     */
    public static function getSchemaTablePrefix()
    {
        return self::$schemaTablePrefix;
    }

    /**
     * Return $structure_schemas
     */
    public static function getStructureSchemas()
    {
        return self::$structureSchemas;
    }
}
