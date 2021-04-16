<?php

    namespace b2db;

    use b2db\interfaces\QueryInterface;

    /**
     * Criterion class
     *
     * @package b2db
     * @subpackage core
     */
    class RawQuery implements QueryInterface
    {

        protected $columns;

        protected $values = [];

        protected $sql;

        protected $action;

        /**
         */
        public function __construct($sql)
        {
            $this->sql = $sql;
        }

        public function getSql()
        {
            return $this->sql;
        }

        /**
         * @return mixed
         */
        public function getColumns()
        {
            return $this->columns;
        }

        /**
         * @return mixed
         */
        public function getValues()
        {
            return $this->values;
        }

        public function setSql($sql)
        {
            $this->sql = $sql;
            $this->action = null;
        }

        public function getAction()
        {
            if ($this->action === null) {
                $action = substr($this->sql, 0, 5);

                switch ($action) {
                    case 'selec':
                        $this->action = QueryInterface::ACTION_SELECT;
                    case 'updat':
                        $this->action = QueryInterface::ACTION_UPDATE;
                    case 'inser':
                        $this->action = QueryInterface::ACTION_INSERT;
                    case 'delet':
                        $this->action = QueryInterface::ACTION_DELETE;
                    case 'count':
                        $this->action = QueryInterface::ACTION_COUNT;
                    default:
                        $this->action = '';
                }
            }

            return $this->action;
        }

        public function isCount()
        {
            return (bool) ($this->action == QueryInterface::ACTION_COUNT);
        }

        public function isSelect()
        {
            return (bool) ($this->action == QueryInterface::ACTION_SELECT);
        }

        public function isDelete()
        {
            return (bool) ($this->action == QueryInterface::ACTION_DELETE);
        }

        public function isInsert()
        {
            return (bool) ($this->action == QueryInterface::ACTION_INSERT);
        }

        public function isUpdate()
        {
            return (bool) ($this->action == QueryInterface::ACTION_UPDATE);
        }

    }
