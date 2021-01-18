<?php

require_once('vendor/autoload.php');

use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

define('STORAGE_ACCOUNT_NAME', '*******************');
define('STORAGE_ACCOUNT_KEY', '*******************');

function get_azure_client() {
    # Setup a specific instance of an Azure::Storage::Client
    $connectionString = "DefaultEndpointsProtocol=https;AccountName=". STORAGE_ACCOUNT_NAME .";AccountKey=". STORAGE_ACCOUNT_KEY.';EndpointSuffix=core.windows.net';
    return BlobRestProxy::createBlobService($connectionString);
}

## adds file to the storage. Usage: storageAddFile("myContainer", "C:\path\to\file.png", "file_name-on-storage.png")
function upload_file_azure($container_name, $file, $file_name) {

    $container_name = trim($container_name);
    $file = trim($file);
    $file_name = trim($file_name);

    if (empty($container_name)) {
        return array('success' => false, 'message' => 'Please provide valid container name!');
    }

    if (empty($file)) {
        return array('success' => false, 'message' => 'Please provide valid file name!');
    }

    $blobClient = get_azure_client();

    $handle = @fopen($file, "r");
    if($handle) {
        $options = new CreateBlockBlobOptions();
        $mime = NULL;

        try {
            $mimes = new \Mimey\MimeTypes;
            $mime = $mimes->getMimeType(pathinfo($file_name, PATHINFO_EXTENSION));
            $options->setContentType($mime);
        } catch ( Exception $e ) {
            return array('success' => false, 'message' => $e->getMessage());
        } 

        try {
            if($mime) {
                $cacheTime = getCacheTimeByMimeType($mime);
                if($cacheTime) {
                    $options->setCacheControl("public, max-age=".$cacheTime);
                }
            }

            $blobClient->createBlockBlob($container_name, $file_name, $handle, $options);
        } catch ( Exception $e ) {
            return array('success' => false, 'message' => $e->getMessage());
        } 

        @fclose($handle);
        $file_url = "https://".STORAGE_ACCOUNT_NAME.".blob.core.windows.net/{$container_name}/{$file_name}";
        return array('success' => true, 'message' => $file_url);
        
    } else {
        return array('success' => false, 'message' => 'Failed to open file -' .$file);
    }
}

function upload_folder_azure($container_name, $folder_name) {

    if (empty(trim($container_name))) {
        return array('success' => false, 'message' => 'Please provide valid bucket name!');
    }

    if (empty(trim($folder_name))) {
        return array('success' => false, 'message' => 'Please provide valid folder name!');
    }

    $keyname = $folder_name;

    try {
        foreach(get_dir_contents($folder_name) as $file_name) {
            $keyname = str_replace(
                array(__DIR__ . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR), 
                array('', '/'),
                $file_name
            );
            upload_file_azure($container_name, $keyname, $keyname);
        }
        
        return array('success' => true);
    } catch (Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    }
}

function get_dir_contents($dir, &$results = array()) {
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . '/' . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($value != "." && $value != "..") {
            get_dir_contents($path, $results);
        }
    }

    return $results;
}

## get cache time by mime type
function getCacheTimeByMimeType($mime) {
    $mime = strtolower($mime);

    $types = array(
        "application/json" => 604800,// 7 days
        "application/javascript" => 604800,// 7 days
        "application/xml" => 604800,// 7 days
        "application/xhtml+xml" => 604800,// 7 days
        "image/bmp" => 604800,// 7 days
        "image/gif" => 604800,// 7 days
        "image/jpeg" => 604800,// 7 days
        "image/png" => 604800,// 7 days
        "image/tiff" => 604800,// 7 days
        "image/svg+xml" => 604800,// 7 days
        "image/x-icon" => 604800,// 7 days
        "text/plain" => 604800, // 7 days
        "text/html" => 604800,// 7 days
        "text/css" => 604800,// 7 days
        "text/richtext" => 604800,// 7 days
        "text/xml" => 604800,// 7 days
    );

    // return value
    if(array_key_exists($mime, $types)) {
        return $types[$mime];
    }

    return FALSE;
}