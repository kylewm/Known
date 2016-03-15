<?php

namespace Idno\Core;

use Idno\Common\Component;

abstract class HttpClient extends Component {

    /**
     * Send a web services request to a specified endpoint
     * @param string $verb The verb to send the request with; one of POST, GET, DELETE, PUT
     * @param string $endpoint The URI to send the request to
     * @param mixed $params Optionally, an array of parameters to send (keys are the parameter names), or the raw body text (depending on Content-Type)
     * @param array $headers Optionally, an array of headers to send with the request (keys are the header names)
     * @return array
     */
    abstract function send($verb, $endpoint, $params = null, array $headers = null);

    /**
     * Retrieves the last HTTP request sent by the service client
     * @return string
     */
    abstract function getLastRequest();

    /**
     * Retrieves the last HTTP response sent to the service client
     * @return string
     */
    abstract function getLastResponse();

    /**
     * Send a web services POST request to a specified URI endpoint
     * @param string $endpoint The URI to send the POST request to
     * @param mixed $params Optionally, an array of parameters to send (keys are the parameter names), or the raw body text (depending on Content-Type)
     * @param array $headers Optionally, an array of headers to send with the request (keys are the header names)
     * @return array
     */
    function post($endpoint, $params = null, array $headers = null)
    {
        return $this->send('post', $endpoint, $params, $headers);
    }

    /**
     * Send a web services HEAD request to a specified URI endpoint
     * @param string $endpoint The URI to send the HEAD request to
     * @param array $params Optionally, an array of parameters to send (keys are the parameter names)
     * @param array $headers Optionally, an array of headers to send with the request (keys are the header names)
     * @return array
     */
    function head($endpoint, array $params = null, array $headers = null)
    {
        return $this->send('head', $endpoint, $params, $headers);
    }

    /**
     * Send a web services PUT request to a specified URI endpoint
     * @param string $endpoint The URI to send the PUT request to
     * @param mixed $params Optionally, an array of parameters to send (keys are the parameter names), or the raw body text (depending on Content-Type)
     * @param array $headers Optionally, an array of headers to send with the request (keys are the header names)
     * @return array
     */
    function put($endpoint, $params = null, array $headers = null)
    {
        return $this->send('put', $endpoint, $params, $headers);
    }

    /**
     * Send a web services DELETE request to a specified URI endpoint
     * @param string $endpoint The URI to send the DELETE request to
     * @param array $params Optionally, an array of parameters to send (keys are the parameter names)
     * @param array $headers Optionally, an array of headers to send with the request (keys are the header names)
     * @return array
     */
    function delete($endpoint, array $params = null, array $headers = null)
    {
        return $this->send('delete', $endpoint, $params, $headers);
    }

    /**
     * Replacement for file_get_contents for retrieving remote files.
     * Essentially a wrapper for $this->get()
     * @param type $url
     */
    function file_get_contents($url)
    {
        $result = $this->get($url);

        // Checking for redirects (HTTP codes 301 and 302)
        $redirect_count = 0;
        while (($result['response'] == 302) || ($result['response'] == 301)) {
            $redirect_count += 1;
            if ($redirect_count >= 3) {
                // We have followed 3 redirections already…
                // This may be a redirect loop so we'd better drop it already.
                return false;
            }
            // The redirection URL is the "location" field of the header
            $headers = http_parse_headers($result['header']);
            $headers = array_change_key_case($headers, CASE_LOWER); // Ensure standardised header array keys
            $result  = $this->get($headers["location"]);
        }

        if ($result['error'] == "")
            return $result['content'];

        return false;
    }

    /**
     * Send a web services GET request to a specified URI endpoint
     * @param string $endpoint The URI to send the GET request to
     * @param array $params Optionally, an array of parameters to send (keys are the parameter names)
     * @param array $headers Optionally, an array of headers to send with the request (keys are the header names)
     * @return array
     */
    function get($endpoint, array $params = null, array $headers = null)
    {
        return $this->send('get', $endpoint, $params, $headers);
    }

}