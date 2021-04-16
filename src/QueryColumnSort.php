<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     * @subpackage core
     */
    class QueryColumnSort
    {

        const SORT_ASC = 'asc';
        const SORT_ASC_NUMERIC = 'asc_numeric';
        const SORT_DESC = 'desc';
        const SORT_DESC_NUMERIC = 'desc_numeric';

        protected $column;

        protected $order;

        /**
         * @param string $column
         * @param string $order
         */
        public function __construct($column, $order = self::SORT_ASC)
        {
            $this->column = $column;
            $this->order = $order;
        }

        /**
         * @return string
         */
        public function getColumn()
        {
            return $this->column;
        }

        /**
         * @return string|array
         */
        public function getOrder()
        {
            return $this->order;
        }

    }
