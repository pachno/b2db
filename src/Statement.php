<?php

    namespace b2db;

    use b2db\interfaces\QueryInterface;

    /**
     * Statement class
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 2.0
     * @license http://www.opensource.org/licenses/mozilla1.1.php Mozilla Public License 1.1 (MPL 1.1)
     * @package b2db
     * @subpackage core
     */

    /**
     * Statement class
     *
     * @package b2db
     * @subpackage core
     */
    class Statement
    {

        /**
         * Current Query
         *
         * @var QueryInterface
         */
        protected $query;

        /**
         * PDO statement
         *
         * @var \PDOStatement
         */
        protected $statement;

        protected $values = [];

        protected $params = [];

        protected $insert_id;

        protected $custom_sql = '';

        /**
         * Returns a statement
         *
         * @param QueryInterface $query
         *
         * @return Statement
         */
        public static function getPreparedStatement(QueryInterface $query)
        {
            $statement = new Statement($query);

            return $statement;
        }

        /**
         * Statement constructor.
         *
         * @param QueryInterface $query
         * @throws \Exception
         */
        public function __construct($query)
        {
            $this->query = $query;
            $this->prepare();
        }

        /**
         * Performs a query, then returns a resultset
         *
         * @return Resultset
         */
        public function execute()
        {
            $values = ($this->getQuery() instanceof QueryInterface) ? $this->getQuery()->getValues() : array();
            if (Core::isDebugMode()) {
                if (Core::isDebugLoggingEnabled() && class_exists('\\caspar\\core\\Logging')) {
                    \caspar\core\Logging::log('executing PDO query (' . Core::getSQLCount() . ') - ' . (($this->getQuery() instanceof Criteria) ? $this->getQuery()->getAction() : 'unknown'), 'B2DB');
                }

                $previous_time = Core::getDebugTime();
            }

            $res = $this->statement->execute($values);

            if (!$res) {
                $error = $this->statement->errorInfo();
                if (Core::isDebugMode()) {
                    Core::sqlHit($this, $previous_time);
                }
                throw new Exception($error[2], $this->printSQL());
            }
            if (Core::isDebugLoggingEnabled() && class_exists('\\caspar\\core\\Logging')) {
                \caspar\core\Logging::log('done', 'B2DB');
            }

            if ($this->getQuery() instanceof Query && $this->getQuery()->isInsert()) {
                if (Core::getDriver() == Core::DRIVER_MYSQL) {
                    $this->insert_id = Core::getDBLink()->lastInsertId();
                } elseif (Core::getDriver() == Core::DRIVER_POSTGRES) {
                    $this->insert_id = Core::getDBLink()->lastInsertId(Core::getTablePrefix() . $this->getQuery()->getTable()->getB2DBName() . '_id_seq');
                    if (Core::isDebugLoggingEnabled() && class_exists('\\caspar\\core\\Logging')) {
                        \caspar\core\Logging::log('sequence: ' . Core::getTablePrefix() . $this->getQuery()->getTable()->getB2DBName() . '_id_seq', 'b2db');
                        \caspar\core\Logging::log('id is: ' . $this->insert_id, 'b2db');
                    }
                }
            }

            $return_value = new Resultset($this);

            if (Core::isDebugMode()) {
                Core::sqlHit($this, $previous_time);
            }

            if (!$this->getQuery() || $this->getQuery()->isSelect()) {
                $this->statement->closeCursor();
            }

            return $return_value;
        }

        /**
         * Returns the criteria object
         *
         * @return Query
         */
        public function getQuery()
        {
            return $this->query;
        }

        /**
         * Return the ID for the inserted record
         */
        public function getInsertID()
        {
            return $this->insert_id;
        }

        public function getColumnValuesForCurrentRow()
        {
            return $this->values;
        }

        /**
         * Return the number of affected rows
         */
        public function getNumRows()
        {
            return $this->statement->rowCount();
        }

        /**
         * Fetch the resultset
         */
        public function fetch()
        {
            try {
                if ($this->values = $this->statement->fetch(\PDO::FETCH_ASSOC)) {
                    return $this->values;
                } else {
                    return false;
                }
            } catch (\PDOException $e) {
                throw new Exception('An error occured while trying to fetch the result: "' . $e->getMessage() . '"');
            }
        }

        /**
         * Prepare the statement
         */
        protected function prepare()
        {
            if (!Core::getDBlink() instanceof \PDO) {
                throw new Exception('Connection not up, can\'t prepare the statement');
            }

            $this->statement = Core::getDBlink()->prepare($this->query->getSql());
        }

        public function printSQL()
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
