<?php

$ROOT_DIR = __DIR__ . DIRECTORY_SEPARATOR;

$classesDir = array (
    $ROOT_DIR . 'Tests',
    $ROOT_DIR . 'Block',
    $ROOT_DIR . 'Block' . DIRECTORY_SEPARATOR . 'Transaction',
    $ROOT_DIR . 'Utils',
    $ROOT_DIR . 'Errors',
    $ROOT_DIR . 'TransactionContainer'
);


function loadDir($directory) {
    foreach (scandir($directory) as $filename) {
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            // var_dump($path);
            require_once $path;
        }
    }
}

function loadclass() {
    global $classesDir;
    foreach ($classesDir as $directory) {
        loadDir($directory);
    }
}

loadclass();