<?php

    /** @noinspection PhpUnused */

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class Join
    {

        public const LEFT = 'LEFT JOIN';
        public const INNER = 'INNER JOIN';
        public const RIGHT = 'RIGHT JOIN';

        protected Table $table;

        protected string $left_column;

        protected string $right_column;

        protected string $original_column;

        /**
         * @var Criteria[]
         */
        protected array $additional_criteria = [];

        protected string $join_type;

        /**
         * @param Table $table
         * @param string $left_column
         * @param string $right_column
         * @param string $original_column
         * @param array<array> $additional_criteria
         * @param string $join_type
         */
        public function __construct(Table $table, string $left_column, string $right_column, string $original_column, array $additional_criteria = null, string $join_type = self::LEFT)
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

        public function getTable(): Table
        {
            return $this->table;
        }

        public function setTable(Table $table): void
        {
            $this->table = $table;
        }

        public function getLeftColumn(): string
        {
            return $this->left_column;
        }

        public function setLeftColumn(string $left_column): void
        {
            $this->left_column = $left_column;
        }

        public function getRightColumn(): string
        {
            return $this->right_column;
        }

        public function setRightColumn(string $right_column): void
        {
            $this->right_column = $right_column;
        }

        public function getOriginalColumn(): string
        {
            return $this->original_column;
        }

        public function setOriginalColumn(string $original_column): void
        {
            $this->original_column = $original_column;
        }

        /**
         * @return Criteria[]
         */
        public function getAdditionalCriteria(): array
        {
            return $this->additional_criteria;
        }

        public function hasAdditionalCriteria(): bool
        {
            return (bool) count($this->additional_criteria);
        }

        /**
         * @param Criteria[] $additional_criteria
         */
        public function setAdditionalCriteria(array $additional_criteria): void
        {
            $this->additional_criteria = $additional_criteria;
        }

        public function getJoinType(): string
        {
            return $this->join_type;
        }

        public function setJoinType(string $join_type): void
        {
            $this->join_type = $join_type;
        }

    }
