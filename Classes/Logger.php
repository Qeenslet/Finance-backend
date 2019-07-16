<?php


class Logger
{

    private static $file = 'log.txt';

    public static function log($message)
    {
        $handle = fopen(self::$file, 'a');
        $dt = new DateTime();
        $line = "\n" . $message . ' ' . $dt->format('d.m.Y H:i:s');
        fwrite($handle, $line);
        fclose($handle);
    }
}