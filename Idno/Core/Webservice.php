<?php

    /**
     * Utility methods for handling external web services
     *
     * @package idno
     * @subpackage core
     */

    namespace Idno\Core {

        class Webservice extends \Idno\Common\Component
        {

            static $client;

            /**
             * @deprecated in favor of HttpClient::post
             */
            static function post($endpoint, $params = null, array $headers = null)
            {
                return self::$client->post($endpoint, $params, $headers);
            }

            /**
             * @deprecated in favor of HttpClient::get
             */
            static function get($endpoint, array $params = null, array $headers = null)
            {
                return self::$client->get($endpoint, $params, $headers);
            }

            /**
             * @deprecated in favor of HttpClient::getLastResponse
             */
            static function getLastResponse()
            {
                return self::$client->getLastResponse();
            }

            /**
             * @deprecated in favor of HttpClient::getLastRequest
             */
            static function getLastRequest()
            {
                return self::$client->getLastRequest();
            }

            /**
             * @deprecated in favor of HttpClient::file_get_contents
             */
            static function file_get_contents($url)
            {
                return self::$client->file_get_contents($url);
            }

            /**
             * Take a URL, check for a schema and add one if necessary
             * @param $url
             * @return string|bool
             */
            static function sanitizeURL($url)
            {
                if (!empty($url)) {
                    if (!substr_count($url, ':') && !substr_count($url, '//')) {
                        $url = 'http://' . $url;
                    }

                    return $url;
                }

                return false;
            }

            /**
             * Takes a query array and flattens it for use in a POST request (etc)
             *
             * TODO unused?
             *
             * @param $params
             * @return string
             */
            static function flattenArrayToQuery($params)
            {
                if (is_array($params) && !empty($params)) {
                    return http_build_query($params);
                }

                return $params;
            }

            /**
             * Converts an "@" formatted file string into a CurlFile
             *
             * TODO unused?
             *
             * @param type $fileuploadstring
             * @return CURLFile|false
             */
            static function fileToCurlFile($fileuploadstring)
            {
                if ($fileuploadstring[0] == '@') {
                    $bits = explode(';', $fileuploadstring);

                    $file = $name = $mime = null;

                    foreach ($bits as $bit) {
                        // File
                        if ($bit[0] == '@') {
                            $file = trim($bit, '@ ;');
                        }
                        if (strpos($bit, 'filename')!==false) {
                            $tmp = explode('=', $bit);
                            $name = trim($tmp[1], ' ;');
                        }
                        if (strpos($bit, 'type')!==false) {
                            $tmp = explode('=', $bit);
                            $mime = trim($tmp[1], ' ;');
                        }

                    }

                    if ($file) {

                        if (file_exists($file)) {
                            if (class_exists('CURLFile')) {
                                return new \CURLFile($file, $mime, $name);
                            } else {
                                throw new \Idno\Exceptions\ConfigurationException("Your version of PHP doesn't support CURLFile.");
                            }
                        }

                    }
                }

                return false;
            }

        }

        Webservice::$client = new CurlHttpClient();

    }
