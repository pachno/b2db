<?php

    namespace b2db;

    use ArrayAccess;
    use b2db\interfaces\QueryInterface;

    /**
     * Row class
     *
     * @package b2db
     *
     * @implements \ArrayAccess<int|string, mixed>
     */
    class Row implements ArrayAccess
    {

        /**
         * @var array<int|string, mixed>
         */
        protected array $fields = [];

        protected Statement $statement;

        protected string $id_column;

        /**
         * Constructor
         *
         * @param array<int|string, mixed> $row
         * @param Statement $statement
         */
        public function __construct(array $row, Statement $statement)
        {
            foreach ($row as $key => $val) {
                $this->fields[$key] = $val;
            }
            $this->statement = $statement;
        }

        /**
         * @return Join[]
         */
        public function getJoinedTables(): array
        {
            return $this->statement->getQuery()->getJoins();
        }

        protected function getColumnName(string $column, string $foreign_key = null): string
        {
            if ($foreign_key !== null) {
                foreach ($this->statement->getQuery()->getJoins() as $join) {
                    if ($join->getOriginalColumn() === $foreign_key) {
                        $column = $join->getTable()->getB2dbAlias() . '.' . Table::getColumnName($column);
                        break;
                    }
                }
            } else {
                $column = $this->statement->getQuery()->getSelectionColumn($column);
            }

            return $column;
        }

        /**
         * @return ?mixed
         */
        public function get(string $column, string $foreign_key = null)
        {
            if (!isset($this->statement)) {
                throw new Exception('Statement did not execute, cannot return unknown value for column ' . $column);
            }

            $column = $this->getColumnName($column, $foreign_key);

            return $this->fields[$this->statement->getQuery()->getSelectionAlias($column)] ?? null;
        }

        public function getQuery(): QueryInterface
        {
            return $this->statement->getQuery();
        }

        public function offsetExists(mixed $offset): bool
        {
            if (strpos($offset, '.') === false) {
                return array_key_exists($offset, $this->fields);
            }

            $column = $this->getColumnName($offset);
            return array_key_exists($this->statement->getQuery()->getSelectionAlias($column), $this->fields);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->get($offset);
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            throw new \Exception('Not supported');
        }

        public function offsetUnset(mixed $offset): void
        {
            throw new \Exception('Not supported');
        }

        public function getID(): int
        {
            if (!isset($this->id_column)) {
                $this->id_column = $this->statement->getQuery()->getTable()->getIdColumn();
            }

            return $this->get($this->id_column);
        }

    }
