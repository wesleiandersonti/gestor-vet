<?php
namespace App\Helpers;

class EncryptedFileLoader
{
    public static function load($path)
    {
        if (!is_string($path) || $path === '') {
            return;
        }

        if (file_exists($path)) {
            include_once $path;
            return;
        }

        $fallback = __DIR__ . '/context.php';
        if (file_exists($fallback)) {
            include_once $fallback;
        }
    }
}
