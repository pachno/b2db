<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class SqlGenerator
    {

        protected string $sql;

        /**
         * @var array<string|int, mixed>
         */
        protected array $values;

        /**
         * @var Query
         */
        protected Query $query;

        protected bool $having = false;

        /**
         * @param Query $query
         */
        public function __construct(Query $query)
        {
            $this->query = $query;
        }

        public static function quoteIdentifier(string $id): string
        {
            $parts = [];
            foreach (explode('.', $id) as $part) {
                /** @noinspection DegradedSwitchInspection */
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

        protected function getTable(): Table
        {
            return $this->query->getTable();
        }

        /**
         * Add a specified value
         *
         * @param mixed $value
         * @param bool $force_null
         */
        protected function addValue($value, bool $force_null = false): void
        {
            if (is_array($value)) {
                foreach ($value as $single_value) {
                    $this->addValue($single_value);
                }
            } elseif ($value !== null || $force_null) {
                $this->values[] = $this->query->getDatabaseValue($value);
            }
        }

        /**
         * @return array<int|string, mixed>
         */
        public function getValues(): array
        {
            return $this->values;
        }

        protected function hasHaving(): bool
        {
            return $this->having;
        }

        protected function generateWherePartSql(bool $strip = false): string
        {
            $sql = '';
            if ($this->query->hasCriteria()) {
                $sql_parts = [];
                $criteria = $this->query->getCriteria();
                foreach ($criteria as $criterion) {
                    if ($criterion->getMode() === Query::MODE_HAVING) {
                        $this->having = true;
                        continue;
                    }

                    $sql_parts[] = $criterion->getSql($strip);
                }

                if (count($sql_parts)) {
                    if (count($sql_parts) > 1) {
                        $sql = '(' . implode(" {$this->query->getMode()} ", $sql_parts) . ')';
                    } else {
                        $sql = $sql_parts[0];
                    }

                    $sql = ' WHERE ' . $sql;
                }
            }

            return $sql;
        }

        protected function generateHavingPartSql(bool $strip = false): string
        {
            $sql_parts = [];
            $criteria = $this->query->getCriteria();
            foreach ($criteria as $criterion) {
                if ($criterion->getMode() !== Query::MODE_HAVING) {
                    continue;
                }

                $sql_parts[] = $criterion->getSql($strip);
            }

            if (count($sql_parts) > 1) {
                $sql = '(' . implode(" {$this->query->getMode()} ", $sql_parts) . ')';
            } else {
                $sql = $sql_parts[0];
            }

            return ' HAVING ' . $sql;
        }

        protected function generateGroupPartSql(): string
        {
            $sql = '';

            if ($this->query->hasSortGroups()) {
                $group_columns = [];
                $groups = [];
                foreach ($this->query->getSortGroups() as $sort_group) {
                    $column_name = $this->query->getSelectionColumn($sort_group->getColumn());
                    $groups[] = self::quoteIdentifier($column_name) . ' ' . $sort_group->getOrder();
                    if ($this->query->isCount()) {
                        $group_columns[$column_name] = $column_name;
                    }
                }
                $sql .= ' GROUP BY ' . implode(', ', $groups);
                if ($this->query->isCount()) {
                    $sort_orders = [];
                    foreach ($this->query->getSortOrders() as $sort_order) {
                        $column_name = $this->query->getSelectionColumn($sort_order->getColumn());
                        if (!array_key_exists($column_name, $group_columns)) {
                            $sort_order_column = self::quoteIdentifier($column_name) . ' ';
                            $sort_orders[$sort_order_column] = $sort_order_column;
                        }
                    }
                    foreach ($this->query->getJoins() as $join) {
                        $join_sort = self::quoteIdentifier($join->getLeftColumn()) . ' ';
                        if (!array_key_exists($join_sort, $sort_orders)) {
                            $sort_orders[$join_sort] = $join_sort;
                        }
                    }
                    $sql .= implode(', ', $sort_orders);
                }
            }

            return $sql;
        }

        protected function generateOrderByPartSql(): string
        {
            $sql = '';

            if ($this->query->hasSortOrders()) {
                $sql_parts = [];
                foreach ($this->query->getSortOrders() as $sort_order) {
                    if (is_array($sort_order->getOrder())) {
                        $subsort_sql_parts = [];
                        foreach ($sort_order->getOrder() as $sort_elm) {
                            $subsort_sql_parts[] = self::quoteIdentifier($this->query->getSelectionColumn($sort_order->getColumn())) . '=' . $sort_elm;
                        }
                        $sql_parts[] = implode(',', $subsort_sql_parts);
                    } else {
                        $sort_sql = self::quoteIdentifier($this->query->getSelectionColumn($sort_order->getColumn()));
                        if (in_array($sort_order->getOrder(), array(QueryColumnSort::SORT_ASC_NUMERIC, QueryColumnSort::SORT_DESC_NUMERIC))) {
                            $sort_sql .= '+0 ' . substr($sort_order->getOrder(), 0, -8);
                        } else {
                            $sort_sql .= ' ' . $sort_order->getOrder();
                        }
                        $sql_parts[] = $sort_sql;
                    }
                }
                $sql .= ' ORDER BY ' . implode(', ', $sql_parts);
            }

            return $sql;
        }

        /**
         * Generate the "where" part of the query
         *
         * @param bool $strip
         * @return string
         */
        protected function generateWhereSQL(bool $strip = false): string
        {
            $sql = $this->generateWherePartSql($strip);
            $sql .= $this->generateGroupPartSql();
            if ($this->hasHaving()) {
                $sql .= $this->generateHavingPartSql();
            }
            $sql .= $this->generateOrderByPartSql();
            if ($this->query->isSelect()) {
                if ($this->query->hasLimit()) {
                    $sql .= ' LIMIT ' . $this->query->getLimit();
                }
                if ($this->query->hasOffset()) {
                    $sql .= ' OFFSET ' . $this->query->getOffset();
                }
            }

            return $sql;
        }

        /**
         * Generate the "join" part of the sql
         *
         * @return string
         */
        protected function generateJoinSQL(): string
        {
            $sql = ' FROM ' . $this->getTable()->getSelectFromSql();
            foreach ($this->query->getJoins() as $join) {
                $sql .= ' ' . $join->getJoinType() . ' ' . $join->getTable()->getSelectFromSql();
                $sql .= ' ON (' . self::quoteIdentifier($join->getLeftColumn()) . Criterion::EQUALS . self::quoteIdentifier($join->getRightColumn());

                if ($join->hasAdditionalCriteria()) {
                    $sql_parts = [];

                    foreach ($join->getAdditionalCriteria() as $criteria) {
                        $criteria->setQuery($this->query);
                        $sql_parts[] = $criteria->getSql();
                        foreach ($criteria->getValues() as $value) {
                            $this->query->addValue($value);
                        }
                    }

                    $sql .= ' AND ' . implode(' AND ', $sql_parts);
                }

                $sql .= ')';
            }

            return $sql;
        }

        /**
         * Adds all select columns from all available tables in the query
         */
        protected function addAllSelectColumns(): void
        {
            foreach ($this->getTable()->getAliasColumns() as $column) {
                $this->query->addSelectionColumnRaw($column);
            }

            foreach ($this->query->getJoins() as $join) {
                foreach ($join->getTable()->getAliasColumns() as $column) {
                    $this->query->addSelectionColumnRaw($column);
                }
            }
        }

        /**
         * Generates "select all" SQL
         *
         * @return string
         */
        protected function generateSelectAllSQL(): string
        {
            $sql_parts = [];
            foreach ($this->query->getSelectionColumns() as $selection) {
                $sql_parts[] = $selection->getSql();
            }
            return implode(', ', $sql_parts);
        }

        /**
         * Generate the "select" part of the query
         *
         * @return string
         */
        protected function generateSelectSQL(): string
        {
            $sql = ($this->query->isDistinct()) ? 'SELECT DISTINCT ' : 'SELECT ';

            if ($this->query->isCustomSelection()) {
                if ($this->query->isDistinct() && Core::getDriver() === Core::DRIVER_POSTGRES) {
                    foreach ($this->query->getSortOrders() as $sort_order) {
                        $this->query->addSelectionColumn($sort_order->getColumn());
                    }
                }

                $sql_parts = [];
                foreach ($this->query->getSelectionColumns() as $column => $selection) {
                    $alias = ($selection->getAlias()) ?? $this->query->getSelectionAlias($column);
                    $sub_sql = $selection->getVariableString();
                    if ($selection->isSpecial()) {
                        $sub_sql .= mb_strtoupper($selection->getSpecial()) . '(' . self::quoteIdentifier($selection->getColumn()) . ')';
                        if ($selection->hasAdditional()) {
                            $sub_sql .= ' ' . $selection->getAdditional() . ' ';
                        }
                        if (mb_strpos($selection->getSpecial(), '(') !== false) {
                            $sub_sql .= ')';
                        }
                    } else {
                        $sub_sql .= self::quoteIdentifier($selection->getColumn());
                    }
                    $sub_sql .= ' AS ' . self::quoteIdentifier($alias);
                    $sql_parts[] = $sub_sql;
                }
                $sql .= implode(', ', $sql_parts);
            } else {
                $this->addAllSelectColumns();
                $sql .= $this->generateSelectAllSQL();
            }

            return $sql;
        }

        /**
         * Generate a "select" query
         *
         * @param bool $all [optional]
         * @return string
         */
        public function getSelectSQL(bool $all = false): string
        {
            if (!$this->query->getTable() instanceof Table) {
                throw new Exception('Trying to generate sql when no table is being used.');
            }
            $sql = $this->generateSelectSQL();
            $sql .= $this->generateJoinSQL();
            if (!$all) {
                $sql .= $this->generateWhereSQL();
            }

            return $sql;
        }

        /**
         * Generate the "count" part of the query
         *
         * @return string
         */
        protected function generateCountSQL(): string
        {
            $sql = ($this->query->isDistinct()) ? 'SELECT COUNT(DISTINCT ' : 'SELECT COUNT(';
            $sql .= self::quoteIdentifier($this->query->getSelectionColumn($this->getTable()->getIdColumn()));
            $sql .= ') as num_col';

            return $sql;
        }

        /**
         * Generate a "count" query
         *
         * @return string
         */
        public function getCountSQL(): string
        {
            if (!$this->query->getTable() instanceof Table) {
                throw new Exception('Trying to generate sql when no table is being used.');
            }
            $sql = $this->generateCountSQL();
            $sql .= $this->generateJoinSQL();
            $sql .= $this->generateWhereSQL();

            return $sql;
        }

        /**
         * Generate the "update" part of the query
         *
         * @param Update $update
         * @return string
         */
        protected function generateUpdateSQL(Update $update): string
        {
            $updates = [];
            foreach ($update->getValues() as $column => $value) {
                $column = mb_substr($column, mb_strpos($column, '.') + 1);
                $prefix = self::quoteIdentifier($column);
                $updates[] = $prefix . Criterion::EQUALS . '?';

                $this->addValue($value, true);
            }
            return 'UPDATE ' . $this->getTable()->getSqlTableName() . ' SET ' . implode(', ', $updates);
        }

        /**
         * Generate an "update" query
         *
         * @param Update $update
         * @return string
         */
        public function getUpdateSQL(Update $update): string
        {
            if (!$this->query->getTable() instanceof Table) {
                throw new Exception('Trying to generate sql when no table is being used.');
            }
            $sql = $this->generateUpdateSQL($update);
            $sql .= $this->generateWhereSQL(true);

            return $sql;
        }

        /**
         * Generate an "insert" query
         *
         * @param Insertion $insertion
         *
         * @return string
         */
        public function getInsertSQL(Insertion $insertion): string
        {
            if (!$this->query->getTable() instanceof Table) {
                throw new Exception('Trying to generate sql when no table is being used.');
            }

            $inserts = [];
            $values = [];
            $table_name = $this->getTable()->getSqlTableName();

            foreach ($insertion->getValues() as $column => $value) {
                $column = mb_substr($column, mb_strpos($column, '.') + 1);
                $inserts[] = self::quoteIdentifier($column);

                if ($insertion->hasVariable($column)) {
                    $values[] = '@' . $insertion->getVariable($column);
                } else {
                    $values[] = '?';
                    $this->addValue($value, true);
                }
            }

            $inserts = implode(', ', $inserts);
            $values = implode(', ', $values);

            return "INSERT INTO $table_name ($inserts) VALUES ($values)";
        }

        /**
         * Generate a "delete" query
         *
         * @return string
         */
        public function getDeleteSQL(): string
        {
            if (!$this->query->getTable() instanceof Table) {
                throw new Exception('Trying to generate sql when no table is being used.');
            }
            $sql = 'DELETE FROM ' . $this->getTable()->getSqlTableName();
            $sql .= $this->generateWhereSQL(true);

            return $sql;
        }

    }
