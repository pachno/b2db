<?php

    namespace b2db;

    use b2db\interfaces\QueryInterface;
    use PDO;
    use PDOException;
    use PDOStatement;

    /**
     * Statement class
     *
     * @package b2db
     */
    class Statement
    {

        /**
         * Current Query
         *
         * @var QueryInterface
         */
        protected QueryInterface $query;

        /**
         * PDO statement
         *
         * @var PDOStatement
         */
        protected PDOStatement $statement;

        /**
         * @var array<int, string|int|bool>|bool
         */
        protected $values = [];

        /**
         * @var array<int, string|int|bool>
         */
        protected array $params = [];

        protected int $insert_id;

        protected string $custom_sql = '';

        /**
         * Returns a statement
         *
         * @param QueryInterface $query
         *
         * @return Statement
         */
        public static function getPreparedStatement(QueryInterface $query): Statement
        {
            return new Statement($query);
        }

        /**
         * Statement constructor.
         *
         * @param QueryInterface $query
         */
        public function __construct(QueryInterface $query)
        {
            $this->query = $query;
            $this->prepare();
        }

        /**
         * Performs a query, then returns a resultset
         *
         * @return Resultset
         */
        public function execute(): Resultset
        {
            $values = ($this->getQuery() instanceof QueryInterface) ? $this->getQuery()->getValues() : [];
            if (Core::isDebugMode()) {
                if (Core::isDebugLoggingEnabled() && class_exists('\\caspar\\core\\Logging')) {
                    // TODO: Add event support instead of manual tie-in
                    /** @noinspection PhpUndefinedClassInspection */
                    /** @noinspection PhpUndefinedNamespaceInspection */
                    /** @noinspection PhpFullyQualifiedNameUsageInspection */
                    \caspar\core\Logging::log('executing PDO query (' . Core::getSQLCount() . ') - ' . (($this->getQuery() instanceof Criteria) ? $this->getQuery()->getAction() : 'unknown'), 'B2DB');
                }

                $previous_time = Core::getDebugTime();
            }

            $res = $this->statement->execute($values);

            if (!$res) {
                $error = $this->statement->errorInfo();
                /** @noinspection NotOptimalIfConditionsInspection */
                if (Core::isDebugMode() && isset($previous_time)) {
                    Core::sqlStatementHit($this, $previous_time);
                }
                throw new Exception($error[2], $this->printSQL());
            }
            if (Core::isDebugLoggingEnabled() && class_exists('\\caspar\\core\\Logging')) {
                // TODO: Add event support instead of manual tie-in
                /** @noinspection PhpUndefinedClassInspection */
                /** @noinspection PhpUndefinedNamespaceInspection */
                /** @noinspection PhpFullyQualifiedNameUsageInspection */
                \caspar\core\Logging::log('done', 'B2DB');
            }

            if ($this->getQuery() instanceof Query && $this->getQuery()->isInsert()) {
                if (Core::getDriver() === Core::DRIVER_MYSQL) {
                    $this->insert_id = (int) Core::getDBLink()->lastInsertId();
                } elseif (Core::getDriver() === Core::DRIVER_POSTGRES) {
                    $this->insert_id = (int) Core::getDBLink()->lastInsertId(Core::getTablePrefix() . $this->getQuery()->getTable()->getB2dbName() . '_id_seq');
                    if (Core::isDebugLoggingEnabled() && class_exists('\\caspar\\core\\Logging')) {
                        // TODO: Add event support instead of manual tie-in

                        /** @noinspection PhpUndefinedClassInspection */
                        /** @noinspection PhpUndefinedNamespaceInspection */
                        /** @noinspection PhpFullyQualifiedNameUsageInspection */
                        \caspar\core\Logging::log('sequence: ' . Core::getTablePrefix() . $this->getQuery()->getTable()->getB2dbName() . '_id_seq', 'b2db');
                        /** @noinspection PhpUndefinedClassInspection */
                        /** @noinspection PhpUndefinedNamespaceInspection */
                        /** @noinspection PhpFullyQualifiedNameUsageInspection */
                        \caspar\core\Logging::log('id is: ' . $this->insert_id, 'b2db');
                    }
                }
            }

            $return_value = new Resultset($this);

            /** @noinspection NotOptimalIfConditionsInspection */
            if (Core::isDebugMode() && isset($previous_time)) {
                Core::sqlStatementHit($this, $previous_time);
            }

            if (!$this->getQuery() || $this->getQuery()->isSelect()) {
                $this->statement->closeCursor();
            }

            return $return_value;
        }

        /**
         * Returns the criteria object
         *
         * @return QueryInterface
         */
        public function getQuery(): ?interfaces\QueryInterface
        {
            return $this->query;
        }

        /**
         * Return the ID for the inserted record
         */
        public function getInsertID(): int
        {
            return $this->insert_id;
        }

        /**
         * Return the number of affected rows
         */
        public function getNumRows(): int
        {
            return $this->statement->rowCount();
        }

        /**
         * Fetch the resultset
         *
         * @return array<int|string, bool|int|string|array>|bool
         */
        public function fetch()
        {
            try {
                if ($this->values = $this->statement->fetch(PDO::FETCH_ASSOC)) {
                    return $this->values;
                }

                return false;
            } catch (PDOException $e) {
                throw new Exception('An error occurred while trying to fetch the result: "' . $e->getMessage() . '"');
            }
        }

        /**
         * Prepare the statement
         */
        protected function prepare(): void
        {
            if (!Core::getDBlink() instanceof PDO) {
                throw new Exception('Connection not up, can\'t prepare the statement');
            }

            $this->statement = Core::getDBlink()->prepare($this->query->getSql());
        }

        public function printSQL(): string
        {
            $str = '';
            $str .= $this->query->getSql();
            foreach ($this->query->getValues() as $val) {
                if (is_null($val)) {
                    $val = 'null';
                } elseif (!is_int($val)) {
                    $val = '\'' . $val . '\'';
                }
                $str = substr_replace($str, $val, mb_strpos($str, '?'), 1);
            }

            return $str;
        }

    }
