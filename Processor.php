<?php

abstract class Processor
{
    private static $curlHandler;

    public function __construct()
    {
        self::$curlHandler = new CurlRequest();
    }

    public static function getCurlHandler(): CurlRequest
    {
        if (is_null(self::$curlHandler)) self::$curlHandler = new CurlRequest();
        return self::$curlHandler;
    }

    public function fetchUrl($URL, $options = [])
    {
        $handler = self::getCurlHandler();
        if (!empty($options)) {
            $handler->setExtra($options);
        }
        $returnData = $handler->get($URL);
        $handler->setExtra([]);
        return $returnData;
    }

    public abstract function getRawData();

}