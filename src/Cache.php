<?php

    namespace b2db;

    use DirectoryIterator;

    /**
     * Cache class
     *
     * @package b2db
     */
    class Cache implements interfaces\Cache
    {

        public const TYPE_DUMMY = 0;
        public const TYPE_APC = 1;
        public const TYPE_FILE = 2;

        /**
         * @var bool
         */
        protected $enabled = true;

        /**
         * @var int
         */
        protected int $type;

        /**
         * @var string
         */
        protected string $path;

        /**
         * Creates an instance of the cache class
         * @param int $type Which type of cache to use
         * @param array<string, bool|string> $options
         */
        public function __construct(int $type, ?array $options = [])
        {
            $this->type = $type;

            if (isset($options['enabled'])) {
                $this->enabled = $options['enabled'];
            }

            if (isset($options['path'])) {
                if (!file_exists($options['path'])) {
                    throw new \Exception("Configured cache path ({$options['path']}) is not writable. Please check your configuration.");
                }

                $this->path = $options['path'];
            }
        }

        public function getCacheTypeDescription(): string
        {
            switch ($this->type) {
                case self::TYPE_DUMMY:
                    return 'Dummy cache';
                case self::TYPE_APC:
                    return 'In-memory cache (apc)';
                case self::TYPE_FILE:
                    return 'File cache (' . $this->path . ')';
            }

            return 'Invalid cache type';
        }

        /**
         * @return int
         */
        public function getType(): int
        {
            return $this->type;
        }

        /**
         * @param string $key The cache key to look up
         * @param mixed $default_value
         * @return mixed
         */
        public function get(string $key, $default_value = null)
        {
            if (!$this->enabled) {
                return $default_value;
            }

            switch ($this->type) {
                case self::TYPE_APC:
                    $success = false;
                    $var = apc_fetch($key, $success);

                    return ($success) ? $var : $default_value;
                case self::TYPE_FILE:
                    $filename = $this->path . $key . '.cache';
                    if (!file_exists($filename)) {
                        return $default_value;
                    }

                    /** @noinspection UnserializeExploitsInspection */
                    return unserialize(file_get_contents($filename));
                case self::TYPE_DUMMY:
                default:
                    return $default_value;
            }
        }

        /**
         * @param string $key The cache key to look up
         *
         * @return bool
         */
        public function has(string $key): bool
        {
            if (!$this->enabled) {
                return false;
            }

            switch ($this->type) {
                case self::TYPE_APC:
                    $success = false;
                    apc_fetch($key, $success);
                    break;
                case self::TYPE_FILE:
                    $filename = $this->path . $key . '.cache';
                    $success = file_exists($filename);
                    break;
                case self::TYPE_DUMMY:
                default:
                    $success = false;
            }

            return $success;
        }

        /**
         * Store an item in the cache
         *
         * @param string $key The cache key to store the item under
         * @param mixed $value The value to store
         *
         * @return bool
         */
        public function set(string $key, $value): bool
        {
            if (!$this->enabled) {
                return true;
            }

            switch ($this->type) {
                case self::TYPE_APC:
                    apc_store($key, $value);
                    break;
                case self::TYPE_FILE:
                    $filename = $this->path . $key . '.cache';
                    file_put_contents($filename, serialize($value));
                    break;
            }


            return true;
        }

        /**
         * Delete an entry from the cache
         *
         * @param string $key The cache key to delete
         */
        public function delete(string $key): bool
        {
            if (!$this->enabled) {
                return true;
            }

            switch ($this->type) {
                case self::TYPE_APC:
                    apc_delete($key);
                    break;
                case self::TYPE_FILE:
                    $filename = $this->path . $key . '.cache';
                    unlink($filename);
            }

            return true;
        }

        /**
         * Set the enabled property
         *
         * @param bool $value
         */
        public function setEnabled(bool $value): void
        {
            $this->enabled = $value;
        }

        /**
         * Temporarily disable the cache
         */
        public function disable(): void
        {
            $this->setEnabled(false);
        }

        /**
         * (Re-)enable the cache
         */
        public function enable(): void
        {
            $this->setEnabled(true);
        }

        /**
         * Flush all entries in the cache
         */
        public function flush(): bool
        {
            if (!$this->enabled) {
                return false;
            }

            if ($this->type === self::TYPE_FILE) {
                $iterator = new DirectoryIterator($this->path);
                foreach ($iterator as $file_info) {
                    if (!$file_info->isDir()) {
                        unlink($file_info->getPathname());
                    }
                }
            }

            return true;
        }

    }
