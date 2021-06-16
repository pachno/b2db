<?php

    namespace b2db;

    use b2db\interfaces\CriterionProvider;

    /**
     * Criteria class
     *
     * @package b2db
     */
    class Criteria
    {

        /**
         * Parent table
         *
         * @var ?Table
         */
        protected ?Table $table;

        /**
         * @var CriterionProvider[]
         */
        protected array $parts = [];

        /**
         * @var Query
         */
        protected Query $query;

        protected bool $is_distinct = false;

        protected string $mode;

        protected string $sql;

        /**
         * Get added values
         *
         * @return array<int|string|bool|array>
         */
        public function getValues(): array
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
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function where($column, $value = '', string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): self
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
         * @param string|CriterionProvider|Criterion $column
         * @param mixed  $value
         * @param string $operator
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function having($column, $value = '', string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): self
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
         * @param string|CriterionProvider|Criterion $column
         * @param mixed  $value
         * @param string $operator
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function and($column, $value = '', string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): self
        {
            if (isset($this->mode) && $this->mode === Query::MODE_OR) {
                throw new Exception('Cannot combine two selection types (AND/OR) in the same Criteria. Use multiple sub-criteria instead');
            }

            $this->where($column, $value, $operator, $variable, $additional, $special);

            $this->mode = Query::MODE_AND;

            return $this;
        }

        /**
         * Adds an "or" part to the query
         *
         * @param string|CriterionProvider|Criterion $column
         * @param mixed $value The value
         * @param string $operator [optional]
         * @param ?string $variable
         * @param ?string $additional
         * @param ?string $special
         *
         * @return Criteria
         */
        public function or($column, $value = null, string $operator = Criterion::EQUALS, string $variable = null, string $additional = null, string $special = null): self
        {
            if (isset($this->mode) && $this->mode === Query::MODE_AND) {
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
        public function getSQL(bool $strip_table_name = false): string
        {
            $sql_parts = [];
            foreach ($this->parts as $part) {
                if ($part instanceof Criterion) {
                    $part->setCriteria($this);
                } elseif ($part instanceof self) {
                    $part->setQuery($this->query);
                }

                $sql_parts[] = $part->getSql($strip_table_name);
            }

            if (count($sql_parts) > 1) {
                return '(' . implode(" {$this->getMode()} ", $sql_parts) . ')';
            }

            return $sql_parts[0];
        }

        /**
         * Set the query to distinct mode
         */
        public function setIsDistinct(): void
        {
            $this->is_distinct = true;
        }

        /**
         * @return bool
         */
        public function isDistinct(): bool
        {
            return $this->is_distinct;
        }

        public function getMode(): string
        {
            if (!isset($this->mode)) {
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
        public function setQuery(Query $query): void
        {
            $this->query = $query;
        }

    }
