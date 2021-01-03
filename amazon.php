<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;

define('AWS_KEY', '*******************');
define('AWS_SECRET', '****************');
define('AWS_VERSION', 'latest');
define('AWS_REGION', 'us-east-2');

function get_s3_client()
{
    return new S3Client(array(
        'credentials' => [
            'key' => AWS_KEY,
            'secret' => AWS_SECRET,
        ],
        'version' => AWS_VERSION,
        'region' => AWS_REGION,
    ));
}


function create_s3_bucket($bucket_name)
{
    if ($bucket_name == '') {
        return array('success' => false, 'message' => 'Please provide valid bucket name!');
    }
    $s3 = get_s3_client();
    try {
        $result = $s3->createBucket([
            'Bucket' => $bucket_name,
        ]);
    } catch (AwsException $e) {
        return array('success' => false, 'message' => $e->getAwsErrorMessage());
    }

    if ($res = make_s3_bucket_public($s3, $bucket_name)) {
        return array(
            'success' => true, 
            'location' => $result['Location'], 
            'Uri' => $result['@metadata']['effectiveUri']
        );
    } else {
        return $res;
    }
}

function make_s3_bucket_public($s3, $bucket_name)
{
    try {
        $s3->putBucketPolicy([
            'Bucket' => $bucket_name,
            'Policy' => json_encode(
                array(
                    "Version" => "2008-10-17",
                    "Statement" => [
                        "Sid" => "AllowPublicRead",
                        "Effect" => "Allow",
                        "Principal" => array(
                            "AWS" => "*"
                        ),
                        "Action" => "s3:GetObject",
                        "Resource" => "arn:aws:s3:::${bucket_name}/*"
                    ]
                ),
            )
        ]);
        return true;
    } catch (AwsException $e) {
        return array('success' => false, 'message' => $e->getMessage());
    }
}

function upload_file_s3($bucket_name, $folder_name = '', $file_name)
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

    $s3 = get_s3_client();

    $file_url = 'https://s3.' . AWS_REGION . '.amazonaws.com/' . $bucket_name . '/' . $keyname;

    try {
        $s3->putObject(array(
            'Bucket' => $bucket_name,
            'Key' => $keyname,
            'SourceFile' => $file_name,
            'StorageClass' => 'REDUCED_REDUNDANCY'
        ));

        return array('success' => true, 'message' => $file_url);
    } catch (S3Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    } catch (Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    }
}

function upload_folder_s3($bucket_name, $folder_name)
{

    if (empty(trim($bucket_name))) {
        return array('success' => false, 'message' => 'Please provide valid bucket name!');
    }

    if (empty(trim($folder_name))) {
        return array('success' => false, 'message' => 'Please provide valid folder name!');
    }

    $keyname = $folder_name;

    $s3 = get_s3_client();

    try {
        $manager = new \Aws\S3\Transfer($s3, $keyname, 's3://' . $bucket_name);
        $manager->transfer();
        return array('success' => true);
    } catch (S3Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    } catch (Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
    }
}
