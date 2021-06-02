<?php

    namespace b2db;

    use b2db\interfaces\QueryInterface;
    use Countable;

    /**
     * Resultset class
     *
     * @package b2db
     */
    class Resultset implements Countable
    {

        /**
         * @var Row[]
         */
        protected array $rows = [];

        /**
         * @var QueryInterface
         */
        protected QueryInterface $query;

        protected int $current_pointer;

        protected int $max_pointer;

        protected int $last_insert_id;

        protected string $id_column;

        public function __construct(Statement $statement)
        {
            $this->query = $statement->getQuery();

            if ($this->query instanceof QueryInterface) {
                if ($this->query->isInsert()) {
                    $this->last_insert_id = $statement->getInsertID();
                } elseif ($this->query->isSelect()) {
                    while ($row = $statement->fetch()) {
                        $this->rows[] = new Row($row, $statement);
                    }
                    $this->max_pointer = count($this->rows);
                    $this->current_pointer = 0;
                } elseif ($this->query->isCount()) {
                    $value = $statement->fetch();
                    $this->max_pointer = $value['num_col'] ?? 0;
                }
            }
        }

        protected function _next(): bool
        {
            if ($this->current_pointer === $this->max_pointer) {
                return false;
            }

            $this->current_pointer++;
            return true;
        }

        public function getCount(): int
        {
            return $this->max_pointer;
        }

        /**
         * Returns the current row
         *
         * @return ?Row
         */
        public function getCurrentRow(): ?Row
        {
            return $this->rows[($this->current_pointer - 1)] ?? null;
        }

        /**
         * Advances through the resultset and returns the current row
         * Returns false when there are no more rows
         *
         * @return Row|false
         */
        public function getNextRow()
        {
            if ($this->_next()) {
                $row = $this->getCurrentRow();
                if ($row instanceof Row) {
                    return $row;
                }
                throw new \Exception('This should never happen. Please file a bug report');
            } else {
                return false;
            }
        }

        /**
         * @return mixed|null
         */
        public function get(string $column, string $foreign_key = null)
        {
            $row = $this->getCurrentRow();
            if (!$row instanceof Row) {
                throw new \Exception("Cannot return value of $column on a row that doesn't exist");
            }

            return $row->get($column, $foreign_key);
        }

        /**
         * @return Row[]
         */
        public function getAllRows(): array
        {
            return $this->rows;
        }

        public function getSQL(): string
        {
            return ($this->query instanceof Criteria) ? $this->query->getSQL() : '';
        }

        /**
         * Return a printable version of the sql string with variables substituted where possible
         * instead of placeholders
         *
         * @return string
         * @noinspection PhpUnused
         */
        public function printSQL(): string
        {
            $str = '';
            if ($this->query instanceof Criteria) {
                $str .= $this->query->getSQL();
                foreach ($this->query->getValues() as $val) {
                    if (!is_int($val)) {
                        $val = '\'' . $val . '\'';
                    }
                    $str = substr_replace($str, $val, mb_strpos($str, '?'), 1);
                }
            }
            return $str;
        }

        public function getInsertID(): ?int
        {
            return $this->last_insert_id ?? null;
        }

        public function rewind(): void
        {
            $this->current_pointer = 0;
        }

        public function current(): ?Row
        {
            return $this->getCurrentRow();
        }

        public function key(): ?string
        {
            if (!isset($this->id_column)) {
                $this->id_column = $this->query->getTable()->getIdColumn();
            }

            $row = $this->getCurrentRow();

            return ($row instanceof Row) ? $row->get($this->id_column) : null;
        }

        public function next(): void
        {
            $this->_next();
        }

        public function valid(): bool
        {
            return (bool) $this->current_pointer < $this->max_pointer;
        }

        public function count(): int
        {
            return $this->max_pointer;
        }

        public function getQuery(): QueryInterface
        {
            return $this->query;
        }

    }
