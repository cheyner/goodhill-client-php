<?php
/*
 * Copyright (c) 2014 Goodhill Solutions
 * https://www.goodhill-solutions.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 *
 * VERSION 1.0.0
 *
 */
namespace Goodhill;

/**
 * Entry point in the PHP API.
 * You should instantiate a Client object with your ApiKey, ApiSecret and Hosts
 * to start using Goodhill API
 */
class Client {

    protected $apiKey;
    protected $apiSecret;
    protected $hostsArray;

    /*
     * Goodhill initialization
     * @param apiKey a valid API key for the service
     * @param apiSecret API secret key
     * @param hostsArray the list of hosts that you have received for the service
     */
    function __construct($apiKey, $apiSecret, $hostsArray) {

        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->hostsArray = $hostsArray;

        if(!function_exists('curl_init')){
            throw new \Exception('Goodhill requires the CURL PHP extension.');
        }
        if(!function_exists('json_decode')){
            throw new \Exception('Goodhill requires the JSON PHP extension.');
        }
    }

    /*
     * Release curl handle
     */
    function __destruct() {
    }

    /*
     * Call isAlive
     */
     public function isAlive() {

        return $this->request(
            "GET",
            "/api/isalive"
        );

     }

     public function search($settings) {

        return $this->request(
            "POST",
            "/api/parts",
            array(),
            $settings
        );

     }

     public function search_get($settings) {

        return $this->request(
            "GET",
            "/api/parts",
            $settings
        );

     }

     public function categories() {

        return $this->request(
            "GET",
            "/api/categories"
        );

     }

     public function category($id) {

        return $this->request(
            "GET",
            "/api/category/{$id}"
        );

     }

     public function category_ancestry($id) {

        $ancestry = array();

        $result = $this->category($id);

        if (!empty($result['data']['Category']['id'])) {
          $ancestry[] = $result['data'];
        }

        if (!empty($result['data']['Category']['parent_id'])) {

          $ancestry = array_merge(
            $ancestry,
            $this->ancestry($result['data']['Category']['parent_id'])
          );

        }

        return $ancestry;

     }

     public function attributes($params = array()) {

        return $this->request(
            "GET",
            "/api/attributes",
            $params
        );

     }

     public function manufacturers() {

        return $this->request(
            "GET",
            "/api/manufacturers"
        );

     }

    public function request($method, $path, $params = array(), $data = array()) {
        $exception = null;
        foreach ($this->hostsArray as &$host) {
            try {
                $res = $this->doRequest($method, $host, $path, $params, $data);
                if ($res !== null)
                    return $res;
            } catch (GoodhillException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exception = $e;
            }
        }
        if ($exception == null)
            throw new GoodhillException('Hosts unreachable');
        else
            throw $exception;
    }

    public function doRequest($method, $host, $path, $params, $data) {
        if (strpos($host, "http") === 0) {
            $url = $host . $path;
        } else {
            $url = "https://" . $host . $path;
        }
        if ($params != null && count($params) > 0) {

            $url .= "?" . http_build_query($params);

        }

        // initialize curl library
        $curlHandle = curl_init();
        //curl_setopt($curlHandle, CURLOPT_VERBOSE, true);

        curl_setopt($curlHandle, CURLOPT_USERAGENT, "Goodhill for PHP");
        //Return the output instead of printing it
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FAILONERROR, true);
        curl_setopt($curlHandle, CURLOPT_ENCODING, '');

        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curlHandle, CURLOPT_CAINFO, __DIR__ . '/../../resources/api_goodhill-solutions_com.ca-bundle');

        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 8);
        curl_setopt($curlHandle, CURLOPT_NOSIGNAL, 1); # The problem is that on (Li|U)nix, when libcurl uses the standard name resolver, a SIGALRM is raised during name resolution which libcurl thinks is the timeout alarm.
        curl_setopt($curlHandle, CURLOPT_FAILONERROR, false);

        if ($method === 'GET') {
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($curlHandle, CURLOPT_HTTPGET, true);
            curl_setopt($curlHandle, CURLOPT_POST, false);
        } else if ($method === 'POST') {
            $body = ($data) ? json_encode($data) : '';
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($curlHandle, CURLOPT_POST, false);
        } elseif ($method === 'PUT') {
            $body = ($data) ? json_encode($data) : '';
            curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $body);
            curl_setopt($curlHandle, CURLOPT_POST, true);
        }

        $date = date('D, d M Y H:i:s O');
        $signature = $this->_getSignature(
            $this->apiSecret,
            $method,
            $host,
            $url,
            $date,
            !empty($body) ? $body : ''
        );

        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
            'Content-type: application/json',
            'Date: ' . $date,
            "Authorization: HMAC {$this->apiKey}:{$signature}",
            "X-Auth-SignedHeaders: content-type;date;host"
        ));

        $response = curl_exec($curlHandle);
        $http_status = (int)curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $error = curl_error($curlHandle);

        if (!empty($error)) {
            throw new \Exception($error);
        }
        if ($http_status === 0 || $http_status === 503) {
            // Could not reach host or service unavailable, try with another one if we have it
            curl_close($curlHandle);
            return null;
        }

        curl_close($curlHandle);

        $answer = json_decode($response, true);

        if ($http_status == 400) {
            throw new GoodhillException(isset($answer['message']) ? $answer['message'] : "Bad request");
        }
        elseif ($http_status === 403) {
            throw new GoodhillException(isset($answer['message']) ? $answer['message'] : "Invalid Application-ID or API-Key");
        }
        elseif ($http_status === 404) {
            throw new GoodhillException(isset($answer['message']) ? $answer['message'] : "Resource does not exist");
        }
        elseif ($http_status != 200 && $http_status != 201) {
            throw new \Exception($http_status . ": " . $response);
        }

        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                $errorMsg = 'JSON parsing error: maximum stack depth exceeded';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $errorMsg = 'JSON parsing error: unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $errorMsg = 'JSON parsing error: syntax error, malformed JSON';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $errorMsg = 'JSON parsing error: underflow or the modes mismatch';
                break;
            case (defined('JSON_ERROR_UTF8') ? JSON_ERROR_UTF8 : -1): // PHP 5.3 less than 1.2.2 (Ubuntu 10.04 LTS)
                $errorMsg = 'JSON parsing error: malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            case JSON_ERROR_NONE:
            default:
                $errorMsg = null;
                break;
        }
        if ($errorMsg !== null)
            throw new GoodhillException($errorMsg);

        return $answer;
    }

    /**
     * Calculate hmac hash of request into a signature string
     * @param  string $secret
     * @param  string $method
     * @param  string $url
     * @param  string $date
     * @param  string $body
     * @return string
     */
    protected function _getSignature($secret, $method, $host, $url, $date, $body) {

        $url = str_replace($host, '', $url);

        $url_parts = explode('?', $url);
        $path = $url_parts[0];

        $query = !empty($url_parts[1]) ? $url_parts[1] : '';

        $host = str_replace('http://', '', $host);

        $body = hash('sha256', $body);

        $request = array();
        $request[] = strtoupper($method);
        $request[] = $path;
        $request[] = $query;

        //signed headers
        $request[] = "content-type:application/json";
        $request[] = "date:{$date}";
        $request[] = "host:{$host}";
        $request[] = 'content-type;date;host';
        $request[] = $body;

        $request_string = implode("\n", $request);

        $signature = hash_hmac(
            'sha256',
            $request_string,
            $secret
        );

        return $signature;

    }

}


