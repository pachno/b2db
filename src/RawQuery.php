<?php

    /** @noinspection SpellCheckingInspection */

    namespace b2db;

    use b2db\interfaces\QueryInterface;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class RawQuery implements QueryInterface
    {

        /**
         * @var array<string>
         */
        protected array $columns;

        /**
         * @var array<int|string|bool>
         */
        protected array $values = [];

        protected string $sql;

        protected string $action;

        /**
         * The table this query originates from
         *
         * @var Table
         */
        protected Table $table;

        /**
         */
        public function __construct(string $sql)
        {
            $this->sql = $sql;
        }

        public function getSql(): string
        {
            return $this->sql;
        }

        /**
         * @return array<string>
         */
        public function getColumns(): array
        {
            return $this->columns;
        }

        /**
         * @return array<int|string|bool>
         */
        public function getValues(): array
        {
            return $this->values;
        }

        public function setSql(string $sql): void
        {
            $this->sql = $sql;
            unset($this->action);
        }

        public function getAction(): string
        {
            if (!isset($this->action)) {
                $action = substr($this->sql, 0, 5);

                switch ($action) {
                    case 'selec':
                        $this->action = QueryInterface::ACTION_SELECT;
                        break;
                    case 'updat':
                        $this->action = QueryInterface::ACTION_UPDATE;
                        break;
                    case 'inser':
                        $this->action = QueryInterface::ACTION_INSERT;
                        break;
                    case 'delet':
                        $this->action = QueryInterface::ACTION_DELETE;
                        break;
                    case 'count':
                        $this->action = QueryInterface::ACTION_COUNT;
                        break;
                    default:
                        $this->action = '';
                }
            }

            return $this->action;
        }

        public function isCount(): bool
        {
            return ($this->getAction() === QueryInterface::ACTION_COUNT);
        }

        public function isSelect(): bool
        {
            return ($this->getAction() === QueryInterface::ACTION_SELECT);
        }

        public function isDelete(): bool
        {
            return ($this->getAction() === QueryInterface::ACTION_DELETE);
        }

        public function isInsert(): bool
        {
            return ($this->getAction() === QueryInterface::ACTION_INSERT);
        }

        public function isUpdate(): bool
        {
            return ($this->getAction() === QueryInterface::ACTION_UPDATE);
        }

        /**
         * Returns the table the criteria applies to
         *
         * @return Table
         */
        public function getTable(): Table
        {
            return $this->table;
        }

        public function setTable(Table $table): void
        {
            $this->table = $table;
        }

        /**
         * @return Join[]
         */
        public function getJoins(): array
        {
            throw new Exception('This method is not implemented in the RawQuery class');
        }

        public function getSelectionColumn(string $column): string
        {
            throw new Exception('This method is not implemented in the RawQuery class');
        }

        /**
         * Get the selection alias for a specified column
         *
         * @param string|array<string, string> $column
         */
        public function getSelectionAlias($column): string
        {
            throw new Exception('This method is not implemented in the RawQuery class');
        }

        public function hasIndexBy(): bool
        {
            return false;
        }

        public function getIndexBy(): string
        {
            throw new Exception('This method is not implemented in the RawQuery class');
        }

    }
