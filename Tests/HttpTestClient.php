<?php

namespace Tests {

    use Idno\Core\Idno;
    use Idno\Core\PageHandler;

    class HttpTestClient {

        static function post($endpoint, $params=false, $headers=false)
        {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI']    = parse_url($endpoint, PHP_URL_PATH);

            if (is_array($headers)) {
                foreach ($headers as $k => $v) {
                    $_SERVER['HTTP_' . strtoupper($k)] = $v;
                }
            }

            if (is_array($params)) {
                foreach ($params as $k => $v) {
                    $_REQUEST[$k] = $_POST[$k] = $v;
                }
            }

            return self::exec();
        }

        static function get($endpoint, $params=false, $headers=false)
        {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI']    = parse_url($endpoint, PHP_URL_PATH);

            if (is_array($headers)) {
                foreach ($headers as $k => $v) {
                    $_SERVER['HTTP_' . strtoupper($k)] = $v;
                }
            }

            if (is_array($params)) {
                foreach ($params as $k => $v) {
                    $_REQUEST[$k] = $_GET[$k] = $v;
                }
            }

            return self::exec();
        }

        private static function exec()
        {
            ob_start();
            PageHandler::serve(Idno::site()->pagehandlers);
            $content     = ob_get_clean();
            $http_status = Idno::site()->currentPage->response;

            return [
                'headers'  => $headers,
                'content'  => $content,
                'response' => $http_status,
            ];
        }

    }

}