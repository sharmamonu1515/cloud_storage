<?php
require 'vendor/autoload.php';

define('DO_ACCESS_KEY', '***************************');
define('DO_SECRET_KEY', '***************************');
define('DO_REGION', 'fra1');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

function get_do_client()
{
    return new S3Client(array(
        'credentials' => [
            'key' => DO_ACCESS_KEY,
            'secret' => DO_SECRET_KEY,
        ],
        'version' => 'latest',
        'region' => DO_REGION,
        'endpoint' => 'https://'. DO_REGION . '.digitaloceanspaces.com/',
    ));
}

function upload_file_do($bucket_name, $folder_name = '', $file_name)
{
    if (empty(trim($bucket_name))) {
        return array('success' => false, 'message' => 'Please provide valid bucket name!');
    }

    if (empty(trim($file_name))) {
        return array('success' => false, 'message' => 'Please provide valid file name!');
    }

    if ($folder_name !== '') {
        $keyname = $folder_name . '/' . $file_name;
    } else {
        $keyname = $file_name;
    }

    $do = get_do_client();

    $file_url = "https://${bucket_name}." . DO_REGION . ".digitaloceanspaces.com/${keyname}";

    try {
        $do->putObject(array(
            'Bucket' => $bucket_name,
            'Key' => $keyname,
            'SourceFile' => $file_name,
            'ACL' => 'public-read'
        ));

        return array('success' => true, 'message' => $file_url);
    } catch (S3Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    } catch (Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    }
}

function upload_folder_do($bucket_name, $folder_name)
{

    if (empty(trim($bucket_name))) {
        return array('success' => false, 'message' => 'Please provide valid bucket name!');
    }

    if (empty(trim($folder_name))) {
        return array('success' => false, 'message' => 'Please provide valid folder name!');
    }

    $keyname = $folder_name;

    $do = get_do_client();

    try {
        $manager = new \Aws\S3\Transfer($do, $keyname, 's3://' . $bucket_name . '/' . $folder_name);
        $manager->transfer();
        return array('success' => true);
    } catch (S3Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    } catch (Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    }
}