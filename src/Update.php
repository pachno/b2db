<?php

    namespace b2db;

    /**
     * Insertion wrapper class
     *
     * @package b2db
     * @subpackage core
     */
    class Update extends Insertion
    {

        public function update($column, $value, $variable = null)
        {
            parent::add($column, $value, $variable);
        }

    }
