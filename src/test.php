<?php
//
// function that list content of directory with last modif time
function ListDirectory(){
    // list directory files with modif time
    $dir = new DirectoryIterator(dirname(__FILE__));
    foreach ($dir as $fileinfo) {
        if (!$fileinfo->isDot()) {
            // store full path name of file and last modif time in a file in such a way that it is easily parsable
            $file = $fileinfo->getFilename();
            $modif = $fileinfo->getMTime();
            $fullpath = $fileinfo->getPathname();
            $data = $fullpath . "::" . $modif . "\n";
            file_put_contents("data.txt", $data, FILE_APPEND);


        }
    }
    
}

function listNewOrModifiedFiles(){
    // read data.txt file and compare last modif time with current modif time
    define('BASE_DIR', __DIR__ . '/..');
    
    $file = fopen(BASE_DIR . "/data.txt", "r");
    while(!feof($file)){
        $line = fgets($file);
        $line = trim($line);
        $line = explode("::", $line);
        $fullpath = $line[0];
        $modif = $line[1];
        $currentModif = filemtime($fullpath);
        if($modif != $currentModif){
            echo $fullpath . " has been modified\n";
        }
    }
    fclose($file);
}

listNewOrModifiedFiles();