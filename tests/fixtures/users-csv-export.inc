<?php

// Runs the real, terminating exporter with isolated WordPress and request-data stubs.
namespace Cleantalk\ApbctWP\Variables {
    class Post
    {
        public static function get($name)
        {
            if ($name !== 'filename') {
                throw new \RuntimeException('Unexpected POST field');
            }
            return 'test-users';
        }
    }
}

namespace {
    function check_ajax_referer($action, $query_arg)
    {
        if ($action !== 'ct_secret_nonce' || $query_arg !== 'security') {
            throw new RuntimeException('Unexpected nonce check');
        }
    }

    function current_user_can($capability)
    {
        if ($capability !== 'manage_options') {
            throw new RuntimeException('Unexpected permission check');
        }
        return true;
    }

    function get_users($params)
    {
        if ($params['meta_query'][0]['key'] !== 'ct_marked_as_spam') {
            throw new RuntimeException('Unexpected user query');
        }
        return array((object) array(
            'ID' => 42,
            'user_login' => 'csv-user',
            'data' => (object) array(
                'user_email' => 'csv-user@example.org',
                'user_nicename' => 'csv-user',
            ),
        ));
    }

    function get_user_meta($id, $key, $single)
    {
        if ($id !== 42 || !$single || !array_key_exists($key, $GLOBALS['csv_test_meta'])) {
            throw new RuntimeException('Unexpected metadata query');
        }
        return $GLOBALS['csv_test_meta'][$key];
    }

    function esc_html($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    function __($text, $domain)
    {
        if ($domain !== 'cleantalk-spam-protect') {
            throw new RuntimeException('Unexpected translation domain');
        }
        return $text;
    }

    function wp_send_json_error($data, $status_code)
    {
        if ($status_code !== 500) {
            throw new RuntimeException('Expected an HTTP 500 export error');
        }
        header('Content-Type: application/json');
        http_response_code($status_code);
        echo json_encode(array('success' => false, 'data' => $data));
        die();
    }

    $csv_test_meta = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($csv_test_meta)) {
        throw new RuntimeException('Invalid fixture input');
    }
    foreach ($csv_test_meta as $key => $value) {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 fixture input');
        }
        $csv_test_meta[$key] = $decoded;
    }
    define('ABSPATH', __DIR__ . '/');
    require dirname(__DIR__, 2) . '/lib/autoloader.php';
    $apbct = (object) array('login_ip_keeper' => new class {
        public function getIP($id)
        {
            if ($id !== 42) {
                throw new RuntimeException('Unexpected user ID');
            }
            return '192.0.2.1';
        }
    });

    \Cleantalk\ApbctWP\FindSpam\UsersChecker::ctGetCsvFile();
}
