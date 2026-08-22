<?php
// config/config.php
class AppConfig
{
    public static function getJsConfig()
    {
        return [
            'baseUrl' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'),
            'apiEndpoint' => '/controller/load_data.php',
            'uploadPath' => 'uploads',
            'maxFileSize' => 5242880, // 5MB
            'allowedFileTypes' => ['image/jpeg', 'image/png', 'image/gif'],
            'defaultLimit' => 5
        ];
    }
}
