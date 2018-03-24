<?php

class bdImportCmd_Helper_Terminal
{
    public static function log($message)
    {
        $args = func_get_args();
        self::display('log', $args);
    }

    public static function warn($message)
    {
        $args = func_get_args();
        self::display('warn', $args);
    }

    public static function error($message)
    {
        $args = func_get_args();
        self::display('error', $args);
        self::stop();
    }

    public static function display($level, array $args)
    {
        $message = call_user_func_array(array(__CLASS__, 'prepareOutput'), $args);

        if (!empty($message)) {
            echo $message;
        }
    }

    public static function prepareOutput($message)
    {
        $message = '';
        $args = func_get_args();

        if (count($args) > 1) {
            $message = call_user_func_array('sprintf', $args);
        } elseif (count($args) > 0) {
            $message = $args[0];
        }

        if (!empty($message)) {
            $message .= "\n";
        }

        return $message;
    }

    public static function stop()
    {
        die("Stop.\n");
    }
}
