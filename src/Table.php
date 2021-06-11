<?php

    namespace b2db;

    use b2db\interfaces\QueryInterface;

    /**
     * Table class
     *
     * @package b2db
     */
    class Table
    {

        /**
         * @var array<string, string>
         */
        protected static array $column_names = [];

        protected string $b2db_name;

        protected string $id_column;

        protected string $b2db_alias;

        /**
         * @var array<int, Saveable>
         */
        protected array $cached_entities = [];

        /**
         * @var array<string, array<string, int|string|bool>|string>
         */
        protected array $columns;

        /**
         * @var array<string, array<string, array<string>|array<string, string>|string|null>>
         */
        protected array $indexes = [];

        protected string $charset = 'utf8';

        protected int $autoincrement_start_at = 1;

        /**
         * @var ForeignTable[]
         */
        protected array $foreign_tables;

        /**
         * @var array<string, array<string, string|bool|int>|string>
         */
        protected array $foreign_columns = [];

        public function __clone()
        {
            $this->b2db_alias = $this->b2db_name . Core::addAlias();
        }

        final public function __construct()
        {
            if ($entity_class = Core::getCachedTableEntityClass('\\'. static::class)) {
                if ($details = Core::getCachedTableDetails($entity_class)) {
                    $this->columns = $details['columns'];
                    $this->foreign_columns = $details['foreign_columns'];
                    $this->b2db_name = $details['name'];
                    $this->b2db_alias = $details['name'] . Core::addAlias();
                    $this->id_column = $details['id'];
                }
            } else {
                $this->initialize();
            }
        }

        protected function initialize(): void
        {
            throw new Exception('The table "\\' . get_class($this) . '" has no corresponding entity class. You must override the initialize() method to set up the table details.');
        }

        protected function setup(string $b2db_name, string $id_column): void
        {
            $this->b2db_name = $b2db_name;
            $this->b2db_alias = $b2db_name . Core::addAlias();
            $this->id_column = $id_column;
            $this->addInteger($id_column, 10, 0, true, true, true);
        }

        /**
         * Return an instance of this table
         */
        public static function getTable(): Table
        {
            return Core::getTable('\\'. static::class);
        }

        public static function getColumnName(string $column): string
        {
            if (!isset(self::$column_names[$column])) {
                if (mb_stripos($column, '.') > 0) {
                    self::$column_names[$column] = mb_substr($column, mb_stripos($column, '.') + 1);
                } else {
                    self::$column_names[$column] = $column;
                }
            }

            return self::$column_names[$column];
        }

        /**
         * @param string $column
         * @param array<string, int|string|bool> $details
         */
        protected function addColumn(string $column, array $details): void
        {
            $this->columns[$column] = $details;
        }

        protected function addInteger(string $column, int $length = 10, int $default_value = 0, bool $not_null = false, bool $auto_inc = false, bool $unsigned = false): void
        {
            $this->addColumn($column, ['type' => 'integer', 'name' => $column, 'length' => $length, 'default_value' => $default_value, 'not_null' => $not_null, 'auto_inc' => $auto_inc, 'unsigned' => $unsigned]);
        }

        protected function addFloat(string $column, int $precision = 2, int $default_value = 0, bool $not_null = false, bool $auto_inc = false, bool $unsigned = false): void
        {
            $this->addColumn($column, ['type' => 'float', 'name' => $column, 'precision' => $precision, 'default_value' => $default_value, 'not_null' => $not_null, 'auto_inc' => $auto_inc, 'unsigned' => $unsigned]);
        }

        protected function addVarchar(string $column, int $length = null, string $default_value = null, bool $not_null = false): void
        {
            $this->addColumn($column, ['type' => 'varchar', 'name' => $column, 'length' => $length, 'default_value' => $default_value, 'not_null' => $not_null]);
        }

        protected function addText(string $column, bool $not_null = false): void
        {
            $this->addColumn($column, ['type' => 'text', 'name' => $column, 'not_null' => $not_null]);
        }

        protected function addBlob(string $column, bool $not_null = false): void
        {
            $this->addColumn($column, ['type' => 'blob', 'name' => $column, 'not_null' => $not_null]);
        }

        protected function addBoolean(string $column, bool $default_value = false, bool $not_null = false): void
        {
            $this->addColumn($column, ['type' => 'boolean', 'name' => $column, 'default_value' => ($default_value) ? 1 : 0, 'not_null' => $not_null]);
        }

        /**
         * @param string $index_name
         * @param array<string>|string $columns
         * @param string|null $index_type
         */
        protected function addIndex(string $index_name, $columns, string $index_type = null): void
        {
            if (!is_array($columns)) {
                $columns = [$columns];
            }

            $this->indexes[$index_name] = ['columns' => $columns, 'type' => $index_type];
        }

        /**
         * Adds a foreign table
         *
         * @param string $column
         * @param Table $table
         * @param ?string $key
         */
        protected function addForeignKeyColumn(string $column, Table $table, string $key = null): void
        {
            $add_table = clone $table;
            $key = $key ?? $add_table->getIdColumn();
            $foreign_column = $add_table->getColumn($key);
            switch ($foreign_column['type']) {
                case 'integer':
                    $this->addInteger($column, $foreign_column['length'], $foreign_column['default_value'], false, false, $foreign_column['unsigned']);
                    break;
                case 'float':
                    $this->addFloat($column, $foreign_column['precision'], $foreign_column['default_value'], false, false, $foreign_column['unsigned']);
                    break;
                case 'varchar':
                    $this->addVarchar($column, $foreign_column['length'], $foreign_column['default_value']);
                    break;
                case 'text':
                case 'boolean':
                case 'blob':
                    throw new Exception('Cannot use a text, blob or boolean column as a foreign key');
            }
            $this->foreign_columns[$column] = ['class' => '\\'.get_class($table), 'key' => $key, 'name' => $column];
        }

        public function getForeignTableByLocalColumn(string $column): ?ForeignTable
        {
            foreach ($this->getForeignTables() as $joined_table) {
                if ($joined_table->getColumn() === $column) {
                    return $joined_table;
                }
            }

            return null;
        }

        public function __toString(): string
        {
            return $this->b2db_name;
        }

        /**
         * Sets the charset to something other than "latin1" which is the default
         */
        public function setCharset(string $charset): void
        {
            $this->charset = $charset;
        }

        /**
         * Sets the initial auto_increment value to something else than 1
         */
        public function setAutoIncrementStart(int $start_at): void
        {
            $this->autoincrement_start_at = $start_at;
        }

        protected function getQuoteCharacter(): string
        {
            if (Core::getDriver() === Core::DRIVER_POSTGRES) {
                return '"';
            }

            return '`';
        }

        /**
         * Returns the table name
         */
        public function getB2dbName(): string
        {
            return $this->b2db_name;
        }

        /**
         * Returns the table alias
         */
        public function getB2dbAlias(): string
        {
            return $this->b2db_alias;
        }

        protected function initializeForeignTables(): void
        {
            $this->foreign_tables = [];
            foreach ($this->getForeignColumns() as $column) {
                $table_classname = $column['class'];

                /** @var Table $table_classname */
                $table = clone $table_classname::getTable();
                $key = ($column['key']) ?? $table->getIdColumn();
                $this->foreign_tables[$table->getB2dbAlias()] = new ForeignTable($table, $key, $column['name']);
            }
        }

        /**
         * @return ForeignTable[]
         */
        public function getForeignTables(): array
        {
            if (!isset($this->foreign_tables)) {
                $this->initializeForeignTables();
            }
            return $this->foreign_tables;
        }

        /**
         * @return array<string, array<string, string>>
         */
        public function getForeignColumns(): array
        {
            return $this->foreign_columns;
        }

        /**
         * Returns a foreign table
         * @noinspection PhpUnused
         */
        public function getForeignTable(Table $table): ?ForeignTable
        {
            return $this->foreign_tables[$table->getB2dbAlias()] ?? null;
        }

        /**
         * Returns the id column for this table
         */
        public function getIdColumn(): string
        {
            return $this->id_column;
        }

        /**
         * @return array<string, array<string, int|string|bool>>
         */
        public function getColumns(): array
        {
            return $this->columns;
        }

        /**
         * @return array<string, int|string|bool>
         */
        public function getColumn(string $column): array
        {
            return $this->columns[$column];
        }

        protected function getRealColumnFieldName(string $column): string
        {
            return mb_substr($column, mb_stripos($column, '.') + 1);
        }

        /**
         * @return string[]
         */
        public function getAliasColumns(): array
        {
            $columns = [];

            foreach ($this->columns as $column => $col_data) {
                $column_name = explode('.', $column);
                $columns[] = $this->b2db_alias . '.' . $column_name[1];
            }

            return $columns;
        }

        /**
         * Selects all records in this table
         */
        public function rawSelectAll(): Resultset
        {
            $query = new Query($this);
            $query->generateSelectSQL(true);

            return Statement::getPreparedStatement($query)->execute();
        }

        /**
         * @return Saveable[]
         */
        public function selectAll(): array
        {
            $resultset = $this->rawSelectAll();
            return $this->populateFromResultset($resultset);
        }

        /**
         * Returns one row from the current table based on a given id
         *
         * @param int $id
         * @param Query|null $query
         * @param mixed $join
         *
         * @return ?Row
         */
        public function rawSelectById(int $id, Query $query = null, $join = 'all'): ?Row
        {
            if (!$id) {
                return null;
            }

            if (!$query instanceof Query) {
                $query = new Query($this);
            }
            $query->where($this->id_column, $id);
            $query->setLimit(1);
            return $this->rawSelectOne($query, $join);
        }

        /**
         * Select an object by its unique id
         *
         * @param int $id
         * @param ?Query $query (optional) Criteria with filters
         * @param ?mixed $join
         *
         * @return ?Saveable
         */
        public function selectById(int $id, Query $query = null, $join = 'all'): ?Saveable
        {
            if (!$this->hasCachedB2DBObject($id)) {
                $row = $this->rawSelectById($id, $query, $join);
                return $this->populateFromRow($row);
            }

            return $this->getB2dbCachedObject($id);
        }

        /**
         * Counts rows
         */
        public function count(Query $query): int
        {
            $query->setTable($this);
            $query->generateCountSQL();

            $resultset = Statement::getPreparedStatement($query)->execute();
            return $resultset->getCount();
        }

        /**
         * Selects rows based on given criteria
         *
         * @param Query $query
         * @param mixed $join
         *
         * @return ?Resultset
         */
        public function rawSelect(Query $query, $join = 'all'): ?Resultset
        {
            if (!$query instanceof Query) {
                $query = new Query($this);
            }

            $query->setupJoinTables($join);
            $query->generateSelectSQL();

            $resultset = Statement::getPreparedStatement($query)->execute();
            return ($resultset->count()) ? $resultset : null;
        }

        /**
         * Perform a select query and return all matching entries
         *
         * @param Query $query
         * @param mixed $join
         *
         * @return Saveable[]
         */
        public function select(Query $query, $join = 'all'): array
        {
            $resultset = $this->rawSelect($query, $join);
            return $this->populateFromResultset($resultset);
        }

        /**
         * Selects one row from the table based on the given criteria
         *
         * @param Query $query
         * @param mixed $join
         *
         * @return ?Row
         * @throws Exception
         */
        public function rawSelectOne(Query $query, $join = 'all'): ?Row
        {
            $query->setTable($this);
            $query->setupJoinTables($join);
            $query->setLimit(1);
            $query->generateSelectSQL();

            $resultset = Statement::getPreparedStatement($query)->execute();
            $resultset->next();

            return $resultset->getCurrentRow();
        }

        /**
         * Selects one object based on the given criteria
         *
         * @param Query $query
         * @param mixed $join
         *
         * @return ?Saveable
         */
        public function selectOne(Query $query, $join = 'all'): ?Saveable
        {
            $row = $this->rawSelectOne($query, $join);
            return $this->populateFromRow($row);
        }

        /**
         * Inserts a row into the table
         *
         * @param Insertion $insertion
         *
         * @return ?Resultset
         */
        public function rawInsert(Insertion $insertion): ?Resultset
        {
            $query = new Query($this);
            $query->generateInsertSQL($insertion);

            return Statement::getPreparedStatement($query)->execute();
        }

        /**
         * Perform an SQL update
         *
         * @return Resultset
         */
        public function rawUpdate(Update $update, Query $query = null): ?Resultset
        {
            if ($query === null) {
                $query = new Query($this);
            }
            $query->generateUpdateSQL($update);

            $value = Statement::getPreparedStatement($query)->execute();
            $this->clearB2DBCachedObjects();

            return $value;
        }

        /**
         * Perform an SQL update
         *
         * @param Update $update
         * @param int $id
         *
         * @return ?Resultset
         */
        public function rawUpdateById(Update $update, int $id): ?Resultset
        {
            if (!$id) {
                return null;
            }

            $query = new Query($this);
            $query->where($this->id_column, $id);
            $query->setLimit(1);
            $query->generateUpdateSQL($update);

            $value = Statement::getPreparedStatement($query)->execute();
            $this->deleteB2DBObjectFromCache($id);

            return $value;
        }

        /**
         * Perform an SQL delete
         *
         * @param Query $query
         *
         * @return ?Resultset
         */
        public function rawDelete(Query $query): ?Resultset
        {
            $query->setTable($this);
            $query->generateDeleteSQL();

            $value = Statement::getPreparedStatement($query)->execute();
            $this->clearB2DBCachedObjects();

            return $value;
        }

        /**
         * Perform an SQL delete by an id
         */
        public function rawDeleteById(int $id): ?Resultset
        {
            if (!$id) {
                return null;
            }

            $query = new Query($this);
            $query->where($this->id_column, $id);
            $query->generateDeleteSQL();

            $this->deleteB2DBObjectFromCache($id);

            return Statement::getPreparedStatement($query)->execute();
        }

        /**
         * creates the table by executing the sql create statement
         *
         * @return ?Resultset
         */
        public function create(): ?Resultset
        {
            $sql = $this->generateCreateSql();
            $query = new RawQuery($sql);
            try {
                $this->drop();
                return Statement::getPreparedStatement($query)->execute();
            } catch (\Exception $e) {
                throw new Exception('Error creating table ' . $this->getB2dbName() . ': ' . $e->getMessage(), $sql);
            }
        }

        protected function setupIndexes(): void {}

        public function createIndexes(): void
        {
            try {
                $this->setupIndexes();
                $qc = $this->getQuoteCharacter();

                foreach ($this->indexes as $index_name => $details) {
                    $index_column_sql_list = array();
                    foreach ($details['columns'] as $column) {
                        $index_column_sql_list[] = $qc . $this->getRealColumnFieldName($column) . $qc;
                    }
                    switch (Core::getDriver()) {
                        case Core::DRIVER_POSTGRES:
                            $sql = " CREATE INDEX " . Core::getTablePrefix() . $this->b2db_name . "_$index_name ON " . $this->getSqlTableName() . " (".implode(', ', $index_column_sql_list).')';
                            break;
                        case Core::DRIVER_MYSQL:
                            $sql = " ALTER TABLE " . $this->getSqlTableName() . " ADD INDEX " . Core::getTablePrefix() . $this->b2db_name . "_$index_name (".implode(', ', $index_column_sql_list).')';
                            break;
                    }

                    if (isset($sql)) {
                        $query = new RawQuery($sql);
                        Statement::getPreparedStatement($query)->execute();
                    }
                }
            } catch (Exception $e) {
                throw new Exception('An error occurred when trying to create indexes for table "' . $this->getB2dbName() . '" (defined in "\\' . get_class($this) . ')": ' . $e->getMessage(), $e->getSQL());
            }
        }

        protected function generateDropSql(): string
        {
            return 'DROP TABLE IF EXISTS ' . Core::getTablePrefix() . $this->b2db_name;
        }

        /**
         * Drops a table
         *
         * @return ?Resultset
         */
        public function drop(): ?Resultset
        {
            $sql = $this->generateDropSql();
            try {
                $query = new RawQuery($sql);
                $statement = Statement::getPreparedStatement($query);
                return $statement->execute();
            } catch (\Exception $e) {
                throw new Exception('Error dropping table ' . $this->getB2dbName() . ': ' . $e->getMessage(), $sql);
            }
        }

        /**
         * Return a new criteria with this table as the from-table
         *
         * @param bool $setup_join_tables [optional] Whether to auto-join all related tables by default
         *
         * @return Query
         */
        public function getQuery(bool $setup_join_tables = false): Query
        {
            return new Query($this, $setup_join_tables);
        }

        /**
         * @param mixed $value
         * @param string $type
         *
         * @return bool|int|mixed|string
         */
        public function formatify($value, string $type)
        {
            switch ($type) {
                case 'serializable':
                    return serialize($value);
                case 'float':
                    return (float) $value;
                case 'varchar':
                case 'text':
                    return (string) $value;
                case 'integer':
                    return (int) $value;
                case 'boolean':
                    return (bool) $value;
                default:
                    return $value;
            }
        }

        public function saveObject(Saveable $object): ?int
        {
            $id = $object->getB2dbID();
            if ($id) {
                $update = new Update();
            } else {
                $insertion = new Insertion();
            }

            $changed = false;

            foreach ($this->getColumns() as $column) {
                if (!array_key_exists('property', $column)) {
                    throw new Exception('Could not match all columns to properties for object of type \\' . get_class($object) . ". Make sure you're not mixing between initializing the table manually and using column (property) annotations");
                }

                $property = $column['property'];
                $value = $this->formatify($object->getB2dbSaveablePropertyValue(mb_strtolower($property)), $column['type']);

                if ($id && !$object->isB2DBValueChanged($property)) {
                    continue;
                }

                if ($value instanceof Saveable) {
                    $value = $value->getB2dbID();
                }

                if (in_array($column['name'], $this->foreign_columns)) {
                    $value = ($value) ? (int) $value : null;
                }

                if ($id && isset($update)) {
                    $changed = true;
                    $update->add($column['name'], $value);
                } elseif (isset($insertion) && $column['name'] !== $this->getIdColumn()) {
                    $insertion->add($column['name'], $value);
                }
            }

            if ($id && isset($update)) {
                if ($changed) {
                    $this->rawUpdateById($update, $id);
                }
                $res_id = $id;
            } elseif (isset($insertion)) {
                $res = $this->rawinsert($insertion);
                $res_id = $res instanceof Resultset ? $res->getInsertID() : null;
            }

            return $res_id ?? null;
        }

        /**
         * @param array<string, int|string> $column
         *
         * @return string
         */
        protected function getColumnDefaultDefinitionSQL(array $column): string
        {
            $sql = '';
            if (isset($column['default_value'])) {
                if (is_int($column['default_value'])) {
                    if ($column['type'] === 'boolean') {
                        $sql .= ' DEFAULT ';
                        $sql .= ($column['default_value']) ? 'true' : 'false';
                    } else {
                        $sql .= ' DEFAULT ' . $column['default_value'];
                    }
                } else {
                    $sql .= ' DEFAULT \'' . $column['default_value'] . '\'';
                }
            }

            return $sql;
        }

        /**
         * @param array<string, int|string|bool> $column
         * @param bool $alter
         *
         * @return string
         */
        protected function getColumnDefinitionSQL(array $column, bool $alter = false): string
        {
            $sql = '';
            switch ($column['type']) {
                case 'integer':
                    if (Core::getDriver() === Core::DRIVER_POSTGRES) {
                        if (isset($column['auto_inc']) && $column['auto_inc'] === true) {
                            $sql .= 'SERIAL';
                        } else {
                            $sql .= 'INTEGER';
                        }
                    } else {
                        $sql .= 'INTEGER(' . $column['length'] . ')';
                        if ($column['unsigned']) {
                            $sql .= ' UNSIGNED';
                        }
                    }
                    break;
                case 'varchar':
                case 'serializable':
                    if (!$column['length']) {
                        throw new Exception("Column '{$column['name']}' (defined in \\" . get_class($this) . ") is missing required 'length' property");
                    }
                    $sql .= 'VARCHAR(' . $column['length'] . ')';
                    break;
                case 'float':
                    $sql .= 'FLOAT(' . $column['precision'] . ')';
                    if ($column['unsigned'] && Core::getDriver() !== Core::DRIVER_POSTGRES) {
                        $sql .= ' UNSIGNED';
                    }
                    break;
                case 'blob':
                    if (Core::getDriver() === Core::DRIVER_MYSQL) {
                        $sql .= 'LONGBLOB';
                    } elseif (Core::getDriver() === Core::DRIVER_POSTGRES) {
                        $sql .= 'BYTEA';
                    } else {
                        $sql .= 'BLOB';
                    }
                    break;
                case 'text':
                case 'boolean':
                    $sql .= mb_strtoupper($column['type']);
                    break;
            }
            if ($column['not_null']) {
                $sql .= ' NOT NULL';
            }

            if ($column['type'] !== 'text') {
                if (isset($column['auto_inc']) && $column['auto_inc'] === true && Core::getDriver() !== Core::DRIVER_POSTGRES) {
                    $sql .= ' AUTO_INCREMENT';
                } elseif (isset($column['default_value']) && $column['default_value'] !== null && !(Core::getDriver() === Core::DRIVER_POSTGRES && $alter) && !(isset($column['auto_inc']) && $column['auto_inc'] === true && Core::getDriver() === Core::DRIVER_POSTGRES)) {
                    $sql .= $this->getColumnDefaultDefinitionSQL($column);
                }
            }
            return $sql;
        }

        public function getSqlTableName(): string
        {
            $qc = $this->getQuoteCharacter();
            return $qc . Core::getTablePrefix() . $this->b2db_name . $qc;
        }

        protected function generateCreateSql(): string
        {
            $sql = '';
            $qc = $this->getQuoteCharacter();
            $sql .= "CREATE TABLE " . $this->getSqlTableName() . " (\n";
            $field_sql = array();
            foreach ($this->columns as $column) {
                $_sql = " $qc" . $this->getRealColumnFieldName($column['name']) . "$qc ";
                $field_sql[] = $_sql . $this->getColumnDefinitionSQL($column);
            }
            $sql .= implode(",\n", $field_sql);
            $sql .= ", PRIMARY KEY ($qc" . $this->getRealColumnFieldName($this->id_column) . "$qc) ";
            $sql .= ') ';
            if (Core::getDriver() !== Core::DRIVER_POSTGRES) {
                $sql .= 'AUTO_INCREMENT=' . $this->autoincrement_start_at . ' ';
                $sql .= 'CHARACTER SET ' . $this->charset;
            }

            return $sql;
        }

        /**
         * @param array<string, int|string|bool> $details
         *
         * @return RawQuery
         */
        protected function getAddColumnQuery(array $details): RawQuery
        {
            $qc = $this->getQuoteCharacter();

            $sql = 'ALTER TABLE ' . $this->getSqlTableName();
            $sql .= " ADD COLUMN $qc" . $this->getRealColumnFieldName($details['name']) . "$qc " . $this->getColumnDefinitionSQL($details);

            return new RawQuery($sql);
        }

        /**
         * @param array<string, int|string|bool> $details
         *
         * @return RawQuery
         */
        protected function getAlterColumnQuery(array $details): ?RawQuery
        {
            $sql = 'ALTER TABLE ' . $this->getSqlTableName();
            $qc = $this->getQuoteCharacter();
            switch (Core::getDriver()) {
                case Core::DRIVER_MYSQL:
                    $sql .= " MODIFY $qc" . $this->getRealColumnFieldName($details['name']) . "$qc ";
                    break;
                case Core::DRIVER_POSTGRES:
                    $sql .= " ALTER COLUMN $qc" . $this->getRealColumnFieldName($details['name']) . "$qc TYPE ";
                    break;
            }
            $sql .= $this->getColumnDefinitionSQL($details, true);

            return new RawQuery($sql);
        }

        /**
         * @param array<string, int|string|bool> $details
         *
         * @return ?RawQuery
         */
        protected function getAlterColumnDefaultQuery(array $details): ?RawQuery
        {
            /** @noinspection DegradedSwitchInspection */
            switch (Core::getDriver()) {
                case Core::DRIVER_POSTGRES:
                    $default_definition = $this->getColumnDefaultDefinitionSQL($details);
                    if ($default_definition) {
                        $sql = 'ALTER TABLE ' . $this->getSqlTableName();
                        $qc = $this->getQuoteCharacter();
                        $sql .= " ALTER COLUMN $qc" . $this->getRealColumnFieldName($details['name']) . "$qc SET";
                        $sql .= $default_definition;
                        return new RawQuery($sql);
                    }
                    break;
            }

            return null;
        }

        protected function getDropColumnQuery(string $column): RawQuery
        {
            $sql = 'ALTER TABLE ' . $this->getSqlTableName();
            $sql .= ' DROP COLUMN ' . $this->getRealColumnFieldName($column);

            return new RawQuery($sql);
        }

        protected function migrateData(Table $old_table): void {}

        /**
         * Perform upgrade for a table, by comparing one table to an old version
         * of the same table
         *
         * @param Table $old_table
         */
        public function upgrade(Table $old_table): void
        {
            $old_columns = $old_table->getColumns();
            $new_columns = $this->getColumns();

            $added_columns = array_diff_key($new_columns, $old_columns);
            $altered_columns = Tools::array_diff_recursive($old_columns, $new_columns);
            $dropped_columns = array_keys(array_diff_key($old_columns, $new_columns));

            /** @var RawQuery[] $queries */
            $queries = [];
            foreach ($added_columns as $details) {
                $queries[] = $this->getAddColumnQuery($details);
            }

            foreach ($queries as $query) {
                if ($query instanceof QueryInterface) {
                    Statement::getPreparedStatement($query)->execute();
                }
            }

            $this->migrateData($old_table);

            $queries = [];
            foreach ($altered_columns as $column => $details) {
                if (in_array($column, $dropped_columns)) {
                    continue;
                }

                $queries[] = $this->getAlterColumnQuery($new_columns[$column]);
                $queries[] = $this->getAlterColumnDefaultQuery($new_columns[$column]);
            }
            foreach ($dropped_columns as $column) {
                $queries[] = $this->getDropColumnQuery($column);
            }
            foreach ($queries as $query) {
                if ($query instanceof RawQuery) {
                    Statement::getPreparedStatement($query)->execute();
                }
            }
        }

        /**
         * @param Row $row
         * @param array<string, string|array<string, string>> $classnames
         * @return string
         * @throws Exception
         */
        protected function getSubclassNameFromRow(Row $row, array $classnames): string
        {
            $identifier = $row->get($this->getB2dbName() . '.' . $classnames['identifier']);
            $classname = $classnames['classes'][$identifier] ?? null;
            if (!$classname) {
                throw new Exception("No classname has been specified in the @SubClasses annotation for identifier '$identifier'");
            }

            return $classname;
        }

        /**
         * @param Row|null $row
         * @param ?string $classname
         * @param ?string $id_column
         * @param ?string $row_id
         * @param bool $from_resultset
         *
         * @return ?Saveable
         */
        protected function populateFromRow(Row $row = null, string $classname = null, string $id_column = null, string $row_id = null, bool $from_resultset = false): ?Saveable
        {
            $item = null;
            if ($row instanceof Row) {
                if (!$from_resultset && Core::isDebugMode()) {
                    $previous_time = Core::getDebugTime();
                }
                if ($classname === null) {
                    $classnames = Core::getCachedTableEntityClasses('\\'.get_class($this));
                    if ($classnames) {
                        $classname = $this->getSubclassNameFromRow($row, $classnames);
                    } else {
                        $classname = Core::getCachedTableEntityClass('\\'.get_class($this));
                    }
                    if (!$classname) {
                        throw new Exception("Classname '$classname' or subclasses for table '{$this->getB2dbName()}' is not valid");
                    }
                }

                $id_column = ($id_column) ?? $row->getQuery()->getTable()->getIdColumn();
                $row_id = $row_id ?? $row->get($id_column);
                /** @var Saveable $classname */
                $is_cached = $classname::getB2dbTable()->hasCachedB2DBObject($row_id);
                $item = $classname::getB2dbCachedObjectIfAvailable($row_id, $classname, $row);
                if (!$from_resultset && !$is_cached && isset($previous_time) && Core::isDebugMode()) {
                    Core::objectPopulationHit(1, [$classname], $previous_time);
                }
            }
            return $item;
        }

        /**
         * @param ?Resultset $resultset
         * @param ?string $classname
         * @param ?string $id_column
         * @param ?string $index_column
         *
         * @return Saveable[]
         */
        protected function populateFromResultset(Resultset $resultset = null, string $classname = null, string $id_column = null, string $index_column = null): array
        {
            $items = [];
            if ($resultset instanceof Resultset) {
                if (Core::isDebugMode()) {
                    $previous_time = Core::getDebugTime();
                    $populated_classnames = [];
                    $populated_classes = 0;
                }

                $query = $resultset->getQuery();
                $id_column = ($id_column) ?? $query->getTable()->getIdColumn();
                if ($index_column === null) {
                    $index_column = ($query->hasIndexBy()) ? $query->getIndexBy() : $id_column;
                }
                $classnames = Core::getCachedTableEntityClasses('\\'.get_class($this));
                if ($classname === null) {
                    $classname = Core::getCachedTableEntityClass('\\'.get_class($this));
                }
                while ($row = $resultset->getNextRow()) {
                    if ($classnames) {
                        $classname = $this->getSubclassNameFromRow($row, $classnames);
                    }
                    $row_id = $row->get($id_column);
                    /** @var Table $table */
                    $table = call_user_func([$classname, 'getB2dbTable']);
                    $is_cached = $table->hasCachedB2DBObject($row_id);
                    $item = $this->populateFromRow($row, $classname, $id_column, $row_id, true);
                    $items[$row->get($index_column)] = $item;
                    if (!$is_cached && isset($populated_classes) && Core::isDebugMode()) {
                        $populated_classnames[$classname] = $classname;
                        $populated_classes++;
                    }
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if (Core::isDebugMode() && isset($populated_classes, $populated_classnames, $previous_time)) {
                    Core::objectPopulationHit($populated_classes, $populated_classnames, $previous_time);
                }
            }
            return $items;
        }

        /**
         * @param Saveable $class
         * @param array<string, string|bool|int> $relation_details
         *
         * @return array<int, Query|string>
         */
        public function generateForeignItemsQuery(Saveable $class, array $relation_details): array
        {
            $query = $this->getQuery();
            $foreign_table = $class->getB2dbTable();
            $foreign_table_class = '\\'.get_class($foreign_table);
            $item_class = $relation_details['class'] ?? null;
            $item_column = null;
            $item_table_class = null;
            if ($relation_details['manytomany']) {
                $item_table_class = Core::getCachedB2DBTableClass($item_class);
            }
            if ($relation_details['foreign_column']) {
                $saveable_class = '\\'.get_class($class);
                $table_details = ($item_class) ? Core::getCachedTableDetails($item_class) : Core::getTableDetails($relation_details['joinclass']);
                if ($relation_details['orderby']) {
                    $order = $relation_details['sort_order'] ?? QueryColumnSort::SORT_ASC;
                    $query->addOrderBy("{$table_details['name']}." . $relation_details['orderby'], $order);
                }
                $query->where("{$table_details['name']}." . $relation_details['foreign_column'], $class->getB2dbSaveablePropertyValue(Core::getCachedColumnPropertyName($saveable_class, $foreign_table->getIdColumn())));
                if (array_key_exists('discriminator', $table_details) && $table_details['discriminator'] && array_key_exists($saveable_class, $table_details['discriminator']['discriminators'])) {
                    $query->where($table_details['discriminator']['column'], $table_details['discriminator']['discriminators'][$saveable_class]);
                }
            } else {
                foreach ($this->getForeignColumns() as $column => $details) {
                    if ($details['class'] === $foreign_table_class) {
                        $foreign_column = ($details['key']) ?: $foreign_table->getIdColumn();
                        $property_name = Core::getCachedColumnPropertyName(Core::getCachedTableEntityClass($details['class']), $foreign_column);
                        $value = $class->getB2dbSaveablePropertyValue($property_name);
                        $query->where($column, $value);
                    } elseif ($item_class && $details['class'] === $item_table_class) {
                        $item_column = $column;
                    }
                }
                if ($relation_details['orderby']) {
                    $query->addOrderBy($foreign_table->getB2dbName() . "." . $relation_details['orderby']);
                }
            }
            return array($query, $item_class, $item_column);
        }

        /**
         * @param Saveable $class
         * @param array<string, string|bool|int> $relation_details
         *
         * @return Saveable[]
         */
        public function getForeignItems(Saveable $class, array $relation_details): array
        {
            [$query, $item_class, $item_column] = $this->generateForeignItemsQuery($class, $relation_details);
            if (!$item_class) {
                $items = array();
                $resultset = $this->rawSelect($query);
                if ($resultset) {
                    $column = "{$this->getB2dbName()}." . $relation_details['column'];
                    while ($row = $resultset->getNextRow()) {
                        $items[] = $row->get($column);
                    }
                }
                return $items;
            }

            if (!$relation_details['manytomany']) {
                return $this->select($query);
            }

            $resultset = $this->rawSelect($query);
            return $this->populateFromResultset($resultset, $item_class, $item_column, $item_column);
        }

        /**
         * @param Saveable $class
         * @param array<string, string|bool|int> $relation_details
         *
         * @return int
         */
        public function countForeignItems(Saveable $class, array $relation_details): int
        {
            [$query, ,] = $this->generateForeignItemsQuery($class, $relation_details);
            return $this->count($query);
        }

        public function cacheB2DBObject(int $id, Saveable $object): void
        {
            if (Core::getCacheEntities()) {
                switch (Core::getCacheEntitiesStrategy()) {
                    case Core::CACHE_TYPE_INTERNAL:
                        $this->cached_entities[$id] = $object;
                        break;

                    case Core::CACHE_TYPE_MEMCACHED:
                        Core::getCacheEntitiesObject()->set('b2db.' . static::class . '.' . $id, $object);
                        break;
                }
            }
        }

        public function deleteB2DBObjectFromCache(int $id): void
        {
            if ($this->hasCachedB2DBObject($id)) {
                switch (Core::getCacheEntitiesStrategy()) {
                    case Core::CACHE_TYPE_INTERNAL:
                        unset($this->cached_entities[$id]);
                        break;

                    case Core::CACHE_TYPE_MEMCACHED:
                        Core::getCacheEntitiesObject()->delete('b2db.' . static::class . '.' . $id);
                        break;
                }
            }
        }

        public function hasCachedB2DBObject(int $id): bool
        {
            switch (Core::getCacheEntitiesStrategy()) {
                case Core::CACHE_TYPE_INTERNAL:
                    return array_key_exists($id, $this->cached_entities);

                case Core::CACHE_TYPE_MEMCACHED:
                    return Core::getCacheEntitiesObject()->has('b2db.' . static::class . '.' . $id);
            }

            return false;
        }

        public function clearB2DBCachedObjects(): void
        {
            switch (Core::getCacheEntitiesStrategy()) {
                case Core::CACHE_TYPE_INTERNAL:
                    $this->cached_entities = [];
                    break;

                case Core::CACHE_TYPE_MEMCACHED:
                    Core::getCacheEntitiesObject()->flush();
            }
        }

        public function getB2dbCachedObject(int $id): ?Saveable
        {
            switch (Core::getCacheEntitiesStrategy()) {
                case Core::CACHE_TYPE_INTERNAL:
                    return $this->cached_entities[$id];

                case Core::CACHE_TYPE_MEMCACHED:
                    return Core::getCacheEntitiesObject()->get('b2db.' . static::class . '.' . $id);
            }

            return null;
        }

        public function getSelectFromSql(): string
        {
            $name = $this->getSqlTableName();
            $alias = $this->getB2dbAlias();

            return $name . ' ' . SqlGenerator::quoteIdentifier($alias);
        }

    }
