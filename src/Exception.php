<?php

    namespace b2db;

    /**
     * B2DB Exception class
     *
     * @package b2db
     */
    class Exception extends \Exception
    {

        protected string $_sql;

        public function __construct(string $message, string $sql = '')
        {
            parent::__construct($message);
            $this->_sql = $sql;
        }

        public function getSQL(): string
        {
            return $this->_sql;
        }

    }
