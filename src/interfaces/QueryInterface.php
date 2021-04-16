<?php

    namespace b2db\interfaces;

    interface QueryInterface
    {

        const ACTION_COUNT = 'COUNT';
        const ACTION_SELECT = 'SELECT';
        const ACTION_UPDATE = 'UPDATE';
        const ACTION_INSERT = 'INSERT';
        const ACTION_DELETE = 'DELETE';

        public function getSql();

        public function getValues();

        public function getAction();

        public function isCount();

        public function isSelect();

        public function isDelete();

        public function isInsert();

        public function isUpdate();

    }