<?php

/**
 * Router based heavily on ToroPHP (https://github.com/anandkunal/ToroPHP)
 */

namespace Idno\Core {

    use Idno\Common\Response;

    class Router {

        function __construct($routes)
        {
            $this->routes = $routes;
        }

        /**
         * @return \Idno\Common\Response
         */
        function serve()
        {
            $request_method = strtolower($_SERVER['REQUEST_METHOD']);
            $path_info = '/';
            if (!empty($_SERVER['PATH_INFO'])) {
                $path_info = $_SERVER['PATH_INFO'];
            }
            else if (!empty($_SERVER['ORIG_PATH_INFO']) && $_SERVER['ORIG_PATH_INFO'] !== '/index.php') {
                $path_info = $_SERVER['ORIG_PATH_INFO'];
            }
            //else {
            if (!empty($_SERVER['REQUEST_URI'])) {
                $path_info = (strpos($_SERVER['REQUEST_URI'], '?') > 0) ? strstr($_SERVER['REQUEST_URI'], '?', true) : $_SERVER['REQUEST_URI'];
            }
            //}

            $is_xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';


            $discovered_handler = null;
            $regex_matches = array();

            if (isset($this->routes[$path_info])) {
                $discovered_handler = $this->routes[$path_info];
            }
            else if ($this->routes) {
                $tokens = array(
                    ':string' => '([a-zA-Z]+)',
                    ':number' => '([0-9]+)',
                    ':alpha'  => '([a-zA-Z0-9-_]+)'
                );
                error_log("using $path_info");
                foreach ($this->routes as $pattern => $handler_name) {
                    $pattern = strtr($pattern, $tokens);
                    if (preg_match('#^/?' . $pattern . '/?$#', $path_info, $matches)) {
                        error_log("found match $handler_name");
                        $discovered_handler = $handler_name;
                        $regex_matches = $matches;
                        break;
                    }
                }
            }

            $result = null;

            $handler_instance = null;
            if ($discovered_handler) {
                if (is_string($discovered_handler)) {
                    $handler_instance = new $discovered_handler();
                }
                elseif (is_callable($discovered_handler)) {
                    $handler_instance = $discovered_handler();
                }
            }

            if ($handler_instance) {
                unset($regex_matches[0]);

                $response = $handler_instance->response;
                if ($is_xhr && method_exists($handler_instance, $request_method . '_xhr')) {
                    $response->header('Content-type: application/json');
                    $response->header('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
                    $response->header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                    $response->header('Cache-Control: no-store, no-cache, must-revalidate');
                    $response->header('Cache-Control: post-check=0, pre-check=0', false);
                    $response->header('Pragma: no-cache');
                    $request_method .= '_xhr';
                }

                if (method_exists($handler_instance, $request_method)) {
                    return $this->serveRoute($handler_instance, $request_method, $regex_matches);
                }
                else {
                    $response = new Response();
                    $response->status = 405;
                    $t = \Idno\Core\Idno::site()->template();
                    $t->autodetectTemplateType();
                    $response->content = $t->__(array(
                        'body' => $t->draw('pages/405'),
                        'title' => 'Method not allowed!')
                    )->drawPage(false);
                    return $response;
                }
            }
            else {
                $response = new Response();
                $response->status = 404;
                $t = \Idno\Core\Idno::site()->template();
                $t->autodetectTemplateType();
                $response->content = $t->__(array(
                    'body' => $t->draw('pages/404'),
                    'title' => 'Not found!')
                )->drawPage(false);
                return $response;
            }
        }

        private function serveRoute($handler_instance, $request_method, $regex_matches)
        {
            ob_start();
            try {
                call_user_func_array(array($handler_instance, $request_method), $regex_matches);
            } catch (ExitException $e) {
                // exit without killing the process
            }
            $response = $handler_instance->response;

            // capture content from stdout if it is not set in the response explicitly
            if (!$response->stream && !$response->content) {
                $response->content = ob_get_clean();
            } else {
                ob_end_flush();
            }

            return $response;
        }

    }

}