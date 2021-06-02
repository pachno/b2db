<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class ForeignTable
    {

        protected string $column;

        /**
         * @var Table
         */
        protected Table $table;

        protected string $key;

        /**
         * @param Table $table
         * @param string $key
         * @param string $column
         */
        public function __construct(Table $table, string $key, string $column)
        {
            $this->table = $table;
            $this->key = $key;
            $this->column = $column;
        }

        public function getColumn(): string
        {
            return $this->column;
        }

        public function getKey(): string
        {
            return $this->key;
        }

        /**
         * @return Table
         */
        public function getTable(): Table
        {
            return $this->table;
        }

        public function getKeyColumnName(): string
        {
            return $this->table->getB2dbAlias() . '.' . Table::getColumnName($this->key);
        }

        public function getColumnName(): string
        {
            return Table::getColumnName($this->column);
        }

    }
