<?php

namespace ApbctWP;

use PHPUnit\Framework\TestCase;

class AjaxNonceCheckExistTest extends TestCase
{
    /**
     * List of nonce check functions.
     */
    private $nonce_check_functions = array(
        'AJAXService::checkPublicNonce',
        'AJAXService::checkAdminNonce',
        'AJAXService::checkNonceRestrictingNonAdmins',
    );
    /**
     * @var string[] List of AJAX handlers that should be excluded from nonce check. Usually this is spam check handlers.
     */
    private $handlers_exclusions = array(
        'apbct_form__',
        'ct_validate_email_ajaxlogin',
        'ct_ajax_hook',
        'checkSpam'
    );
    /**
     * @var string[] List of all CleanTalk AJAX handlers names.
     */
    private $cleantalk_ajax_handlers_names;
    /**
     * @var string Path to the plugin directory.
     */
    private $files_dir;
    /**
     * @var string[] List of files with <code>add_action('wp_ajax%)</code> calls.
     */
    private $files_with_add_action_func;

    public function setUp(): void
    {
        $this->files_dir                      = $this->getFilesDir();
        $this->files_with_add_action_func     = $this->getFilesWithPreg($this->files_dir, 'add_action(\'wp_ajax');
        $this->cleantalk_ajax_handlers_names  = $this->getFunctionNames($this->files_with_add_action_func);
    }

    public function testCTHasAjaxCalls()
    {
        $this->assertNotEmpty($this->files_with_add_action_func, 'No files with add_task() calls found! Dir: ' . $this->files_dir);
        $this->assertNotEmpty($this->cleantalk_ajax_handlers_names, 'No AJAX calls parsed!');
    }

    /**
     * Main test function.
     * @return void
     */
    public function testAJAXCallbackHasNonceCheck()
    {
        // obtain all CleanTalk AJAX handlers
        $handlers = [];
        $excluded_count = 0;
        foreach ($this->cleantalk_ajax_handlers_names as $function_name) {
            // process exclusion list
            foreach ($this->handlers_exclusions as $exclusion) {
                if (strpos($function_name, $exclusion) !== false) {
                    $excluded_count++;
                    continue(2);
                }
            }
            // global search for files with AJAX handlers
            $file_with_ajax_callers = $this->getFilesWithPreg($this->files_dir, 'function ' . $function_name);
            $this->assertNotEmpty($file_with_ajax_callers, 'No files with AJAX handler ' . $function_name . ' found!');
            $handlers[$function_name] =  $file_with_ajax_callers;
        }
        $this->assertEquals(count($handlers) + $excluded_count, count($this->cleantalk_ajax_handlers_names), 'Some AJAX handlers were not found in files!');
        // for each AJAX handler check nonce check
        foreach ($handlers as $function_name => $files) {
            foreach ($files as $file) {
                $handler_found = false;
                // get content of the file
                $content = file_get_contents($file);
                preg_match("/function $function_name\(([\s\S]+?)[\r\n]}/", $content, $matches);
                $function_content = $matches[1];
                // check if nonce check function is present
                foreach ($this->nonce_check_functions as $nonce_check_function) {
                    if (strpos($function_content, $nonce_check_function) !== false) {
                        $handler_found = true;
                        break;
                    }
                }
                // asserting
                $this->assertTrue(
                    $handler_found,
                    'No nonce check found in function ' . $function_name
                );
            }
        }
    }

    /**
     * Service function. Get all function names from files with AJAX calls.
     * @param $files_with_call
     *
     * @return array
     */
    private function getFunctionNames($files_with_call)
    {
        $with_action = [];
        foreach ($files_with_call as $file) {
            $content = @file_get_contents($file);
            if ($content) {
                // process callbacks provided as a single string
                preg_match_all("/add_action\('wp_ajax_\S+?',[\s\S]{0,1}'{0,1}(\w+?)[,'].*\);/", $content, $standalone_matches);
                if (isset($standalone_matches[1])) {
                    foreach ($standalone_matches[1] as $match) {
                        $match = trim($match, " '");
                        $with_action[] = $match;
                    }
                }
                // process callbacks provided as an array
                preg_match_all("/.*add_action\('wp_ajax_.+?'{0,1},[\s\S]{0,1}(\[.+?'|array\('{0,1}.+?')(\w+)'/", $content, $array_callback_matches);
                if (isset($array_callback_matches[2])) {
                    foreach ($array_callback_matches[2] as $match) {
                        $with_action[] = $match;
                    }
                }
            }
        }
        return array_unique($with_action);
    }

    /**
     * Global search for files with specific content.
     * @param $dir
     * @param $preg
     * @param $recursive
     * @param $include_folders
     *
     * @return array
     */
    private function getFilesWithPreg($dir, $preg, $recursive = true, $include_folders = false )
    {
        if (!is_dir($dir) ||
            (is_dir($dir) && preg_match('/tests|node_modules|vendor|css|js|github/', $dir))
        ) {
            return array();
        }

        $files = array();
        $dir = rtrim($dir, '/\\');

        foreach (glob("$dir/{,.}[!.,!..]*", GLOB_BRACE) as $file) {
            if ( is_dir($file) ) {
                if ( $include_folders ) {
                    $files[] = $file;
                }
                if ( $recursive ) {
                    $files = array_merge($files, call_user_func(__METHOD__, $file, $preg, $recursive, $include_folders));
                }
            } elseif (strpos($file, '.php') !== false && filesize($file) > 0) {
                $content = file_get_contents($file);
                if ($content) {
                    if (strpos($content, $preg) !== false) {
                        $files[] = $file;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Get files directory.
     * @return string
     */
    private function getFilesDir()
    {
        if ( !function_exists('php_uname') ) {
            if ( defined('PHP_OS') ) {
                $is_windows = strpos(strtolower(PHP_OS), 'win') !== false;
            } else {
                $is_windows = false;
            }
        } else {
            $is_windows = strpos(strtolower(php_uname('s')), 'windows') !== false ? true : false;
        }

        $sep = $is_windows ? '\\' : '/';

        return str_replace($sep . 'tests' . $sep . 'ApbctWP', '', __DIR__) . $sep;
    }
}
