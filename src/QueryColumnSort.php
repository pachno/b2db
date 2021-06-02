<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class QueryColumnSort
    {

        public const SORT_ASC = 'asc';
        public const SORT_ASC_NUMERIC = 'asc_numeric';
        public const SORT_DESC = 'desc';
        public const SORT_DESC_NUMERIC = 'desc_numeric';

        protected string $column;

        protected string $order;

        /**
         * @param string $column
         * @param ?string $order
         */
        public function __construct(string $column, string $order = null)
        {
            $this->column = $column;
            $this->order = $order ?? self::SORT_ASC;
        }

        public function getColumn(): string
        {
            return $this->column;
        }

        /**
         * @return string|array<string>
         * @noinspection PhpReturnDocTypeMismatchInspection
         */
        public function getOrder()
        {
            return $this->order;
        }

    }
