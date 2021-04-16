<?php

    namespace b2db;

    use b2db\interfaces\CriterionProvider;

    /**
     * Criteria class
     *
     * @package b2db
     * @subpackage core
     */
    class Criteria
    {

        /**
         * Parent table
         *
         * @var Table
         */
        protected $table;

        /**
         * @var CriterionProvider[]
         */
        protected $parts = [];

        /**
         * @var Query
         */
        protected $query;

        protected $is_distinct = false;

        protected $mode;

        protected $sql;

        /**
         * Get added values
         *
         * @return mixed[]
         */
        public function getValues()
        {
            $values = [];

            foreach ($this->parts as $part) {
                if ($part instanceof Criterion) {
                    if ($part->getValue() !== null) {
                        $values[] = $part->getValue();
                    }
                } else {
                    foreach ($part->getValues() as $value) {
                        if ($value !== null) {
                            $values[] = $value;
                        }
                    }
                }
            }

            return $values;
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
        public function where($column, $value = '', $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): self
        {
            if (!$column instanceof CriterionProvider) {
                $column = new Criterion($column, $value, $operator, $variable, $additional, $special);
            }

            $this->parts[] = $column;

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
        public function having($column, $value = '', $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): self
        {
            if (!$column instanceof CriterionProvider) {
                $column = new Criterion($column, $value, $operator, $variable, $additional, $special);
            }

            if (count($this->parts)) {
                throw new Exception('Cannot combine more than one HAVING clause in the same Query. Use multiple sub-criteria instead');
            }

            $this->parts[] = $column;
            $this->mode = Query::MODE_HAVING;

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
        public function and($column, $value = '', $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): self
        {
            if ($this->mode == Query::MODE_OR) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Criteria. Use multiple sub-criteria instead');
            }

            $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = Query::MODE_AND;

            return $this;
        }

        /**
         * Adds an "or" part to the query
         *
         * @param string $column The column to update
         * @param mixed $value The value
         * @param mixed $operator [optional]
         * @param string $variable
         * @param string $additional
         * @param string $special
         * @return Criteria
         */
        public function or($column, $value = null, $operator = Criterion::EQUALS, $variable = null, $additional = null, $special = null): self
        {
            if ($this->mode == Query::MODE_AND) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Criteria. Use multiple sub-criteria instead');
            }

            $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = Query::MODE_OR;

            return $this;
        }

        /**
         * Returns the SQL string for the current criteria
         *
         * @param bool $strip_table_name
         * @return string
         */
        public function getSQL($strip_table_name = false)
        {
            $sql_parts = [];
            foreach ($this->parts as $part) {
                if ($part instanceof Criterion) {
                    $part->setCriteria($this);
                } elseif ($part instanceof Criteria) {
                    $part->setQuery($this->query);
                }

                $sql_parts[] = $part->getSql($strip_table_name);
            }

            if (count($sql_parts) > 1) {
                return '(' . join(" {$this->getMode()} ", $sql_parts) . ')';
            } else {
                return $sql_parts[0];
            }
        }

        /**
         * Set the query to distinct mode
         */
        public function setIsDistinct()
        {
            $this->is_distinct = true;
        }

        /**
         * @return bool
         */
        public function isDistinct()
        {
            return $this->is_distinct;
        }

        /**
         * @return mixed
         */
        public function getMode()
        {
            if (!$this->mode) {
                $this->mode = Query::MODE_AND;
            }
            return $this->mode;
        }

        /**
         * @return Query
         */
        public function getQuery(): Query
        {
            return $this->query;
        }

        /**
         * @param Query $query
         */
        public function setQuery(Query $query)
        {
            $this->query = $query;
        }

    }
