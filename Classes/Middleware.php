<?php


class Middleware
{
    public static function check()
    {
        return !empty($_SESSION['logged_in']);
    }
}