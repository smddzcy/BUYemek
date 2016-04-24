<?php

/**
 * Class DB
 */
class DB
{
    /**
     * @var PDO $instance Singleton DB reference
     */
    private static $db = null;

    /**
     * Returns the DB instance
     *
     * @return PDO The DB instance.
     */
    public static function getInstance()
    {
        if (static::$db === null) {
            static::$db = new PDO(Config::DB_TYPE . ':host=' . Config::DB_HOST . ';port=' . Config::DB_PORT . ';dbname=' . Config::DB_NAME . ';charset=' . Config::DB_CHARSET, Config::DB_USER, Config::DB_PASS);
            static::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            static::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return static::$db;
    }

    /**
     * DB constructor.
     * Protected to make sure it can't be instantiated with new keyword
     */
    protected function __construct()
    {
    }

    /**
     * Private to make sure it's not clonable
     */
    private function __clone()
    {
    }

    /**
     * Private to make sure it's not unserializable
     */
    private function __wakeup()
    {
    }

}