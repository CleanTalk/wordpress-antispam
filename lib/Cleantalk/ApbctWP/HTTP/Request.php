<?php

namespace Cleantalk\ApbctWP\HTTP;

use Cleantalk\Common\HTTP\Response;
use Cleantalk\Common\TT;

class Request extends \Cleantalk\Common\HTTP\Request
{
    public function request()
    {
        global $apbct;

        // Make a request via builtin WordPress HTTP API
        if ( $apbct->settings['wp__use_builtin_http_api'] ) {
            $this->appendOptionsObligatory();
            $this->processPresets();

            // Call cURL multi request if many URLs passed
            $this->response = is_array($this->url)
                ? $this->requestMulti()
                : $this->requestSingle();

            return $this->runCallbacks();
        }

        return parent::request();
    }

    /**
     * @inheritDoc
     *
     * @psalm-suppress UndefinedDocblockClass
     * @psalm-suppress UndefinedClass
     */
    protected function requestSingle()
    {
        global $apbct;

        if ( ! $apbct->settings['wp__use_builtin_http_api'] ) {
            return parent::requestSingle();
        }

        $type = is_array($this->presets) && in_array('get', $this->presets, true) ? 'GET' : 'POST';

        // WP 6.2 support: Requests/Response classes has been replaced into the another namespace in the core
        if ( class_exists('\WpOrg\Requests\Requests') ) {
            /** @var \WpOrg\Requests\Requests $requests_class */
            $requests_class = '\WpOrg\Requests\Requests';
            /** @var \WpOrg\Requests\Response $response_class */
            $response_class = '\WpOrg\Requests\Response';
        } else {
            /** @var \Requests $requests_class */
            $requests_class = '\Requests';
            /** @var \Requests_Response $response_class */
            $response_class = '\Requests_Response';
        }

        $headers_for_api = $this->prepareHeadersForWpHTTPAPI(
            TT::getArrayValueAsArray($this->options, CURLOPT_HTTPHEADER)
        );

        if ( !is_string($this->url) ) {
            return new Response(['error' => 'Cannot run several URLs on requestSingle()!'], []);
        }

        try {
            $response = $requests_class::request(
                $this->url,
                $headers_for_api,
                $this->data,
                $type,
                $this->options
            );
        } catch ( \Exception $e ) {
            return new Response(['error' => $e->getMessage()], []);
        }

        if ( $response instanceof $response_class ) {
            return new Response($response->body, ['http_code' => $response->status_code]);
        }

        // String passed
        return new Response($response, []);
    }

    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress UndefinedDocblockClass
     * @psalm-suppress UndefinedClass
     */
    protected function requestMulti()
    {
        global $apbct;

        if ( ! $apbct->settings['wp__use_builtin_http_api'] ) {
            return parent::requestMulti();
        }

        $responses = [];
        $requests  = [];
        $options   = [];

        if (is_string($this->url)) {
            $this->url = array($this->url);
        }

        $type = is_array($this->presets) && in_array('get', $this->presets, true) ? 'GET' : 'POST';

        // Prepare options
        foreach ( $this->url as $url ) {
            $requests[] = [
                'url'     => $url,
                'headers' => TT::getArrayValueAsArray($this->options, CURLOPT_HTTPHEADER),
                'data'    => $this->data,
                'type'    => $type,
                'cookies' => [],
            ];
            $options[]  = array();
        }

        // WP 6.2 support: Requests/Response classes has been replaced into the another namespace in the core
        if ( class_exists('\WpOrg\Requests\Requests') ) {
            /** @var \WpOrg\Requests\Requests $requests_class */
            $requests_class = '\WpOrg\Requests\Requests';
            /** @var \WpOrg\Requests\Response $response_class */
            $response_class = '\WpOrg\Requests\Response';
        } else {
            /** @var \Requests $requests_class */
            $requests_class = '\Requests';
            /** @var \Requests_Response $response_class */
            $response_class = '\Requests_Response';
        }

        $responses_raw = $requests_class::request_multiple($requests, $options);

        foreach ( $responses_raw as $response ) {
            if ( $response instanceof \Exception ) {
                $responses[$this->url] = new Response(['error' => $response->getMessage()], []);
                continue;
            }
            if ( $response instanceof $response_class ) {
                $responses[$response->url] = new Response($response->body, ['http_code' => $response->status_code]);
                continue;
            }

            // String passed
            $responses[$response->url] = new Response($response, []);
        }

        return $responses;
    }

