<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class QueryColumnSelection
    {

        protected string $column;

        protected string $alias;

        protected string $variable;

        protected string $additional;

        protected string $special;

        /**
         * @var Query
         */
        protected Query $query;

        /**
         * @param Query $query
         * @param string $column
         * @param string $alias
         * @param ?string $special
         * @param ?string $variable
         * @param ?string $additional
         */
        public function __construct(Query $query, string $column, string $alias = '', string $special = null, string $variable = null, string $additional = null)
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

        public function getSql(): string
        {
            return $this->column . ' AS ' . $this->query->getSelectionAlias($this->column);
        }

        public function getAlias(): string
        {
            return $this->alias;
        }

        public function getColumn(): string
        {
            return $this->column;
        }

        public function getVariable(): ?string
        {
            return $this->variable;
        }

        public function hasVariable(): bool
        {
            return (isset($this->variable) && $this->variable !== '');
        }

        public function getVariableString(): string
        {
            return ($this->hasVariable()) ? ' @' . $this->variable . ':=' : '';
        }

        /**
         * @return ?string
         */
        public function getAdditional(): ?string
        {
            return $this->additional;
        }

        public function hasAdditional(): bool
        {
            return (isset($this->additional) && $this->additional !== '');
        }

        /**
         * @return ?string
         */
        public function getSpecial(): ?string
        {
            return $this->special;
        }

        public function isSpecial(): bool
        {
            return (isset($this->special) && $this->special !== '');
        }

    }
