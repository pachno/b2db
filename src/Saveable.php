<?php

    namespace b2db;

    /**
     * B2DB Saveable class, active record implementation for B2DB
     *
     * @package b2db
     * @subpackage core
     */
    class Saveable
    {

        protected $b2db_initial_values = [];

        /**
         * Return the associated Table class for this class
         *
         * @return Table
         */
        public static function getB2DBTable()
        {
            $b2db_table_name = Core::getCachedB2DBTableClass('\\'.get_called_class());
            return $b2db_table_name::getTable();
        }

        /**
         * @param $id
         * @param $classname
         * @param Row|null $row
         * @return Saveable
         */
        public static function getB2DBCachedObjectIfAvailable($id, $classname, $row = null)
        {
            $has_cached = self::getB2DBTable()->hasCachedB2DBObject($id);
            $object = ($has_cached) ? self::getB2DBTable()->getB2DBCachedObject($id) : new $classname($id, $row);

            if (!$has_cached) {
                self::getB2DBTable()->cacheB2DBObject($id, $object);
            }

            return $object;
        }

        protected function _b2dbLazyCount($property)
        {
            $relation_details = Core::getCachedEntityRelationDetails('\\'.get_class($this), $property);
            if (array_key_exists('manytomany', $relation_details) && $relation_details['manytomany']) {
                $table = $relation_details['joinclass'];
            } else {
                $table = Core::getCachedB2DBTableClass($relation_details['class']);
            }
            $count = $table::getTable()->countForeignItems($this, $relation_details);
            return $count;
        }

        protected function _b2dbLazyLoad($property, $use_cache = true)
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
                $items = (isset($table)) ? $table::getTable()->getForeignItems($this, $relation_details) : null;
                $value = ($items !== null) ? $items : array();
                $this->$property = $value;
            } elseif (is_numeric($this->$property)) {
                if ($this->$property > 0) {
                    if ($relation_details && \class_exists($relation_details['class'])) {
                        /** @var Saveable $classname */
                        $classname = $relation_details['class'];
                        try {
                            if (!$use_cache) {
                                $this->$property = new $classname($this->$property);
                            } else {
                                $this->$property = $classname::getB2DBCachedObjectIfAvailable($this->$property, $classname);
                            }
                        } catch (\Exception $e) {
                            $this->$property = null;
                        }
                    } else {
                        throw new \Exception("Unknown class definition for property {$property} in class \\" . get_class($this));
                    }
                } else {
                    return null;
                }
            }
            return $this->$property;
        }

        protected function _populatePropertiesFromRow(Row $row, $traverse = true, $foreign_key = null)
        {
            $table = self::getB2DBTable();
            $id_column = $table->getIdColumn();
            $this_class = '\\'.get_class($this);
            foreach ($table->getColumns() as $column) {
                if ($column['name'] == $id_column) continue;

                $property_name = $column['property'];
                $property_type = $column['type'];
                if (!property_exists($this, $property_name)) {
                    throw new \Exception("Could not find class property {$property_name} in class " . $this_class . ". The class must have all properties from the corresponding B2DB table class available");
                }
                if ($traverse && in_array($column['name'], $table->getForeignColumns())) {
                    if ($row->get($column['name']) > 0) {
                        $relation_details = Core::getCachedEntityRelationDetails($this_class, $property_name);
                        if ($relation_details && class_exists($relation_details['class'])) {
                            foreach ($row->getJoinedTables() as $join_details) {
                                if ($join_details['original_column'] == $column['name']) {
                                    $property_type = 'class';
                                    break;
                                }
                            }
                        }
                    }
                }
                switch ($property_type) {
                    case 'serializable':
                        $this->$property_name = unserialize($row->get($column['name'], $foreign_key));
                        break;
                    case 'class':
                        $value = (int) $row->get($column['name']);
                        $relation_details = Core::getCachedEntityRelationDetails($this_class, $property_name);
                        $type_name = $relation_details['class'];
                        $this->$property_name = new $type_name($value, $row, false, $column['name']);
                        break;
                    case 'boolean':
                        $this->$property_name = (boolean) $row->get($column['name'], $foreign_key);
                        break;
                    case 'integer':
                        $this->$property_name = (integer) $row->get($column['name'], $foreign_key);
                        break;
                    case 'float':
                        $this->$property_name = floatval($row->get($column['name'], $foreign_key));
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

        protected function _preInitialize() {}

        protected function _postInitialize() {}

        protected function _construct(Row $row, $foreign_key = null) {}

        protected function _clone() {}

        protected function _preSave($is_new) {}

        protected function _postSave($is_new) {}

        protected function _preDelete() {}

        protected function _postDelete() {}

        protected function _preMorph() {}

        protected function _postMorph(Saveable $original_object) {}

        public function getB2DBSaveablePropertyValue($property_name)
        {
            if (!property_exists($this, $property_name)) {
                throw new \Exception("Could not find class property '{$property_name}' in class \\" . get_class($this) . ". The class must have all properties from the corresponding B2DB table class available");
            }
            if (is_object($this->$property_name)) {
                return (int) $this->$property_name->getID();
            } elseif (!is_object($this->$property_name)) {
                return $this->$property_name;
            }
        }

        public function getB2DBID()
        {
            $column = self::getB2DBTable()->getIdColumn();
            $property = explode('.', $column);
            $property_name = (property_exists($this, $property[1])) ? $property[1] : "_{$property[1]}";
            return $this->$property_name;
        }

        protected function _b2dbResetInitialValues()
        {
            foreach ($this->getB2DBTable()->getColumns() as $column) {
                $property = mb_strtolower($column['property']);
                $value = $this->getB2DBSaveablePropertyValue($property);
                $this->b2db_initial_values[$property] = $value;
            }
        }

        final public function __construct($id = null, $row = null, $traverse = true, $foreign_key = null)
        {
            if ($id != null) {
                if (!is_numeric($id)) {
                    throw new \Exception('Please specify a valid id for object of type \\' . get_class($this));
                }
                if ($row === null) {
                    $row = self::getB2DBTable()->rawSelectById($id);
                }

                if (!$row instanceof Row) {
                    throw new \Exception('The specified id (' . $id . ') does not exist in table ' . self::getB2DBTable()->getB2DBName());
                }
                try {
                    $this->_preInitialize();
                    $table = $this->getB2DBTable();
                    if (property_exists($this, 'id')) {
                        $this->id = (integer) $id;
                    }
                    if (property_exists($this, '_id')) {
                        $this->_id = (integer) $id;
                    }
                    $this->_populatePropertiesFromRow($row, $traverse, $foreign_key);
                    $this->_b2dbResetInitialValues();
                    $this->_construct($row, $foreign_key);
                    $this->_postInitialize();
                    $table->cacheB2DBObject($id, $this);
                } catch (\Exception $e) {
                    throw $e;
                }
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

        final public function isB2DBValueChanged($property)
        {
            return $this->b2db_initial_values[$property] !== $this->getB2DBSaveablePropertyValue($property);
        }

        final public function save()
        {
            if (property_exists($this, 'id')) {
                $is_new = !(bool) $this->id;
            } else {
                $is_new = !(bool) $this->_id;
            }
            $this->_preSave($is_new);
            $res_id = self::getB2DBTable()->saveObject($this);
            $this->_b2dbResetInitialValues();
            if (property_exists($this, 'id')) {
                $this->id = $res_id;
            } else {
                $this->_id = $res_id;
            }
            if ($is_new || Core::getCacheEntitiesStrategy() == Core::CACHE_TYPE_MEMCACHED) {
                $this->getB2DBTable()->cacheB2DBObject($res_id, $this);
            }
            $this->_postSave($is_new);
        }

        final public function delete()
        {
            $id = $this->getB2DBID();

            $this->_preDelete();
            self::getB2DBTable()->rawDeleteById($id);
            $this->getB2DBTable()->deleteB2DBObjectFromCache($id);
            $this->_postDelete();
        }

        final public function getB2DBMorphedDataArray()
        {
            $data = array();
            foreach (self::getB2DBTable()->getColumns() as $column) {
                $property_name = $column['property'];
                $data[$property_name] = $this->$property_name;
            }

            return $data;
        }

        final public function B2DBpopulateMorphedData(Saveable $original_object, $keep_id = true)
        {
            $this->_preMorph();
            $data = $original_object->getB2DBMorphedDataArray();
            $table = self::getB2DBTable();
            $id_column = $table->getIdColumn();
            foreach ($table->getColumns() as $column) {
                if (!$keep_id && $column['name'] == $id_column) {
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
         * @param string $classname[optional] The FQCN to morph into
         * @param boolean $keep_id[optional] Whether to keep the id or not
         *
         * @return \b2db\Saveable The morphed object, or this object if unmorphed
         *
         * @throws \b2db\Exception
         */
        final public function morph($classname = null, $keep_id = true)
        {
            if ($classname === null) {
                $table = $this->getB2DBTable();
                $classnames = Core::getCachedTableEntityClasses('\\'.get_class($table));
                if ($classnames === null) {
                    return $this;
                }
                $columns = $table->getColumns();
                $property = $columns[$table->getB2DBName() . '.' . $classnames['identifier']]['property'];
                $identifier = $this->$property;
                $classname = (\array_key_exists($identifier, $classnames['classes'])) ? $classnames['classes'][$identifier] : null;
                if (!$classname) {
                    throw new Exception("No classname has been specified in the @SubClasses annotation for identifier '{$identifier}'");
                }
            }
            $morphed_object = new $classname();
            if ($morphed_object instanceof Saveable) {
                $morphed_object->B2DBpopulateMorphedData($this, $keep_id);
            }
            return $morphed_object;
        }

    }
