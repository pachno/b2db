<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     * @subpackage core
     */
    class QueryColumnSelection
    {

        protected $column;

        protected $alias;

        protected $variable;

        protected $additional;

        protected $special;

        /**
         * @var Query
         */
        protected $query;

        /**
         * @param Query $query
         * @param string $column
         * @param string $alias
         * @param string $special
         * @param string $variable
         * @param string $additional
         */
        public function __construct(Query $query, $column, $alias = '', $special = null, $variable = null, $additional = null)
        {
            $this->query = $query;
            $this->column = $column;
            $this->alias = $alias;
            if ($variable) {
                $this->variable = $variable;
            }
            if ($additional) {
                $this->additional = $additional;
            }
            if ($special) {
                $this->special = $special;
            }
        }

        public function getSql()
        {
            return $this->column . ' AS ' . $this->query->getSelectionAlias($this->column);
        }

        /**
         * @return string
         */
        public function getAlias()
        {
            return $this->alias;
        }

        /**
         * @return string
         */
        public function getColumn()
        {
            return $this->column;
        }

        /**
         * @return string|null
         */
        public function getVariable()
        {
            return $this->variable;
        }

        public function getVariableString()
        {
            return (isset($this->variable) && $this->variable != '') ? ' @' . $this->variable . ':=' : '';
        }

        /**
         * @return string|null
         */
        public function getAdditional()
        {
            return $this->additional;
        }

        public function hasAdditional()
        {
            return (bool) ($this->additional != '');
        }

        /**
         * @return string|null
         */
        public function getSpecial()
        {
            return $this->special;
        }

        public function isSpecial()
        {
            return (bool) ($this->special != '');
        }

    }
