<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     * @subpackage core
     */
    class Join
    {

        const LEFT = 'LEFT JOIN';
        const INNER = 'INNER JOIN';
        const RIGHT = 'RIGHT JOIN';

        protected $table;

        protected $left_column;

        protected $right_column;

        protected $original_column;

        /**
         * @var Criteria[]
         */
        protected $additional_criteria = [];

        protected $join_type;

        /**
         * @param Table $table
         * @param $left_column
         * @param $right_column
         * @param $original_column
         * @param $additional_criteria
         * @param string $join_type
         */
        public function __construct(Table $table, $left_column, $right_column, $original_column, $additional_criteria = null, $join_type = self::LEFT)
        {
            $this->table = $table;
            $this->left_column = $left_column;
            $this->right_column = $right_column;
            $this->original_column = $original_column;
            if ($additional_criteria) {
                foreach ($additional_criteria as $additional_criterion) {
                    $criteria = new Criteria();
                    $criteria->where(...$additional_criterion);
                    $this->additional_criteria[] = $criteria;
                }
            }
            $this->join_type = $join_type;
        }

        /**
         * @return Table
         */
        public function getTable(): Table
        {
            return $this->table;
        }

        /**
         * @param Table $table
         */
        public function setTable(Table $table)
        {
            $this->table = $table;
        }

        /**
         * @return mixed
         */
        public function getLeftColumn()
        {
            return $this->left_column;
        }

        /**
         * @param mixed $left_column
         */
        public function setLeftColumn($left_column)
        {
            $this->left_column = $left_column;
        }

        /**
         * @return mixed
         */
        public function getRightColumn()
        {
            return $this->right_column;
        }

        /**
         * @param mixed $right_column
         */
        public function setRightColumn($right_column)
        {
            $this->right_column = $right_column;
        }

        /**
         * @return mixed
         */
        public function getOriginalColumn()
        {
            return $this->original_column;
        }

        /**
         * @param mixed $original_column
         */
        public function setOriginalColumn($original_column)
        {
            $this->original_column = $original_column;
        }

        /**
         * @return Criteria[]
         */
        public function getAdditionalCriteria()
        {
            return $this->additional_criteria;
        }

        /**
         * @return bool
         */
        public function hasAdditionalCriteria()
        {
            return (bool) count($this->additional_criteria);
        }

        /**
         * @param mixed $additional_criteria
         */
        public function setAdditionalCriteria($additional_criteria)
        {
            $this->additional_criteria = $additional_criteria;
        }

        /**
         * @return string
         */
        public function getJoinType()
        {
            return $this->join_type;
        }

        /**
         * @param string $join_type
         */
        public function setJoinType($join_type)
        {
            $this->join_type = $join_type;
        }

    }
