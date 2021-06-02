<?php

    namespace b2db;

    /**
     * Transaction class
     *
     * @author Daniel Andre Eikeland <zegenie@zegeniestudios.net>
     * @version 2.0
     * @license http://www.opensource.org/licenses/mozilla1.1.php Mozilla Public License 1.1 (MPL 1.1)
     * @package b2db
     * @subpackage core
     */

    /**
     * Transaction class
     *
     * @package b2db
     */
    class Transaction
    {

        protected int $state = 0;

        public const DB_TRANSACTION_NOT_STARTED = 0;

        public const DB_TRANSACTION_STARTED = 1;

        public const DB_TRANSACTION_COMMITTED = 2;

        public const DB_TRANSACTION_ROLLED_BACK = 3;

        public const DB_TRANSACTION_ENDED = 4;

        public function __construct()
        {
            if (Core::getDBLink()->beginTransaction()) {
                $this->state = self::DB_TRANSACTION_STARTED;
                Core::setTransaction(self::DB_TRANSACTION_STARTED);
            }
        }

        public function __destruct()
        {
            if ($this->state === self::DB_TRANSACTION_STARTED) {
                echo 'forcing transaction rollback';
            }
        }

        public function end(): void
        {
            if ($this->state === self::DB_TRANSACTION_COMMITTED) {
                $this->state = self::DB_TRANSACTION_ENDED;
                Core::setTransaction(self::DB_TRANSACTION_ENDED);
            }
        }

        public function commitAndEnd(): void
        {
            $this->commit();
            $this->end();
        }

        public function commit(): void
        {
            if ($this->state === self::DB_TRANSACTION_STARTED) {
                if (Core::getDBLink()->commit()) {
                    $this->state = self::DB_TRANSACTION_COMMITTED;
                    Core::setTransaction(self::DB_TRANSACTION_COMMITTED);
                } else {
                    throw new Exception('Error committing transaction: ' . implode(', ', Core::getDBLink()->errorInfo()));
                }
            } else {
                throw new Exception('There is no active transaction');
            }
        }

        public function rollback(): void
        {
            if ($this->state === self::DB_TRANSACTION_STARTED) {
                if (Core::getDBLink()->rollback()) {
                    $this->state = self::DB_TRANSACTION_ROLLED_BACK;
                    Core::setTransaction(self::DB_TRANSACTION_ROLLED_BACK);
                } else {
                    throw new Exception('Error rolling back transaction: ' . implode(', ', Core::getDBLink()->errorInfo()));
                }
            } else {
                throw new Exception('There is no active transaction');
            }
        }

    }
