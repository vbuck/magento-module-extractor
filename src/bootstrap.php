<?php
/**
 * Application bootstrap for with the compiled Phar package.
 *
 * @author Rick Buczynski <richard.buczynski@gmail.com>
 * @license MIT
 */

declare(strict_types=1);

define('DS', DIRECTORY_SEPARATOR);
$appPath = dirname(__DIR__);
$autoloader = $appPath . DS . 'vendor' . DS . 'autoload.php';
$input = mapInput();

if ($input['help']) {
    showHelp();
    exit(0);
}

if (!file_exists($autoloader)) {
    echo "Cannot run application.\n> Vendor libraries not installed. Please run: composer install";
    exit(1);
}

require_once $autoloader;

try {
    $instance = new \Vbuck\MagentoModuleExtractor\Extractor($input['base_path']);
    $result = $instance->extract($input['artifacts']);
    $hasError = false;

    if (empty($result)) {
        echo 'No modules extracted.' . PHP_EOL;
        exit(0);
    }

    foreach ($result as $artifact) {
        if ($artifact['state'] === true) {
            echo "→ Extracted module '{$artifact['name']}'" . PHP_EOL;
        } else {
            echo "→ Failed to extract module: {$artifact['message']}" . PHP_EOL;
            $hasError = true;
        }
    }

    echo PHP_EOL;
    echo 'You may need to run the Magento upgrade process.' . PHP_EOL;

    exit((int) $hasError);
} catch (\Exception $error) {
    echo $error->getMessage() . PHP_EOL;
    exit(1);
}

function mapInput() {
    global $argv;
    $input = [
        'base_path' => rtrim(getcwd(), DS),
        'artifacts' => [],
        'help' => false,
    ];

    foreach ($argv as $value) {
        if ($value === '--help') {
            $input['help'] = true;
        }
    }

    if (!empty($argv[1])) {
        $input['base_path'] = realpath($argv[1]);
    }

    $input['artifacts'] = array_slice($argv, 2);

    return $input;
}

function showHelp() {
    echo  <<<EOF
Module Installation Utility

Extracts Magento modules from ZIP artifacts into the correct app space.

To use:

    bin/module-extractor /path/to/magento https://url.to/artifact1.zip /path/to/artifact2.zip

First argument is the path to your target Magento installation.
Additional arguments are paths or URLs to your module artifacts.

https://github.com/vbuck/magento-module-extractor
(c) Rick Buczynski <richard.buczynski@gmail.com>

EOF;
}
