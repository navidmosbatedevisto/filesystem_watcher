<?php

namespace Navid\FilesystemWatcher;

use Dotenv\Dotenv;
use Aws\S3\S3Client;
use DirectoryIterator;
use Spatie\Watcher\Watch;
use RecursiveIteratorIterator;
use Aws\Exception\AwsException;
use RecursiveDirectoryIterator;
use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;


define('BASE_DIR', __DIR__ . '/..');
require_once BASE_DIR . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(BASE_DIR);
$dotenv->load();

$credentials = new Credentials($_ENV['PARSPACK_S3_KEY'], $_ENV['PARSPACK_S3_SECRET']);

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'us-west-2',
    'credentials' => $credentials,
    'endpoint' => $_ENV['PARSPACK_S3_DOMAIN'],
    'http'    => [
        'verify' => false
    ],
    'use_path_style_endpoint'   =>  true
]);

$directory = $_ENV['DIRECTORY'];

$modified = [];

function listDirectory($directory)
{
    $list = [];
    // check paramtere is a directory
    if (!is_dir($directory)) {
        echo "Error: " . $directory . " is not a directory\n";
        return;
    }

    // list directory files with modif time recursively
    $dir = new RecursiveDirectoryIterator($directory);
    foreach (new RecursiveIteratorIterator($dir) as $fileinfo) {
        // store full path name of file and last modif time in a file in such a way that it is easily parsable
        // ignore if file is a directory
        if ($fileinfo->isDir()) {
            continue;
        }
        $file = $fileinfo->getFilename();
        $modif = $fileinfo->getMTime();
        $fullpath = $fileinfo->getPathname();
        $list[] = [
            $fullpath,
            $modif
        ];
    }
    
    return $list;
}

function storeDirectoryListToFile()
{
    $list = listDirectory($_ENV['DIRECTORY']);
    // format each list item array [path,mime] to string "path::mime";
    $list = array_map(function ($item) {
        return implode("::", $item);
    }, $list);
    // write list to data.txt file
    $file = fopen(BASE_DIR . "/data.txt", "w");
    foreach ($list as $line) {
        fwrite($file, $line . "\n");
    }
    fclose($file);
}

function parseDirectoryListFile()
{
    $list = [];
    // check if file exists
    if (!file_exists(BASE_DIR . "/data.txt")) {
        return $list;
    }
    $file = fopen(BASE_DIR . "/data.txt", "r");
    while (!feof($file)) {
        $line = fgets($file);
        $line = trim($line);
        // check if line is empty
        if (empty($line)) {
            continue;
        }
        $line = explode("::", $line);
        $fullpath = $line[0];
        $modif = $line[1];
        $list[] = [
            $fullpath,
            $modif
        ];
    }
    fclose($file);
    return $list;
}

function compareDirectoryList()
{
    $list = listDirectory($_ENV['DIRECTORY']);
    $listFromFile = parseDirectoryListFile();
    $modified = [];
    foreach ($list as $item) {
        $fullpath = $item[0];
        $modif = $item[1];
        $found = false;
        foreach ($listFromFile as $itemFromFile) {
            $fullpathFromFile = $itemFromFile[0];
            $modifFromFile = $itemFromFile[1];
            if ($fullpath == $fullpathFromFile) {
                $found = true;
                if ($modif != $modifFromFile) {
                    $modified[] = $fullpath;
                }
            }
        }
        if (!$found) {
            $modified[] = $fullpath;
        }
    }
    return $modified;
}

function uploadFileToS3($file)
{
    global $s3;
    // try {
    //     $s3->putObject([
    //         'Bucket' => $_ENV['PARSPACK_S3_BUCKET'],
    //         'Key' => str_replace($_ENV['DIRECTORY'], '', $file),
    //         'SourceFile' => $file
    //     ]);
    // } catch (S3Exception $e) {
    //     echo $e->getMessage() . PHP_EOL;
    // }
    return;
}

function syncToS3NewOrModifiedFiles()
{
    // check if script with this name is running
    $pid = getmypid();
    $pids = explode(PHP_EOL, shell_exec("ps -e | grep " . __FILE__));
    $pids = array_filter($pids, function ($item) use ($pid) {
        return strpos($item, $pid) === false;
    });
    if (count($pids) > 0) {
        echo "Script is already running\n";
        return;
    }
    
   
    $modified = compareDirectoryList();
    foreach ($modified as $file) {
        uploadFileToS3($file);
    }
    storeDirectoryListToFile();
}

syncToS3NewOrModifiedFiles();