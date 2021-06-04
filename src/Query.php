<?php

    /** @noinspection PhpUnused */

    namespace b2db;

    use b2db\interfaces\QueryInterface;

    /**
     * Criteria class
     *
     * @package b2db
     */
    class Query implements QueryInterface
    {

        public const DB_COUNT = 'COUNT';
        public const DB_MAX = 'MAX';
        public const DB_SUM = 'SUM';
        public const DB_CONCAT = 'CONCAT';
        public const DB_LOWER = 'LOWER';
        public const DB_DISTINCT = 'DISTINCT';
        public const DB_COUNT_DISTINCT = 'COUNT(DISTINCT';

        public const MODE_AND = 'AND';
        public const MODE_OR = 'OR';
        public const MODE_HAVING = 'HAVING';

        /**
         * @var Criteria[]
         */
        protected array $criteria = [];

        /**
         * @var Join[]
         */
        protected array $joins = [];

        /**
         * @var QueryColumnSort[]
         */
        protected array $sort_orders = [];

        /**
         * @var QueryColumnSort[]
         */
        protected array $sort_groups = [];

        /**
         * @var QueryColumnSelection[]
         */
        protected array $selections = [];

        /**
         * @var array<int, mixed>
         */
        protected array $values = [];

        /**
         * @var array<int|string, mixed>
         */
        protected array $updates = [];

        /**
         * @var array<string, string>
         */
        protected array $aliases = [];

        protected bool $is_distinct = false;

        protected string $index_by;

        /**
         * The table this query originates from
         *
         * @var Table
         */
        protected Table $table;

        protected int $limit;

        protected int $offset;

        protected bool $is_custom_selection = false;

        protected string $action;

        protected string $sql;

        protected string $mode;

        /**
         * Constructor
         *
         * @param Table $table
         * @param bool $setup_join_tables [optional]
         */
        public function __construct(Table $table, bool $setup_join_tables = false)
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
        public function setTable(Table $table, bool $setup_join_tables = false): self
        {
            $this->table = $table;

            if ($setup_join_tables) {
                $this->setupJoinTables();
            }

            return $this;
        }

        public function getAction(): string
        {
            return $this->action;
        }

        public function isCount(): bool
        {
            return ($this->action === QueryInterface::ACTION_COUNT);
        }

        public function isSelect(): bool
        {
            return ($this->action === QueryInterface::ACTION_SELECT);
        }

        public function isDelete(): bool
        {
            return ($this->action === QueryInterface::ACTION_DELETE);
        }

        public function isInsert(): bool
        {
            return ($this->action === QueryInterface::ACTION_INSERT);
        }

        public function isUpdate(): bool
        {
            return ($this->action === QueryInterface::ACTION_UPDATE);
        }

        /**
         * Get added values
         *
         * @return array<int|string, mixed>
         */
        public function getValues(): array
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
                if (Core::getDriver() === Core::DRIVER_MYSQL) {
                    return (int) $value;
                }

                if (Core::getDriver() === Core::DRIVER_POSTGRES) {
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
        public function addValue($value): void
        {
            if (is_array($value)) {
                foreach ($value as $single_value) {
                    $this->addValue($single_value);
                }
            } else if ($value !== null) {
                $this->values[] = $this->getDatabaseValue($value);
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
        public function addSelectionColumn(string $column, string $alias = '', string $special = '', string $variable = '', string $additional = ''): self
        {
            if (!$this->table instanceof Table) {
                throw new \Exception('You must set the from-table before adding selection columns');
            }

            $column = $this->getSelectionColumn($column);
            $select_alias = ($alias === '') ? str_replace('.', '_', $column) : $alias;

            $this->is_custom_selection = true;
            $this->addSelectionColumnRaw($column, $select_alias, $special, $variable, $additional);

            return $this;
        }

        public function addSelectionColumnRaw(string $column, string $alias = '', string $special = '', string $variable = '', string $additional = ''): void
        {
            $this->selections[Core::getTablePrefix() . $column] = new QueryColumnSelection($this, $column, $alias, $special, $variable, $additional);
        }

        public function isCustomSelection(): bool
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
        public function update(string $column, $value): self
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
         * @param string|Criteria $column
         * @param mixed  $value
         * @param string $operator
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function where($column, $value = '', string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): Criteria
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
         * Add a "and" part to the criteria
         *
         * @param string|Criteria $column
         * @param mixed  $value
         * @param string $operator
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function and($column, $value = '', string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): Criteria
        {
            if (isset($this->mode) && $this->mode === self::MODE_OR) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Query. Use sub-criteria instead');
            }

            $criteria = $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = self::MODE_AND;

            return $criteria;
        }

        /**
         * Add a "or" part to the criteria
         *
         * @param string|Criteria $column
         * @param mixed  $value
         * @param string $operator
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function or($column, $value = '', string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): Criteria
        {
            if (isset($this->mode) && $this->mode === self::MODE_AND) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Query. Use sub-criteria instead');
            }

            if (isset($this->mode) && $this->mode === self::MODE_HAVING) {
                throw new Exception('Cannot combine more than one HAVING clause in the same Query. Use multiple sub-criteria instead');
            }

            $criteria = $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = self::MODE_OR;

            return $criteria;
        }

        /**
         * Join one table on another
         *
         * @param Table $table The table to join
         * @param string $joined_table_column The left matching column
         * @param string $column The right matching column
         * @param array<array> $criteria An array of criteria (ex: array(array(DB_FLD_ISSUE_ID, 1), array(DB_FLD_ISSUE_STATE, 1));
         * @param string $join_type Type of join
         * @param ?Table $on_table If different than the main table, specify the left side of the join here
         *
         * @return Table
         */
        public function join(Table $table, string $joined_table_column, string $column, array $criteria = [], string $join_type = Join::LEFT, Table $on_table = null): Table
        {
            if (!$this->table instanceof Table) {
                throw new Exception('Cannot use ' . $this->table . ' as a table. You need to call setTable() before trying to join a new table');
            }

            if (!$table instanceof Table) {
                throw new Exception('Cannot join table ' . $table . ' since it is not a table');
            }

            foreach ($this->joins as $join) {
                if ($join->getTable()->getB2dbAlias() === $table->getB2dbAlias()) {
                    $table = clone $table;
                    break;
                }
            }

            $left_column = $table->getB2dbAlias() . '.' . Table::getColumnName($joined_table_column);
            $join_on_table = $on_table ?? $this->table;
            $right_column = $join_on_table->getB2dbAlias() . '.' . Table::getColumnName($column);

            $this->joins[$table->getB2dbAlias()] = new Join($table, $left_column, $right_column, $this->getRealColumnName($column), $criteria, $join_type);

            return $table;
        }

        public function getRealColumnName(string $column): string
        {
            [$table_alias, $column_name] = explode('.', $column);

            if ($table_alias === $this->table->getB2dbAlias() || $table_alias === $this->table->getB2dbName()) {
                $real_table_name = $this->table->getB2dbName();
            } else {
                foreach ($this->getJoins() as $alias => $join) {
                    if ($table_alias === $alias || $table_alias === $join->getTable()->getB2dbName()) {
                        $real_table_name = $join->getTable()->getB2dbName();
                        break;
                    }
                }
            }

            if (!isset($real_table_name)) {
                throw new Exception("Could not find the real column name for column $column. Check that all relevant tables are joined.");
            }

            return "$real_table_name.$column_name";
        }

        public function indexBy(string $column): void
        {
            $this->index_by = $column;
        }

        public function getIndexBy(): string
        {
            return $this->index_by;
        }

        public function hasIndexBy(): bool
        {
            return (isset($this->index_by) && $this->index_by !== '');
        }

        /**
         * Retrieve a list of foreign tables
         *
         * @return Join[]
         */
        public function getJoins(): array
        {
            return $this->joins;
        }

        /**
         * Returns the table the criteria applies to
         *
         * @return Table
         */
        public function getTable(): Table
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
        public function getColumnName(string $column): string
        {
            if (mb_stripos($column, '.') > 0) {
                return mb_substr($column, mb_stripos($column, '.') + 1);
            }

            return $column;
        }

        /**
         * Get all select columns
         *
         * @return QueryColumnSelection[]
         */
        public function getSelectionColumns(): array
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
        public function getSelectionColumn(string $column): string
        {
            if (isset($this->selections[$column])) {
                return $this->selections[$column]->getColumn();
            }

            foreach ($this->selections as $selection) {
                if ($selection->getAlias() === $column) {
                    return $column;
                }
            }

            [$table_name, $column_name] = (strpos($column, '.') !== false) ? explode('.', $column) : [$this->table->getB2dbName(), $column];

            if ($this->table->getB2dbAlias() === $table_name || $this->table->getB2dbName() === $table_name) {
                return $this->table->getB2dbAlias() . '.' . $column_name;
            }

            if (isset($this->joins[$table_name])) {
                return $this->joins[$table_name]->getTable()->getB2dbAlias() . '.' . $column_name;
            }

            foreach ($this->joins as $join) {
                if ($join->getTable()->getB2dbName() === $table_name) {
                    return $join->getTable()->getB2dbAlias() . '.' . $column_name;
                }
            }

            throw new Exception("Couldn't find table name '$table_name' for column '$column_name', column was '$column'. If this is a column from a foreign table, make sure the foreign table is joined.");
        }

        /**
         * Get the selection alias for a specified column
         *
         * @param string|array<string, string> $column
         */
        public function getSelectionAlias($column): string
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
         * @param string|array<int, mixed> $column The column to order by
         * @param ?string $sort [optional] The sort order
         *
         * @return Query
         */
        public function addOrderBy($column, string $sort = null): self
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
        public function getSortOrders(): array
        {
            return $this->sort_orders;
        }

        public function hasSortOrders(): bool
        {
            return (bool) count($this->sort_orders);
        }

        /**
         * Limit the query
         *
         * @param int $limit The number to limit
         *
         * @return Query
         */
        public function setLimit(int $limit): self
        {
            $this->limit = $limit;

            return $this;
        }

        public function getLimit(): int
        {
            return $this->limit;
        }

        public function hasLimit(): bool
        {
            return (isset($this->limit) && $this->limit > 0);
        }

        /**
         * Add a group by clause
         *
         * @param string|array<int, mixed> $column The column to group by
         * @param ?string $sort [optional] The sort order
         *
         * @return Query
         */
        public function addGroupBy($column, string $sort = null): self
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
        public function getSortGroups(): array
        {
            return $this->sort_groups;
        }

        public function hasSortGroups(): bool
        {
            return (bool) count($this->sort_groups);
        }

        /**
         * Offset the query
         *
         * @param int $offset The number to offset by
         *
         * @return Query
         */
        public function setOffset(int $offset): self
        {
            $this->offset = $offset;

            return $this;
        }

        public function getOffset(): int
        {
            return $this->offset;
        }

        public function hasOffset(): bool
        {
            return (isset($this->offset) && $this->offset > 0);
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @param bool $all
         * @return string
         */
        public function generateSelectSQL(bool $all = false): string
        {
            if (!isset($this->sql)) {
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
        public function generateUpdateSQL(Update $update): string
        {
            if (!isset($this->sql)) {
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
        public function generateInsertSQL(Insertion $insertion): string
        {
            if (!isset($this->sql)) {
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
        public function generateDeleteSQL(): string
        {
            if (!isset($this->sql)) {
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
        public function generateCountSQL(): string
        {
            if (!isset($this->sql)) {
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
         * @param array<string>|string|bool $join [optional]
         */
        public function setupJoinTables($join = 'all'): void
        {
            if (is_array($join)) {
                foreach ($join as $join_column) {
                    $foreign_table = $this->table->getForeignTableByLocalColumn($join_column);
                    if ($foreign_table instanceof ForeignTable) {
                        $this->join($foreign_table->getTable(), $foreign_table->getKeyColumnName(), $this->table->getB2dbAlias() . '.' . $foreign_table->getColumnName());
                    }
                }
            } elseif ($join === 'all') {
                foreach ($this->table->getForeignTables() as $foreign_table) {
                    $this->join($foreign_table->getTable(), $foreign_table->getKeyColumnName(), $this->table->getB2dbAlias() . '.' . $foreign_table->getColumnName());
                }
            }
        }

        /**
         * (Re-)generate selection columns based on criteria selection columns
         */
        protected function detectDistinct(): void
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
        public function getCriteria(): array
        {
            return $this->criteria;
        }

        public function hasCriteria(): bool
        {
            return (bool) count($this->criteria);
        }

        /**
         * Set the query to distinct mode
         */
        public function setIsDistinct(): void
        {
            $this->is_distinct = true;
        }

        public function isDistinct(): bool
        {
            return $this->is_distinct;
        }

        public function getMode(): string
        {
            if (!isset($this->mode)) {
                $this->mode = self::MODE_AND;
            }
            return $this->mode;
        }

        public function getSql(): string
        {
            return $this->sql;
        }

    }
