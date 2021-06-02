<?php

    namespace b2db\interfaces;

    interface CriterionProvider
    {

        public function getSql(bool $strip_table_name = false): string;

        /**
         * @return array<string, mixed>
         */
        public function getValues(): array;

        /**
         * @return array<string, mixed>
         */
        public function getColumns(): array;

    }