    /**
     * Set default options to make a request
     */
    protected function appendOptionsObligatory()
    {
        parent::appendOptionsObligatory();

        global $apbct;

        if ( $apbct->settings['wp__use_builtin_http_api'] ) {
            $this->options['useragent'] = self::AGENT;
        }
    }

    /**
     * Append options considering passed presets
     */
    protected function processPresets()
    {
        parent::processPresets();

        global $apbct;

        if ( $apbct->settings['wp__use_builtin_http_api'] && is_array($this->presets) ) {
            foreach ( $this->presets as $preset ) {
                switch ( $preset ) {
                    // Do not follow redirects
                    case 'dont_follow_redirects':
                        $this->options['follow_redirects'] = false;
                        $this->options['redirects']        = 0;
                        break;

                    // Make a request, don't wait for an answer
                    case 'async':
                        $this->options['timeout']         = 3;
                        $this->options['connect_timeout'] = 3;
                        $this->options['blocking']        = false;
                        break;

                    case 'ssl':
                        $this->options['verifyname'] = true;
                        if ( defined('APBCT_CASERT_PATH') && APBCT_CASERT_PATH ) {
                            $this->options['verify'] = APBCT_CASERT_PATH;
                        }
                        break;

                    // Get headers only
                    case 'get_code':
                        $this->options['header'] = true;
                        $this->options['nobody'] = true;
                        break;

                    case 'no_cache':
                        // Append parameter in a different way for single and multiple requests
                        if ( is_array($this->url) ) {
                            $this->url = array_map(static function ($elem) {
                                return self::appendParametersToURL($elem, ['apbct_no_cache' => mt_rand()]);
                            }, $this->url);
                        } else {
                            $this->url = self::appendParametersToURL(
                                $this->url,
                                ['apbct_no_cache' => mt_rand()]
                            );
                        }
                        break;
                        //for API 3.0 preset: headers already provided in parent call, so they will be used in WP HTTP API call
                }
            }
        }
    }

    /**
     * Prepare headers for WP HTTP API.
     * WP HTTP API requires headers to be in the associative array format because of use Request::flatten
     * @param array $curl_headers Inassociative array of headers
     * @return array Associative array of headers
     */
    private function prepareHeadersForWpHTTPAPI($curl_headers)
    {
        $headers_for_api = array();
        $separator = '%ct_header_separator%';

        try {
            foreach ( $curl_headers as $header) {
                //we can't use regex or str_replace because of just single replacement needs
                if (is_string($header)) {
                    // Check if header has a delimiter ": " - very first
                    $found_delimiter = stripos($header, ': ');
                    if ($found_delimiter !== false) {
                        $sub1 = substr($header, 0, $found_delimiter);
                        $sub2 = substr($header, $found_delimiter + 2);
                        $mod_header = $sub1 . $separator . $sub2;
                    } else {
                        //Check if header has a delimiter ":" without space  - very first
                        $found_delimiter = stripos($header, ':');
                        if ($found_delimiter !== false) {
                            $sub1 = substr($header, 0, $found_delimiter);
                            $sub2 = substr($header, $found_delimiter + 1);
                            $mod_header = $sub1 . $separator . $sub2;
                        }
                    }
                    //if any found - add to the headers
                    if (isset($mod_header)) {
                        $pair = explode($separator, $mod_header);
                        if (isset($pair[1], $pair[0])) {
                            $headers_for_api[$pair[0]] = $pair[1];
                        }
                    }
                }
            }
        } catch (\Exception $_e) {
            return $curl_headers;
        }

        return $headers_for_api;
    }
}
