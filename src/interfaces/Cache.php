<?php

    namespace b2db\interfaces;

    interface Cache
    {

        /**
         * Query the cache for the existence of a specific key
         *
         * @param mixed $key
         *
         * @return boolean
         */
        public function has($key);

        /**
         * Get the content of a specific key from the cache
         *
         * @param mixed $key
         * @param null  $default_value
         *
         * @return mixed
         */
        public function get($key, $default_value = null);

        /**
         * Delete an item from the cache based on a specific key
         *
         * @param mixed $key
         *
         * @return bool
         */
        public function delete($key);

        /**
         * Set an item in the cache for a specific key
         *
         * @param mixed $key
         * @param mixed $value
         *
         * @return mixed
         */
        public function set($key, $value);

        /**
         * Invalidate the entire cache
         *
         * @return mixed
         */
        public function flush();

    }