/**
 * Router based heavily on ToroPHP (https://github.com/anandkunal/ToroPHP)
 */

namespace Idno\Core {

    class Router {

        function __construct($routes)
        {
            $this->routes = $routes;
        }

        /**
         * @param \Idno\Common\Request $request
         * @return \Idno\Common\Response
         */
        function serve($request)
        {
            $path_info = $request->getPathInfo();
            $discovered_handler = null;
            $regex_matches = array();

            if (isset($routes[$path_info])) {
                $discovered_handler = $routes[$path_info];
            }
            else if ($routes) {
                $tokens = array(
                    ':string' => '([a-zA-Z]+)',
                    ':number' => '([0-9]+)',
                    ':alpha'  => '([a-zA-Z0-9-_]+)'
                );
                foreach ($routes as $pattern => $handler_name) {
                    $pattern = strtr($pattern, $tokens);
                    if (preg_match('#^/?' . $pattern . '/?$#', $path_info, $matches)) {
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

                if ($request->isXHR() && method_exists($handler_instance, $request_method . '_xhr')) {
                    header('Content-type: application/json');
                    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    header('Cache-Control: post-check=0, pre-check=0', false);
                    header('Pragma: no-cache');
                    $request_method .= '_xhr';
                }

                if (method_exists($handler_instance, $request_method)) {
                    return $this->serveRoute($request, $handler_instance, $request_method, $regex_matches);
                }
                else {
                    return new Response('', 405);
                }
            }
            else {
                return new Response('', 404);
            }
        }

        private function serveRoute($request, $handler_instance, $request_method, $regex_matches)
        {
            $handler_instance->request = $request;

            ob_start()
            call_user_func_array(array($handler_instance, $request_method), $regex_matches);
            $content = ob_get_clean();

            $response = $handler_instance->response;
            if ($content && $response->getContent()) {
                $content .= $response->getContent()
            }
            $response->setContent($content);


            $response->prepare($request);
            return $response;
        }

    }

}