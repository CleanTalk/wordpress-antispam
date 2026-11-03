<?php

namespace Cleantalk\ApbctWP;

/**
 * Replaces the `$apbct->service_constants-><name>->isDefined()` / `getValue()` usage with a single static call:
 *     Constant::is(Constant::APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE)         // just defined & of the declared type
 *     Constant::is(Constant::APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE, false)  // ... and strictly equals false
 *
 * The registry holds no state and does not depend on Cleantalk\ApbctWP\State, so it is usable
 * at any point of the bootstrap - including before $apbct is instantiated.
 *
 * Every constant is declared twice: under its canonical `APBCT_SERVICE__*` name and, when a legacy
 * name is still supported, under that name carrying a deprecation tag. Both class constants resolve
 * to the same registry entry, so outdated call sites keep working while an IDE/Psalm strikes them
 * through and reports `DeprecatedConstant`.
 */
class Constant
{
    /**
     * Disable the exception that skips POST data checks when no email address is found.
     * Defining either true or false activates this behavior. The "Check all POST data"
     * setting takes precedence. Without this constant, normal exception handling applies.
     *
     * @var bool
     */
    const APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION = 'APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION';

    /**
     * Skip Anti-Crawler checks for requests whose URI contains "feed".
     * Defining either true or false enables this additional RSS exception.
     * Without this constant, the normal Anti-Crawler exclusions apply.
     *
     * @var bool
     */
    const APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED = 'APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED';

    /**
     * Pass anti-crawler check on RSS feed service.
     * @deprecated Use self::APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED
     */
    const APBCT_ANTICRAWLER_EXLC_FEED = 'APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED';

    /**
     * Force the AJAX route to 'rest' or 'admin_ajax'.
     * Other strings leave automatic route selection unchanged.
     *
     * @var string
     */
    const APBCT_SERVICE__SET_AJAX_ROUTE_TYPE = 'APBCT_SERVICE__SET_AJAX_ROUTE_TYPE';

    /**
     * Provides AJAX route type. Expected values: 'rest', 'admin_ajax'.
     * @deprecated Use self::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE
     */
    const APBCT_SET_AJAX_ROUTE_TYPE = 'APBCT_SERVICE__SET_AJAX_ROUTE_TYPE';

    /**
     * Supply a site-owned access key and override the key field in the settings.
     * Any string is accepted by the registry, including an empty string; use a valid key.
     * Its presence also affects database-prefix selection. The network updater still
     * reads only the deprecated CLEANTALK_ACCESS_KEY name directly.
     *
     * @var string
     */
    const APBCT_SERVICE__SELF_OWNED_ACCESS_KEY = 'APBCT_SERVICE__SELF_OWNED_ACCESS_KEY';

    /**
     * Provides user own access key.
     * @deprecated Use self::APBCT_SERVICE__SELF_OWNED_ACCESS_KEY
     */
    const CLEANTALK_ACCESS_KEY = 'APBCT_SERVICE__SELF_OWNED_ACCESS_KEY';

    /**
     * Force Bot Detector on with true or off with false.
     * Without this constant, the saved Bot Detector settings apply.
     *
     * @var bool
     */
    const APBCT_SERVICE__BOT_DETECTOR_ENABLED = 'APBCT_SERVICE__BOT_DETECTOR_ENABLED';

    /**
     * Place public JavaScript files in the page footer when the value is true.
     * False or an absent constant leaves normal script placement unchanged.
     *
     * @var bool
     */
    const APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER = 'APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER';

    /**
     * If isset, any public scripts will be placed in a page footer.
     * @deprecated Use self::APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER
     */
    const CLEANTALK_PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER = 'APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER';

    /**
     * Override the white-label FAQ link with a string, sanitized using esc_url.
     * Without this constant, the existing FAQ link is retained.
     *
     * @var string
     */
    const APBCT_SERVICE__WHITELABEL_FAQ_LINK = 'APBCT_SERVICE__WHITELABEL_FAQ_LINK';

    /**
     * Provides whitelabel-mode FAQ link.
     * @deprecated Use self::APBCT_SERVICE__WHITELABEL_FAQ_LINK
     */
    const APBCT_WHITELABEL_FAQ_LINK = 'APBCT_SERVICE__WHITELABEL_FAQ_LINK';

    /**
     * Override the white-label plugin description with a string, escaped using esc_html.
     * Without this constant, the existing description is retained.
     *
     * @var string
     */
    const APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION = 'APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION';

