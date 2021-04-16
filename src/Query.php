<?php

    namespace b2db;

    use b2db\interfaces\CriterionProvider;
    use b2db\interfaces\QueryInterface;

    /**
     * Criteria class
     *
     * @package b2db
     * @subpackage core
     */
    class Query implements QueryInterface
    {

        const DB_COUNT = 'COUNT';
        const DB_MAX = 'MAX';
        const DB_SUM = 'SUM';
        const DB_CONCAT = 'CONCAT';
        const DB_LOWER = 'LOWER';
        const DB_DISTINCT = 'DISTINCT';
        const DB_COUNT_DISTINCT = 'COUNT(DISTINCT';

        const MODE_AND = 'AND';
        const MODE_OR = 'OR';
        const MODE_HAVING = 'HAVING';

        /**
         * @var Criteria[]
         */
        protected $criteria = [];

        /**
         * @var Join[]
         */
        protected $joins = [];

        /**
         * @var QueryColumnSort[]
         */
        protected $sort_orders = [];

        /**
         * @var QueryColumnSort[]
         */
        protected $sort_groups = [];

        protected $selections = [];

        protected $values = [];

        protected $is_distinct = false;

        protected $updates = [];

        protected $aliases = [];

        protected $return_selections = [];

        protected $index_by;

        /**
         * Parent table
         *
         * @var Table
         */
        protected $table;

        protected $limit;

        protected $offset;

        protected $is_custom_selection = false;

        protected $action;

        protected $sql;

        protected $mode;

        public static function quoteIdentifier($id)
        {
            $parts = [];
            foreach (explode('.', $id) as $part) {
                switch (Core::getDriver()) {
                    case Core::DRIVER_MYSQL:
                        $parts[] = "`$part`";
                        break;
                    default: # ANSI
                        $parts[] = "\"$part\"";
                        break;
                }
            }
            return implode('.', $parts);
        }

        /**
         * Constructor
         *
         * @param Table $table
         * @param bool $setup_join_tables [optional]
         */
        public function __construct(Table $table, $setup_join_tables = false)
        {
            $this->setTable($table, $setup_join_tables);
        }

        /**
         * Set the "from" table
         *
         * @param Table $table The table
         * @param bool $setup_join_tables [optional] Whether to automatically join other tables
         *
         * @return Query
         */
        public function setTable(Table $table, $setup_join_tables = false): self
        {
            $this->table = $table;

            if ($setup_join_tables) {
                $this->setupJoinTables();
            }

            return $this;
        }

        public function getAction()
        {
            return $this->action;
        }

        public function isCount()
        {
            return (bool) ($this->action == QueryInterface::ACTION_COUNT);
        }

        public function isSelect()
        {
            return (bool) ($this->action == QueryInterface::ACTION_SELECT);
        }

        public function isDelete()
        {
            return (bool) ($this->action == QueryInterface::ACTION_DELETE);
        }

        public function isInsert()
        {
            return (bool) ($this->action == QueryInterface::ACTION_INSERT);
        }

        public function isUpdate()
        {
            return (bool) ($this->action == QueryInterface::ACTION_UPDATE);
        }

        /**
         * Get added values
         *
         * @return mixed[]
         */
        public function getValues()
        {
            $values = $this->values;

            foreach ($this->criteria as $criteria) {
                foreach ($criteria->getValues() as $value) {
                    if (is_array($value)) {
                        foreach ($value as $single_value) {
                            $values[] = $single_value;
                        }
                    } else {
                        $values[] = $value;
                    }
                }
            }

            return $values;
        }

        /**
         * Get the quoted / converted value depending on database type
         *
         * @param mixed $value
         * @return int|mixed|string
         */
        public function getDatabaseValue($value)
        {
            if (is_bool($value)) {
                if (Core::getDriver() == Core::DRIVER_MYSQL) {
                    return (int) $value;
                } elseif (Core::getDriver() == Core::DRIVER_POSTGRES) {
                    return ($value) ? 'true' : 'false';
                }
            }
            return $value;
        }

        /**
         * Add a value to the value container
         *
         * @param mixed $value
         */
        public function addValue($value)
        {
            if (is_array($value)) {
                foreach ($value as $single_value) {
                    $this->addValue($single_value);
                }
            } else {
                if ($value !== null) {
                    $this->values[] = $this->getDatabaseValue($value);
                }
            }
        }

        /**
         * Add a column to select
         *
         * @param string $column The column
         * @param string $alias [optional] An alias for the column
         * @param string $special [optional] Whether to use a special method on the column
         * @param string $variable [optional] An optional variable to assign it to
         * @param string $additional [optional] Additional parameter
         *
         * @return Query
         */
        public function addSelectionColumn($column, $alias = '', $special = '', $variable = '', $additional = ''): self
        {
            if (!$this->table instanceof Table) {
                throw new \Exception('You must set the from-table before adding selection columns');
            }

            $column = $this->getSelectionColumn($column);
            $alias = ($alias === '') ? str_replace('.', '_', $column) : $alias;

            $this->is_custom_selection = true;
            $this->addSelectionColumnRaw($column, $alias, $special, $variable, $additional);

            return $this;
        }

        public function addSelectionColumnRaw($column, $alias = '', $special = '', $variable = '', $additional = '')
        {
            $this->selections[Core::getTablePrefix() . $column] = new QueryColumnSelection($this, $column, $alias, $special, $variable, $additional);
        }

        public function isCustomSelection()
        {
            return $this->is_custom_selection;
        }

        /**
         * Add a field to update
         *
         * @param string $column The column name
         * @param mixed $value The value to update
         *
         * @return Query
         */
        public function update($column, $value): self
        {
            if (is_object($value)) {
                throw new Exception("Invalid value, can't be an object.");
            }
            $this->updates[] = compact('column', 'value');

            return $this;
        }

        /**
         * Adds a "where" part to the criteria
         *
         * @param mixed  $column
         * @param mixed  $value
         * @param string $operator
         * @param string $variable
         * @param mixed  $additional
         * @param string $special
         *
         * @return Criteria
         */
        public function where($column, $value = '', $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): Criteria
        {
            if (!$column instanceof Criteria) {
                $criteria = new Criteria();
                $criteria->where($column, $value, $operator, $variable, $additional, $special);
                $column = $criteria;
            }

            $column->setQuery($this);
            $this->criteria[] = $column;

            return $column;
        }

        /**
         * Add a "where" part to the criteria
         *
         * @param $column
         * @param string $value
         * @param string $operator
         * @param string $variable
         * @param string $additional
         * @param string $special
         *
         * @return Criteria
         */
        public function and($column, $value = '', $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): Criteria
        {
            if ($this->mode == Query::MODE_OR) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Query. Use sub-criteria instead');
            }

            $criteria = $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = Query::MODE_AND;

            return $criteria;
        }

        /**
         * Add a "where" part to the criteria
         *
         * @param $column
         * @param string $value
         * @param string $operator
         * @param string $variable
         * @param string $additional
         * @param string $special
         *
         * @return Criteria
         */
        public function or($column, $value = '', $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): Criteria
        {
            if ($this->mode == Query::MODE_AND) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Query. Use sub-criteria instead');
            }

            if ($this->mode == Query::MODE_HAVING) {
                throw new Exception('Cannot combine more than one HAVING clause in the same Query. Use multiple sub-criteria instead');
            }

            $criteria = $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = Query::MODE_OR;

            return $criteria;
        }

        /**
         * Join one table on another
         *
         * @param Table $table The table to join
         * @param string $joined_table_column The left matching column
         * @param string $column The right matching column
         * @param Criteria[] $criteria An array of criteria (ex: array(array(DB_FLD_ISSUE_ID, 1), array(DB_FLD_ISSUE_STATE, 1));
         * @param string $join_type Type of join
         * @param Table $on_table If different than the main table, specify the left side of the join here
         *
         * @return Table
         */
        public function join(Table $table, $joined_table_column, $column, $criteria = [], $join_type = Join::LEFT, $on_table = null)
        {
            if (!$this->table instanceof Table) {
                throw new Exception('Cannot use ' . $this->table . ' as a table. You need to call setTable() before trying to join a new table');
            }

            if (!$table instanceof Table) {
                throw new Exception('Cannot join table ' . $table . ' since it is not a table');
            }

            foreach ($this->joins as $join) {
                if ($join->getTable()->getB2DBAlias() === $table->getB2DBAlias()) {
                    $table = clone $table;
                    break;
                }
            }

            $left_column = $table->getB2DBAlias() . '.' . Table::getColumnName($joined_table_column);
            $join_on_table = $on_table ?? $this->table;
            $right_column = $join_on_table->getB2DBAlias() . '.' . Table::getColumnName($column);

            $this->joins[$table->getB2DBAlias()] = new Join($table, $left_column, $right_column, $this->getRealColumnName($column), $criteria, $join_type);

            return $table;
        }

        public function getRealColumnName($column)
        {
            list ($table_alias, $column_name) = explode('.', $column);

            if ($table_alias == $this->table->getB2DBAlias() || $table_alias == $this->table->getB2DBName()) {
                $real_table_name = $this->table->getB2DBName();
            } else {
                foreach ($this->getJoins() as $alias => $join) {
                    if ($table_alias == $alias || $table_alias == $join->getTable()->getB2DBName()) {
                        $real_table_name = $join->getTable()->getB2DBName();
                        break;
                    }
                }
            }

            if (!isset($real_table_name)) {
                throw new Exception("Could not find the real column name for column {$column}. Check that all relevant tables are joined.");
            }

            return "{$real_table_name}.{$column_name}";
        }

        public function indexBy($column)
        {
            $this->index_by = $column;
        }

        public function getIndexBy()
        {
            return $this->index_by;
        }

        /**
         * Retrieve a list of foreign tables
         *
         * @return Join[]
         */
        public function getJoins()
        {
            return $this->joins;
        }

        /**
         * Returns the table the criteria applies to
         *
         * @return Table
         */
        public function getTable()
        {
            return $this->table;
        }

        /**
         * Get the column name part of a column
         *
         * @param string $column
         *
         * @return string
         */
        public function getColumnName($column)
        {
            if (mb_stripos($column, '.') > 0) {
                return mb_substr($column, mb_stripos($column, '.') + 1);
            } else {
                return $column;
            }
        }

        /**
         * Get all select columns
         *
         * @return QueryColumnSelection[]
         */
        public function getSelectionColumns()
        {
            return $this->selections;
        }

        /**
         * Return a select column
         *
         * @param string $column
         *
         * @return string
         */
        public function getSelectionColumn($column)
        {
            if (isset($this->selections[$column])) {
                return $this->selections[$column]->getColumn();
            }

            foreach ($this->selections as $selection) {
                if ($selection->getAlias() == $column) {
                    return $column;
                }
            }

            list($table_name, $column_name) = (strpos($column, '.') !== false) ? explode('.', $column) : [$this->table->getB2DBName(), $column];

            if ($this->table->getB2DBAlias() == $table_name || $this->table->getB2DBName() == $table_name) {
                return $this->table->getB2DBAlias() . '.' . $column_name;
            } elseif (isset($this->joins[$table_name])) {
                return $this->joins[$table_name]->getTable()->getB2DBAlias() . '.' . $column_name;
            }

            foreach ($this->joins as $join) {
                if ($join->getTable()->getB2DBName() == $table_name) {
                    return $join->getTable()->getB2DBAlias() . '.' . $column_name;
                }
            }

            throw new Exception("Couldn't find table name '{$table_name}' for column '{$column_name}', column was '{$column}'. If this is a column from a foreign table, make sure the foreign table is joined.");
        }

        /**
         * Get the selection alias for a specified column
         *
         * @param string $column
         *
         * @return string
         */
        public function getSelectionAlias($column)
        {
            if (!is_numeric($column) && !is_string($column)) {
                if (is_array($column) && array_key_exists('column', $column)) {
                    $column = $column['column'];
                } else {
                    throw new Exception('Invalid column!');
                }
            }
            if (!isset($this->aliases[$column])) {
                $this->aliases[$column] = str_replace('.', '_', $column);
            }

            return $this->aliases[$column];
        }

        /**
         * Add an order by clause
         *
         * @param string|array $column The column to order by
         * @param string $sort [optional] The sort order
         *
         * @return Query
         */
        public function addOrderBy($column, $sort = null)
        {
            if (is_array($column)) {
                foreach ($column as $single_column) {
                    $this->addOrderBy($single_column[0], $single_column[1]);
                }
            } else {
                $this->sort_orders[] = new QueryColumnSort($column, $sort);
            }

            return $this;
        }

        /**
         * @return QueryColumnSort[]
         */
        public function getSortOrders()
        {
            return $this->sort_orders;
        }

        public function hasSortOrders()
        {
            return (bool) count($this->sort_orders);
        }

        /**
         * Limit the query
         *
         * @param integer $limit The number to limit
         *
         * @return Query
         */
        public function setLimit($limit)
        {
            $this->limit = (int) $limit;

            return $this;
        }

        public function getLimit()
        {
            return $this->limit;
        }

        public function hasLimit()
        {
            return (bool) ($this->limit !== null);
        }

        /**
         * Add a group by clause
         *
         * @param string|array $column The column to group by
         * @param string $sort [optional] The sort order
         *
         * @return Query
         */
        public function addGroupBy($column, $sort = null)
        {
            if (is_array($column)) {
                foreach ($column as $single_column) {
                    $this->addGroupBy($single_column[0], $single_column[1]);
                }
            } else {
                $this->sort_groups[] = new QueryColumnSort($column, $sort);
            }

            return $this;
        }

        /**
         * @return QueryColumnSort[]
         */
        public function getSortGroups()
        {
            return $this->sort_groups;
        }

        /**
         * @return bool
         */
        public function hasSortGroups()
        {
            return (bool) count($this->sort_groups);
        }

        /**
         * Offset the query
         *
         * @param integer $offset The number to offset by
         *
         * @return Query
         */
        public function setOffset($offset)
        {
            $this->offset = (int) $offset;

            return $this;
        }

        public function getOffset()
        {
            return $this->offset;
        }

        public function hasOffset()
        {
            return (bool) $this->offset;
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @param bool $all
         * @return string
         */
        public function generateSelectSQL($all = false)
        {
            if ($this->sql === null) {
                $this->detectDistinct();
                $this->action = QueryInterface::ACTION_SELECT;
                $sql_generator = new SqlGenerator($this);

                $this->sql = $sql_generator->getSelectSQL($all);
            }

            return $this->sql;
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @param Update $update
         * @return string
         * @throws Exception
         */
        public function generateUpdateSQL(Update $update)
        {
            if ($this->sql === null) {
                $this->detectDistinct();
                $this->action = QueryInterface::ACTION_UPDATE;
                $sql_generator = new SqlGenerator($this);

                $this->sql = $sql_generator->getUpdateSQL($update);
                $this->values = $sql_generator->getValues();
            }

            return $this->sql;
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @param Insertion $insertion
         * @return string
         */
        public function generateInsertSQL(Insertion $insertion)
        {
            if ($this->sql === null) {
                $this->detectDistinct();
                $this->action = QueryInterface::ACTION_INSERT;
                $sql_generator = new SqlGenerator($this);

                $this->sql = $sql_generator->getInsertSQL($insertion);
                $this->values = $sql_generator->getValues();
            }

            return $this->sql;
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @return string
         */
        public function generateDeleteSQL()
        {
            if ($this->sql === null) {
                $this->detectDistinct();
                $this->action = QueryInterface::ACTION_DELETE;
                $sql_generator = new SqlGenerator($this);

                $this->sql = $sql_generator->getDeleteSQL();
            }

            return $this->sql;
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @return string
         */
        public function generateCountSQL()
        {
            if ($this->sql === null) {
                $this->detectDistinct();
                $this->action = QueryInterface::ACTION_COUNT;
                $sql_generator = new SqlGenerator($this);

                $this->sql = $sql_generator->getCountSQL();
            }

            return $this->sql;
        }

        /**
         * Add all available foreign tables
         *
         * @param array|string|bool $join [optional]
         */
        public function setupJoinTables($join = 'all')
        {
            if (is_array($join)) {
                foreach ($join as $join_column) {
                    $foreign_table = $this->table->getForeignTableByLocalColumn($join_column);
                    $this->join($foreign_table->getTable(), $foreign_table->getKeyColumnName(), $this->table->getB2DBAlias() . '.' . $foreign_table->getColumnName());
                }
            } elseif (!is_array($join) && $join == 'all') {
                foreach ($this->table->getForeignTables() as $foreign_table) {
                    $this->join($foreign_table->getTable(), $foreign_table->getKeyColumnName(), $this->table->getB2DBAlias() . '.' . $foreign_table->getColumnName());
                }
            }
        }

        /**
         * (Re-)generate selection columns based on criteria selection columns
         */
        protected function detectDistinct()
        {
            foreach ($this->criteria as $criteria) {
                if ($criteria->isDistinct()) {
                    $this->is_distinct = true;
                }
            }
        }

        /**
         * @return Criteria[]
         */
        public function getCriteria()
        {
            return $this->criteria;
        }

        public function hasCriteria()
        {
            return (bool) count($this->criteria);
        }

        /**
         * Set the query to distinct mode
         */
        public function setIsDistinct()
        {
            $this->is_distinct = true;
        }

        public function isDistinct()
        {
            return $this->is_distinct;
        }

        public function getMode()
        {
            if (!$this->mode) {
                $this->mode = self::MODE_AND;
            }
            return $this->mode;
        }

        public function getSql()
        {
            return $this->sql;
        }

    }
