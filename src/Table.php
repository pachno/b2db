<?php

    namespace b2db;

    use b2db\interfaces\QueryInterface;

    /**
     * Table class
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 2.0
     * @license http://www.opensource.org/licenses/mozilla1.1.php Mozilla Public License 1.1 (MPL 1.1)
     * @package b2db
     * @subpackage core
     */

    /**
     * Table class
     *
     * @package b2db
     * @subpackage core
     */
    class Table
    {

        protected static $column_names = [];

        /**
         * @var string
         */
        protected $b2db_name;

        protected $id_column;

        protected $b2db_alias;

        protected $cached_entities = array();

        protected $columns;

        protected $indexes = array();

        protected $charset = 'utf8';

        protected $autoincrement_start_at = 1;

        /**
         * @var ForeignTable[]
         */
        protected $foreign_tables = null;

        /**
         * @var Table[][]
         */
        protected $foreign_columns = [];

        public function __clone()
        {
            $this->b2db_alias = $this->b2db_name . Core::addAlias();
        }

        final public function __construct()
        {
            if ($entity_class = Core::getCachedTableEntityClass('\\'.get_called_class())) {
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

        protected function initialize()
        {
            throw new Exception('The table "\\' . get_class($this) . '" has no corresponding entity class. You must override the initialize() method to set up the table details.');
        }

        protected function setup($b2db_name, $id_column)
        {
            $this->b2db_name = $b2db_name;
            $this->b2db_alias = $b2db_name . Core::addAlias();
            $this->id_column = $id_column;
            $this->addInteger($id_column, 10, 0, true, true, true);
        }

        /**
         * Return an instance of this table
         *
         * @return Table
         */
        public static function getTable()
        {
            return Core::getTable('\\'.get_called_class());
        }

        public static function getColumnName($column)
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

        protected function addColumn($column, $details)
        {
            $this->columns[$column] = $details;
        }

        protected function addInteger($column, $length = 10, $default_value = 0, $not_null = false, $auto_inc = false, $unsigned = false)
        {
            $this->addColumn($column, ['type' => 'integer', 'name' => $column, 'length' => $length, 'default_value' => $default_value, 'not_null' => $not_null, 'auto_inc' => $auto_inc, 'unsigned' => $unsigned]);
        }

        protected function addFloat($column, $precision = 2, $default_value = 0, $not_null = false, $auto_inc = false, $unsigned = false)
        {
            $this->addColumn($column, ['type' => 'float', 'name' => $column, 'precision' => $precision, 'default_value' => $default_value, 'not_null' => $not_null, 'auto_inc' => $auto_inc, 'unsigned' => $unsigned]);
        }

        protected function addVarchar($column, $length = null, $default_value = null, $not_null = false)
        {
            $this->addColumn($column, ['type' => 'varchar', 'name' => $column, 'length' => $length, 'default_value' => $default_value, 'not_null' => $not_null]);
        }

        protected function addText($column, $not_null = false)
        {
            $this->addColumn($column, ['type' => 'text', 'name' => $column, 'not_null' => $not_null]);
        }

        protected function addBlob($column, $not_null = false)
        {
            $this->addColumn($column, ['type' => 'blob', 'name' => $column, 'not_null' => $not_null]);
        }

        protected function addBoolean($column, $default_value = false, $not_null = false)
        {
            $this->addColumn($column, ['type' => 'boolean', 'name' => $column, 'default_value' => ($default_value) ? 1 : 0, 'not_null' => $not_null]);
        }

        protected function addIndex($index_name, $columns, $index_type = null)
        {
            if (!is_array($columns)) {
                $columns = array($columns);
            }

            $this->indexes[$index_name] = ['columns' => $columns, 'type' => $index_type];
        }

        /**
         * Adds a foreign table
         *
         * @param string $column
         * @param Table $table
         * @param string $key
         */
        protected function addForeignKeyColumn($column, Table $table, $key = null)
        {
            $add_table = clone $table;
            $key = ($key !== null) ? $key : $add_table->getIdColumn();
            $foreign_column = $add_table->getColumn($key);
            switch ($foreign_column['type']) {
                case 'integer':
                    $this->addInteger($column, $foreign_column['length'], $foreign_column['default_value'], false, false, $foreign_column['unsigned']);
                    break;
                case 'float':
                    $this->addFloat($column, $foreign_column['precision'], $foreign_column['default_value'], false, false, $foreign_column['unsigned']);
                    break;
                case 'varchar':
                    $this->addVarchar($column, $foreign_column['length'], $foreign_column['default_value'], false);
                    break;
                case 'text':
                case 'boolean':
                case 'blob':
                    throw new Exception('Cannot use a text, blob or boolean column as a foreign key');
            }
            $this->foreign_columns[$column] = array('class' => '\\'.get_class($table), 'key' => $key, 'name' => $column);
        }

        /**
         * @param $column
         * @return ForeignTable
         */
        public function getForeignTableByLocalColumn($column)
        {
            foreach ($this->getForeignTables() as $joined_table) {
                if ($joined_table->getColumn() == $column) {
                    return $joined_table;
                }
            }
        }

        public function __toString()
        {
            return $this->b2db_name;
        }

        /**
         * Sets the charset to something other than "latin1" which is the default
         *
         * @param string $charset
         */
        public function setCharset($charset)
        {
            $this->charset = $charset;
        }

        /**
         * Sets the initial auto_increment value to something else than 1
         *
         * @param integer $start_at
         */
        public function setAutoIncrementStart($start_at)
        {
            $this->autoincrement_start_at = $start_at;
        }

        protected function getQC()
        {
            $qc = '`';
            switch (Core::getDriver()) {
                case Core::DRIVER_POSTGRES:
                    $qc = '"';
                    break;
            }
            return $qc;
        }

        /**
         * Returns the table name
         *
         * @return string
         */
        public function getB2DBName()
        {
            return $this->b2db_name;
        }

        /**
         * Returns the table alias
         *
         * @return string
         */
        public function getB2DBAlias()
        {
            return $this->b2db_alias;
        }

        protected function initializeForeignTables()
        {
            $this->foreign_tables = [];
            foreach ($this->getForeignColumns() as $column) {
                $table_classname = $column['class'];
                $table = clone $table_classname::getTable();
                $key = ($column['key']) ?? $table->getIdColumn();
                $this->foreign_tables[$table->getB2DBAlias()] = new ForeignTable($table, $key, $column['name']);
            }
        }

        /**
         * @return ForeignTable[]
         */
        public function getForeignTables()
        {
            if ($this->foreign_tables === null) {
                $this->initializeForeignTables();
            }
            return $this->foreign_tables;
        }

        public function getForeignColumns()
        {
            return $this->foreign_columns;
        }

        /**
         * Returns a foreign table
         *
         * @param Table $table
         *
         * @return array
         */
        public function getForeignTable($table)
        {
            return $this->foreign_tables[$table->getB2DBAlias()];
        }

        /**
         * Returns the id column for this table
         *
         * @return string
         */
        public function getIdColumn()
        {
            return $this->id_column;
        }

        public function getColumns()
        {
            return $this->columns;
        }

        public function getColumn($column)
        {
            return $this->columns[$column];
        }

        protected function getRealColumnFieldName($column)
        {
            return mb_substr($column, mb_stripos($column, '.') + 1);
        }

        public function getAliasColumns()
        {
            $columns = array();

            foreach ($this->columns as $column => $col_data) {
                $column_name = explode('.', $column);
                $columns[] = $this->b2db_alias . '.' . $column_name[1];
            }

            return $columns;
        }

        /**
         * Selects all records in this table
         *
         * @return Resultset
         */
        public function rawSelectAll()
        {
            $query = new Query($this);
            $query->generateSelectSQL(true);

            return Statement::getPreparedStatement($query)->execute();
        }

        public function selectAll()
        {
            $resultset = $this->rawSelectAll();
            return $this->populateFromResultset($resultset);
        }

        /**
         * Returns one row from the current table based on a given id
         *
         * @param integer $id
         * @param Query|null $query
         * @param mixed $join
         *
         * @return \b2db\Row|null
         */
        public function rawSelectById($id, Query $query = null, $join = 'all')
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
         * @param integer $id
         * @param Query $query (optional) Criteria with filters
         * @param mixed $join
         *
         * @return Saveable
         * @throws Exception
         */
        public function selectById($id, Query $query = null, $join = 'all')
        {
            if (!$this->hasCachedB2DBObject($id)) {
                $row = $this->rawSelectById($id, $query, $join);
                $object = $this->populateFromRow($row);
            } else {
                $object = $this->getB2DBCachedObject($id);
            }
            return $object;
        }

        /**
         * Counts rows
         *
         * @param Query $query
         *
         * @return integer
         */
        public function count(Query $query)
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
         *
         * @param string $join
         * @return Resultset
         */
        public function rawSelect(Query $query, $join = 'all')
        {
            if (!$query instanceof Query) {
                $query = new Query($this);
            }

            $query->setupJoinTables($join);
            $query->generateSelectSQL();

            $resultset = Statement::getPreparedStatement($query)->execute();
            return ($resultset->count()) ? $resultset : null;
        }

        public function select(Query $query, $join = 'all')
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
         * @return Row
         * @throws Exception
         */
        public function rawSelectOne(Query $query, $join = 'all')
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
         * @return Saveable
         */
        public function selectOne(Query $query, $join = 'all')
        {
            $row = $this->rawSelectOne($query, $join);
            return $this->populateFromRow($row);
        }

        /**
         * Inserts a row into the table
         *
         * @param Insertion $insertion
         *
         * @return Resultset
         */
        public function rawInsert(Insertion $insertion)
        {
            $query = new Query($this);
            $query->generateInsertSQL($insertion);

            return Statement::getPreparedStatement($query)->execute();
        }

        /**
         * Perform an SQL update
         *
         * @param Update $update
         *
         * @param Query|null $query
         * @return Resultset
         * @throws Exception
         */
        public function rawUpdate(Update $update, Query $query = null)
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
         * @param integer $id
         *
         * @return Resultset|null
         */
        public function rawUpdateById(Update $update, $id)
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
         * @return Resultset
         */
        public function rawDelete(Query $query)
        {
            $query->setTable($this);
            $query->generateDeleteSQL();

            $value = Statement::getPreparedStatement($query)->execute();
            $this->clearB2DBCachedObjects();

            return $value;
        }

        /**
         * Perform an SQL delete by an id
         *
         * @param integer $id
         *
         * @return Resultset|null
         */
        public function rawDeleteById($id)
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
         * @return Resultset
         */
        public function create()
        {
            $sql = $this->generateCreateSql();
            $query = new RawQuery($sql);
            try {
                $this->drop();
                return Statement::getPreparedStatement($query)->execute();
            } catch (\Exception $e) {
                throw new Exception('Error creating table ' . $this->getB2DBName() . ': ' . $e->getMessage(), $sql);
            }
        }

        protected function setupIndexes() {}

        public function createIndexes()
        {
            try {
                $this->setupIndexes();
                $qc = $this->getQC();

                foreach ($this->indexes as $index_name => $details) {
                    $index_column_sqls = array();
                    foreach ($details['columns'] as $column) {
                        $index_column_sqls[] = $qc . $this->getRealColumnFieldName($column) . $qc;
                    }
                    switch (Core::getDriver()) {
                        case Core::DRIVER_POSTGRES:
                            $sql = " CREATE INDEX " . Core::getTablePrefix() . $this->b2db_name . "_{$index_name} ON " . $this->getSqlTableName() . " (".join(', ', $index_column_sqls).')';
                            break;
                        case Core::DRIVER_MYSQL:
                            $sql = " ALTER TABLE " . $this->getSqlTableName() . " ADD INDEX " . Core::getTablePrefix() . $this->b2db_name . "_{$index_name} (".join(', ', $index_column_sqls).')';
                            break;
                    }

                    if (isset($sql)) {
                        $query = new RawQuery($sql);
                        Statement::getPreparedStatement($query)->execute();
                    }
                }
            } catch (Exception $e) {
                throw new Exception('An error occured when trying to create indexes for table "' . $this->getB2DBName() . '" (defined in "\\' . get_class($this) . ')": ' . $e->getMessage(), $e->getSQL());
            }
        }

        protected function generateDropSql()
        {
            return 'DROP TABLE IF EXISTS ' . Core::getTablePrefix() . $this->b2db_name;
        }

        /**
         * Drops a table
         *
         * @return null
         */
        public function drop()
        {
            $sql = $this->generateDropSql();
            try {
                $query = new RawQuery($sql);
                $statement = Statement::getPreparedStatement($query);
                return $statement->execute();
            } catch (\Exception $e) {
                throw new Exception('Error dropping table ' . $this->getB2DBName() . ': ' . $e->getMessage(), $sql);
            }
        }

        /**
         * Return a new criteria with this table as the from-table
         *
         * @param boolean $setup_join_tables [optional] Whether to auto-join all related tables by default
         *
         * @return Query
         */
        public function getQuery($setup_join_tables = false)
        {
            $query = new Query($this, $setup_join_tables);
            return $query;
        }

        public function formatify($value, $type)
        {
            switch ($type) {
                case 'serializable':
                    return serialize($value);
                case 'float':
                    settype($value, 'float');
                    return $value;
                case 'varchar':
                case 'text':
                    return (string) $value;
                case 'integer':
                    return (integer) $value;
                case 'boolean':
                    return (boolean) $value;
                default:
                    return $value;
            }
        }

        public function saveObject(Saveable $object)
        {
            $id = $object->getB2DBID();
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
                $value = $this->formatify($object->getB2DBSaveablePropertyValue(mb_strtolower($property)), $column['type']);

                if ($id && !$object->isB2DBValueChanged($property)) {
                    continue;
                }

                if ($value instanceof Saveable) {
                    $value = (int) $value->getB2DBID();
                }

                if (in_array($column['name'], $this->foreign_columns)) {
                    $value = ($value) ? (int) $value : null;
                }

                if ($id) {
                    $changed = true;
                    $update->add($column['name'], $value);
                } elseif ($column['name'] != $this->getIdColumn()) {
                    $insertion->add($column['name'], $value);
                }
            }

            if ($id) {
                if ($changed) {
                    $this->rawUpdateById($update, $id);
                }
                $res_id = $id;
            } else {
                $res = $this->rawinsert($insertion);
                $res_id = $res->getInsertID();
            }

            return $res_id;
        }

        protected function getColumnDefaultDefinitionSQL($column)
        {
            $sql = '';
            if (isset($column['default_value'])) {
                if (is_int($column['default_value'])) {
                    if ($column['type'] == 'boolean') {
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

        protected function getColumnDefinitionSQL($column, $alter = false)
        {
            $sql = '';
            switch ($column['type']) {
                case 'integer':
                    if (Core::getDriver() == Core::DRIVER_POSTGRES && isset($column['auto_inc']) && $column['auto_inc'] == true) {
                        $sql .= 'SERIAL';
                    } elseif (Core::getDriver() == Core::DRIVER_POSTGRES) {
                        $sql .= 'INTEGER';
                    } else {
                        $sql .= 'INTEGER(' . $column['length'] . ')';
                    }
                    if ($column['unsigned'] && Core::getDriver() != Core::DRIVER_POSTGRES)
                        $sql .= ' UNSIGNED';
                    break;
                case 'varchar':
                case 'serializable':
                    if (!$column['length'])
                        throw new Exception("Column '{$column['name']}' (defined in \\" . get_class($this) . ") is missing required 'length' property");
                    $sql .= 'VARCHAR(' . $column['length'] . ')';
                    break;
                case 'float':
                    $sql .= 'FLOAT(' . $column['precision'] . ')';
                    if ($column['unsigned'] && Core::getDriver() != Core::DRIVER_POSTGRES)
                        $sql .= ' UNSIGNED';
                    break;
                case 'blob':
                    if (Core::getDriver() == Core::DRIVER_MYSQL) {
                        $sql .= 'LONGBLOB';
                    } elseif (Core::getDriver() == Core::DRIVER_POSTGRES) {
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
            if ($column['not_null'])
                $sql .= ' NOT NULL';
            if ($column['type'] != 'text') {
                if (isset($column['auto_inc']) && $column['auto_inc'] == true && Core::getDriver() != Core::DRIVER_POSTGRES) {
                    $sql .= ' AUTO_INCREMENT';
                } elseif (isset($column['default_value']) && $column['default_value'] !== null && !(Core::getDriver() == Core::DRIVER_POSTGRES && $alter) && !(isset($column['auto_inc']) && $column['auto_inc'] == true && Core::getDriver() == Core::DRIVER_POSTGRES)) {
                    $sql .= $this->getColumnDefaultDefinitionSQL($column);
                }
            }
            return $sql;
        }

        public function getSqlTableName()
        {
            $qc = $this->getQC();
            $sql = $qc . Core::getTablePrefix() . $this->b2db_name . $qc;

            return $sql;
        }

        protected function generateCreateSql()
        {
            $sql = '';
            $qc = $this->getQC();
            $sql .= "CREATE TABLE " . $this->getSqlTableName() . " (\n";
            $field_sql = array();
            foreach ($this->columns as $column) {
                $_sql = " $qc" . $this->getRealColumnFieldName($column['name']) . "$qc ";
                $field_sql[] = $_sql . $this->getColumnDefinitionSQL($column);
            }
            $sql .= join(",\n", $field_sql);
            $sql .= ", PRIMARY KEY ($qc" . $this->getRealColumnFieldName($this->id_column) . "$qc) ";
            $sql .= ') ';
            if (Core::getDriver() != Core::DRIVER_POSTGRES) {
                $sql .= 'AUTO_INCREMENT=' . $this->autoincrement_start_at . ' ';
                $sql .= 'CHARACTER SET ' . $this->charset;
            }

            return $sql;
        }

        /**
         * @param $details
         * @return RawQuery
         */
        protected function getAddColumnQuery($details)
        {
            $qc = $this->getQC();

            $sql = 'ALTER TABLE ' . $this->getSqlTableName();
            $sql .= " ADD COLUMN $qc" . $this->getRealColumnFieldName($details['name']) . "$qc " . $this->getColumnDefinitionSQL($details);

            return new RawQuery($sql);
        }

        /**
         * @param $details
         * @return RawQuery
         */
        protected function getAlterColumnQuery($details)
        {
            $sql = 'ALTER TABLE ' . $this->getSqlTableName();
            $qc = $this->getQC();
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
         * @param $details
         * @return RawQuery
         */
        protected function getAlterColumnDefaultQuery($details)
        {
            switch (Core::getDriver()) {
                case Core::DRIVER_POSTGRES:
                    $default_definition = $this->getColumnDefaultDefinitionSQL($details);
                    if ($default_definition) {
                        $sql = 'ALTER TABLE ' . $this->getSqlTableName();
                        $qc = $this->getQC();
                        $sql .= " ALTER COLUMN $qc" . $this->getRealColumnFieldName($details['name']) . "$qc SET";
                        $sql .= $default_definition;
                        return new RawQuery($sql);
                    }
                    break;
            }

            return null;
        }

        /**
         * @param $details
         * @return RawQuery
         */
        protected function getDropColumnQuery($details)
        {
            $sql = 'ALTER TABLE ' . $this->getSqlTableName();
            $sql .= ' DROP COLUMN ' . $this->getRealColumnFieldName($details);

            return new RawQuery($sql);
        }

        protected function migrateData(Table $old_table)
        {

        }

        /**
         * Perform upgrade for a table, by comparing one table to an old version
         * of the same table
         *
         * @param Table $old_table
         */
        public function upgrade(Table $old_table)
        {
            $old_columns = $old_table->getColumns();
            $new_columns = $this->getColumns();

            $added_columns = \array_diff_key($new_columns, $old_columns);
            $altered_columns = Tools::array_diff_recursive($old_columns, $new_columns);
            $dropped_columns = \array_keys(array_diff_key($old_columns, $new_columns));

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
                if (in_array($column, $dropped_columns)) continue;

                $queries[] = $this->getAlterColumnQuery($new_columns[$column]);
                $queries[] = $this->getAlterColumnDefaultQuery($new_columns[$column]);
            }
            foreach ($dropped_columns as $details) {
                $queries[] = $this->getDropColumnQuery($details);
            }
            foreach ($queries as $query) {
                if ($query instanceof RawQuery) {
                    Statement::getPreparedStatement($query)->execute();
                }
            }
        }

        protected function getSubclassNameFromRow(Row $row, $classnames)
        {
            $identifier = $row->get($this->getB2DBName() . '.' . $classnames['identifier']);
            $classname = (\array_key_exists($identifier, $classnames['classes'])) ? $classnames['classes'][$identifier] : null;
            if (!$classname) {
                throw new Exception("No classname has been specified in the @SubClasses annotation for identifier '{$identifier}'");
            }

            return $classname;
        }

        /**
         * @param Row|null $row
         * @param Saveable $classname
         * @param null $id_column
         * @param null $row_id
         * @param bool $from_resultset
         *
         * @return Saveable|null
         */
        protected function populateFromRow(Row $row = null, $classname = null, $id_column = null, $row_id = null, $from_resultset = false)
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
                        throw new Exception("Classname '{$classname}' or subclasses for table '{$this->getB2DBName()}' is not valid");
                    }
                }

                $id_column = ($id_column) ?? $row->getQuery()->getTable()->getIdColumn();
                $row_id = ($row_id !== null) ? $row_id : $row->get($id_column);
                $is_cached = $classname::getB2DBTable()->hasCachedB2DBObject($row_id);
                $item = $classname::getB2DBCachedObjectIfAvailable($row_id, $classname, $row);
                if (!$from_resultset && !$is_cached && Core::isDebugMode()) {
                    Core::objectPopulationHit(1, array($classname), $previous_time);
                }
            }
            return $item;
        }

        /**
         * @param Resultset|null $resultset
         * @param Saveable $classname
         * @param null $id_column
         * @param null $index_column
         *
         * @return Saveable[]
         */
        protected function populateFromResultset(Resultset $resultset = null, $classname = null, $id_column = null, $index_column = null)
        {
            $items = array();
            if ($resultset instanceof Resultset) {
                if (Core::isDebugMode()) {
                    $previous_time = Core::getDebugTime();
                    $populated_classnames = array();
                    $populated_classes = 0;
                }

                $query = $resultset->getQuery();
                $id_column = ($id_column) ?? $query->getTable()->getIdColumn();
                if ($index_column === null) {
                    $index_column = ($query->getIndexBy()) ? $query->getIndexBy() : $id_column;
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
                    $is_cached = $classname::getB2DBTable()->hasCachedB2DBObject($row_id);
                    $item = $this->populateFromRow($row, $classname, $id_column, $row_id, true);
                    $items[$row->get($index_column)] = $item;
                    if (!$is_cached && Core::isDebugMode()) {
                        $populated_classnames[$classname] = $classname;
                        $populated_classes++;
                    }
                }

                if (Core::isDebugMode()) {
                    Core::objectPopulationHit($populated_classes, $populated_classnames, $previous_time);
                }
            }
            return $items;
        }

        /**
         * @param Saveable $class
         * @param $relation_details
         *
         * @return Query[]|string[]
         */
        public function generateForeignItemsQuery(Saveable $class, $relation_details)
        {
            $query = $this->getQuery();
            $foreign_table = $class->getB2DBTable();
            $foreign_table_class = '\\'.get_class($foreign_table);
            $item_class = (array_key_exists('class', $relation_details)) ? $relation_details['class'] : null;
            $item_column = null;
            $item_table_class = null;
            if ($relation_details['manytomany']) {
                $item_table_class = Core::getCachedB2DBTableClass($item_class);
            }
            if ($relation_details['foreign_column']) {
                $saveable_class = '\\'.get_class($class);
                $table_details = ($item_class) ? Core::getCachedTableDetails($item_class) : Core::getTableDetails($relation_details['joinclass']);
                if ($relation_details['orderby']) {
                    $query->addOrderBy("{$table_details['name']}." . $relation_details['orderby']);
                }
                $query->where("{$table_details['name']}." . $relation_details['foreign_column'], $class->getB2DBSaveablePropertyValue(Core::getCachedColumnPropertyName($saveable_class, $foreign_table->getIdColumn())));
                if (array_key_exists('discriminator', $table_details) && $table_details['discriminator'] && array_key_exists($saveable_class, $table_details['discriminator']['discriminators'])) {
                    $query->where($table_details['discriminator']['column'], $table_details['discriminator']['discriminators'][$saveable_class]);
                }
            } else {
                foreach ($this->getForeignColumns() as $column => $details) {
                    if ($details['class'] == $foreign_table_class) {
                        $foreign_column = ($details['key']) ? $details['key'] : $foreign_table->getIdColumn();
                        $property_name = Core::getCachedColumnPropertyName(Core::getCachedTableEntityClass($details['class']), $foreign_column);
                        $value = $class->getB2DBSaveablePropertyValue($property_name);
                        $query->where($column, $value);
                    } elseif ($item_class && $details['class'] == $item_table_class) {
                        $item_column = $column;
                    }
                }
                if ($relation_details['orderby']) {
                    $query->addOrderBy($foreign_table->getB2DBName() . "." . $relation_details['orderby']);
                }
            }
            return array($query, $item_class, $item_column);
        }

        /**
         * @param Saveable $class
         * @param $relation_details
         *
         * @return Saveable[]
         */
        public function getForeignItems(Saveable $class, $relation_details)
        {
            list ($query, $item_class, $item_column) = $this->generateForeignItemsQuery($class, $relation_details);
            if (!$item_class) {
                $items = array();
                $resultset = $this->rawSelect($query);
                if ($resultset) {
                    $column = "{$this->getB2DBName()}." . $relation_details['column'];
                    while ($row = $resultset->getNextRow()) {
                        $items[] = $row->get($column);
                    }
                }
                return $items;
            } elseif (!$relation_details['manytomany']) {
                return $this->select($query);
            } else {
                $resultset = $this->rawSelect($query);

                return $this->populateFromResultset($resultset, $item_class, $item_column, $item_column);
            }
        }

        /**
         * @param Saveable $class
         * @param $relation_details
         *
         * @return int
         */
        public function countForeignItems(Saveable $class, $relation_details)
        {
            list ($query,,) = $this->generateForeignItemsQuery($class, $relation_details);
            $result = $this->count($query);
            return $result;
        }

        public function cacheB2DBObject($id, $object)
        {
            if (Core::getCacheEntities()) {
                switch (Core::getCacheEntitiesStrategy()) {
                    case Core::CACHE_TYPE_INTERNAL:
                        $this->cached_entities[$id] = $object;
                        break;

                    case Core::CACHE_TYPE_MEMCACHED:
                        Core::getCacheEntitiesObject()->set('b2db.' . get_called_class() . '.' . $id, $object);
                        break;
                }
            }
        }

        public function deleteB2DBObjectFromCache($id)
        {
            if ($this->hasCachedB2DBObject($id)) {
                switch (Core::getCacheEntitiesStrategy()) {
                    case Core::CACHE_TYPE_INTERNAL:
                        unset($this->cached_entities[$id]);
                        break;

                    case Core::CACHE_TYPE_MEMCACHED:
                        Core::getCacheEntitiesObject()->delete('b2db.' . get_called_class() . '.' . $id);
                        break;
                }
            }
        }

        public function hasCachedB2DBObject($id)
        {
            switch (Core::getCacheEntitiesStrategy()) {
                case Core::CACHE_TYPE_INTERNAL:
                    return array_key_exists($id, $this->cached_entities);

                case Core::CACHE_TYPE_MEMCACHED:
                    return Core::getCacheEntitiesObject()->has('b2db.' . get_called_class() . '.' . $id);
            }
        }

        public function clearB2DBCachedObjects()
        {
            switch (Core::getCacheEntitiesStrategy()) {
                case Core::CACHE_TYPE_INTERNAL:
                    $this->cached_entities = [];
                    break;

                case Core::CACHE_TYPE_MEMCACHED:
                    Core::getCacheEntitiesObject()->flush();
            }
        }

        /**
         * @param $id
         * @return Saveable
         */
        public function getB2DBCachedObject($id)
        {
            switch (Core::getCacheEntitiesStrategy()) {
                case Core::CACHE_TYPE_INTERNAL:
                    return $this->cached_entities[$id];

                case Core::CACHE_TYPE_MEMCACHED:
                    return Core::getCacheEntitiesObject()->get('b2db.' . get_called_class() . '.' . $id);
            }
        }

        /**
         * @return string
         */
        public function getSelectFromSql()
        {
            $name = $this->getSqlTableName();
            $alias = $this->getB2DBAlias();
            $sql = $name . ' ' . Query::quoteIdentifier($alias);

            return $sql;
        }

    }