    /**
     * Provides whitelabel-mode plugin description.
     * @deprecated Use self::APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION
     */
    const APBCT_WHITELABEL_PLUGIN_DESCRIPTION = 'APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION';

    /**
     * Register the white-label mode switch.
     * The network updater currently reads only APBCT_WHITELABEL directly:
     * true enables the mode and false disables it. Defining the current name alone
     * does not activate that legacy code path.
     *
     * @var bool
     */
    const APBCT_SERVICE__WHITELABEL_ENABLED = 'APBCT_SERVICE__WHITELABEL_ENABLED';

    /**
     * If defined, plugin will be in whitelabel mode.
     * @deprecated Use self::APBCT_SERVICE__WHITELABEL_ENABLED
     */
    const APBCT_WHITELABEL = 'APBCT_SERVICE__WHITELABEL_ENABLED';

    /**
     * Register a custom white-label product name.
     * The network updater currently reads only APBCT_WHITELABEL_NAME directly,
     * when APBCT_WHITELABEL is defined. The current name alone does not change
     * the product name in that legacy code path.
     *
     * @var string
     */
    const APBCT_SERVICE__WHITELABEL_PRODUCT_NAME = 'APBCT_SERVICE__WHITELABEL_PRODUCT_NAME';

    /**
     * Provides product name for whitelabel mode.
     * @deprecated Use self::APBCT_SERVICE__WHITELABEL_PRODUCT_NAME
     */
    const APBCT_WHITELABEL_NAME = 'APBCT_SERVICE__WHITELABEL_PRODUCT_NAME';

    /**
     * Force direct SFW updates instead of the update queue.
     * Defining either true or false activates this behavior.
     * Useful when remote calls fail; remove the definition to restore automatic selection.
     *
     * @var bool
     */
    const APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE = 'APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE';

    /**
     * If defined, SFW update mode is always DIRECT. Helpful if update queue fails due remote calls.
     * @deprecated Use self::APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE
     */
    const APBCT_SFW_FORCE_DIRECT_UPDATE = 'APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE';

    /**
     * Hide the CleanTalk title on blocking pages and rejected-comment messages with true.
     * False or an absent constant preserves the title.
     *
     * @var bool
     */
    const APBCT_SERVICE__DISABLE_BLOCKING_TITLE = 'APBCT_SERVICE__DISABLE_BLOCKING_TITLE';

    /**
     * If defined, no title will be provided for blocking page.
     * @deprecated Use self::APBCT_SERVICE__DISABLE_BLOCKING_TITLE
     */
    const CLEANTALK_DISABLE_BLOCKING_TITLE = 'APBCT_SERVICE__DISABLE_BLOCKING_TITLE';

    /**
     * Set the approved-comment count required to skip checking an author's comments,
     * when the corresponding comment-checking setting is enabled.
     * The default is 3. The registry checks the integer type but not its range.
     *
     * @var int
     */
    const APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER = 'APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER';

    /**
     * Redefine how many comments should be approved before skip checking.
     * @deprecated Use self::APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER
     */
    const CLEANTALK_CHECK_COMMENTS_NUMBER = 'APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER';

    /**
     * Disable frontend-data log collection for debugging.
     * Defining either true or false disables collection and sets the frontend log flag to 0.
     * Without this constant, normal log collection applies.
     *
     * @var bool
     */
    const APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS = 'APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS';

    /**
     * If defined, no frontend-data logs will be collected. Debugging case usage.
     * @deprecated Use self::APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS
     */
    const APBCT_DO_NOT_COLLECT_FRONTEND_DATA_LOGS = 'APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS';

    /**
     * Register a custom CleanTalk server domain.
     * Bootstrap currently reads only CLEANTALK_SERVER directly, building
     * https://moderate.<domain> and, unless CLEANTALK_API_URL is already defined,
     * https://api.<domain>. Use a domain, not a complete URL.
     * Defining the current name alone does not change those endpoints.
     *
     * @var string
     */
    const APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL = 'APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL';

    /**
     * Provides own URL of API server.
     * @deprecated Use self::APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL
     */
    const CLEANTALK_SERVER = 'APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL';

    /**
     * Disable integration metrics when defined as either true or false.
     * Remove the definition to allow normal metric collection.
     *
     * @var bool
     */
    const APBCT_SERVICE__DISABLE_INTEGRATION_METRICS = 'APBCT_SERVICE__DISABLE_INTEGRATION_METRICS';
    /**
     * Registry cache. Built once per request by self::getRegistry().
     *
     * @var array<string, array{names: string[], type: string, description: string}>|null
     */
    private static $registry;

