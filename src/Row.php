<?php

    namespace b2db;

    /**
     * Row class
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 2.0
     * @license http://www.opensource.org/licenses/mozilla1.1.php Mozilla Public License 1.1 (MPL 1.1)
     * @package b2db
     * @subpackage core
     */

    /**
     * Row class
     *
     * @package b2db
     * @subpackage core
     */
    class Row implements \ArrayAccess
    {

        protected $fields = [];

        /**
         * Statement
         *
         * @var Statement
         */
        protected $statement = null;

        protected $id_col = null;

        /**
         * Constructor
         *
         * @param mixed[] $row
         * @param Statement $statement
         */
        public function __construct($row, $statement)
        {
            foreach ($row as $key => $val) {
                $this->fields[$key] = $val;
            }
            $this->statement = $statement;
        }

        /**
         * @return Join[]
         */
        public function getJoinedTables()
        {
            return $this->statement->getQuery()->getJoins();
        }

        protected function getColumnName($column, $foreign_key = null)
        {
            if ($foreign_key !== null) {
                foreach ($this->statement->getQuery()->getJoins() as $join) {
                    if ($join->getOriginalColumn() == $foreign_key) {
                        $column = $join->getTable()->getB2DBAlias() . '.' . Table::getColumnName($column);
                        break;
                    }
                }
            } else {
                $column = $this->statement->getQuery()->getSelectionColumn($column);
            }

            return $column;
        }

        public function get($column, $foreign_key = null)
        {
            if ($this->statement == null) {
                throw new Exception('Statement did not execute, cannot return unknown value for column ' . $column);
            }

            $column = $this->getColumnName($column, $foreign_key);

            if (isset($this->fields[$this->statement->getQuery()->getSelectionAlias($column)])) {
                return $this->fields[$this->statement->getQuery()->getSelectionAlias($column)];
            }

            return null;
        }

        /**
         * Return the associated Query
         *
         * @return Query
         */
        public function getQuery()
        {
            return $this->statement->getQuery();
        }

        public function offsetExists($offset)
        {
            if (strpos($offset, '.') === false)
                return (bool) array_key_exists($offset, $this->fields);

            $column = $this->getColumnName($offset);
            return (bool) array_key_exists($this->statement->getQuery()->getSelectionAlias($column), $this->fields);
        }

        public function offsetGet($offset)
        {
            return $this->get($offset);
        }

        public function offsetSet($offset, $value)
        {
            throw new \Exception('Not supported');
        }

        public function offsetUnset($offset)
        {
            throw new \Exception('Not supported');
        }

        public function getID()
        {
            if ($this->id_col === null) {
                $this->id_col = $this->statement->getQuery()->getTable()->getIdColumn();
            }

            return $this->get($this->id_col);
        }

    }
