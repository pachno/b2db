<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     * @subpackage core
     */
    class ForeignTable
    {

        protected $column;

        /**
         * @var Table
         */
        protected $table;

        protected $key;

        /**
         * @param Table $table
         * @param $key
         * @param $column
         */
        public function __construct(Table $table, $key, $column)
        {
            $this->table = $table;
            $this->key = $key;
            $this->column = $column;
        }

        /**
         * @return string
         */
        public function getColumn()
        {
            return $this->column;
        }

        /**
         * @return string
         */
        public function getKey()
        {
            return $this->key;
        }

        /**
         * @return Table
         */
        public function getTable()
        {
            return $this->table;
        }

        public function getKeyColumnName()
        {
            return $this->table->getB2DBAlias() . '.' . Table::getColumnName($this->key);
        }

        public function getColumnName()
        {
            return Table::getColumnName($this->column);
        }

    }
