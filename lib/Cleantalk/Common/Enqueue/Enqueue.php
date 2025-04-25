<?php

namespace Cleantalk\Common\Enqueue;

use Cleantalk\Templates\Singleton;

class Enqueue
{
    use Singleton;

    /**
     * @var string
     */
    protected $plugin_version = '';
    /**
     * @var string
     */
    protected $assets_path = '';
    /**
     * @var string
     */
    protected $plugin_path = '';
    /**
     * @var string
     */
    protected $css_subdir_path = '/css/';
    /**
     * @var string
     */
    protected $js_subdir_path = '/js/';
    /**
     * @var string
     */
    private $type = '';
    /**
     * @var array
     */
    private $errors = array();
    /**
     * @var array
     */
    private $handles_to_register = array();

    /**
     * Initializes the Enqueue class.
     *
     * @throws \Exception If the plugin version, assets path, or plugin path is not set.
     */
    public function init()
    {
        if (empty($this->plugin_version)) {
            throw new \Exception(__('Plugin version is not set.', 'cleantalk-spam-protect'));
        }
        if (empty($this->assets_path)) {
            throw new \Exception(__('Assets path is not set.', 'cleantalk-spam-protect'));
        }
        if (empty($this->plugin_path)) {
            throw new \Exception(__('Plugin path is not set.', 'cleantalk-spam-protect'));
        }
        $this->errors = array();
        add_action('admin_footer', array($this, 'isAllHandlesQueued'), PHP_INT_MAX);
    }

    /**
     * Run WP enqueue style/script in accordance with $this->type.
     *
     * @param EnqueueDataDTO|null $data
     * @psalm-suppress PossiblyInvalidArgument
     */
    private function load($data)
    {
        // Enqueue script logic
        try {
            if (is_null($data)) {
                throw new \Exception(__('Data DTO is invalid', 'cleantalk-spam-protect'));
            }
            $this->handles_to_register = array_merge($this->handles_to_register, array($data->handle));
            // any must have a type
            if ($this->type === 'css') {
                wp_enqueue_style($data->handle, $data->web_path, $data->deps, $data->version, $data->media);
            } elseif ($this->type === 'js') {
                wp_enqueue_script($data->handle, $data->web_path, $data->deps, $data->version, $data->args);
            } else {
                throw new \Exception(__('Unknown type of asset.', 'cleantalk-spam-protect'));
            }
        } catch (\Exception $e) {
            $this->errorLog($e->getMessage());
        }
        // output errors to error_log
        if ($this->hasErrors()) {
            error_log(__CLASS__ . __(' errors:', 'cleantalk-spam-protect'));
            error_log(implode("\n", $this->errors));
        }
    }

    /**
     * Enqueues a CSS file from plugin assets path.
     * Specify only unminified original script name, other data is collected automatically.
     *
     * @param string $asset_file_name The name of the CSS file to enqueue.
     * @param array $deps An array of dependencies.
     * @param string $media The media for which this stylesheet has been defined.
     * @return string $handle The name of the script handle.
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function css($asset_file_name, $deps = array(), $media = 'all')
    {
        $this->type = 'css';
        $data = $this->prepareData($asset_file_name, $deps, null, $media);
        $this->load($data);
        return is_null($data) ? '' : $data->handle;
    }

    /**
     * Enqueues a JavaScript file plugin from assets path.
     * Specify only unminified original script name, other data is collected automatically.
     *
     * @param string $asset_file_name The name of the JavaScript file to enqueue.
     * @param array $deps An array of dependencies.
     * @param bool|array $args Whether to enqueue the script in the footer or other instructions via array.
     *
     * @return string $handle The name of the script handle.
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function js($asset_file_name, $deps = array(), $args = false)
    {
        $this->type = 'js';
        $data = $this->prepareData($asset_file_name, $deps, $args, null);
        $this->load($data);
        return is_null($data) ? '' : $data->handle;
    }

    /**
     * Run custom script loading.
     *
     * @param $handle - handling name
     * @param $asset_file_name - path to the file
     * @param $deps - dependencies
     * @param $version - version
     * @param $args - if isset - JS will be handled, if null, then $media must be not null
     * @param $media - if isset - CSS will be handled, if null, then $args must be not null
     *
     * @return string $handle The name of the script handle.
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function custom($handle, $asset_file_name, $deps, $version, $args, $media)
    {
        try {
            if ($args === null && $media === null) {
                throw new \Exception(__('Both $args and $media cannot be null.', 'cleantalk-spam-protect'));
            }
            if ($args !== null && $media !== null) {
                throw new \Exception(__('Both $args and $media cannot be not null.', 'cleantalk-spam-protect'));
            }
            if ($args === null && $media !== null) {
                $this->type = 'css';
            } else {
                $this->type = 'js';
            }
            $data = new EnqueueDataDTO(
                array(
                    'web_path' => $this->validateWebPath($asset_file_name),
                    'handle' => $handle,
                    'version' => $version,
                    'deps' => $deps,
                    'args' => $args,
                    'media' => $media,
                )
            );
        } catch (\Exception $e) {
            $this->errorLog($e->getMessage());
            $data = null;
        }
        $this->load($data);
        return is_null($data) ? '' : $data->handle;
    }

    /**
     * Prepares data for enqueuing a file.
     *
     * @param string $asset_file_name The name of the asset file.
     * @param array $deps An array of dependencies.
     * @param bool|array|null $args Whether to enqueue the script in the footer or other instructions via array.
     * @param string|null $media The media for which this stylesheet has been defined.
     * @return EnqueueDataDTO|null The prepared data.
     */
    private function prepareData($asset_file_name, $deps, $args, $media)
    {
        $work_script_name = $this->getMinifiedScriptName($asset_file_name);
        if (!@file_exists($this->getPath($work_script_name, false))) {
            $work_script_name = $asset_file_name;
            if (!@file_exists($this->getPath($work_script_name, false))) {
                $work_script_name = 'src' . DIRECTORY_SEPARATOR . $asset_file_name;
            }
        }
        $fresh_version = $this->getFreshVersion($work_script_name);
        $handle = $this->getUniqueScriptHandler($asset_file_name);
        $web_path = $this->validateWebPath($this->getPath($work_script_name, true));
        try {
            return new EnqueueDataDTO(
                array(
                    'web_path' => $web_path,
                    'handle' => $handle,
                    'version' => $fresh_version,
                    'deps' => isset($deps) ? $deps : array(),
                    'args' => isset($args) ? $args : false,
                    'media' => isset($media) ? $media : '',
                )
            );
        } catch (\Exception $e) {
            $this->errorLog($e->getMessage());
        }
        return null;
    }

