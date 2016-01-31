<?php

    /**
     * Allow logging, with toggle support
     *
     * @package idno
     * @subpackage core
     */

    namespace Idno\Core {

        class Logging extends \Idno\Common\Component implements \Psr\Log\LoggerInterface
        {

            private $monolog;
            private $logHandler;

            /**
             * Create a basic logger to log to the PHP log.
             *
             * @param type $loglevel_filter Log levels to show 0 - off, 1 - errors, 2 - errors & warnings, 3 - errors, warnings and info, 4 - 3 + debug
             * @param type $identifier Identify this site in the log (defaults to current domain)
             */
            public function __construct($loglevel_filter = 0, $identifier = null)
            {
                if (!$identifier) {
                    $identifier = \Idno\Core\Idno::site()->config->host;
                }

                $this->monolog = new \Monolog\Logger($identifier);
                if (isset(\Idno\Core\Idno::site()->config->logfile)) {
                    $this->logHandler = new \Monolog\Handler\StreamHandler(\Idno\Core\Idno::site()->config->logfile);
                } else {
                    $this->logHandler = new \Monolog\Handler\ErrorLogHandler();
                }

                $this->monolog->pushHandler($this->logHandler);
                if (isset(\Idno\Core\Idno::site()->config->loglevel)) {
                    $this->setLogLevel(\Idno\Core\Idno::site()->config->loglevel);
                }
            }

            /**
             * Sets the log level
             * @param $loglevel
             */
            public function setLogLevel($loglevel)
            {
                $this->logHandler->setLevel(self::toPsrLevel($loglevel));
            }

            /**
             * System is unusable.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function emergency($message, array $context = array())
            {
                return $this->monolog->emergency($message, $context);
            }

            /**
             * Action must be taken immediately.
             *
             * Example: Entire website down, database unavailable, etc. This should
             * trigger the SMS alerts and wake you up.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function alert($message, array $context = array())
            {
                return $this->monolog->alert($message, $context);
            }

            /**
             * Critical conditions.
             *
             * Example: Application component unavailable, unexpected exception.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function critical($message, array $context = array())
            {
                return $this->monolog->critical($message, $context);
            }

            /**
             * Runtime errors that do not require immediate action but should typically
             * be logged and monitored.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function error($message, array $context = array())
            {
                return $this->monolog->error($message, $context);
            }

            /**
             * Exceptional occurrences that are not errors.
             *
             * Example: Use of deprecated APIs, poor use of an API, undesirable things
             * that are not necessarily wrong.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function warning($message, array $context = array())
            {
                return $this->monolog->warning($message, $context);
            }

            /**
             * Normal but significant events.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function notice($message, array $context = array())
            {
                return $this->monolog->notice($message, $context);
            }

            /**
             * Interesting events.
             *
             * Example: User logs in, SQL logs.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function info($message, array $context = array())
            {
                return $this->monolog->info($message, $context);
            }

            /**
             * Detailed debug information.
             *
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function debug($message, array $context = array())
            {
                return $this->monolog->debug($message, $context);
            }

            /**
             * Logs with an arbitrary level.
             *
             * @param mixed  $level
             * @param string $message
             * @param array  $context
             *
             * @return null
             */
            public function log($level, $message=LOGLEVEL_INFO, array $context = array())
            {
                // Backward compatibility with the old signature, log($message, $level).
                // This is also why the default value of $message is LOGLEVEL_INFO for now.
                if (is_int($message) && is_string($message)) {
                    // TODO tack on a warning that this method might go away eventually
                    $idnoLevel = $message;
                    $message = $level;
                    $level = self::toPsrLevel($idnoLevel);
                }

                return $this->monolog->log($level, $message, $context);
            }

            /**
             * Convert a (possibly) old-style integral log level to a
             * \Psr\Log\LogLevel
             * @param mixed $level
             *
             * @return string
             */
            private static function toPsrLevel($level) {
                if (is_string($level)) {
                    return $level; // probably already PSR
                }
                switch($level) {
                case LOGLEVEL_DEBUG:   return \Psr\Log\LogLevel::DEBUG;
                case LOGLEVEL_INFO:    return \Psr\Log\LogLevel::INFO;
                case LOGLEVEL_WARNING: return \Psr\Log\LogLevel::WARNING;
                case LOGLEVEL_ERROR:   return \Psr\Log\LogLevel::ERROR;
                }

                return \Psr\Log\LogLevel::ERROR; // Shouldn't get here.
            }
        }

        define('LOGLEVEL_OFF', 0);
        define('LOGLEVEL_ERROR', 1);
        define('LOGLEVEL_WARNING', 2);
        define('LOGLEVEL_INFO', 3);
        define('LOGLEVEL_DEBUG', 4);
    }
