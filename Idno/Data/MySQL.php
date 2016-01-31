<?php

    /**
     * MySQL back-end for Known data.
     *
     * @package idno
     * @subpackage data
     */

    namespace Idno\Data {

        use Idno\Core\Idno;

        class MySQL extends \Idno\Core\DataConcierge
        {

            private $client = null;
            private $database = null;

            function init()
            {

                try {
                    $connection_string = 'mysql:host=' . \Idno\Core\Idno::site()->config()->dbhost . ';dbname=' . \Idno\Core\Idno::site()->config()->dbname . ';charset=utf8';
                    if (!empty(\Idno\Core\Idno::site()->config()->dbport)) {
                        $connection_string .= ';port=' . \Idno\Core\Idno::site()->config()->dbport;
                    }
                    $this->client = new \PDO($connection_string, \Idno\Core\Idno::site()->config()->dbuser, \Idno\Core\Idno::site()->config()->dbpass, array(\PDO::MYSQL_ATTR_LOCAL_INFILE => 1));
                    $this->client->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    //$this->client->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                    if (!empty(\Idno\Core\Idno::site()->config()->forward_on_empty)) {
                        header('Location: ' . \Idno\Core\Idno::site()->config()->forward_on_empty);
                        exit;
                    } else {

                        http_response_code(500);

                        if (\Idno\Core\Idno::site()->config()->debug) {
                            $message = '<p>' . $e->getMessage() . '</p>';
                            $message .= '<p>' . $connection_string . '</p>';
                        }
                        error_log($e->getMessage());
                        include \Idno\Core\Idno::site()->config()->path . '/statics/db.php';
                        exit;
                    }
                }

                $this->database = \Idno\Core\Idno::site()->config()->dbname;
                $this->checkAndUpgradeSchema();

            }

            /**
             * Checks the current schema version and upgrades if necessary
             */
            function checkAndUpgradeSchema()
            {
                if ($versions = $this->getVersions()) {
                    foreach ($versions as $version) {
                        if ($version->label == 'schema') {
                            $basedate          = $newdate = (int)$version->value;
                            $upgrade_sql_files = array();
                            $schema_dir        = dirname(dirname(dirname(__FILE__))) . '/schemas/mysql/';
                            $client            = $this->client;
                            /* @var \PDO $client */
                            if ($basedate < 2014100801) {
                                if ($sql = @file_get_contents($schema_dir . '2014100801.sql')) {
                                    try {
                                        $statement = $client->prepare($sql);
                                        $statement->execute();
                                    } catch (\Exception $e) {
                                        //\Idno\Core\Idno::site()->logging()->error($e->getMessage());
                                        error_log($e->getMessage());
                                    }
                                }
                                $newdate = 2014100801;
                            }
                            if ($basedate < 2015061501) {
                                if ($sql = @file_get_contents($schema_dir . '2015061501.sql')) {
                                    try {
                                        $statement = $client->prepare($sql);
                                        $statement->execute();
                                    } catch (\Exception $e) {
                                        //\Idno\Core\Idno::site()->logging()->error($e->getMessage());
                                        error_log($e->getMessage());
                                    }
                                }
                                $newdate = 2015061501;
                            }
                            if ($basedate < 2016013101) {
                                if ($sql = @file_get_contents($schema_dir . '2016013101.sql')) {
                                    try {
                                        $statement = $client->prepare($sql);
                                        $statement->execute();
                                    } catch (\Exception $e) {
                                        //\Idno\Core\Idno::site()->logging()->log($e->getMessage());
                                        error_log($e->getMessage());
                                    }
                                }
                                $newdate = 2016013101;
                            }
                        }
                    }
                }
            }

            /**
             * Retrieve version information from the schema
             * @return array|bool
             */
            function getVersions()
            {
                try {
                    $client = $this->client;
                    /* @var \PDO $client */
                    $statement = $client->prepare("select * from `versions`");
                    if ($statement->execute()) {
                        return $statement->fetchAll(\PDO::FETCH_OBJ);
                    }
                } catch (\Exception $e) {
                    //\Idno\Core\Idno::site()->logging()->error($e->getMessage());
                    error_log($e->getMessage());
                }

                return false;
            }

            /**
             * Optimize tables - this can reduce overall database storage space and query time
             * @return bool
             */
            function optimize()
            {
                try {
                    $this->client->query("optimize table entities");
                    $this->client->query("optimize table metadata");
                    $this->client->query("optimize table session");
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }

                return false;
            }

            /**
             * Handle the session in MySQL
             */
            function handleSession()
            {
                if (version_compare(phpversion(), '5.3', '>')) {
                    $sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler(\Idno\Core\Idno::site()->db()->getClient(),
                        array(
                            'db_table'    => 'session',
                            'db_id_col'   => 'session_id',
                            'db_data_col' => 'session_value',
                            'db_time_col' => 'session_time',
                        )
                    );

                    session_set_save_handler($sessionHandler, true);
                }
            }

            /**
             * Returns an instance of the database reference variable
             * @return string;
             */
            function getDatabase()
            {
                return $this->database;
            }

            /**
             * Returns an instance of the database client reference variable
             * @return \PDO
             */
            function getClient()
            {
                return $this->client;
            }

            /**
             * MySQL doesn't need the ID to be processed.
             * @param $id
             * @return string
             */
            function processID($id)
            {
                return $id;
            }

            /**
             * Saves a Known entity to the database, returning the _id
             * field on success.
             *
             * @param Entity $object
             */

            function saveObject($object)
            {
                if ($object instanceof \Idno\Common\Entity) {
                    if ($collection = $object->getCollection()) {
                        $array = $object->saveToArray();

                        return $this->saveRecord($collection, $array);
                    }
                }

                return false;
            }

            /**
             * Saves a record to the specified database collection
             *
             * @param string $collection
             * @param array $array
             * @return int | false
             */

            function saveRecord($collection, $array)
            {
                /*
                $collection_obj = $this->database->selectCollection($collection);
                if ($result = $collection_obj->save($array, array('w' => 1))) {
                    if ($result['ok'] == 1) {
                        return $array['_id'];
                    }
                }*/
                $collection = $this->sanitiseCollection($collection);

                if (empty($array['_id'])) {
                    $array['_id'] = md5(rand() . microtime(true));
                }
                if (empty($array['uuid'])) {
                    $array['uuid'] = \Idno\Core\Idno::site()->config()->getURL() . 'view/' . $array['_id'];
                }
                if (empty($array['owner'])) {
                    $array['owner'] = '';
                }
                try {
                    $contents = json_encode($array);
                } catch (\Exception $e) {
                    $contents = json_encode([]);
                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());

                    return false;
                }
                $search = '';
                if (!empty($array['title'])) {
                    $search .= $array['title'] . ' ';
                }
                if (!empty($array['tags'])) {
                    $search .= $array['tags'] . ' ';
                }
                if (!empty($array['description'])) {
                    $search .= $array['description'] . ' ';
                }
                if (!empty($array['body'])) {
                    $search .= strip_tags($array['body']);
                }
                if (!empty($array['handle'])) {
                    $search .= $array['handle'] . ' ';
                }
                if (!empty($array['profile'])) {
                    if (is_array($array['profile'])) {
                        foreach($array['profile'] as $profile_item) {
                            if (is_array($profile_item)) {

                            } else {
                                $search .= strip_tags($profile_item) . ' ';
                            }
                        }
                    }
                }
                if (empty($array['entity_subtype'])) {
                    $array['entity_subtype'] = 'Idno\\Common\\Entity';
                }
                if (empty($array['created'])) {
                    $array['created'] = date("Y-m-d H:i:s", time());
                } else {
                    $array['created'] = date("Y-m-d H:i:s", $array['created']);
                }

                $search = str_replace("\n", " \n ", $search);
                $search = str_replace("\r", "", $search);
                $search = str_replace("#", " #", $search);
                $search = strtolower($search);
                $search = Idno::site()->language()->uncurlQuotes($search);

                $client = $this->client;
                /* @var \PDO $client */

                $retval = false;
                $benchmark_start = microtime(true);
                try {
                    $client->beginTransaction();
                    $statement = $client->prepare("insert into {$collection}
                                                    (`uuid`, `_id`, `entity_subtype`,`owner`, `contents`, `search`, `created`)
                                                    values
                                                    (:uuid, :id, :subtype, :owner, :contents, :search, :created)
                                                    on duplicate key update `uuid` = :uuid, `entity_subtype` = :subtype, `owner` = :owner, `contents` = :contents, `search` = :search, `created` = :created");
                    if ($statement->execute(array(':uuid' => $array['uuid'], ':id' => $array['_id'], ':owner' => $array['owner'], ':subtype' => $array['entity_subtype'], ':contents' => $contents, ':search' => $search, ':created' => $array['created']))) {
                        $retval = $array['_id'];
                        if ($statement = $client->prepare("delete from metadata where _id = :id")) {
                            $statement->execute(array(':id' => $array['_id']));
                        }

                        foreach ($array as $key => $val) {
                            if (!is_array($val)) {
                                $val = array($val);
                            }
                            foreach ($val as $value) {
                                if (is_array($value) || is_object($value)) {
                                    try {
                                        $value = json_encode($value);
                                    } catch (\Exception $e) {
                                        $value = json_encode([]);
                                        \Idno\Core\Idno::site()->logging()->error($e->getMessage());
                                    }
                                }
                                if (empty($value)) {
                                    $value = 0;
                                }
                                if (strlen($value) > 255) { // We only need to store the first 255 characters
                                    $value = substr($value,0,255);
                                }
                                if ($statement = $client->prepare("insert into metadata set `collection` = :collection, `entity` = :uuid, `_id` = :id, `name` = :name, `value` = :value")) {
                                    $statement->execute(array('collection' => $collection, ':uuid' => $array['uuid'], ':id' => $array['_id'], ':name' => $key, ':value' => $value));
                                }
                            }
                        }
                    }
                    $client->commit();
                } catch (\Exception $e) {
                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());
                    $client->rollback();
                }

                \Idno\Core\Idno::site()->logging()->debug('saveRecord(): insert or update took ' . (microtime(true) - $benchmark_start) . 's');
                return $retval;
            }

            /**
             * Retrieves a Known entity object by its UUID, casting it to the
             * correct class
             *
             * @param string $id
             * @return \Idno\Common\Entity | false
             */

            function getObject($uuid)
            {
                if ($result = $this->getRecordByUUID($uuid)) {
                    if ($object = $this->rowToEntity($result)) {
                        if ($object->canRead()) {
                            return $object;
                        }
                    }
                }

                return false;
            }

            /**
             * Retrieves a record from the database by its UUID
             *
             * @param string $id
             * @param string $collection The collection to retrieve from (default: entities)
             * @return array
             */

            function getRecordByUUID($uuid, $collection = 'entities')
            {
                try {
                    $collection = $this->sanitiseCollection($collection);

                    $statement = $this->client->prepare("select distinct {$collection}.* from " . $collection . " where uuid = :uuid");
                    if ($statement->execute(array(':uuid' => $uuid))) {
                        return $statement->fetch(\PDO::FETCH_ASSOC);
                    }
                } catch (\Exception $e) {
                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());
                }

                return false;
            }

            /**
             * Converts a database row into a Known entity
             *
             * @param array $row
             * @return \Idno\Common\Entity
             */
            function rowToEntity($row)
            {
                if (!empty($row['entity_subtype']) && !empty($row['contents'])) {
                    if (class_exists($row['entity_subtype'])) {

                        $contents = (array)json_decode($row['contents'], true);

                        $object = new $row['entity_subtype']();
                        $object->loadFromArray($contents);

                        return $object;
                    }
                }

                return false;
            }

            /**
             * Retrieves a record from the database by ID
             *
             * @param string $id
             * @param string $entities The collection name to retrieve from (default: 'entities')
             * @return array
             */

            function getRecord($id, $collection = 'entities')
            {
                $collection = $this->sanitiseCollection($collection);

                $statement = $this->client->prepare("select {$collection}.* from " . $collection . " where _id = :id");
                if ($statement->execute(array(':id' => $id))) {
                    return $statement->fetch(\PDO::FETCH_ASSOC);
                }

                return false;
            }

            /**
             * Retrieves ANY record from a collection
             *
             * @param string $collection
             * @return mixed
             */
            function getAnyRecord($collection = 'entities')
            {
                try {
                    $collection = $this->sanitiseCollection($collection);

                    $statement = $this->client->prepare("select {$collection}.* from " . $collection . " limit 1");
                    if ($statement->execute()) {
                        if ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                            if ($obj = $this->rowToEntity($row)) {
                                return $obj;
                            }

                            return $row;
                        }
                    }
                } catch (\Exception $e) {
                    if (\Idno\Core\Idno::site()->session() == null)
                        die($e->getMessage());
                }

                return false;
            }

            /**
             * Retrieve objects of a certain kind that we're allowed to see,
             * (or excluding kinds that we don't want to see),
             * in reverse chronological order
             *
             * @param string|array $subtypes String or array of subtypes we're allowed to see
             * @param array $search Any extra search terms in array format (eg array('foo' => 'bar')) (default: empty)
             * @param array $fields An array of fieldnames to return (leave empty for all; default: all)
             * @param int $limit Maximum number of records to return (default: 10)
             * @param int $offset Number of records to skip (default: 0)
             * @param string $collection Collection to query; default: entities
             * @param array $readGroups Which ACL groups should we check? (default: everything the user can see)
             * @return array|false Array of elements or false, depending on success
             */

            function getObjects($subtypes = '', $search = array(), $fields = array(), $limit = 10, $offset = 0, $collection = 'entities', $readGroups = [])
            {

                // Initialize query parameters to be an empty array
                $query_parameters = array();

                // Ensure subtypes are recorded properly
                // and remove subtypes that have an exclamation mark before them
                // from consideration
                if (!empty($subtypes)) {
                    $not = array();
                    if (!is_array($subtypes)) {
                        $subtypes = array($subtypes);
                    }
                    foreach ($subtypes as $key => $subtype) {
                        if (substr($subtype, 0, 1) == '!') {
                            unset($subtypes[$key]);
                            $not[] = substr($subtype, 1);
                        }
                    }
                    if (!empty($subtypes)) {
                        $query_parameters['entity_subtype']['$in'] = $subtypes;
                    }
                    if (!empty($not)) {
                        $query_parameters['entity_subtype']['$not'] = $not;
                    }
                }

                // Make sure we're only getting objects that we're allowed to see
                if (empty($readGroups)) {
                    $readGroups                 = \Idno\Core\Idno::site()->session()->getReadAccessGroupIDs();
                }
                $query_parameters['access'] = array('$in' => $readGroups);

                // Join the rest of the search query elements to this search
                $query_parameters = array_merge($query_parameters, $search);

                // Prepare the fields array for searching, if required
                if (!empty($fields) && is_array($fields)) {
                    $fields = array_flip($fields);
                    $fields = array_fill_keys($fields, true);
                } else {
                    $fields = array();
                }

                // Run the query
                if ($results = $this->getRecords($fields, $query_parameters, $limit, $offset, $collection)) {
                    $return = array();
                    foreach ($results as $row) {
                        $return[] = $this->rowToEntity($row);
                    }

                    return $return;
                }

                return array();

            }

            /**
             * Retrieves a set of records from the database with given parameters, in
             * reverse chronological order
             *
             * @param array $parameters Query parameters in MongoDB format
             * @param int $limit Maximum number of records to return
             * @param int $offset Number of records to skip
             * @param string $collection The collection to interrogate (default: 'entities')
             * @return iterator|false Iterator or false, depending on success
             */

            function getRecords($fields, $parameters, $limit, $offset, $collection = 'entities')
            {
                try {
                    $collection = $this->sanitiseCollection($collection);

                    // Build query
                    $query            = "select distinct {$collection}.* from {$collection} ";
                    $variables        = array();
                    $metadata_joins   = 0;
                    $non_md_variables = array();
                    $limit            = (int)$limit;
                    $offset           = (int)$offset;
                    $where            = $this->build_where_from_array($parameters, $variables, $metadata_joins, $non_md_variables, 'and', $collection);
                    for ($i = 1; $i <= $metadata_joins; $i++) {
                        $query .= " left join metadata md{$i} on md{$i}.entity = {$collection}.uuid ";
                    }
                    if (!empty($where)) {
                        $query .= ' where ' . $where . ' ';
                    }
                    $query .= " order by {$collection}.`created` desc limit {$offset},{$limit}";

                    $client = $this->client;
                    /* @var \PDO $client */

                    $statement = $client->prepare($query);

                    if ($result = $statement->execute($variables)) {
                        return $statement->fetchAll(\PDO::FETCH_ASSOC);
                    }

                } catch (\Exception $e) {
                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());

                    return false;
                }

                return false;
            }

            /**
             * Recursive function that takes an array of parameters and returns an array of clauses suitable
             * for compiling into an SQL query
             * @param $params
             * @param $where
             * @param $variables
             * @param $metadata_joins
             * @param $non_md_variables
             * @param string $clause Defaults to 'and'
             */
            function build_where_from_array($params, &$variables, &$metadata_joins, &$non_md_variables, $clause = 'and', $collection = 'entities')
            {

                $where = '';
                if (empty($variables)) {
                    $variables = array();
                }
                if (empty($metadata_joins)) {
                    $metadata_joins = 0;
                }
                if (empty($non_md_variables)) {
                    $non_md_variables = 0;
                }
                if (is_array($params) && !empty($params)) {
                    $subwhere = array();
                    foreach ($params as $key => $value) {
                        if (!is_array($value)) {
                            if (in_array($key, array('uuid', '_id', 'entity_subtype', 'owner', 'created'))) {
                                $subwhere[] = "(`{$collection}`.`{$key}` = :nonmdvalue{$non_md_variables})";
                                if ($key == 'created') {
                                    if (!is_int($value)) {
                                        $value = strtotime($value);
                                    }
                                }
                                $variables[":nonmdvalue{$non_md_variables}"] = $value;
                                $non_md_variables++;
                            } else {
                                $metadata_joins++;
                                $subwhere[]                           = "(md{$metadata_joins}.`name` = :name{$metadata_joins} and md{$metadata_joins}.`value` = :value{$metadata_joins} and md{$metadata_joins}.`collection` = '{$collection}')";
                                $variables[":name{$metadata_joins}"]  = $key;
                                $variables[":value{$metadata_joins}"] = $value;
                            }
                        } else {
                            if (!empty($value['$or'])) {
                                $subwhere[] = "(" . $this->build_where_from_array($value['$or'], $variables, $metadata_joins, $non_md_variables, 'or', $collection) . ")";
                            }
                            if (!empty($value['$not'])) {
                                if (!empty($value['$not']['$in'])) {
                                    $value['$not'] = array_merge($value['$not'], $value['$not']['$in']);
                                    unset($value['$not']['$in']);
                                }
                                if (in_array($key, array('uuid', '_id', 'entity_subtype', 'owner'))) {
                                    $notstring = "`{$collection}`.`$key` not in(";
                                    $i         = 0;
                                    foreach ($value['$not'] as $val) {
                                        if ($i > 0) $notstring .= ', ';
                                        $notstring .= ":nonmdvalue{$non_md_variables}";
                                        $variables[":nonmdvalue{$non_md_variables}"] = $val;
                                        $non_md_variables++;
                                        $i++;
                                    }
                                    $notstring .= ")";
                                } else {
                                    $metadata_joins++;
                                    $notstring                           = "(md{$metadata_joins}.`name` = :name{$metadata_joins} and md{$metadata_joins}.`collection` = '{$collection}' and md{$metadata_joins}.`value` not in (";
                                    $variables[":name{$metadata_joins}"] = $key;
                                    $i                                   = 0;
                                    foreach ($value['$not'] as $val) {
                                        if ($i > 0) $notstring .= ', ';
                                        $notstring .= ":nonmdvalue{$non_md_variables}";
                                        $variables[":nonmdvalue{$non_md_variables}"] = $val;
                                        $non_md_variables++;
                                        $i++;
                                    }
                                    $notstring .= "))";
                                }
                                $subwhere[] = $notstring;
                            }
                            if (!empty($value['$in'])) {
                                if (in_array($key, array('uuid', '_id', 'entity_subtype', 'owner'))) {
                                    $instring = "`{$collection}`.`$key` in (";
                                    $i        = 0;
                                    foreach ($value['$in'] as $val) {
                                        if ($i > 0) $instring .= ', ';
                                        $instring .= ":nonmdvalue{$non_md_variables}";
                                        $variables[":nonmdvalue{$non_md_variables}"] = $val;
                                        $non_md_variables++;
                                        $i++;
                                    }
                                    $instring .= ")";
                                } else {
                                    $metadata_joins++;
                                    $instring                            = "(md{$metadata_joins}.`name` = :name{$metadata_joins} and md{$metadata_joins}.`collection` = '{$collection}' and md{$metadata_joins}.`value` in (";
                                    $variables[":name{$metadata_joins}"] = $key;
                                    $i                                   = 0;
                                    foreach ($value['$in'] as $val) {
                                        if ($i > 0) $instring .= ', ';
                                        $instring .= ":nonmdvalue{$non_md_variables}";
                                        $variables[":nonmdvalue{$non_md_variables}"] = $val;
                                        $non_md_variables++;
                                        $i++;
                                    }
                                    $instring .= "))";
                                }
                                $subwhere[] = $instring;
                            }
                            if ($key == '$search') {
                                if(!empty($value[0])) {
                                    $val = $value[0]; // The search query is always in $value position [0] for now
                                    if (strlen($val) > 5 && !Idno::site()->config()->bypass_fulltext_search) {
                                        if (Idno::site()->config()->boolean_search) {
                                            $boolean = 'in boolean mode';
                                        } else {
                                            $boolean = '';
                                        }
                                        $subwhere[]                                  = "match (`search`) against (:nonmdvalue{$non_md_variables} {$boolean})";
                                        $variables[":nonmdvalue{$non_md_variables}"] = $val;
                                    } else {
                                        $subwhere[]                                  = "`search` like :nonmdvalue{$non_md_variables}";
                                        $variables[":nonmdvalue{$non_md_variables}"] = '%' . $val . '%';
                                    }
                                    $non_md_variables++;
                                }
                            }
                        }
                    }
                    if (!empty($subwhere)) {
                        $where = '(' . implode(" {$clause} ", $subwhere) . ')';
                    }
                }

                return $where;
            }

            /**
             * Export a collection as SQL.
             * @param string $collection
             * @return bool|string
             */
            function exportRecords($collection = 'entities')
            {
                try {
                    $collection = $this->sanitiseCollection($collection);

                    $file   = tempnam(\Idno\Core\Idno::site()->config()->getTempDir(), 'sqldump');
                    $client = $this->client;
                    /* @var \PDO $client */
                    $statement = $client->prepare("select * from {$collection}");
                    $output    = '';
                    if ($response = $statement->execute()) {
                        while ($object = $statement->fetch(\PDO::FETCH_ASSOC)) {
                            $uuid   = $object['uuid'];
                            $fields = array_keys($object);
                            $fields = array_map(function ($v) {
                                return '`' . $v . '`';
                            }, $fields);
                            $object = array_map(function ($v) {
                                return \Idno\Core\Idno::site()->db()->getClient()->quote($v);
                            }, $object);
                            $line   = 'insert into ' . $collection . ' ';
                            $line .= '(' . implode(',', $fields) . ')';
                            $line .= ' values ';
                            $line .= '(' . implode(',', $object) . ');';
                            $output .= $line . "\n";
                            $metadata_statement = $client->prepare("select * from metadata where `entity` = :uuid");
                            if ($metadata_response = $metadata_statement->execute([':uuid' => $uuid])) {
                                while ($object = $metadata_statement->fetch(\PDO::FETCH_ASSOC)) {
                                    $fields = array_keys($object);
                                    $fields = array_map(function ($v) {
                                        return '`' . $v . '`';
                                    }, $fields);
                                    $object = array_map(function ($v) {
                                        return \Idno\Core\Idno::site()->db()->getClient()->quote($v);
                                    }, $object);
                                    $line   = 'insert into metadata ';
                                    $line .= '(' . implode(',', $fields) . ')';
                                    $line .= ' values ';
                                    $line .= '(' . implode(',', $object) . ');';
                                    $output .= $line . "\n";
                                }
                                unset($metadata_statement);
                                gc_collect_cycles();    // Clean memory
                            }
                            $output .= "\n";
                            unset($object);
                            unset($fields);
                            gc_collect_cycles();    // Clean memory
                        }
                    }

                    return $output;
                } catch (\Exception $e) {
                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());

                    return false;
                }

                return false;
            }

            /**
             * Count objects of a certain kind that we're allowed to see
             *
             * @param string|array $subtypes String or array of subtypes we're allowed to see
             * @param array $search Any extra search terms in array format (eg array('foo' => 'bar')) (default: empty)
             * @param string $collection Collection to query; default: entities
             */
            function countObjects($subtypes = '', $search = array(), $collection = 'entities')
            {

                // Initialize query parameters to be an empty array
                $query_parameters = array();

                // Ensure subtypes are recorded properly
                // and remove subtypes that have an exclamation mark before them
                // from consideration
                if (!empty($subtypes)) {
                    $not = array();
                    if (!is_array($subtypes)) {
                        $subtypes = array($subtypes);
                    }
                    foreach ($subtypes as $key => $subtype) {
                        if (substr($subtype, 0, 1) == '!') {
                            unset($subtypes[$key]);
                            $not[] = substr($subtype, 1);
                        }
                    }
                    if (!empty($subtypes)) {
                        $query_parameters['entity_subtype']['$in'] = $subtypes;
                    }
                    if (!empty($not)) {
                        $query_parameters['entity_subtype']['$not'] = $not;
                    }
                }

                // Make sure we're only getting objects that we're allowed to see
                $readGroups                 = \Idno\Core\Idno::site()->session()->getReadAccessGroupIDs();
                $query_parameters['access'] = array('$in' => $readGroups);

                // Join the rest of the search query elements to this search
                $query_parameters = array_merge($query_parameters, $search);

                return $this->countRecords($query_parameters, $collection);

            }

            /**
             * Count the number of records that match the given parameters
             * @param array $parameters
             * @param string $collection The collection to interrogate (default: 'entities')
             * @return int
             */
            function countRecords($parameters, $collection = 'entities')
            {
                try {

                    $collection = $this->sanitiseCollection($collection);

                    // Build query
                    $query            = "select count(distinct {$collection}.uuid) as total from {$collection} ";
                    $variables        = array();
                    $metadata_joins   = 0;
                    $non_md_variables = array();
                    $where            = $this->build_where_from_array($parameters, $variables, $metadata_joins, $non_md_variables, 'and', $collection);
                    for ($i = 1; $i <= $metadata_joins; $i++) {
                        $query .= " left join metadata md{$i} on md{$i}.entity = {$collection}.uuid ";
                    }
                    if (!empty($where)) {
                        $query .= ' where ' . $where . ' ';
                    }

                    $client = $this->client;
                    /* @var \PDO $client */
                    $statement = $client->prepare($query);
                    if ($result = $statement->execute($variables)) {
                        if ($obj = $statement->fetchObject()) {
                            return (int)$obj->total;
                        }
                    }

                } catch (Exception $e) {
                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());

                    return false;
                }

                return 0;
            }

            /**
             * Get database errors
             * @return mixed
             */
            function getErrors()
            {
                if (!empty($this->client)) {
                    return $this->client->errorInfo();
                }

                return false;
            }

            /**
             * Remove an entity from the database
             * @param string $id
             * @return true|false
             */
            function deleteRecord($id, $collection = 'entities')
            {
                try {

                    $collection = $this->sanitiseCollection($collection);

                    $client = $this->client;
                    /* @var \PDO $client */
                    $statement = $client->prepare("delete from {$collection} where _id = :id");
                    if ($statement->execute(array(':id' => $id))) {
                        if ($statement = $client->prepare("delete from metadata where _id = :id")) {
                            return $statement->execute(array(':id' => $id));
                        }
                    }

                } catch (\Exception $e) {

                    \Idno\Core\Idno::site()->logging()->error($e->getMessage());

                    return false;

                }

                return false;
            }

            /**
             * Retrieve the filesystem associated with the current db, suitable for saving
             * and retrieving files
             * @return bool
             */
            function getFilesystem()
            {
                // We're not returning a filesystem for MySQL
                return false;
            }

            /**
             * Given a text query, return an array suitable for adding into getFromX calls
             * @param $query
             * @return array
             */
            function createSearchArray($query)
            {
                return array('$search' => array($query));
            }

        }

        /**
         * Helper function that returns the current database object
         * @return \Idno\Core\DataConcierge
         */
        function db()
        {
            return \Idno\Core\Idno::site()->db();
        }

    }
