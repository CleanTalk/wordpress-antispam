<?php

namespace Cleantalk\ApbctWP\HTTP;

use Cleantalk\Common\HTTP\Response;
use Requests;

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

    protected function requestSingle()
    {
        global $apbct;

        if ( ! $apbct->settings['wp__use_builtin_http_api'] ) {
            return parent::requestSingle();
        }

        $type = in_array('get', $this->presets, true) ? 'GET' : 'POST';

        try {
            $response = Requests::request(
                $this->url,
                $this->options[CURLOPT_HTTPHEADER],
                $this->data,
                $type,
                $this->options
            );
        } catch ( \Requests_Exception $e ) {
            return new Response(['error' => $e->getMessage()], []);
        }

        if ( $response instanceof \Requests_Response ) {
            return new Response($response->body, ['http_code' => $response->status_code]);
        }

        // String passed
        return new Response($response, []);
    }

    protected function requestMulti()
    {
        global $apbct;

        if ( ! $apbct->settings['wp__use_builtin_http_api'] ) {
            return parent::requestMulti();
        }

        $responses = [];
        $requests  = [];
        $options   = [];

        // Prepare options
        foreach ( $this->url as $url ) {
            $requests[] = [
                'url'     => $url,
                'headers' => $this->options[CURLOPT_HTTPHEADER],
                'data'    => $this->data,
                'type'    => $type = in_array('get', $this->presets, true) ? 'GET' : 'POST',
                'cookies' => [],
            ];
            $options[]  = [

            ];
        }

        $responses_raw = \Requests::request_multiple($requests, $options);

        foreach ( $responses_raw as $response ) {
            if ( $response instanceof \Requests_Exception ) {
                /**
                * @psalm-suppress UndefinedPropertyFetch
                */
                $responses[$response->url] = new Response(['error' => $response->getMessage()], []);
                continue;
            }
            if ( $response instanceof \Requests_Response ) {
                $responses[$response->url] = new Response($response->body, ['http_code' => $response->status_code]);
                continue;
            }

            // String passed
            $responses[$response->url] = new Response($response, []);
        }

        return $responses;
    }

    /**
     * Append options considering passed presets
     */
    protected function processPresets()
    {
        parent::processPresets();

        global $apbct;

        if ( $apbct->settings['wp__use_builtin_http_api'] ) {
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
                }
            }
        }
    }
}
