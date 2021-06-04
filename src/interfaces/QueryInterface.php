<?php

    namespace b2db\interfaces;

    use b2db\Join;
    use b2db\Table;

    interface QueryInterface
    {

        public const ACTION_COUNT = 'COUNT';
        public const ACTION_SELECT = 'SELECT';
        public const ACTION_UPDATE = 'UPDATE';
        public const ACTION_INSERT = 'INSERT';
        public const ACTION_DELETE = 'DELETE';

        public function getSql(): string;

        /**
         * @return array<int|string, mixed>
         */
        public function getValues(): array;

        public function getAction(): string;

        public function isCount(): bool;

        public function isSelect(): bool;

        public function isDelete(): bool;

        public function isInsert(): bool;

        public function isUpdate(): bool;

        public function getTable(): Table;

        public function hasIndexBy(): bool;

        public function getIndexBy(): string;

        /**
         * @return Join[]
         */
        public function getJoins(): array;

        public function getSelectionColumn(string $column): string;

        /**
         * Get the selection alias for a specified column
         *
         * @param string|array<string, string> $column
         */
        public function getSelectionAlias($column): string;

    }