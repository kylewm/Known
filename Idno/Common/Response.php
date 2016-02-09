<?php

namespace Idno\Common {

    class Response {

        public $status  = 200;
        public $headers = array();
        public $content = null;
        public $stream  = null;

        private static function startsWith($str, $prefix)
        {
            return strtolower(substr($str, 0, strlen($prefix))) === strtolower($prefix);
        }

        /**
         * Add a header
         */
        function header($string, $replace=true)
        {
            if ($replace && $idx = strpos($string, ':')) {

                $key = substr($string, 0, $idx+1);
                for ($i = count($this->headers)-1 ; $i >= 0 ; $i--) {
                    if (self::startsWith($this->headers[$i], $key)) {
                        array_splice($this->headers, $i, 1);
                    }
                }
            }

            $this->headers[] = $string;
        }

        function hasHeader($key)
        {
            foreach ($this->headers as $header) {
                if (self::startsWith($header, $key . ':')) {
                    return true;
                }
            }
        }

        /**
         * Render this response to stdout
         */
        function send()
        {
            foreach ($this->headers as $header) {
                header($header, false);
            }
            if ($this->status !== 200) {
                http_response_code($this->status);
            }
            if ($this->stream) {
                error_log("streaming bytes from " . $this->stream);
                if ($hnd = fopen($this->stream, 'r')) {
                    fpassthru($hnd);
                    fclose($hnd);
                }
            }
            else {
                echo $this->content;
            }
        }

    }

}