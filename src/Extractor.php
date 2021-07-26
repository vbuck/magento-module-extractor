<?php
/**
 * @author Rick Buczynski <richard.buczynski@gmail.com>
 * @license MIT
 */

declare(strict_types=1);

namespace Vbuck\MagentoModuleExtractor;

/**
 * Module extractor utility. Extracts Magento modules from ZIP artifacts into the correct app space.
 *
 * When installing modules from ZIP files, the target path will usually be created manually before extraction. This
 * utility will automate that, by generating the target path from the module's config file.
 */
class Extractor
{
    const ERROR_READ_FAILURE = 'Failed to read path';
    const ERROR_WRITE_FAILURE = 'Failed to write contents to path';
    const ERROR_EXTRACT_FAILURE = 'Failed to extract artifact to target path';
    const ERROR_INVALID_BASE_PATH = 'The base path is invalid.';

    /** @var string */
    private $basePath;

    /**
     * @param string $basePath
     * @throws \InvalidArgumentException
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = \rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->checkBasePath($this->basePath);
    }

    /**
     * Install artifacts from a list of compatible ZIP archives.
     *
     * Returns a report as an array indexed by the given artifacts, as:
     *
     * [
     *     ['id' => string, 'path' => string, 'state' => boolean, 'message' => string],
     *     […],
     * ]
     *
     * @param string[] $artifacts An array of paths or URLs to artifacts to install.
     * @return array A status report in the order of provided artifacts/
     */
    public function extract(array $artifacts = []) : array
    {
        $results = [];

        foreach ($artifacts as $key => $path) {
            $results[$key] = $this->createMutableResult((string) $key, $path, false);
            $data = $this->loadArtifact($path);

            if ($data === false) {
                $results[$key]['message'] = static::ERROR_READ_FAILURE;
                continue;
            }

            $results[$key]['tmp_path'] = $this->writeArtifact($data);
            if (!$results[$key]['tmp_path']) {
                $results[$key]['message'] = static::ERROR_WRITE_FAILURE;
                continue;
            }

            if (!$this->extractArtifact($results[$key]['tmp_path'], $results[$key])) {
                $results[$key]['message'] = static::ERROR_EXTRACT_FAILURE;
            }

            @\unlink($results[$key]['tmp_path']);

            $results[$key]['state'] = true;
        }

        return $results;
    }

    /**
     * @param string $path
     * @throws \InvalidArgumentException
     */
    private function checkBasePath(string $path)
    {
        $testPath = $path . DIRECTORY_SEPARATOR
            . 'app' . DIRECTORY_SEPARATOR
            . 'etc' . DIRECTORY_SEPARATOR
            . 'env.php';

        if (!\file_exists($testPath)) {
            throw new \InvalidArgumentException(static::ERROR_INVALID_BASE_PATH);
        }
    }

    /**
     * @param string $id
     * @param string $path
     * @param bool $state
     * @param string $name
     * @param string $message
     * @param string $tmpPath
     * @return array
     */
    private function createMutableResult(
        string $id,
        string $path,
        bool $state = false,
        string $name = '',
        string $message = '',
        string $tmpPath = ''
    ) : array {
        return [
            'id' => $id,
            'path' => $path,
            'state' => $state,
            'name' => $name,
            'message' => $message,
            'tmp_path' => $tmpPath,
        ];
    }

    /**
     * Extract the artifact to the module space.
     * @param string $source
     * @param array $result
     * @return bool
     */
    private function extractArtifact(string $source, array &$result) : bool
    {
        $workPath = $this->basePath . DIRECTORY_SEPARATOR . uniqid('module_');
        $zip = new \ZipArchive();
        $zip->open($source);
        if (!$zip->extractTo($workPath)) {
            return false;
        }
        $zip->close();

        $finalPath = $this->getModulePath($workPath, $result);
        // Assumes contents are always extracted with a container directory
        $innerPath = \current(\glob($workPath . DIRECTORY_SEPARATOR . '*')) . DIRECTORY_SEPARATOR;
        if (!$finalPath) {
            return false;
        }

        return !\shell_exec(\sprintf('mkdir -p %s', $finalPath))
            && !\shell_exec(\sprintf('cp -rf %s %s', $innerPath, $finalPath))
            && !\shell_exec(\sprintf('rm -rf %s', $workPath));
    }

    /**
     * Resolve the expected module path from the given source path.
     * @param string $path
     * @param array $result
     * @return bool
     */
    private function getModulePath(string $path, array &$result)
    {
        $configPath = current(
            (array) \glob(
                \rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . '*' . DIRECTORY_SEPARATOR
                . 'etc' . DIRECTORY_SEPARATOR
                . 'module.xml'
            )
        );

        if (!$configPath) {
            return false;
        }

        try {
            $config = new \DOMDocument();
            $config->load($configPath);
            $result['name'] = $config->getElementsByTagName('module')[0]->getAttribute('name');
            $moduleName = \explode('_', $result['name']);

            return $this->basePath . DIRECTORY_SEPARATOR
                . 'app' . DIRECTORY_SEPARATOR
                . 'code' . DIRECTORY_SEPARATOR
                . $moduleName[0] . DIRECTORY_SEPARATOR
                . $moduleName[1];
        } catch (\Exception $error) {
            return false;
        }
    }

    /**
     * @param string $path Path or URL to artifact target.
     * @return false|string
     */
    private function loadArtifact(string $path)
    {
        return @\file_get_contents($path);
    }

    /**
     * @param string $data
     * @return bool|string
     */
    private function writeArtifact(string $data)
    {
        $path = \tempnam(\sys_get_temp_dir(), \uniqid('artifact_'));
        return (bool) \file_put_contents($path, $data) ? $path : false;
    }
}
