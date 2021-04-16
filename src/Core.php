<?php

    namespace b2db;

    use PDO,
        PDOException,
        ReflectionClass;

    /**
     * B2DB Core class
     *
     * @package b2db
     * @subpackage core
     */
    class Core
    {

        const CACHE_TYPE_INTERNAL = 'internal';
        const CACHE_TYPE_MEMCACHED = 'memcached';

        const DRIVER_MYSQL = 'mysql';
        const DRIVER_POSTGRES = 'pgsql';
        const DRIVER_MS_SQL_SERVER = 'mssql';

        /**
         * PDO object
         *
         * @var \PDO
         */
        protected static $db_connection = null;

        protected static $hostname;

        protected static $username;

        protected static $password;

        protected static $database_name;

        protected static $driver;

        protected static $port;

        protected static $dsn;

        protected static $table_prefix = '';

        protected static $sql_hits = array();

        protected static $sql_timing;

        protected static $object_population_hits = array();

        protected static $object_population_timing;

        protected static $object_population_counts;

        protected static $alias_counter = 0;

        protected static $cache_entities = true;

        protected static $cache_entities_strategy = self::CACHE_TYPE_INTERNAL;

        protected static $cache_entities_object;

        protected static $transaction_active = false;

        protected static $tables = array();

        protected static $debug_mode = true;

        protected static $debug_logging = null;

        /**
         * @var interfaces\Cache
         */
        protected static $cache_object = null;

        protected static $cache_dir;

        protected static $cached_entity_classes = array();

        protected static $cached_table_classes = array();

        /**
         * Loads a table and adds it to the B2DBObject stack
         *
         * @param Table $table
         *
         * @return Table
         */
        public static function loadNewTable(Table $table)
        {
            self::$tables['\\'.get_class($table)] = $table;
            return $table;
        }

        /**
         * Enable or disable debug mode
         *
         * @param boolean $debug_mode
         */
        public static function setDebugMode($debug_mode)
        {
            self::$debug_mode = $debug_mode;
        }

        /**
         * Return whether or not debug mode is enabled
         *
         * @return boolean
         */
        public static function isDebugMode()
        {
            return self::$debug_mode;
        }

        public static function getDebugTime()
        {
            return array_sum(explode(' ', microtime()));
        }

        public static function isDebugLoggingEnabled()
        {
            if (self::$debug_logging === null)
                self::$debug_logging = (self::isDebugMode() && class_exists('\\caspar\\core\\Logging'));

            return self::$debug_logging;
        }

        /**
         * Add a table alias to alias counter
         *
         * @return integer
         */
        public static function addAlias()
        {
            return self::$alias_counter++;
        }

        /**
         * Initialize B2DB and load related B2DB classes
         *
         * @param array $configuration [optional] Configuration to load
         * @param \b2db\interfaces\Cache $cache_object
         */
        public static function initialize($configuration = array(), $cache_object = null)
        {
            try {
                if (array_key_exists('dsn', $configuration) && $configuration['dsn'])
                    self::setDSN($configuration['dsn']);
                if (array_key_exists('driver', $configuration) && $configuration['driver'])
                    self::setDriver($configuration['driver']);
                if (array_key_exists('hostname', $configuration) && $configuration['hostname'])
                    self::setHostname($configuration['hostname']);
                if (array_key_exists('port', $configuration) && $configuration['port'])
                    self::setPort($configuration['port']);
                if (array_key_exists('username', $configuration) && $configuration['username'])
                    self::setUsername($configuration['username']);
                if (array_key_exists('password', $configuration) && $configuration['password'])
                    self::setPassword($configuration['password']);
                if (array_key_exists('database', $configuration) && $configuration['database'])
                    self::setDatabaseName($configuration['database']);
                if (array_key_exists('tableprefix', $configuration) && $configuration['tableprefix'])
                    self::setTablePrefix($configuration['tableprefix']);
                if (array_key_exists('debug', $configuration))
                    self::setDebugMode((bool) $configuration['debug']);
                if (array_key_exists('caching', $configuration))
                    self::setCacheEntities((bool) $configuration['caching']);

                if ($cache_object !== null) {
                    if (is_callable($cache_object)) {
                        self::$cache_object = call_user_func($cache_object);
                    } else {
                        self::$cache_object = $cache_object;
                    }
                }

                if (!self::$cache_object) {
                    self::$cache_object = new Cache(Cache::TYPE_DUMMY);
                    self::$cache_object->disable();
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }

        /**
         * Return true if B2DB is initialized with database name and username
         *
         * @return boolean
         */
        public static function isInitialized()
        {
            return (bool) (self::getDriver() != '' && self::getUsername() != '');
        }

        /**
         * Store connection parameters
         *
         * @param string $bootstrap_location Where to save the connection parameters
         */
        public static function saveConnectionParameters($bootstrap_location)
        {
            $string = "b2db:\n";
            $string .= "    driver: " . self::getDriver() . "\n";
            $string .= "    username: " . self::getUsername() . "\n";
            $string .= "    password: \"" . self::getPassword() . "\"\n";
            $string .= '    dsn: "' . self::getDSN() . "\"\n";
            $string .= "    tableprefix: '" . self::getTablePrefix() . "'\n";
            $string .= "\n";
            try {
                if (file_put_contents($bootstrap_location, $string) === false) {
                    throw new Exception('Could not save the database connection details');
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }

        /**
         * Returns the Table object
         *
         * @param string $table_name The table class name to load
         *
         * @return Table
         */
        public static function getTable($table_name)
        {
            if (!isset(self::$tables[$table_name])) {
                self::loadNewTable(new $table_name());
            }
            if (!isset(self::$tables[$table_name])) {
                throw new Exception('Table ' . $table_name . ' is not loaded');
            }
            return self::$tables[$table_name];
        }

        protected static function getRelevantDebugBacktraceElement($backtrace = null)
        {
            $trace = null;
            $backtrace = ($backtrace !== null) ? $backtrace : debug_backtrace();
            $reserved_names = array('Core.php', 'Saveable.php', 'Criteria.php', 'Criterion.php', 'Resultset.php', 'Row.php', 'Statement.php', 'Transaction.php', 'Criteria.php', 'B2DBCriterion.php', 'Row.php', 'Statement.php', 'Transaction.php', 'Table.php');

            foreach ($backtrace as $t) {
                if (isset($trace)) {
                    $trace['function'] = (isset($t['function'])) ? $t['function'] : 'unknown';
                    $trace['class'] = (isset($t['class'])) ? $t['class'] : 'unknown';
                    $trace['type'] = (isset($t['type'])) ? $t['type'] : 'unknown';
                    break;
                }
                if (!array_key_exists('file', $t)) continue;
                if (!\in_array(basename($t['file']), $reserved_names)) {
                    $trace = $t;
                    continue;
                }
            }

            return (!$trace) ? array('file' => 'unknown', 'line' => 'unknown', 'function' => 'unknown', 'class' => 'unknown', 'type' => 'unknown', 'args' => array()) : $trace;
        }

        /**
         * Register a new object population call (debug only)
         *
         * @param $num_classes
         * @param array $class_names
         * @param mixed $previous_time
         */
        public static function objectPopulationHit($num_classes, $class_names, $previous_time)
        {
            if (!Core::isDebugMode() || !$num_classes)
                return;

            $time = Core::getDebugTime() - $previous_time;
            $trace = self::getRelevantDebugBacktraceElement();

            self::$object_population_hits[] = array('classnames' => $class_names, 'num_classes' => $num_classes, 'time' => $time, 'filename' => $trace['file'], 'line' => $trace['line'], 'function' => $trace['function'], 'class' => (isset($trace['class']) ? $trace['class'] : 'unknown'), 'type' => (isset($trace['type']) ? $trace['type'] : 'unknown'), 'arguments' => $trace['args']);
            self::$object_population_counts += $num_classes;
            self::$object_population_timing += $time;
        }

        /**
         * Register a new SQL call (debug only)
         *
         * @param Statement $statement
         * @param mixed $previous_time
         */
        public static function sqlHit(Statement $statement, $previous_time)
        {
            if (!Core::isDebugMode())
                return;

            $time = Core::getDebugTime() - $previous_time;
            $sql = $statement->printSQL();
            $values = ($statement->getQuery() instanceof Criteria) ? $statement->getQuery()->getValues() : array();

            $backtrace = debug_backtrace();
            $trace = self::getRelevantDebugBacktraceElement($backtrace);
            $traces = [];
            foreach ($backtrace as $trace_item) {
                $traces[] = [
                    'file' => (isset($trace_item['file'])) ? $trace_item['file'] : null,
                    'line' => (isset($trace_item['line'])) ? $trace_item['line'] : null,
                    'function' => $trace_item['function'],
                    'class' => (isset($trace_item['class'])) ? $trace_item['class'] : null,
                    'type' => (isset($trace_item['type'])) ? $trace_item['type'] : null
                ];
            }

            self::$sql_hits[] = array('sql' => $sql, 'values' => implode(', ', $values), 'time' => $time, 'filename' => $trace['file'], 'line' => $trace['line'], 'function' => $trace['function'], 'class' => (isset($trace['class']) ? $trace['class'] : 'unknown'), 'type' => (isset($trace['type']) ? $trace['type'] : 'unknown'), 'arguments' => $trace['args'], 'backtrace' => $traces);
            self::$sql_timing += $time;
        }

        /**
         * Get number of SQL calls
         *
         * @return string[]
         */
        public static function getSQLHits()
        {
            return self::$sql_hits;
        }

        public static function getSQLCount()
        {
            return count(self::$sql_hits) + 1;
        }

        public static function getSQLTiming()
        {
            return self::$sql_timing;
        }

        /**
         * Get number of object population calls
         *
         * @return string[]
         */
        public static function getObjectPopulationHits()
        {
            return self::$object_population_hits;
        }

        public static function getObjectPopulationCount()
        {
            return self::$object_population_counts;
        }

        public static function getObjectPopulationTiming()
        {
            return self::$object_population_timing;
        }

        /**
         * Returns PDO object
         *
         * @return \PDO
         */
        public static function getDBlink()
        {
            if (!self::$db_connection instanceof PDO) {
                self::doConnect();
            }
            return self::$db_connection;
        }

        /**
         * returns a PDO resultset
         *
         * @param string $sql
         * @return \PDOStatement
         */
        public static function simpleQuery($sql)
        {
            self::$sql_hits++;
            try {
                $res = self::getDBLink()->query($sql);
            } catch (PDOException $e) {
                throw new Exception($e->getMessage());
            }
            return $res;
        }

        /**
         * Set the DSN
         *
         * @param string $dsn
         */
        public static function setDSN($dsn)
        {
            $dsn_details = parse_url($dsn);
            if (!is_array($dsn_details) || !array_key_exists('scheme', $dsn_details)) {
                throw new Exception('This does not look like a valid DSN - cannot read the database type');
            }
            try {
                self::setDriver($dsn_details['scheme']);
                $details = explode(';', $dsn_details['path']);
                foreach ($details as $detail) {
                    $detail_info = explode('=', $detail);
                    if (count($detail_info) != 2) {
                        throw new Exception('This does not look like a valid DSN - cannot read the connection details');
                    }
                    switch ($detail_info[0]) {
                        case 'host':
                            self::setHostname($detail_info[1]);
                            break;
                        case 'port':
                            self::setPort($detail_info[1]);
                            break;
                        case 'dbname':
                            self::setDatabaseName($detail_info[1]);
                            break;
                    }
                }
            } catch (\Exception $e) {
                throw $e;
            }
            self::$dsn = $dsn;
        }

        /**
         * Generate the DSN when needed
         */
        protected static function generateDSN()
        {
            $dsn = self::getDriver() . ":host=" . self::getHostname();
            if (self::getPort()) {
                $dsn .= ';port=' . self::getPort();
            }
            $dsn .= ';dbname=' . self::getDatabaseName();
            self::$dsn = $dsn;
        }

        /**
         * Return current DSN
         *
         * @return string
         */
        public static function getDSN()
        {
            if (self::$dsn === null) {
                self::generateDSN();
            }
            return self::$dsn;
        }

        /**
         * Set the database host
         *
         * @param string $hostname
         */
        public static function setHostname($hostname)
        {
            self::$hostname = $hostname;
        }

        /**
         * Return the database host
         *
         * @return string
         */
        public static function getHostname()
        {
            return self::$hostname;
        }

        /**
         * Return the database port
         *
         * @return integer
         */
        public static function getPort()
        {
            return self::$port;
        }

        /**
         * Set the database port
         *
         * @param integer $port
         */
        public static function setPort($port)
        {
            self::$port = $port;
        }

        /**
         * Set database username
         *
         * @param string $username
         */
        public static function setUsername($username)
        {
            self::$username = $username;
        }

        /**
         * Get database username
         *
         * @return string
         */
        public static function getUsername()
        {
            return self::$username;
        }

        /**
         * Set the database table prefix
         *
         * @param string $prefix
         */
        public static function setTablePrefix($prefix)
        {
            self::$table_prefix = $prefix;
        }

        /**
         * Get the database table prefix
         *
         * @return string
         */
        public static function getTablePrefix()
        {
            return self::$table_prefix;
        }

        /**
         * Set the database password
         *
         * @param string $password
         */
        public static function setPassword($password)
        {
            self::$password = $password;
        }

        /**
         * Return the database password
         *
         * @return string
         */
        public static function getPassword()
        {
            return self::$password;
        }

        /**
         * Set the database name
         *
         * @param string $database_name
         */
        public static function setDatabaseName($database_name)
        {
            self::$database_name = $database_name;
            self::$dsn = null;
        }

        /**
         * Get the database name
         *
         * @return string
         */
        public static function getDatabaseName()
        {
            return self::$database_name;
        }

        /**
         * Set the database type
         *
         * @param string $driver
         */
        public static function setDriver($driver)
        {
            if (self::isDriverSupported($driver) == false) {
                throw new Exception('The selected database is not supported: "' . $driver . '".');
            }
            self::$driver = $driver;
        }

        /**
         * Get the database type
         *
         * @return string
         */
        public static function getDriver()
        {
            return self::$driver;
        }

        public static function hasDriver()
        {
            return (bool) (self::getDriver() != '');
        }

        /**
         * Try connecting to the database
         */
        public static function doConnect()
        {
            if (!\class_exists('\\PDO')) {
                throw new Exception('B2DB needs the PDO PHP libraries installed. See http://php.net/PDO for more information.');
            }
            try {
                $uname = self::getUsername();
                $pwd = self::getPassword();
                $dsn = self::getDSN();
                if (self::$db_connection instanceof PDO) {
                    self::$db_connection = null;
                }
                self::$db_connection = new PDO($dsn, $uname, $pwd);
                if (!self::$db_connection instanceof PDO) {
                    throw new Exception('Could not connect to the database, but not caught by PDO');
                }
        switch (self::getDriver())
        {
            case self::DRIVER_MYSQL:
                self::getDBLink()->query('SET NAMES UTF8');
                break;
            case self::DRIVER_POSTGRES:
                self::getDBlink()->query('set client_encoding to UTF8');
                break;
        }
            } catch (PDOException $e) {
                throw new Exception("Could not connect to the database [" . $e->getMessage() . "], dsn: ".self::getDSN());
            } catch (Exception $e) {
                throw $e;
            }
        }

        /**
         * Return entity caching on/off
         *
         * @return boolean
         */
        public static function getCacheEntities()
        {
            return self::$cache_entities;
        }

        /**
         * Set entity caching on/off
         *
         * @param boolean $caching
         */
        public static function setCacheEntities($caching)
        {
            self::$cache_entities = $caching;
        }

        /**
         * Set entity caching strategy
         *
         * @param string $strategy
         *
         * @see self::CACHE_TYPE_INTERNAL
         * @see self::CACHE_TYPE_MEMCACHED
         */
        public static function setCacheEntitiesStrategy($strategy)
        {
            self::$cache_entities_strategy = $strategy;
        }

        /**
         * Retrieve entitiy caching strategy
         *
         * @return string
         *
         * @see self::CACHE_TYPE_INTERNAL
         * @see self::CACHE_TYPE_MEMCACHED
         */
        public static function getCacheEntitiesStrategy()
        {
            return self::$cache_entities_strategy;
        }

        /**
         * Set the cache object
         *
         * @param interfaces\Cache $object
         */
        public static function setCacheEntitiesObject(interfaces\Cache $object)
        {
            self::$cache_entities_object = $object;
        }

        /**
         * @return interfaces\Cache
         */
        public static function getCacheEntitiesObject()
        {
            return self::$cache_entities_object;
        }

        /**
         * Create the specified database
         *
         * @param string $db_name
         */
        public static function createDatabase($db_name)
        {
            self::getDBLink()->query('create database ' . $db_name);
        }

        /**
         * Close the database connection
         */
        public static function closeDBLink()
        {
            self::$db_connection = null;
        }

        public static function isConnected()
        {
            return (bool) (self::$db_connection instanceof PDO);
        }

        /**
         * Toggle the transaction state
         *
         * @param boolean $state
         */
        public static function setTransaction($state)
        {
            self::$transaction_active = $state;
        }

        /**
         * Starts a new transaction
         */
        public static function startTransaction()
        {
            return new Transaction();
        }

        public static function isTransactionActive()
        {
            return (bool) self::$transaction_active == Transaction::DB_TRANSACTION_STARTED;
        }

        /**
         * Get available DB drivers
         *
         * @return array
         */
        public static function getDrivers()
        {
            $types = array();

            if (class_exists('\PDO')) {
                $types[self::DRIVER_MYSQL] = 'MySQL / MariaDB';
                $types[self::DRIVER_POSTGRES] = 'PostgreSQL';
                $types[self::DRIVER_MS_SQL_SERVER] = 'Microsoft SQL Server';
            } else {
                throw new Exception('You need to have PHP PDO installed to be able to use B2DB');
            }

            return $types;
        }

        /**
         * Whether a specific DB driver is supported
         *
         * @param string $driver
         *
         * @return boolean
         */
        public static function isDriverSupported($driver)
        {
            return array_key_exists($driver, self::getDrivers());
        }

        protected static function storeCachedTableClass($classname)
        {
            $key = 'b2db_cache_' . str_replace(['/', '\\'], ['_', '_'], $classname);
            self::$cache_object->set($key, self::$cached_table_classes[$classname]);
        }

        protected static function cacheTableClass($classname)
        {
            if (!\class_exists($classname)) {
                throw new Exception("The class '{$classname}' does not exist");
            }
            self::$cached_table_classes[$classname] = array('entity' => null, 'name' => null, 'discriminator' => null);

            $reflection = new ReflectionClass($classname);
            $docblock = $reflection->getDocComment();
            $annotation_set = new AnnotationSet($docblock);
            if (!$table_annotation = $annotation_set->getAnnotation('Table')) {
                throw new Exception("The class '{$classname}' does not have a proper @Table annotation");
            }
            $table_name = $table_annotation->getProperty('name');

            if ($entity_annotation = $annotation_set->getAnnotation('Entity'))
                self::$cached_table_classes[$classname]['entity'] = $entity_annotation->getProperty('class');

            if ($entities_annotation = $annotation_set->getAnnotation('Entities')) {
                $details = array('identifier' => $entities_annotation->getProperty('identifier'), 'classes' => $annotation_set->getAnnotation('SubClasses')->getProperties());
                self::$cached_table_classes[$classname]['entities'] = $details;
            }

            if ($discriminator_annotation = $annotation_set->getAnnotation('Discriminator')) {
                $details = array('column' => "{$table_name}." . $discriminator_annotation->getProperty('column'), 'discriminators' => $annotation_set->getAnnotation('Discriminators')->getProperties());
                self::$cached_table_classes[$classname]['discriminator'] = $details;
            }

            if (!$table_annotation->hasProperty('name')) {
                throw new Exception("The class @Table annotation in '{$classname}' is missing required 'name' property");
            }

            self::$cached_table_classes[$classname]['name'] = $table_name;

            if (!self::$debug_mode) self::storeCachedTableClass($classname);
        }

        protected static function storeCachedEntityClass($classname)
        {
            $key = 'b2db_cache_' . str_replace(['/', '\\'], ['_', '_'], $classname);
            self::$cache_object->set($key, self::$cached_entity_classes[$classname]);
        }

        protected static function cacheEntityClass($classname, $reflection_classname = null)
        {
            $rc_name = ($reflection_classname !== null) ? $reflection_classname : $classname;
            $reflection = new ReflectionClass($rc_name);
            $annotation_set = new AnnotationSet($reflection->getDocComment());

            if ($reflection_classname === null) {
                self::$cached_entity_classes[$classname] = array('columns' => array(), 'relations' => array(), 'foreign_columns' => array(),);
                if (!$annotation = $annotation_set->getAnnotation('Table')) {
                    throw new Exception("The class '{$classname}' is missing a valid @Table annotation");
                } else {
                    $table_name = $annotation->getProperty('name');
                }
                if (!\class_exists($table_name)) {
                    throw new Exception("The class table class '{$table_name}' for class '{$classname}' does not exist");
                }
                self::$cached_entity_classes[$classname]['table'] = $table_name;
                self::populateCachedTableClassFiles($table_name);
                if (($re = $reflection->getExtension()) && $classnames = $re->getClassNames()) {
                    foreach ($classnames as $extends_classname) {
                        self::cacheEntityClass($classname, $extends_classname);
                    }
                }
            }
            if (!\array_key_exists('name', self::$cached_table_classes[self::$cached_entity_classes[$classname]['table']])) {
                throw new Exception("The class @Table annotation in '" . self::$cached_entity_classes[$classname]['table'] . "' is missing required 'name' property");
            }
            $column_prefix = self::$cached_table_classes[self::$cached_entity_classes[$classname]['table']]['name'] . '.';

            foreach ($reflection->getProperties() as $property) {
                $annotation_set = new AnnotationSet($property->getDocComment());
                if ($annotation_set->hasAnnotations()) {
                    $property_name = $property->getName();
                    if ($column_annotation = $annotation_set->getAnnotation('Column')) {
                        $column_name = $column_prefix . (($column_annotation->hasProperty('name')) ? $column_annotation->getProperty('name') : substr($property_name, 1));

                        $column = array('property' => $property_name, 'name' => $column_name, 'type' => $column_annotation->getProperty('type'));

                        $column['not_null'] = ($column_annotation->hasProperty('not_null')) ? $column_annotation->getProperty('not_null') : false;

                        if ($column_annotation->hasProperty('default_value')) $column['default_value'] = $column_annotation->getProperty('default_value');
                        if ($column_annotation->hasProperty('length')) $column['length'] = $column_annotation->getProperty('length');

                        switch ($column['type']) {
                            case 'serializable':
                                $column['type'] = 'serializable';
                                break;
                            case 'varchar':
                            case 'string':
                                $column['type'] = 'varchar';
                                break;
                            case 'float':
                                $column['precision'] = ($column_annotation->hasProperty('precision')) ? $column_annotation->getProperty('precision') : 2;
                            case 'integer':
                                $column['auto_inc'] = ($column_annotation->hasProperty('auto_increment')) ? $column_annotation->getProperty('auto_increment') : false;
                                $column['unsigned'] = ($column_annotation->hasProperty('unsigned')) ? $column_annotation->getProperty('unsigned') : false;
                                if (!isset($column['length'])) $column['length'] = 10;
                                if ($column['type'] != 'float'&& !isset($column['default_value'])) $column['default_value'] = 0;
                                break;
                        }
                        self::$cached_entity_classes[$classname]['columns'][$column_name] = $column;
                        if ($annotation_set->hasAnnotation('Id')) {
                            self::$cached_entity_classes[$classname]['id_column'] = $column_name;
                        }
                    }
                    if ($annotation = $annotation_set->getAnnotation('Relates')) {
                        $value = $annotation->getProperty('class');
                        $collection = (bool) $annotation->getProperty('collection');
                        $many_to_many = (bool) $annotation->getProperty('manytomany');
                        $join_class = $annotation->getProperty('joinclass');
                        $foreign_column = $annotation->getProperty('foreign_column');
                        $order_by = $annotation->getProperty('orderby');
                        $f_column = $annotation->getProperty('column');
                        self::$cached_entity_classes[$classname]['relations'][$property_name] = array('collection' => $collection, 'property' => $property_name, 'foreign_column' => $foreign_column, 'manytomany' => $many_to_many, 'joinclass' => $join_class, 'class' => $annotation->getProperty('class'), 'column' => $f_column, 'orderby' => $order_by);
                        if (!$collection) {
                            if (!$column_annotation || !isset($column)) {
                                throw new Exception("The property '{$property_name}' in class '{$classname}' is missing an @Column annotation, or is improperly marked as not being a collection");
                            }
                            $column_name = $column_prefix . (($annotation->hasProperty('name')) ? $annotation->getProperty('name') : substr($property_name, 1));
                            $column['class'] = self::getCachedB2DBTableClass($value);
                            $column['key'] = ($annotation->hasProperty('key')) ? $annotation->getProperty('key') : null;
                            self::$cached_entity_classes[$classname]['foreign_columns'][$column_name] = $column;
                        }
                    }
                }
            }

            if (!self::$debug_mode) self::storeCachedEntityClass($classname);
        }

        protected static function populateCachedClassFiles($classname)
        {
            if (!array_key_exists($classname, self::$cached_entity_classes)) {
                $entity_key = 'b2db_cache_' . str_replace(['/', '\\'], ['_', '_'], $classname);
                if (self::$cache_object && self::$cache_object->has($entity_key)) {
                    self::$cached_entity_classes[$classname] = self::$cache_object->get($entity_key);
                } else {
                    self::cacheEntityClass($classname);
                }
            }
        }

        protected static function populateCachedTableClassFiles($classname)
        {
            if (!array_key_exists($classname, self::$cached_table_classes)) {
                $key = 'b2db_cache_' . str_replace(['/', '\\'], ['_', '_'], $classname);
                if (self::$cache_object && self::$cache_object->has($key)) {
                    self::$cached_table_classes[$classname] = self::$cache_object->get($key);
                } else {
                    self::cacheTableClass($classname);
                }
            }
        }

        /**
         * @param Table $classname
         * @return array
         */
        public static function getTableDetails($classname)
        {
            $table = $classname::getTable();
            if ($table instanceof Table) {
                self::populateCachedTableClassFiles($classname);
                return array('columns' => $table->getColumns(),
                    'foreign_columns' => $table->getForeignColumns(),
                    'id' => $table->getIdColumn(),
                    'discriminator' => self::$cached_table_classes[$classname]['discriminator'],
                    'name' => self::$cached_table_classes[$classname]['name']
                );
            }
        }

        public static function getCachedTableDetails($classname)
        {
            self::populateCachedClassFiles($classname);
            if (array_key_exists($classname, self::$cached_entity_classes) && array_key_exists('columns', self::$cached_entity_classes[$classname])) {
                if (!array_key_exists('id_column', self::$cached_entity_classes[$classname])) {
                    throw new Exception('Cannot find an id column for this table.');
                }
                return array('columns' => self::$cached_entity_classes[$classname]['columns'],
                    'foreign_columns' => self::$cached_entity_classes[$classname]['foreign_columns'],
                    'id' => self::$cached_entity_classes[$classname]['id_column'],
                    'discriminator' => self::$cached_table_classes[self::$cached_entity_classes[$classname]['table']]['discriminator'],
                    'name' => self::$cached_table_classes[self::$cached_entity_classes[$classname]['table']]['name']
                );
            }
            return null;
        }

        protected static function getCachedEntityDetail($classname, $key, $detail = null)
        {
            self::populateCachedClassFiles($classname);
            if (array_key_exists($classname, self::$cached_entity_classes)) {
                if (!array_key_exists($key, self::$cached_entity_classes[$classname])) {
                    if ($key == 'table') throw new Exception("The class '{$classname}' is missing a valid @Table annotation");
                } elseif ($detail === null) {
                    return self::$cached_entity_classes[$classname][$key];
                } elseif (isset(self::$cached_entity_classes[$classname][$key][$detail])) {
                    return self::$cached_entity_classes[$classname][$key][$detail];
                }
            }
            return null;
        }

        protected static function getCachedTableDetail($classname, $detail)
        {
            self::populateCachedTableClassFiles($classname);
            if (array_key_exists($classname, self::$cached_table_classes)) {
                if (!array_key_exists($detail, self::$cached_table_classes[$classname])) {
                    if ($detail == 'entity') throw new Exception("The class '{$classname}' is missing a valid @Entity annotation");
                } else {
                    return self::$cached_table_classes[$classname][$detail];
                }
            }
            return null;
        }

        public static function getCachedEntityRelationDetails($classname, $property)
        {
            return self::getCachedEntityDetail($classname, 'relations', $property);
        }

        public static function getCachedColumnDetails($classname, $column)
        {
            return self::getCachedEntityDetail($classname, 'columns', $column);
        }

        public static function getCachedColumnPropertyName($classname, $column)
        {
            $column_details = self::getCachedColumnDetails($classname, $column);
            return (is_array($column_details)) ? $column_details['property'] : null;
        }

        /**
         * @param $classname
         * @return Table
         */
        public static function getCachedB2DBTableClass($classname)
        {
            return self::getCachedEntityDetail($classname, 'table');
        }

        public static function getCachedTableEntityClasses($classname)
        {
            return self::getCachedTableDetail($classname, 'entities');
        }

        public static function getCachedTableEntityClass($classname)
        {
            return self::getCachedTableDetail($classname, 'entity');
        }

    }
