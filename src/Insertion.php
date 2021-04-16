<?php

    namespace b2db;

    /**
     * Insertion wrapper class
     *
     * @package b2db
     * @subpackage core
     */
    class Insertion
    {

        /**
         * Parent table
         *
         * @var Criterion[]
         */
        protected $criteria;

        protected $columns;

        protected $values;
        
        protected $variables;

        /**
         * Get added columns
         *
         * @return mixed[]
         */
        public function getColumns()
        {
            if ($this->columns === null) {
                $this->generateColumnsAndValues();
            }
            return $this->columns;
        }

        /**
         * Get added values
         *
         * @return mixed[]
         */
        public function getValues()
        {
            if ($this->values === null) {
                $this->generateColumnsAndValues();
            }
            return $this->values;
        }

        /**
         * Get added variables
         *
         * @return mixed[]
         */
        public function getVariables()
        {
            if ($this->variables === null) {
                $this->generateColumnsAndValues();
            }
            return $this->variables;
        }

        public function hasVariable($column)
        {
            return array_key_exists($column, $this->variables);
        }

        public function getVariable($column)
        {
            return $this->variables[$column];
        }

        protected function generateColumnsAndValues()
        {
            $this->columns = [];
            $this->values = [];
            $this->variables = [];

            foreach ($this->criteria as $criterion) {
                $this->columns[$criterion->getColumn()] = $criterion->getColumn();
                $this->values[$criterion->getColumn()] = $criterion->getValue();
                $this->variables[$criterion->getColumn()] = $criterion->getVariable();
            }
        }

        public function add($column, $value, $variable = null)
        {
            $this->criteria[$column] = new Criterion($column, $value, null, $variable);
        }

    }
