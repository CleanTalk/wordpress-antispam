<a href="https://cleantalk.org" target="_blank"><img src="https://ps.w.org/cleantalk-spam-protect/assets/icon-256x256.png" alt="CleanTalk" width="150"></a>

Cleantalk HTTP Requests
===================

Standalone PHP HTTP Request library

### Description
This standalone library allowing you to use different HTTP-requests in easy way.
It could be simply merged into your project. Please, see "Usage" to learn more.  

### Features

- POST, GET HTTP-methods.
- Multiple requests.
- Asynchronous requests.
- Callback functions.
- CURL options support.
- Any combination of the above features.

### Usage
Download and include the file into your project by using PSR-0 (autoload) or directly attaching the files with "include" instruction.
Use the following code to use the Request:

    $request = new Cleantalk\Common\HTTP\Request();
    $request_result = $request
        ->setUrl('example.com')
        ->setData(['foo' => 'bar']])
        ->setPresets(['async', 'ssl']))
        ->request();
You can reuse once created object change it URL and callback functions any time:

    $another_request_result = $request
        ->setUrl($new_url)
        ->setCallback(
            function ($response_content, $requested_url){
                $processed_response_content = strreplace(
                    'Hello',
                    'Hey!',
                    $response_content
                ); 
                
                return $processed_response_content;
            })
        ->request();
Or use a multiple async requests. This one will send asynchronous requests to example.com and exapme2.com wth custom user-agent and pass the response direct to the output buffer:

    $miltiple_request = new Cleantalk\Common\HTTP\Request();
    $multiple_request_result = $request
        ->setUrl(['example.com', 'example2.com'])
        ->setData(['foo' => 'bar']])
        ->setPresets(['async']))
        ->setOptions([
            CURLOPT_RETURNTRANSFER => fase,                  // CURL format is supported
            'user-agent'           => 'My custom User-Agent' // And user-friendly
            ])
        ->request();
        
## Presets
The lib is using presets which allow you to configure its behaviour.
Use 'setPresets()' method to set them.

    ->setPresets([
            'async',
            'get',
            'dont_follow_redirects'
        ])

May use the following presets (you can combine them in any way you want):
+ dont_follow_redirects - ignore 300-family response code and don't follow redirects
+ get_code              - getting only HTTP response code
+ async                 - async requests. Sends request and return 'true' value. Doesn't wait for response.
+ get                   - makes GET-type request instead of default POST-type
+ ssl                   - uses SSL
+ cache                 - allow caching for this request
+ retry_with_socket     - make another request with socket if cURL failed to retrieve data

## Options
If you need to precise tune you could use 'setOptions' method:

    ->setOptions([
            CURLOPT_RETURNTRANSFER => fase,                  // CURL format is supported
            'user-agent'           => 'My custom User-Agent' // And user-friendly
        ])
It supports any cURL type options(learn more in [cURL documentation](https://www.php.net/curl_setopt)) and the following human-friendly:
+ timeout         - maximum connection duration(int)
+ sslverify       - verify host (bool)
+ sslcertificates - pass your own SSL certificate (string)
+ headers         - any custom headers (array of strings)
+ user-agent      - custom user-agent (string)

## License
This library is open-sourced software licensed under the [GPLv3 license](http://www.gnu.org/licenses/gpl-3.0.html).