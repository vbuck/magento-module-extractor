# Magento Module Extractor Utility

Extracts Magento modules from ZIP artifacts into the correct app space.

When installing modules from ZIP files, the target path will usually be created manually before extraction. This utility
will automate that, by generating the target path from the module's config file.

## Use Cases

 * Automated installation of Magento
 * Developer tooling for quickly pulling in non-Composer packages

This tool was built to support automated provisioning of Magento sandboxes which could be pre-loaded with modules.

## Installation

Works as a Composer package to load with your project:

    composer config repositories.magento-module-extractor vcs https://github.com/vbuck/magento-module-extractor.git
    composer require vbuck/magento-module-extractor:*

Or you can download the standalone CLI tool:

    wget https://raw.githubusercontent.com/vbuck/magento-module-extractor/main/bin/module-extractor
    chmod +x module-extractor
    sudo mv module-extractor /usr/local/bin/

## How to Use

Example scenario:

 1. I want to install a new module.
 2. I have a path or URL to its ZIP artifact.
 3. To install this module, I must first extract its content to a workspace.
 4. I must then create the correctly-named module directory structure in `app/code` space.
 5. I must then move the workspace contents into the prepared module directory.

I want to simplify this, so I only need to get to step 2.

### Code Sample

    $instance = new \Vbuck\MagentoModuleExtractor\Extractor('/path/to/magento/root');
    $result = $instance->extract([
        'https://url.to/module1.zip',
        'https://url.to/module2.zip',
        '/path/to/module3.zip',
    ]);
    
    if ($result[0]['state'] === true) {
        echo "Module {$result[0]['name']} has been extracted"; 
    }

### Command-Line Usage

    bin/module-extractor /path/to/magento/root https://url.to/module1.zip /path/to/module2.zip

## Architectural Principles

* Simple: low complexity, few dependencies, "gistable" deployment
* Flexible: can use in a project or as a standalone utility
