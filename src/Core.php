<?php

    /**
     * @noinspection PhpUnused
     * @noinspection PhpMissingFieldTypeInspection
     */

    namespace b2db;

    use b2db\interfaces\QueryInterface;
    use PDO;
    use PDOException;
    use ReflectionClass;
    use PDOStatement;

    /**
     * B2DB Core class
     *
     * @package b2db
     */
    class Core
    {

        public const CACHE_TYPE_INTERNAL = 'internal';
        public const CACHE_TYPE_MEMCACHED = 'memcached';

        public const DRIVER_MYSQL = 'mysql';
        public const DRIVER_POSTGRES = 'pgsql';
        public const DRIVER_MS_SQL_SERVER = 'mssql';

        /**
         * PDO object
         *
         * @var ?PDO
         */
        protected static ?PDO $db_connection;

        protected static string $hostname;

        protected static string $username;

        protected static string $password;

        protected static string $database_name;

        protected static string $driver;

        protected static int $port;

        protected static ?string $dsn;

        protected static string $table_prefix;

        /**
         * @var array<int, int|string|array<string, int|string|array<string, string|int>>>
         */
        protected static array $sql_hits = [];

        protected static int $sql_timing = 0;

        /**
         * @var array<int, array<string, int|string|array<string>>>
         */
        protected static array $object_population_hits = [];

        protected static int $object_population_timing = 0;

        protected static int $object_population_counts = 0;

        protected static int $alias_counter = 0;

        protected static bool $cache_entities = true;

        protected static string $cache_entities_strategy = self::CACHE_TYPE_INTERNAL;

        protected static interfaces\Cache $cache_entities_object;

        protected static int $transaction_active = Transaction::DB_TRANSACTION_NOT_STARTED;

        /**
         * @var Table[]
         */
        protected static array $tables = [];

        protected static bool $debug_mode = true;

        protected static ?bool $debug_logging;

        /**
         * @var ?interfaces\Cache
         */
        protected static $cache_object;

        protected static string $cache_dir;

        /**
         * @var array<string, array<string>|mixed>
         */
        protected static array $cached_entity_classes = [];

        /**
         * @var array<string, array>
         */
        protected static array $cached_table_classes = [];

        /**
         * Loads a table and adds it to the B2DBObject stack
         *
         * @param Table $table
         *
         * @return Table
         */
        public static function loadNewTable(Table $table): Table
        {
            self::$tables['\\'.get_class($table)] = $table;
            return $table;
        }

        /**
         * Enable or disable debug mode
         *
         * @param bool $debug_mode
         */
        public static function setDebugMode(bool $debug_mode): void
        {
            self::$debug_mode = $debug_mode;
        }

        /**
         * Return whether or not debug mode is enabled
         *
         * @return bool
         */
        public static function isDebugMode(): bool
        {
            return self::$debug_mode;
        }

        /**
         * @return int
         */
        public static function getDebugTime(): int
        {
            return array_sum(explode(' ', microtime()));
        }

        public static function isDebugLoggingEnabled(): bool
        {
            if (!isset(self::$debug_logging)) {
                self::$debug_logging = (self::isDebugMode() && class_exists('\\caspar\\core\\Logging'));
            }

            return self::$debug_logging;
        }

        /**
         * Add a table alias to alias counter
         *
         * @return int
         */
        public static function addAlias(): int
        {
            return self::$alias_counter++;
        }

        /**
         * Initialize B2DB and load related B2DB classes
         *
         * @param array<string, int|string|array<string, int|string>> $configuration [optional] Configuration to load
         * @param interfaces\Cache|callable $cache_object
         */
        public static function initialize(array $configuration = [], $cache_object = null): void
        {
            try {
                if (array_key_exists('dsn', $configuration) && $configuration['dsn']) {
                    self::setDSN($configuration['dsn']);
                }
                if (array_key_exists('driver', $configuration) && $configuration['driver']) {
                    self::setDriver($configuration['driver']);
                }
                if (array_key_exists('hostname', $configuration) && $configuration['hostname']) {
                    self::setHostname($configuration['hostname']);
                }
                if (array_key_exists('port', $configuration) && $configuration['port']) {
                    self::setPort($configuration['port']);
                }
                if (array_key_exists('username', $configuration) && $configuration['username']) {
                    self::setUsername($configuration['username']);
                }
                if (array_key_exists('password', $configuration) && $configuration['password']) {
                    self::setPassword($configuration['password']);
                }
                if (array_key_exists('database', $configuration) && $configuration['database']) {
                    self::setDatabaseName($configuration['database']);
                }
                if (array_key_exists('tableprefix', $configuration) && $configuration['tableprefix']) {
                    self::setTablePrefix($configuration['tableprefix']);
                }
                if (array_key_exists('debug', $configuration)) {
                    self::setDebugMode((bool) $configuration['debug']);
                }
                if (array_key_exists('caching', $configuration)) {
                    self::setCacheEntities((bool) $configuration['caching']);
                }

                if ($cache_object !== null) {
                    self::$cache_object = (is_callable($cache_object)) ? $cache_object() : $cache_object;
                }

                if (!self::$cache_object instanceof interfaces\Cache) {
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
         * @return bool
         */
        public static function isInitialized(): bool
        {
            return isset(self::$driver, self::$username);
        }

        /**
         * Store connection parameters
         *
         * @param string $bootstrap_location Where to save the connection parameters
         */
        public static function saveConnectionParameters(string $bootstrap_location): void
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
        public static function getTable(string $table_name): Table
        {
            if (!isset(self::$tables[$table_name])) {
                self::loadNewTable(new $table_name());
            }
            if (!isset(self::$tables[$table_name])) {
                throw new Exception('Table ' . $table_name . ' is not loaded');
            }
            return self::$tables[$table_name];
        }

        /**
         * @param ?array<int, array<string, int|string>> $backtrace
         * @return array<string, int|string|array<int, string>>
         */
        protected static function getRelevantDebugBacktraceElement(array $backtrace = null): array
        {
            $trace = null;
            $backtrace = $backtrace ?? debug_backtrace();
            $reserved_names = ['Core.php', 'Saveable.php', 'Criteria.php', 'Criterion.php', 'Resultset.php', 'Row.php', 'Statement.php', 'Transaction.php', 'Criteria.php', 'Row.php', 'Statement.php', 'Transaction.php', 'Table.php'];

            foreach ($backtrace as $t) {
                if (isset($trace)) {
                    $trace['function'] = $t['function'] ?? 'unknown';
                    $trace['class'] = $t['class'] ?? 'unknown';
                    $trace['type'] = $t['type'] ?? 'unknown';
                    break;
                }
                if (!array_key_exists('file', $t) || in_array(basename($t['file']), $reserved_names)) {
                    continue;
                }
                $trace = $t;
            }

            return $trace ?? ['file' => 'unknown', 'line' => 'unknown', 'function' => 'unknown', 'class' => 'unknown', 'type' => 'unknown', 'args' => []];
        }

        /**
         * Register a new object population call (debug only)
         *
         * @param int $num_classes
         * @param array<string> $class_names
         * @param int $previous_time
         */
        public static function objectPopulationHit(int $num_classes, array $class_names, int $previous_time): void
        {
            if ($num_classes === 0 || !self::isDebugMode()) {
                return;
            }

            $time = self::getDebugTime() - $previous_time;
            $trace = self::getRelevantDebugBacktraceElement();

            self::$object_population_hits[] = [
                'classnames' => $class_names,
                'num_classes' => $num_classes,
                'time' => $time,
                'filename' => $trace['file'],
                'line' => $trace['line'],
                'function' => $trace['function'],
                'class' => $trace['class'] ?? 'unknown',
                'type' => $trace['type'] ?? 'unknown',
                'arguments' => $trace['args']
            ];
            self::$object_population_counts += $num_classes;
            self::$object_population_timing += $time;
        }

        /**
         * Register a new SQL call (debug only)
         *
         * @param Statement $statement
         * @param int $previous_time
         */
        public static function sqlStatementHit(Statement $statement, int $previous_time): void
        {
            $sql = $statement->printSQL();
            $values = ($statement->getQuery() instanceof QueryInterface) ? $statement->getQuery()->getValues() : [];
            self::sqlHit($sql, $values, $previous_time);
        }

        /**
         * Register a new SQL call (debug only)
         *
         * @param string $sql
         * @param array<string|int|bool> $values
         * @param int $previous_time
         */
        public static function sqlHit(string $sql, array $values, int $previous_time): void
        {
            if (!self::isDebugMode()) {
                return;
            }

            $time = self::getDebugTime() - $previous_time;

            $backtrace = debug_backtrace();
            $trace = self::getRelevantDebugBacktraceElement($backtrace);
            $traces = [];
            foreach ($backtrace as $trace_item) {
                $traces[] = [
                    'file' => $trace_item['file'] ?? null,
                    'line' => $trace_item['line'] ?? null,
                    'function' => $trace_item['function'],
                    'class' => $trace_item['class'] ?? null,
                    'type' => $trace_item['type'] ?? null
                ];
            }

            // @phpstan-ignore-next-line
            self::$sql_hits[] = [
                'sql' => $sql,
                'values' => implode(', ', $values),
                'time' => $time,
                'filename' => $trace['file'],
                'line' => $trace['line'],
                'function' => $trace['function'],
                'class' => $trace['class'] ?? 'unknown',
                'type' => $trace['type'] ?? 'unknown',
                'arguments' => $trace['args'],
                'backtrace' => $traces
            ];
            self::$sql_timing += $time;
        }

        /**
         * Get number of SQL calls
         *
         * @return array<int, int|string|array<string, int|string|array<string, string|int>>>
         */
        public static function getSQLHits(): array
        {
            return self::$sql_hits;
        }

        public static function getSQLCount(): int
        {
            return count(self::$sql_hits) + 1;
        }

        public static function getSQLTiming(): int
        {
            return self::$sql_timing;
        }

        /**
         * Get number of object population calls
         *
         * @return array<int, array<string, int|string|array<string>>>
         */
        public static function getObjectPopulationHits(): array
        {
            return self::$object_population_hits;
        }

        public static function getObjectPopulationCount(): int
        {
            return self::$object_population_counts;
        }

        public static function getObjectPopulationTiming(): int
        {
            return self::$object_population_timing;
        }

        /**
         * Returns PDO object
         *
         * @return PDO
         */
        public static function getDBlink(): PDO
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
         * @return PDOStatement
         */
        public static function simpleQuery(string $sql): PDOStatement
        {
            $time = self::getDebugTime();
            try {
                $res = self::getDBLink()->query($sql);
                self::sqlHit($sql, [], $time);
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
        public static function setDSN(string $dsn): void
        {
            $dsn_details = parse_url($dsn);
            if (!is_array($dsn_details) || !array_key_exists('scheme', $dsn_details)) {
                throw new Exception('This does not look like a valid DSN - cannot read the database type');
            }

            self::setDriver($dsn_details['scheme']);
            $details = explode(';', $dsn_details['path']);
            foreach ($details as $detail) {
                $detail_info = explode('=', $detail);
                if (count($detail_info) !== 2) {
                    throw new Exception('This does not look like a valid DSN - cannot read the connection details');
                }
                switch ($detail_info[0]) {
                    case 'host':
                        self::setHostname($detail_info[1]);
                        break;
                    case 'port':
                        self::setPort((int) $detail_info[1]);
                        break;
                    case 'dbname':
                        self::setDatabaseName($detail_info[1]);
                        break;
                }
            }

            self::$dsn = $dsn;
        }

        /**
         * Generate the DSN when needed
         */
        protected static function generateDSN(): void
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
        public static function getDSN(): string
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
        public static function setHostname(string $hostname): void
        {
            self::$hostname = $hostname;
        }

        /**
         * Return the database host
         *
         * @return string
         */
        public static function getHostname(): string
        {
            return self::$hostname;
        }

        /**
         * Return the database port
         *
         * @return ?int
         */
        public static function getPort(): ?int
        {
            return self::$port ?? null;
        }

        /**
         * Set the database port
         *
         * @param int $port
         */
        public static function setPort(int $port): void
        {
            self::$port = $port;
        }

        /**
         * Set database username
         *
         * @param string $username
         */
        public static function setUsername(string $username): void
        {
            self::$username = $username;
        }

        /**
         * Get database username
         *
         * @return string
         */
        public static function getUsername(): string
        {
            return self::$username;
        }

        /**
         * Set the database table prefix
         *
         * @param string $prefix
         */
        public static function setTablePrefix(string $prefix): void
        {
            self::$table_prefix = $prefix;
        }

        /**
         * Get the database table prefix
         *
         * @return string
         */
        public static function getTablePrefix(): string
        {
            return self::$table_prefix;
        }

        /**
         * Set the database password
         *
         * @param string $password
         */
        public static function setPassword(string $password): void
        {
            self::$password = $password;
        }

        /**
         * Return the database password
         *
         * @return string
         */
        public static function getPassword(): string
        {
            return self::$password;
        }

        /**
         * Set the database name
         *
         * @param string $database_name
         */
        public static function setDatabaseName(string $database_name): void
        {
            self::$database_name = $database_name;
            self::$dsn = null;
        }

        /**
         * Get the database name
         *
         * @return string
         */
        public static function getDatabaseName(): string
        {
            return self::$database_name;
        }

        /**
         * Set the database type
         *
         * @param string $driver
         */
        public static function setDriver(string $driver): void
        {
            if (!self::isDriverSupported($driver)) {
                throw new Exception('The selected database is not supported: "' . $driver . '".');
            }
            self::$driver = $driver;
        }

        /**
         * Get the database type
         *
         * @return string
         */
        public static function getDriver(): string
        {
            return self::$driver;
        }

        public static function hasDriver(): bool
        {
            return self::getDriver() !== '';
        }

        /**
         * Try connecting to the database
         */
        public static function doConnect(): void
        {
            try {
                $uname = self::getUsername();
                $pwd = self::getPassword();
                $dsn = self::getDSN();

                self::$db_connection = new PDO($dsn, $uname, $pwd);
                if (!self::$db_connection instanceof PDO) {
                    throw new Exception('Could not connect to the database, but not caught by PDO');
                }

                switch (self::getDriver())
                {
                    case self::DRIVER_MYSQL:
                        self::getDBLink()->exec('SET NAMES UTF8');
                        break;
                    case self::DRIVER_POSTGRES:
                        self::getDBlink()->exec('SET client_encoding TO UTF8');
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
         * @return bool
         */
        public static function getCacheEntities(): bool
        {
            return self::$cache_entities;
        }

        /**
         * Set entity caching on/off
         *
         * @param bool $caching
         */
        public static function setCacheEntities(bool $caching): void
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
        public static function setCacheEntitiesStrategy(string $strategy): void
        {
            self::$cache_entities_strategy = $strategy;
        }

        /**
         * Retrieve entity caching strategy
         *
         * @return string
         *
         * @see self::CACHE_TYPE_INTERNAL
         * @see self::CACHE_TYPE_MEMCACHED
         */
        public static function getCacheEntitiesStrategy(): string
        {
            return self::$cache_entities_strategy;
        }

        /**
         * Set the cache object
         *
         * @param interfaces\Cache $object
         */
        public static function setCacheEntitiesObject(interfaces\Cache $object): void
        {
            self::$cache_entities_object = $object;
        }

        /**
         * @return interfaces\Cache
         * @noinspection PhpMissingReturnTypeInspection
         * @noinspection ReturnTypeCanBeDeclaredInspection
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
        public static function createDatabase(string $db_name): void
        {
            self::getDBLink()->exec('CREATE DATABASE ' . $db_name);
        }

        /**
         * Close the database connection
         */
        public static function closeDBLink(): void
        {
            self::$db_connection = null;
        }

        public static function isConnected(): bool
        {
            return self::$db_connection instanceof PDO;
        }

        /**
         * Toggle the transaction state
         *
         * @param int $state
         */
        public static function setTransaction(int $state): void
        {
            self::$transaction_active = $state;
        }

        /**
         * Starts a new transaction
         */
        public static function startTransaction(): Transaction
        {
            return new Transaction();
        }

        public static function isTransactionActive(): bool
        {
            return self::$transaction_active === Transaction::DB_TRANSACTION_STARTED;
        }

        /**
         * Get available DB drivers
         *
         * @return array<string, string>
         */
        public static function getDrivers(): array
        {
            return [
                self::DRIVER_MYSQL => 'MySQL / MariaDB',
                self::DRIVER_POSTGRES => 'PostgreSQL',
                self::DRIVER_MS_SQL_SERVER => 'Microsoft SQL Server',
            ];
        }

        /**
         * Whether a specific DB driver is supported
         *
         * @param string $driver
         *
         * @return bool
         */
        public static function isDriverSupported(string $driver): bool
        {
            return array_key_exists($driver, self::getDrivers());
        }

        protected static function storeCachedTableClass(string $classname): void
        {
            $key = 'b2db_cache_' . str_replace(['/', '\\'], ['_', '_'], $classname);
            self::$cache_object->set($key, self::$cached_table_classes[$classname]);
        }

        protected static function cacheTableClass(string $classname): void
        {
            if (!class_exists($classname)) {
                throw new Exception("The class '$classname' does not exist");
            }

            self::$cached_table_classes[$classname] = ['entity' => null, 'name' => null, 'discriminator' => null];

            $reflection = new ReflectionClass($classname);
            $docblock = $reflection->getDocComment();
            $annotation_set = new AnnotationSet($docblock);
            if (!$table_annotation = $annotation_set->getAnnotation('Table')) {
                throw new Exception("The class '$classname' does not have a proper @Table annotation");
            }
            $table_name = $table_annotation->getProperty('name');

            if ($entity_annotation = $annotation_set->getAnnotation('Entity')) {
                self::$cached_table_classes[$classname]['entity'] = $entity_annotation->getProperty('class');
            }

            if ($entities_annotation = $annotation_set->getAnnotation('Entities')) {
                $subclass_annotation = $annotation_set->getAnnotation('SubClasses');
                if (!$subclass_annotation instanceof Annotation) {
                    throw new Exception("The class @Entities annotation in '$classname' is missing required complementary '@SubClasses' annotation");
                }

                $details = [
                    'identifier' => $entities_annotation->getProperty('identifier'),
                    'classes' => $subclass_annotation->getProperties()
                ];
                self::$cached_table_classes[$classname]['entities'] = $details;
            }

            if ($discriminator_annotation = $annotation_set->getAnnotation('Discriminator')) {
                $discriminators_annotation = $annotation_set->getAnnotation('Discriminators');
                if (!$discriminators_annotation instanceof Annotation) {
                    throw new Exception("The class @Discriminator annotation in '$classname' is missing required complementary '@Discriminators' annotation");
                }

                $details = [
                    'column' => "$table_name." . $discriminator_annotation->getProperty('column'),
                    'discriminators' => $discriminators_annotation->getProperties()
                ];
                self::$cached_table_classes[$classname]['discriminator'] = $details;
            }

            if (!$table_annotation->hasProperty('name')) {
                throw new Exception("The class @Table annotation in '$classname' is missing required 'name' property");
            }

            self::$cached_table_classes[$classname]['name'] = $table_name;

            if (!self::$debug_mode) {
                self::storeCachedTableClass($classname);
            }
        }

        protected static function storeCachedEntityClass(string $classname): void
        {
            $key = 'b2db_cache_' . str_replace(['/', '\\'], ['_', '_'], $classname);
            self::$cache_object->set($key, self::$cached_entity_classes[$classname]);
        }

        protected static function cacheEntityClass(string $classname, string $reflection_classname = null): void
        {
            $rc_name = $reflection_classname ?? $classname;
            $reflection = new ReflectionClass($rc_name);
            $annotation_set = new AnnotationSet($reflection->getDocComment());

            if ($reflection_classname === null) {
                self::$cached_entity_classes[$classname] = ['columns' => [], 'relations' => [], 'foreign_columns' => []];
                if (!$annotation = $annotation_set->getAnnotation('Table')) {
                    throw new Exception("The class '$classname' is missing a valid @Table annotation");
                } else {
                    $table_name = $annotation->getProperty('name');
                }
                if (!class_exists($table_name)) {
                    throw new Exception("The class table class '$table_name' for class '$classname' does not exist");
                }
                self::$cached_entity_classes[$classname]['table'] = $table_name;
                self::populateCachedTableClassFiles($table_name);
                if (($re = $reflection->getExtension()) && $classnames = $re->getClassNames()) {
                    foreach ($classnames as $extends_classname) {
                        self::cacheEntityClass($classname, $extends_classname);
                    }
                }
            }
            $cached_entity_class = (string) self::$cached_entity_classes[$classname]['table'];
            if (!array_key_exists('name', self::$cached_table_classes[$cached_entity_class])) {
                throw new Exception("The class @Table annotation in '" . self::$cached_entity_classes[$classname]['table'] . "' is missing required 'name' property");
            }
            $column_prefix = self::$cached_table_classes[$cached_entity_class]['name'] . '.';

            foreach ($reflection->getProperties() as $property) {
                $annotation_set = new AnnotationSet($property->getDocComment());
                if ($annotation_set->hasAnnotations()) {
                    $property_name = $property->getName();
                    if ($column_annotation = $annotation_set->getAnnotation('Column')) {
                        $column_name = $column_prefix . (($column_annotation->hasProperty('name')) ? $column_annotation->getProperty('name') : substr($property_name, 1));

                        $column = [
                            'property' => $property_name,
                            'name' => $column_name,
                            'type' => $column_annotation->getProperty('type'),
                            'not_null' => (bool) $column_annotation->getProperty('not_null', false)
                        ];

                        if ($column_annotation->hasProperty('default_value')) {
                            $column['default_value'] = $column_annotation->getProperty('default_value');
                        }
                        if ($column_annotation->hasProperty('length')) {
                            $column['length'] = (int) $column_annotation->getProperty('length');
                        }

                        switch ($column['type']) {
                            case 'serializable':
                                $column['type'] = 'serializable';
                                break;
                            case 'varchar':
                            case 'string':
                                $column['type'] = 'varchar';
                                break;
                            /** @noinspection PhpMissingBreakStatementInspection */
                            case 'float':
                                $column['precision'] = (int) $column_annotation->getProperty('precision', 2);
                            case 'integer':
                                $column['auto_inc'] = (bool) $column_annotation->getProperty('auto_increment', false);
                                $column['unsigned'] = (bool) $column_annotation->getProperty('unsigned', false);
                                if (!isset($column['length'])) {
                                    $column['length'] = 10;
                                }
                                if ($column['type'] !== 'float'&& !isset($column['default_value'])) {
                                    $column['default_value'] = 0;
                                }
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
                        self::$cached_entity_classes[$classname]['relations'][$property_name] = [
                            'collection' => $collection,
                            'property' => $property_name,
                            'foreign_column' => $annotation->getProperty('foreign_column'),
                            'manytomany' => (bool) $annotation->getProperty('manytomany'),
                            'joinclass' => $annotation->getProperty('joinclass'),
                            'class' => $annotation->getProperty('class'),
                            'column' => $annotation->getProperty('column'),
                            'orderby' => $annotation->getProperty('orderby')
                        ];

                        if (!$collection) {
                            if (!$column_annotation || !isset($column)) {
                                throw new Exception("The property '$property_name' in class '$classname' is missing an @Column annotation, or is improperly marked as not being a collection");
                            }
                            $column_name = $column_prefix . $annotation->getProperty('name', substr($property_name, 1));
                            $column['class'] = self::getCachedB2DBTableClass($value);
                            $column['key'] = $annotation->getProperty('key');
                            self::$cached_entity_classes[$classname]['foreign_columns'][$column_name] = $column;
                        }
                    }
                }
            }

            if (!self::$debug_mode) {
                self::storeCachedEntityClass($classname);
            }
        }

        protected static function populateCachedClassFiles(string $classname): void
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

        protected static function populateCachedTableClassFiles(string $classname): void
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
         * @param string $classname
         * @return array<string, string|array>
         */
        public static function getTableDetails(string $classname): array
        {
            /**
             * @var ?Table
             */
            $table = call_user_func([$classname, 'getTable']);

            if ($table instanceof Table) {
                self::populateCachedTableClassFiles($classname);
                return ['columns' => $table->getColumns(),
                    'foreign_columns' => $table->getForeignColumns(),
                    'id' => $table->getIdColumn(),
                    'discriminator' => self::$cached_table_classes[$classname]['discriminator'],
                    'name' => self::$cached_table_classes[$classname]['name']
                ];
            }

            return [];
        }

        /**
         * @param string $classname
         * @return ?array<string, array<string, int|string|bool>|string>
         */
        public static function getCachedTableDetails(string $classname): ?array
        {
            self::populateCachedClassFiles($classname);
            if (array_key_exists($classname, self::$cached_entity_classes) && array_key_exists('columns', self::$cached_entity_classes[$classname])) {
                if (!array_key_exists('id_column', self::$cached_entity_classes[$classname])) {
                    throw new Exception('Cannot find an id column for this table.');
                }
                return [
                    'columns' => self::$cached_entity_classes[$classname]['columns'],
                    'foreign_columns' => self::$cached_entity_classes[$classname]['foreign_columns'],
                    'id' => self::$cached_entity_classes[$classname]['id_column'],
                    'discriminator' => self::$cached_table_classes[self::$cached_entity_classes[$classname]['table']]['discriminator'],
                    'name' => self::$cached_table_classes[self::$cached_entity_classes[$classname]['table']]['name']
                ];
            }

            return null;
        }

        /**
         * @param string $classname
         * @param string $key
         * @param string|null $detail
         * @return int|array<string, string|int>|string|null
         */
        protected static function getCachedEntityDetail(string $classname, string $key, string $detail = null)
        {
            self::populateCachedClassFiles($classname);
            if (array_key_exists($classname, self::$cached_entity_classes)) {
                if (!array_key_exists($key, self::$cached_entity_classes[$classname])) {
                    if ($key === 'table') {
                        throw new Exception("The class '$classname' is missing a valid @Table annotation");
                    }
                } elseif ($detail === null) {
                    return self::$cached_entity_classes[$classname][$key];
                } elseif (isset(self::$cached_entity_classes[$classname][$key][$detail])) {
                    return self::$cached_entity_classes[$classname][$key][$detail];
                }
            }

            return null;
        }

        /**
         * @param string $classname
         * @param string $detail
         * @return array<string, string|int>|string|null
         */
        protected static function getCachedTableDetail(string $classname, string $detail)
        {
            self::populateCachedTableClassFiles($classname);
            if (array_key_exists($classname, self::$cached_table_classes)) {
                if (!array_key_exists($detail, self::$cached_table_classes[$classname])) {
                    if ($detail === 'entity') {
                        throw new Exception("The class '$classname' is missing a valid @Entity annotation");
                    }
                } else {
                    return self::$cached_table_classes[$classname][$detail];
                }
            }
            return null;
        }

        /**
         * @param string $classname
         * @param string $property
         * @return ?array<string, string|int>
         */
        public static function getCachedEntityRelationDetails(string $classname, string $property)
        {
            return self::getCachedEntityDetail($classname, 'relations', $property);
        }

        /**
         * @param string $classname
         * @param string $column
         * @return ?array<string, string|int>
         */
        public static function getCachedColumnDetails(string $classname, string $column): ?array
        {
            return self::getCachedEntityDetail($classname, 'columns', $column);
        }

        public static function getCachedColumnPropertyName(string $classname, string $column): ?string
        {
            $column_details = self::getCachedColumnDetails($classname, $column);
            return (is_array($column_details)) ? $column_details['property'] : null;
        }

        /**
         * @param string $classname
         * @return ?string
         */
        public static function getCachedB2DBTableClass(string $classname): ?string
        {
            return self::getCachedEntityDetail($classname, 'table');
        }

        /**
         * @param string $classname
         * @return int[]|string[]|null
         * @throws Exception
         */
        public static function getCachedTableEntityClasses(string $classname): ?array
        {
            return self::getCachedTableDetail($classname, 'entities');
        }

        /**
         * @param string $classname
         * @return ?string
         * @throws Exception
         */
        public static function getCachedTableEntityClass(string $classname): ?string
        {
            return self::getCachedTableDetail($classname, 'entity');
        }

    }
