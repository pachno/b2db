<?php

    namespace b2db\interfaces;

    interface CriterionProvider
    {

        public function getSql();

        public function getValues();

        public function getColumns();

    }