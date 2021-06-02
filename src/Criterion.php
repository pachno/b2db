<?php

    namespace b2db;

    /**
     * Criterion class
     *
     * @package b2db
     */
    class Criterion
    {

        public const EQUALS = '=';
        public const NOT_EQUALS = '!=';
        public const GREATER_THAN = '>';
        public const LESS_THAN = '<';
        public const GREATER_THAN_EQUAL = '>=';
        public const LESS_THAN_EQUAL = '<=';
        public const IS_NULL = 'IS NULL';
        public const IS_NOT_NULL = 'IS NOT NULL';
        public const LIKE = 'LIKE';
        public const ILIKE = 'ILIKE';
        public const NOT_LIKE = 'NOT LIKE';
        public const NOT_ILIKE = 'NOT ILIKE';
        public const IN = 'IN';
        public const NOT_IN = 'NOT IN';

        protected string $column;

        /**
         * @var mixed
         */
        protected $value;

        protected string $operator = self::EQUALS;

        protected string $variable;

        protected string $additional;

        protected string $special;

        protected string $sql;

        /**
         * @var Criteria
         */
        protected Criteria $criteria;

        /**
         * @return string[]
         */
        public static function getOperators(): array
        {
            return [
                self::EQUALS,
                self::GREATER_THAN,
                self::GREATER_THAN_EQUAL,
                self::ILIKE,
                self::IN,
                self::IS_NOT_NULL,
                self::IS_NULL,
                self::LESS_THAN,
                self::LESS_THAN_EQUAL,
                self::LIKE,
                self::NOT_EQUALS,
                self::NOT_ILIKE,
                self::NOT_IN,
                self::NOT_LIKE
            ];
        }

        /**
         * Generate a new criterion
         *
         * @param string $column
         * @param mixed $value[optional]
         * @param string $operator[optional]
         * @param ?string $variable[optional]
         * @param ?string $additional[optional]
         * @param ?string $special[optional]
         */
        public function __construct(string $column, $value = '', string $operator = self::EQUALS, string $variable = null, string $additional = null, string $special = null)
        {
            if ($column !== '') {
                $this->column = $column;
                $this->value = $value;
                if ($operator !== null) {
                    if ($operator === self::IN && !$value) {
                        throw new Exception('Cannot use an empty value for WHERE IN criteria');
                    }

                    $this->operator = $operator;
                }
                if ($variable) {
                    $this->variable = $variable;
                }
                if ($additional) {
                    $this->additional = $additional;
                }
                if ($special) {
                    $this->special = $special;
                }
            }

            $this->validateOperator();
        }

        protected function validateOperator(): void
        {
            if (!in_array($this->operator, self::getOperators())) {
                throw new Exception("Invalid operator", $this->getOperator());
            }
        }

        /**
         * @param Criteria $criteria
         */
        public function setCriteria(Criteria $criteria): void
        {
            $this->criteria = $criteria;
        }

        /**
         * @return Criteria
         */
        public function getCriteria(): Criteria
        {
            return $this->criteria;
        }

        public function getColumn(): string
        {
            return $this->column;
        }

        public function setColumn(string $column): void
        {
            $this->column = $column;
        }

        /**
         * @return mixed
         */
        public function getValue()
        {
            return $this->value;
        }

        /**
         * @param mixed $value
         */
        public function setValue($value): void
        {
            $this->value = $value;
        }

        public function getOperator(): string
        {
            return $this->operator;
        }

        public function setOperator(string $operator): void
        {
            $this->operator = $operator;
            $this->validateOperator();
        }

        public function getVariable(): string
        {
            return $this->variable ?? '';
        }

        public function setVariable(string $variable): void
        {
            $this->variable = $variable;
        }

        public function getAdditional(): string
        {
            return $this->additional;
        }

        public function setAdditional(string $additional): void
        {
            $this->additional = $additional;
        }

        public function getSpecial(): string
        {
            return $this->special;
        }

        public function setSpecial(string $special): void
        {
            $this->special = $special;
        }

        protected function getQuery(): Query
        {
            return $this->criteria->getQuery();
        }

        public function isNullTypeOperator(): bool
        {
            return in_array($this->operator, [self::IS_NOT_NULL, self::IS_NULL]);
        }

        public function isInTypeOperator(): bool
        {
            return in_array($this->operator, [self::IN, self::NOT_IN]);
        }

        public function getSql(bool $strip_table_name = false): string
        {
            if (isset($this->sql)) {
                return $this->sql;
            }

            $column = ($strip_table_name) ? Table::getColumnName($this->column) : $this->getQuery()->getSelectionColumn($this->column);
            $initial_sql = SqlGenerator::quoteIdentifier($column);

            if (isset($this->special)) {
                $sql = "$this->special($initial_sql)";
            } else {
                $sql = $initial_sql;
            }

            if ($this->value === null && !$this->isNullTypeOperator()) {
                $this->operator = ($this->operator === self::EQUALS) ? self::IS_NULL : self::IS_NOT_NULL;
            } elseif (is_array($this->value) && $this->operator !== self::NOT_IN) {
                $this->operator = self::IN;
            }

            $sql .= " $this->operator ";

            if (!$this->isNullTypeOperator()) {
                if (is_array($this->value)) {
                    $placeholders = [];
                    for ($cc = 0, $ccMax = count($this->value); $cc < $ccMax; $cc++) {
                        $placeholders[] = '?';
                    }
                    $sql .= '(' . implode(', ', $placeholders) . ')';
                } else {
                    $sql .= $this->isInTypeOperator() ? '(?)' : '?';
                }
            }

            $this->sql = $sql;
            return $sql;
        }

    }
