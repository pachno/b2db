<?php

    namespace b2db;

    /**
     * Insertion wrapper class
     *
     * @package b2db
     */
    class Insertion
    {

        /**
         * Criteria objects
         *
         * @var Criterion[]
         */
        protected array $criteria;

        /**
         * @var array<string, string>
         */
        protected array $columns;

        /**
         * @var array<string|int|bool>
         */
        protected array $values;

        /**
         * @var array<string, string>
         */
        protected array $variables;

        /**
         * Get added columns
         *
         * @return array<string, string>
         */
        public function getColumns(): array
        {
            if (!isset($this->columns)) {
                $this->generateColumnsAndValues();
            }
            return $this->columns;
        }

        /**
         * Get added values
         *
         * @return array<string|int|bool>
         */
        public function getValues(): array
        {
            if (!isset($this->values)) {
                $this->generateColumnsAndValues();
            }
            return $this->values;
        }

        /**
         * Get added variables
         *
         * @return array<string, string>
         */
        public function getVariables(): array
        {
            if (!isset($this->variables)) {
                $this->generateColumnsAndValues();
            }
            return $this->variables;
        }

        public function hasVariable(string $column): bool
        {
            return array_key_exists($column, $this->variables);
        }

        public function getVariable(string $column): string
        {
            return $this->variables[$column];
        }

        protected function generateColumnsAndValues(): void
        {
            $this->columns = [];
            $this->values = [];
            $this->variables = [];

            foreach ($this->criteria as $criterion) {
                $column = $criterion->getColumn();

                $this->columns[$column] = $column;
                $this->values[$column] = $criterion->getValue();
                $this->variables[$column] = $criterion->getVariable();
            }
        }

        /**
         * @param string $column
         * @param mixed $value
         * @param ?string $variable
         */
        public function add(string $column, $value, string $variable = null): void
        {
            $this->criteria[$column] = new Criterion($column, $value, Criterion::EQUALS, $variable);
        }

    }