    /**
     * Maps a declared type to what gettype() actually returns.
     *
     * @var array<string, string>
     */
    private static $type_aliases = array(
        'bool'  => 'boolean',
        'int'   => 'integer',
        'float' => 'double',
    );

    /**
     * Is the constant usable: defined, of the declared type and - when $expected_value is passed -
     * strictly equal to it.
     *
     * Passing no $expected_value means "the fact of definition is enough". Passing one makes the
     * check value-aware, so `define('APBCT_SERVICE__DISABLE_BLOCKING_TITLE', false)` can be told
     * apart from the constant being absent.
     *
     * @param string $constant One of the self::* class constants.
     * @param mixed $expected_value Optional. Compared strictly (===) against the constant value.
     *
     * @return bool
     */
    public static function is($constant, $expected_value = null)
    {
        $defined_name = self::getDefinedName($constant);

        if ( $defined_name === false ) {
            return false;
        }

        $value = constant($defined_name);

        if ( ! self::typeIsValid($constant, $value) ) {
            return false;
        }

        // Distinguishes "no second argument" from an explicit null/false, which a default cannot do.
        if ( func_num_args() < 2 ) {
            return true;
        }

        return $value === $expected_value;
    }

    /**
     * Value of the constant, or $default when it is not defined or fails the type check.
     *
     * @param string $constant One of the self::* class constants.
     * @param mixed $default
     *
     * @return mixed
     */
    public static function getValue($constant, $default = null)
    {
        $defined_name = self::getDefinedName($constant);

        if ( $defined_name === false ) {
            return $default;
        }

        $value = constant($defined_name);

        return self::typeIsValid($constant, $value) ? $value : $default;
    }

    /**
     * All public names the constant may be defined under: the canonical one first, then legacy ones.
     * Intended for diagnostics - reporting to the user which names are recognized.
     *
     * @param string $constant One of the self::* class constants.
     *
     * @return string[]
     */
    public static function getNames($constant)
    {
        $registry = self::getRegistry();

        return isset($registry[$constant]) ? $registry[$constant]['names'] : array();
    }

    /**
     * All known service constants and their current state.
     *
     * @return array[]
     */
    public static function getDefinitions()
    {
        $result = array();

        foreach ( self::getRegistry() as $constant => $entry ) {
            $defined_name = self::getDefinedName($constant);
            $result[]     = array(
                'is_defined'  => $defined_name,
                'value'       => $defined_name === false ? null : constant($defined_name),
                'description' => $entry['description'],
            );
        }

        return $result;
    }

    /**
     * Only the constants that are currently defined.
     *
     * @return array[]
     */
    public static function getDefinitionsActive()
    {
        $active = array();

        foreach ( self::getDefinitions() as $definition ) {
            if ( ! empty($definition['is_defined']) ) {
                $active[] = $definition;
            }
        }

        return $active;
    }

    /**
     * Name of the first defined constant among the canonical one and its legacy aliases.
     *
     * @param string $constant One of the self::* class constants.
     *
     * @return string|false
     */
    private static function getDefinedName($constant)
    {
        $registry = self::getRegistry();

        if ( ! isset($registry[$constant]) ) {
            return false;
        }

        foreach ( $registry[$constant]['names'] as $name ) {
            if ( defined($name) ) {
                return $name;
            }
        }

        return false;
    }

    /**
     * @param string $constant One of the self::* class constants.
     * @param mixed $value
     *
     * @return bool
     */
    private static function typeIsValid($constant, $value)
    {
        $registry = self::getRegistry();

        if ( ! isset($registry[$constant]) ) {
            return false;
        }

        $type = $registry[$constant]['type'];
        $type = isset(self::$type_aliases[$type]) ? self::$type_aliases[$type] : $type;

        return gettype($value) === $type;
    }

