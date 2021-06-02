<?php

    namespace b2db;

    /**
     * Annotation class
     *
     * @package b2db
     */
    class Annotation
    {

        /**
         * @var string
         */
        protected string $_key;

        /**
         * @var array<string, string>
         */
        protected array $_data;

        /**
         * Annotation constructor.
         * @param string $key
         * @param string $annotation_data_string
         */
        public function __construct(string $key, string $annotation_data_string)
        {
            $this->_key = $key;
            $this->_data = [];

            $annotation_data = explode(',', str_replace("\n", "", $annotation_data_string));
            foreach ($annotation_data as $annotation_item) {
                $annotation_info = explode('=', trim($annotation_item));
                $annotation_info[0] = trim($annotation_info[0], "* \t\r\n\0\x0B");

                if (array_key_exists(1, $annotation_info)) {
                    switch (true) {
                        case (in_array($annotation_info[1][0], ['"', "'"]) && in_array($annotation_info[1][strlen($annotation_info[1]) - 1], ['"', "'"])):
                            $value = trim(str_replace(['"', "'"], ['', ''], $annotation_info[1]));
                            break;
                        case (in_array($annotation_info[1], ['true', 'false'])):
                            $value = $annotation_info[1] === 'true';
                            break;
                        case (is_numeric($annotation_info[1])):
                            $value = (int) $annotation_info[1];
                            break;
                        case (defined($annotation_info[1])):
                            $value = ['type' => 'constant', 'value' => $annotation_info[1]];
                            break;
                        default:
                            $value = trim($annotation_info[1]);
                    }
                    $this->_data[trim($annotation_info[0])] = $value;
                }
            }
        }

        /**
         * @param string $property
         * @return bool
         */
        public function hasProperty(string $property): bool
        {
            return array_key_exists($property, $this->_data);
        }

        /**
         * @param string $property
         * @param mixed|null $default_value
         * @return string|int
         */
        public function getProperty(string $property, $default_value = null)
        {
            return ($this->hasProperty($property)) ? $this->_data[$property] : $default_value;
        }

        /**
         * @return array<string, string>
         */
        public function getProperties(): array
        {
            return $this->_data;
        }

    }
