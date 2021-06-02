<?php

    namespace b2db\interfaces;

    interface Cache
    {

        /**
         * Query the cache for the existence of a specific key
         *
         * @param string $key
         *
         * @return bool
         */
        public function has(string $key): bool;

        /**
         * Get the content of a specific key from the cache
         *
         * @param string $key
         * @param mixed $default_value
         *
         * @return mixed
         */
        public function get(string $key, $default_value = null);

        /**
         * Delete an item from the cache based on a specific key
         *
         * @param string $key
         *
         * @return bool
         */
        public function delete(string $key): bool;

        /**
         * Set an item in the cache for a specific key
         *
         * @param string $key
         * @param mixed $value
         *
         * @return bool
         */
        public function set(string $key, $value): bool;

        /**
         * Invalidate the entire cache
         *
         * @return mixed
         */
        public function flush();

    }