<?php


use
class MageDiff
{

    protected $oldPath;
    protected $newPath;
    protected $oldVersion;
    protected $newVersion;
    protected $templateFiles = [];
    protected $diffClass;

    // Where modules will be found
    const MAGENTO_BASE = 'vendor/magento';
    // Where composer.json for magento can be found
    const MAGENTO_BASE_COMPOSER = 'vendor/magento/magento2-base/composer.json';
    // Pattern to match for module directories
    const MAGENTO_MODULE_PATTERN = 'module-*';
    // Where template files will be located relative to module folder
    const MAGENTO_BASE_MODULE_TEMPLATE = 'view/frontend/templates';
    // Template files to examine
    const MAGENTO_TEMPLATE_FILES = 'phtml';

    public function __construct($oldPath, $newPath)
    {
        $this->oldPath = $this->addTrailingSlash($oldPath);
        $this->newPath = $this->addTrailingSlash($newPath);
    }

    public function compare()
    {
        try {
            $this->checkPaths();

            echo "\n";

            $this->oldVersion = $this->detectVersion($this->oldPath);
            $this->newVersion = $this->detectVersion($this->newPath);

            if (!$this->oldVersion || !$this->newVersion) {
                throw new Exception("Versions could not be detected.");
            }

            echo "Old version detected: " . $this->oldVersion . "\n";
            echo "New version detected: " . $this->newVersion . "\n";

            if ($this->oldVersion == $this->newVersion) {
                echo "Identical versions detected!\n";
            }

            echo "\n";

            echo "Scanning directories...\n";

            $this->scanPath($this->oldPath);

            echo number_format(count($this->templateFiles)) . " files found.\n";

            echo "\n";

            echo "Comparing files...\n";

            $this->compareFiles();



        } catch (Exception $e) {
            echo "*****\nERROR\n*****\n\t" . $e->getMessage() . "\n\nExecution terminated.\n";
            die();
        }
    }

    private function scanPath($path)
    {
        if( !file_exists($path) ) {
            throw new Exception('`' . $path . '` not found');
        }
        $templateFiles = [];
        // Recursively draw directory tree. Gets a little O(n)ish.
        $directories = glob($path . self::MAGENTO_BASE . '/' . self::MAGENTO_MODULE_PATTERN);
        for ($i=0;$i<count($directories);$i++ ) {
            $directory = $directories[$i];
            if (file_exists($directory . '/' . self::MAGENTO_BASE_MODULE_TEMPLATE)) {
                $directories[] = $directory . '/' . self::MAGENTO_BASE_MODULE_TEMPLATE;
            } else {
                $newDirectories = glob($directory . '/*');
                foreach( $newDirectories as $newDir ) {
                        if (is_dir($newDir)) {
                            $directories[] = $newDir;
                        }
                }
            }
        }

        // Search for template files
        foreach( $directories as $directory ) {
            $files = glob($directory . '/*.' . self::MAGENTO_TEMPLATE_FILES);
            if( !empty($files) ) {
                $files = array_map(function($file){
                    return str_replace([$this->oldPath . self::MAGENTO_BASE], '', $file);
                }, $files);
                $templateFiles = array_merge($templateFiles, $files);
            }
        }

        $this->templateFiles = $templateFiles;

        //


    }

    private function compareFiles()
    {
        foreach( $this->templateFiles as $file) {

        }
    }

    private function checkPaths()
    {
        $this->checkExists([
            $this->oldPath,
            $this->newPath,
            $this->oldPath . self::MAGENTO_BASE_COMPOSER,
            $this->newPath . self::MAGENTO_BASE_COMPOSER
        ]);
    }

    private function checkExists($paths)
    {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                throw new Exception('`' . $path . '` not found.');
            }
        }
    }

    private function detectVersion($basePath)
    {
        $versionDetected = false;
        $basePath = $this->addTrailingSlash($basePath);
        if (file_exists($basePath . self::MAGENTO_BASE_COMPOSER)) {
            $composerData = @file_get_contents($basePath . self::MAGENTO_BASE_COMPOSER);
            $composerData = json_decode($composerData);
            if( $composerData && isset($composerData->version) ) {
                $versionDetected = preg_replace('#[^0-9\.]#', '',$composerData->version);
            }
        }

        return $versionDetected;
    }


    private function addTrailingSlash($path)
    {
        if (strlen($path) > 0 && $path{strlen($path)-1} != '/') {
            $path .= '/';
        }
        return $path;
    }

    private function getMagentoVersion($path)
    {
        if ($path{strlen($path)-1} != '/') {
            $path .= '/';
        }
    }

}

?>
