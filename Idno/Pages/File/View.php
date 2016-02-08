<?php

    /**
     * Generic, backup viewer for entities
     */

    namespace Idno\Pages\File {

        /**
         * Default class to serve the homepage
         */
        class View extends \Idno\Common\Page
        {

            // Handle GET requests to the entity

            function getContent()
            {
                // Check modified ts
                if ($cache = \Idno\Core\Idno::site()->cache()) {
                    if ($modifiedts = $cache->load("{$this->arguments[0]}_modified_ts")) {
                        $this->lastModifiedGatekeeper($modifiedts); // Set 304 and exit if we've not modified this object
                    }
                }

                if (!empty($this->arguments[0])) {
                    $object = \Idno\Entities\File::getByID($this->arguments[0]);
                }

                if (empty($object)) $this->noContent();


                session_write_close();  // Close the session early

                //$this->setResponseHeader("Pragma: public");

                // Determine uploaded timestamp
                if ($object instanceof \MongoGridFSFile) {
                    $upload_ts = $object->file['uploadDate']->sec;
                } else if (!empty($object->updated)) {
                    $upload_ts = $object->updated;
                } else if (!empty($object->created)) {
                    $upload_ts = $object->created;
                } else {
                    $upload_ts = time();
                }

                $this->setResponseHeader("Pragma: public");
                $this->setResponseHeader("Cache-Control: public");
                $this->setResponseHeader('Expires: ' . date(\DateTime::RFC1123, time() + (86400 * 30))); // Cache files for 30 days!
                $this->setLastModifiedHeader($upload_ts);
                if ($cache = \Idno\Core\Idno::site()->cache()) {
                    $cache->store("{$this->arguments[0]}_modified_ts", $upload_ts);
                }
                if (!empty($object->file['mime_type'])) {
                    $this->setResponseHeader('Content-type: ' . $object->file['mime_type']);
                } else {
                    $this->setResponseHeader('Content-type: application/data');
                }
                //$this->setResponseHeader('Accept-Ranges: bytes');
                //$this->setResponseHeader('Content-Length: ' . filesize($object->getSize()));

                if (is_callable(array($object, 'passThroughBytes'))) {
                    $object->passThroughBytes();
                } else {
                    if ($stream = $object->getResource()) {
                        while (!feof($stream)) {
                            echo fread($stream, 8192);
                        }
                    }
                }

            }

        }

    }