<?php

    namespace b2db;

    /**
     * B2DB Saveable class, active record implementation for B2DB
     *
     * @package b2db
     *
     * @property ?int $id
     * @property ?int $_id
     */
    class Saveable
    {

        /**
         * @var array<string, mixed>
         */
        protected array $b2db_initial_values = [];

        /**
         * Return the associated Table class for this class
         *
         * @see Table::getTable()
         *
         * @return Table
         * @access protected
         */
        public static function getB2dbTable(): Table
        {
            $b2db_table_name = Core::getCachedB2DBTableClass('\\'. static::class);
            return call_user_func([$b2db_table_name, 'getTable']);
        }

        public static function getB2dbCachedObjectIfAvailable(int $id, string $classname, Row $row = null): ?Saveable
        {
            $has_cached = self::getB2dbTable()->hasCachedB2DBObject($id);
            $object = ($has_cached) ? self::getB2dbTable()->getB2dbCachedObject($id) : new $classname($id, $row);

            if (!$has_cached) {
                self::getB2dbTable()->cacheB2DBObject($id, $object);
            }

            return $object;
        }

        /** @noinspection PhpUnused */
        protected function _b2dbLazyCount(string $property): int
        {
            $relation_details = Core::getCachedEntityRelationDetails('\\'.get_class($this), $property);
            if (array_key_exists('manytomany', $relation_details) && $relation_details['manytomany']) {
                $table = $relation_details['joinclass'];
            } else {
                $table = Core::getCachedB2DBTableClass($relation_details['class']);
            }

            /** @var Table $table */
            return $table::getTable()->countForeignItems($this, $relation_details);
        }

        /**
         * @return null|Saveable[]|Saveable
         * @noinspection PhpUnused
         */
        protected function _b2dbLazyLoad(string $property, bool $use_cache = true)
        {
            $relation_details = Core::getCachedEntityRelationDetails('\\'.get_class($this), $property);
            if ($relation_details['collection']) {
                if (array_key_exists('manytomany', $relation_details) && $relation_details['manytomany']) {
                    $table = $relation_details['joinclass'];
                } elseif (array_key_exists('class', $relation_details) && $relation_details['class']) {
                    $table = Core::getCachedB2DBTableClass($relation_details['class']);
                } elseif (array_key_exists('joinclass', $relation_details) && $relation_details['joinclass']) {
                    $table = $relation_details['joinclass'];
                }
                /**
                 * @var Saveable[] $items
                 * @var Table $table
                 */
                $items = (isset($table)) ? $table::getTable()->getForeignItems($this, $relation_details) : null;
                $this->$property = $items ?? array();
            } elseif (is_numeric($this->$property)) {
                if ($this->$property > 0) {
                    if ($relation_details && class_exists($relation_details['class'])) {
                        /** @var Saveable $classname */
                        $classname = $relation_details['class'];
                        try {
                            if (!$use_cache) {
                                $this->$property = new $classname($this->$property);
                            } else {
                                /** @var Saveable $classname */
                                $this->$property = $classname::getB2dbCachedObjectIfAvailable($this->$property, (string) $classname);
                            }
                        } catch (\Exception $e) {
                            $this->$property = null;
                        }
                    } else {
                        throw new \Exception("Unknown class definition for property $property in class \\" . get_class($this));
                    }
                } else {
                    return null;
                }
            }
            return $this->$property;
        }

        protected function _populatePropertiesFromRow(Row $row, bool $traverse = true, string $foreign_key = null): void
        {
            $table = self::getB2dbTable();
            $id_column = $table->getIdColumn();
            $this_class = '\\'.get_class($this);
            foreach ($table->getColumns() as $column) {
                if ($column['name'] === $id_column) {
                    continue;
                }

                $property_name = $column['property'];
                $property_type = $column['type'];
                if (!property_exists($this, $property_name)) {
                    throw new \Exception("Could not find class property $property_name in class " . $this_class . ". The class must have all properties from the corresponding B2DB table class available");
                }
                if ($traverse && $row->get($column['name']) > 0 && in_array($column['name'], $table->getForeignColumns())) {
                    $relation_details = Core::getCachedEntityRelationDetails($this_class, $property_name);
                    if ($relation_details && class_exists($relation_details['class'])) {
                        foreach ($row->getJoinedTables() as $join_details) {
                            if ($join_details['original_column'] === $column['name']) {
                                $property_type = 'class';
                                break;
                            }
                        }
                    }
                }
                switch ($property_type) {
                    case 'serializable':
                        /** @noinspection UnserializeExploitsInspection */
                        $this->$property_name = unserialize($row->get($column['name'], $foreign_key));
                        break;
                    case 'class':
                        $value = (int) $row->get($column['name']);
                        $relation_details = Core::getCachedEntityRelationDetails($this_class, $property_name);
                        $type_name = $relation_details['class'];
                        $this->$property_name = new $type_name($value, $row, false, $column['name']);
                        break;
                    case 'boolean':
                        $this->$property_name = (bool) $row->get($column['name'], $foreign_key);
                        break;
                    case 'integer':
                        $this->$property_name = (int) $row->get($column['name'], $foreign_key);
                        break;
                    case 'float':
                        $this->$property_name = (float) $row->get($column['name'], $foreign_key);
                        break;
                    case 'text':
                    case 'varchar':
                        $this->$property_name = (string) $row->get($column['name'], $foreign_key);
                        break;
                    default:
                        $this->$property_name = $row->get($column['name'], $foreign_key);
                }
            }
        }

        protected function _preInitialize(): void {}

        protected function _postInitialize(): void {}

        protected function _construct(Row $row, string $foreign_key = null): void {}

        protected function _clone(): void {}

        protected function _preSave(bool $is_new):void {}

        protected function _postSave(bool $is_new): void {}

        protected function _preDelete(): void {}

        protected function _postDelete(): void {}

        protected function _preMorph(): void {}

        protected function _postMorph(Saveable $original_object): void {}

        /**
         * @return mixed
         */
        public function getB2dbSaveablePropertyValue(string $property_name)
        {
            if (!property_exists($this, $property_name)) {
                throw new \Exception("Could not find class property '$property_name' in class \\" . get_class($this) . ". The class must have all properties from the corresponding B2DB table class available");
            }
            if (is_object($this->$property_name)) {
                return (int) $this->$property_name->getID();
            }

            return $this->$property_name;
        }

        public function getB2dbID(): int
        {
            $column = self::getB2dbTable()->getIdColumn();
            $property = explode('.', $column);
            $property_name = (property_exists($this, $property[1])) ? $property[1] : "_$property[1]";
            return $this->$property_name;
        }

        protected function _b2dbResetInitialValues(): void
        {
            foreach (self::getB2dbTable()->getColumns() as $column) {
                $property = mb_strtolower($column['property']);
                $value = $this->getB2dbSaveablePropertyValue($property);
                $this->b2db_initial_values[$property] = $value;
            }
        }

        final public function __construct(int $id = null, Row $row = null, bool $traverse = true, string $foreign_key = null)
        {
            if (isset($id)) {
                if (!is_numeric($id)) {
                    throw new \Exception('Please specify a valid id for object of type \\' . get_class($this));
                }
                if ($row === null) {
                    $row = self::getB2dbTable()->rawSelectById($id);
                }

                if (!$row instanceof Row) {
                    throw new \Exception('The specified id (' . $id . ') does not exist in table ' . self::getB2dbTable()->getB2dbName());
                }
                $this->_preInitialize();
                $table = self::getB2dbTable();
                if (property_exists($this, 'id')) {
                    $this->id = (int) $id;
                }
                if (property_exists($this, '_id')) {
                    $this->_id = (int) $id;
                }
                $this->_populatePropertiesFromRow($row, $traverse, $foreign_key);
                $this->_b2dbResetInitialValues();
                $this->_construct($row, $foreign_key);
                $this->_postInitialize();
                $table->cacheB2DBObject($id, $this);
            } else {
                $this->_preInitialize();
            }
        }

        final public function __clone()
        {
            if (property_exists($this, 'id')) {
                $this->id = null;
            }
            if (property_exists($this, '_id')) {
                $this->_id = null;
            }
            $this->_clone();
        }

        final public function isB2DBValueChanged(string $property): bool
        {
            return $this->b2db_initial_values[$property] !== $this->getB2dbSaveablePropertyValue($property);
        }

        final public function save(): void
        {
            if (property_exists($this, 'id')) {
                $is_new = !(bool) $this->id;
            } else {
                $is_new = !(bool) $this->_id;
            }
            $this->_preSave($is_new);
            $res_id = self::getB2dbTable()->saveObject($this);
            $this->_b2dbResetInitialValues();
            if (property_exists($this, 'id')) {
                $this->id = $res_id;
            } else {
                $this->_id = $res_id;
            }
            if ($is_new || Core::getCacheEntitiesStrategy() === Core::CACHE_TYPE_MEMCACHED) {
                self::getB2dbTable()->cacheB2DBObject($res_id, $this);
            }
            $this->_postSave($is_new);
        }

        final public function delete(): void
        {
            $id = $this->getB2dbID();

            $this->_preDelete();
            self::getB2dbTable()->rawDeleteById($id);
            self::getB2dbTable()->deleteB2DBObjectFromCache($id);
            $this->_postDelete();
        }

        /**
         * @return array<string, mixed>
         */
        final protected function _b2dbGetMorphedDataArray(): array
        {
            $data = [];
            foreach (self::getB2dbTable()->getColumns() as $column) {
                $property_name = $column['property'];
                $data[$property_name] = $this->$property_name;
            }

            return $data;
        }

        final public function _b2dbPopulateMorphedData(Saveable $original_object, bool $keep_id = true): void
        {
            $this->_preMorph();
            $data = $original_object->_b2dbGetMorphedDataArray();
            $table = self::getB2dbTable();
            $id_column = $table->getIdColumn();
            foreach ($table->getColumns() as $column) {
                if (!$keep_id && $column['name'] === $id_column) {
                    continue;
                }

                $property_name = $column['property'];
                if (!array_key_exists($property_name, $data)) {
                    continue;
                }

                $this->$property_name = $data[$property_name];
            }
            $this->_postMorph($original_object);
        }

        /**
         * Returns an existing Saveable object morphed to an object of a
         * different class - either the one specified, or as specified by the
         * @SubClass annotation
         *
         * @param ?string $classname The FQCN to morph into
         * @param ?bool $keep_id Whether to keep the id or not
         *
         * @return Saveable The morphed object, or this object if unmorphed
         */
        final public function morph(string $classname = null, ?bool $keep_id = true): ?Saveable
        {
            if (!isset($classname)) {
                $table = self::getB2dbTable();
                $classnames = Core::getCachedTableEntityClasses('\\'.get_class($table));
                if ($classnames === null) {
                    return $this;
                }
                $columns = $table->getColumns();
                $property = $columns[$table->getB2dbName() . '.' . $classnames['identifier']]['property'];
                $identifier = $this->$property;
                $classname = $classnames['classes'][$identifier] ?? null;
                if (!$classname) {
                    throw new Exception("No classname has been specified in the @SubClasses annotation for identifier '$identifier'");
                }
            }
            $morphed_object = new $classname();
            if ($morphed_object instanceof self) {
                $morphed_object->_b2dbPopulateMorphedData($this, $keep_id);
            }
            return $morphed_object;
        }

        public function __toString(): string
        {
            return static::class;
        }

    }
