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


    public static function varDumpIt($something)
    {
        ob_start();
        var_dump($something);
        $html = ob_get_clean();
        self::log($html);
    }


    public static function print_rIt($something)
    {
        ob_start();
        print_r($something);
        $html = ob_get_clean();
        self::log($html);
    }

    public static function error($message)
    {
        $handle = fopen('php://stderr', 'w');
        $dt = new DateTime();
        $line = "\n" . $message . ' ' . $dt->format('d.m.Y H:i:s');
        fwrite($handle, $line);
        fclose($handle);
    }

    public static function debugToStdout($something)
    {
        ob_start();
        print_r($something);
        $html = ob_get_clean();
        self::error($html);
    }
}