    /**
     * Canonical name => allowed public names (canonical first, then legacy), declared type, description.
     *
     * @return array<string, array{names: string[], type: string, description: string}>
     */
    private static function getRegistry()
    {
        if ( self::$registry === null ) {
            self::$registry = array(
                self::APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION     => array(
                    'names'       => array(self::APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION),
                    'type'        => 'bool',
                    'description' => 'If set, do not skip POST data from check if no email address found',
                ),
                self::APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED      => array(
                    'names'       => array(
                        self::APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED,
                        'APBCT_ANTICRAWLER_EXLC_FEED',
                    ),
                    'type'        => 'bool',
                    'description' => 'Pass anti-crawler check on RSS feed service',
                ),
                self::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE               => array(
                    'names'       => array(
                        self::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE,
                        'APBCT_SET_AJAX_ROUTE_TYPE',
                    ),
                    'type'        => 'string',
                    'description' => 'Provides AJAX route type',
                ),
                self::APBCT_SERVICE__SELF_OWNED_ACCESS_KEY             => array(
                    'names'       => array(
                        self::APBCT_SERVICE__SELF_OWNED_ACCESS_KEY,
                        'CLEANTALK_ACCESS_KEY',
                    ),
                    'type'        => 'string',
                    'description' => 'Provides user own access key.',
                ),
                self::APBCT_SERVICE__BOT_DETECTOR_ENABLED              => array(
                    'names'       => array(self::APBCT_SERVICE__BOT_DETECTOR_ENABLED),
                    'type'        => 'bool',
                    'description' => 'Allows to set Bot-Detector enabled/disabled',
                ),
                self::APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER => array(
                    'names'       => array(
                        self::APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER,
                        'CLEANTALK_PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER',
                    ),
                    'type'        => 'bool',
                    'description' => 'If isset, any public scripts will be placed in a page footer.',
                ),
                self::APBCT_SERVICE__WHITELABEL_FAQ_LINK               => array(
                    'names'       => array(
                        self::APBCT_SERVICE__WHITELABEL_FAQ_LINK,
                        'APBCT_WHITELABEL_FAQ_LINK',
                    ),
                    'type'        => 'string',
                    'description' => 'Provides whitelabel-mode FAQ link',
                ),
                self::APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION     => array(
                    'names'       => array(
                        self::APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION,
                        'APBCT_WHITELABEL_PLUGIN_DESCRIPTION',
                    ),
                    'type'        => 'string',
                    'description' => 'Provides whitelabel-mode plugin description.',
                ),
                self::APBCT_SERVICE__WHITELABEL_ENABLED                => array(
                    'names'       => array(
                        self::APBCT_SERVICE__WHITELABEL_ENABLED,
                        'APBCT_WHITELABEL',
                    ),
                    'type'        => 'bool',
                    'description' => 'If defined, plugin will be in whitelabel mode.',
                ),
                self::APBCT_SERVICE__WHITELABEL_PRODUCT_NAME           => array(
                    'names'       => array(
                        self::APBCT_SERVICE__WHITELABEL_PRODUCT_NAME,
                        'APBCT_WHITELABEL_NAME',
                    ),
                    'type'        => 'string',
                    'description' => 'Provides product name for whitelabel mode.',
                ),
                self::APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE           => array(
                    'names'       => array(
                        self::APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE,
                        'APBCT_SFW_FORCE_DIRECT_UPDATE',
                    ),
                    'type'        => 'bool',
                    'description' => 'If defined, SFW update mode is always DIRECT. Helpful if update queue fails due remote calls.',
                ),
                self::APBCT_SERVICE__DISABLE_BLOCKING_TITLE            => array(
                    'names'       => array(
                        self::APBCT_SERVICE__DISABLE_BLOCKING_TITLE,
                        'CLEANTALK_DISABLE_BLOCKING_TITLE',
                    ),
                    'type'        => 'bool',
                    'description' => 'If defined, no title will be provided for blocking page.',
                ),
                self::APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER  => array(
                    'names'       => array(
                        self::APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER,
                        'CLEANTALK_CHECK_COMMENTS_NUMBER',
                    ),
                    'type'        => 'int',
                    'description' => 'Redefine how many comments should be approved before skip checking.',
                ),
                self::APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS => array(
                    'names'       => array(
                        self::APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS,
                        'APBCT_DO_NOT_COLLECT_FRONTEND_DATA_LOGS',
                    ),
                    'type'        => 'bool',
                    'description' => 'If defined, no frontend-data logs will be collected. Debugging case usage.',
                ),
                self::APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL   => array(
                    'names'       => array(
                        self::APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL,
                        'CLEANTALK_SERVER',
                    ),
                    'type'        => 'string',
                    'description' => 'Provides own URL of API server.',
                ),
                self::APBCT_SERVICE__DISABLE_INTEGRATION_METRICS   => array(
                    'names'       => array(
                        self::APBCT_SERVICE__DISABLE_INTEGRATION_METRICS,
                    ),
                    'type'        => 'bool',
                    'description' => 'If defined, integration metrics will be disabled.',
                ),
            );
        }

        return self::$registry;
    }
}