    /**
     * Gets the minified script name.
     *
     * @param string $file_name The original file name.
     * @return string The minified file name.
     */
    private function getMinifiedScriptName($file_name)
    {
        if ($this->type === 'js') {
            $replace = '/\.js$/';
            $replace_to = '.min.js';
        } elseif ($this->type === 'css') {
            $replace = '/\.css$/';
            $replace_to = '.min.css';
        } else {
            return $file_name;
        }

        return preg_replace($replace, $replace_to, $file_name);
    }

    /**
     * Gets the path to the file.
     *
     * @param string $file_name The file name.
     * @param bool $is_web_path Whether to get the web path or the file system path.
     * @return string The path to the file.
     */
    private function getPath($file_name, $is_web_path)
    {
        $root_path = $is_web_path ? $this->assets_path : $this->plugin_path;
        $sub_dir = $this->type === 'css' ? $this->css_subdir_path : $this->js_subdir_path;
        $sub_dir = $is_web_path
            ? $sub_dir
            : trim($sub_dir, '/') . DIRECTORY_SEPARATOR;
        return $root_path . $sub_dir . $file_name;
    }

    /**
     * Gets a unique script handler.
     *
     * @param string $file_name The file name.
     * @return string The unique script handler.
     */
    private function getUniqueScriptHandler($file_name)
    {
        return pathinfo($file_name, PATHINFO_FILENAME) . '-' . $this->type;
    }

    /**
     * Gets the fresh version of the script.
     *
     * @param string $work_script_name The name of the script.
     * @return string The fresh version of the script.
     */
    private function getFreshVersion($work_script_name)
    {
        $abs_path = $this->getPath($work_script_name, false);
        if (@file_exists($abs_path) && @filemtime($abs_path)) {
            return $this->plugin_version . '_' . @filemtime($abs_path);
        }

        return $this->plugin_version;
    }

    /**
     * @param string $path
     * @return string
     */
    private function validateWebPath($path)
    {
        if (! preg_match('/^https?:\/\//', $path)) {
            $this->errorLog(__('Web path for script is invalid: ' . $path, 'cleantalk-spam-protect'));
            return $path;
        }
        if (!ini_get('allow_url_fopen')) {
            return $path;
        }
        $abs_path = str_replace($this->assets_path, $this->plugin_path, $path);
        if (!@file_exists($abs_path) && !@file_get_contents($abs_path)) {
            $this->errorLog(__('Script file is not accessible:' . $path, 'cleantalk-spam-protect'));
            return $path;
        }
        return $path;
    }

    /**
     * Logs an error message.
     * @param $message
     * @return void
     */
    private function errorLog($message)
    {
        $this->errors[] = $message;
    }

    /** Self-check. Check if all handles are queued on admin-footer.
     * @return bool
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function isAllHandlesQueued()
    {
        foreach ($this->handles_to_register as $handle) {
            if (!wp_script_is($handle, 'queue') && !wp_style_is($handle, 'queue')) {
                $this->errorLog(__('Script is not queued: ' . $handle, 'cleantalk-spam-protect'));
                return false;
            }
        }
        return true;
    }

    /**
     * Does enqueue process has errors occurred.
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
}
