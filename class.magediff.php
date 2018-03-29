<?php
namespace Huebacca;

class MageDiff
{

    /**
     * @var string
     */
    protected $oldPath;
    /**
     * @var string
     */
    protected $newPath;
    /**
     * @var string
     */
    protected $oldVersion;
    /**
     * @var string
     */
    protected $newVersion;
    /**
     * @var array
     */
    protected $templateFiles = [];
    /**
     * @var array
     */
    protected $modules = [];
    /**
     * @var array
     */
    protected $modulesOverridden = [];
    /**
     * @var array
     */
    protected $changedFiles = [];
    /**
     * @var array
     */
    protected $overrideFiles = [];
    /**
     * @var array
     */
    protected $moduleMap = [];
    /**
     * @var array
     */
    protected $modulePathMap = [];
    /**
     * @var array
     */
    protected $changedModuleFiles = [];

    /**
     * Relative path where modules will be found
     */
    const MAGENTO_BASE = 'vendor/magento';
    /**
     * Relative path where Magento 2's composer.json file can be found
     */
    const MAGENTO_BASE_COMPOSER = 'vendor/magento/magento2-base/composer.json';
    // Pattern to match for module directories
    /**
     * Glob to match for module directories
     */
    const MAGENTO_MODULE_PATTERN = 'module-*';
    /**
     * Relative path where template files will be located from module folder
     */
    const MAGENTO_BASE_MODULE_TEMPLATE = 'view/frontend/templates';
    /**
     * Template file types to look for
     */
    const MAGENTO_TEMPLATE_FILES = 'phtml';
    /**
     * Relative path where module overrides will be found
     */
    const MAGENTO_MODULE_OVERRIDE_PATH = 'app/design/frontend';

    /**
     * MageDiff constructor.
     * @param $oldPath
     * @param $newPath
     */
    public function __construct($oldPath, $newPath)
    {
        $this->oldPath = $this->addTrailingSlash($oldPath);
        $this->newPath = $this->addTrailingSlash($newPath);
    }

    /**
     * Main function to run comparison
     */
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

            echo "Scanning template directories...\n";

            $this->scanPath($this->oldPath);

            echo "\t" . number_format(count($this->templateFiles)) . " files found.\n";

            echo "\n";

            echo "Finding template changes...\n";

            $this->compareFiles();

            echo "\t" . number_format(count($this->changedFiles)) . " changed files found.\n";

            if( count($this->changedFiles) < 1 ) {
                echo "\nNo changes found. Exiting.\n";
                die();
            }

            echo "\n";

            echo "Finding modules corresponding to template changes...\n";

            $this->checkModules();

            echo "\t" . number_format(count(array_unique($this->modules))) . " modules found corresponding to changed files.\n";

            if( count($this->modules) < 1 ) {
                echo "\nNo modules corresponding to changed files found. Exiting.\n";
                die();
            }

            echo "\n";

            echo "Scanning modules for overrides corresponding to template changes...\n";

            $this->scanModules();

            echo "\t" . number_format(count($this->modulesOverridden)) . " modules with overrides found.\n";
            if( count($this->modulesOverridden) < 1 ) {
                echo "\nNo module overrides found. Exiting.\n";
                die();
            }

            echo "\n";

            echo "Scanning module overrides files for template changes...\n";

            $this->scanModuleOverrides();




        } catch (Exception $e) {
            echo "*****\nERROR\n*****\n\t" . $e->getMessage() . "\n\nExecution terminated.\n";
            die();
        }
    }

    /**
     * Step 1: Recursively scan directory tree
     *
     * @param $path
     */
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
                    return substr(str_replace([$this->oldPath . self::MAGENTO_BASE], '', $file),1);
                }, $files);
                $templateFiles = array_merge($templateFiles, $files);
            }
        }

        $this->templateFiles = $templateFiles;
    }

    /**
     * Step 2: Compare files in old installation to files in new installation for differences
     *
     */
    private function compareFiles()
    {
        foreach( $this->templateFiles as $file) {
            $fileOld = $this->oldPath . self::MAGENTO_BASE . '/' . $file;
            $fileNew = $this->newPath . self::MAGENTO_BASE . '/' . $file;
            if( file_exists($fileNew) ) {
                $fileOldData = file_get_contents($fileOld);
                $fileNewData = file_get_contents($fileNew);
                if( $fileOldData != $fileNewData ) {
                    $this->changedFiles[] = $file;
                }
            }
        }
    }

    /**
     * Step 4: Find all modules and map them from module-path to Module_Name
     *
     */
    private function checkModules()
    {
        foreach( $this->changedFiles as $file ) {
            $modulePath = '';
            // Improve efficiency - store matches
            $path = explode('/', $file);
            if (isset($this->moduleMap[$path[0]])) {
                $modulePath = $this->moduleMap[$path[0]];
            } else {
                if (preg_match('#\Amodule-(.+)#', $path[0], $matches)) {
                    $modulePath = 'Magento_';
                    $modulePathItems = explode('-', $matches[1]);
                    foreach ($modulePathItems as $item) {
                        $modulePath .= ucwords(strtolower($item));
                    }
                    $this->moduleMap[$path[0]] = $modulePath;
                }
            }
            if( !empty($modulePath) ) {
                $this->modules[] = $modulePath;
            }
        }
    }

    /**
     * Step 5: Build directory tree of all module folders
     */
    private function scanModules()
    {
        $moduleBasePath = $this->newPath . self::MAGENTO_MODULE_OVERRIDE_PATH;
        $directories = glob($moduleBasePath . '/*', GLOB_ONLYDIR);
        // Build full tree first
        for( $i=0;$i<count($directories);$i++ ) {
            $dir = $directories[$i];
            $directories = array_merge($directories, glob($dir . '/*', GLOB_ONLYDIR));

        }
        foreach( $directories as $key => $directory ) {
            $dir = explode('/', $directory);
            $module = array_pop($dir);
            if( in_array($module, $this->modules) ) {
                $modulePath = array_search($module, $this->moduleMap);
                if( $modulePath ) {
                    $this->modulesOverridden[] = $modulePath;
                }
            }
        }

    }

    /**
     * Step 6: Scan all module directories for override files
     */
    private function scanModuleOverrides()
    {
        $moduleBasePath = $this->newPath . self::MAGENTO_BASE_MODULE_TEMPLATE;
        foreach( $this->modulesOverridden as $key => $module ) {
            $moduleFiles = array_filter($this->changedFiles, function($value) use($module){
                return substr($value,0,strlen($module)) == $module;
            });
            if( $moduleFiles ) {
                $moduleFiles = array_values($moduleFiles);
                foreach ($moduleFiles as $moduleFile) {
                    $path = str_replace($module . '/' . self::MAGENTO_BASE_MODULE_TEMPLATE, '', $moduleFile);
                    echo "$path\n";
                }
            }
        }
    }


    /**
     * Check that provided and expected paths exist
     */
    private function checkPaths()
    {
        $this->checkExists([
            $this->oldPath,
            $this->newPath,
            $this->oldPath . self::MAGENTO_BASE_COMPOSER,
            $this->newPath . self::MAGENTO_BASE_COMPOSER
        ]);
    }

    /**
     * Check that an array of paths exists
     *
     * @param $paths
     */
    private function checkExists($paths)
    {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                throw new Exception('`' . $path . '` not found.');
            }
        }
    }

    /**
     * Detect version of Magento 2 based on composer.json file (return false if not found)
     *
     * @param $basePath
     * @return bool|string
     */
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

    /**
     * Checks if path has trailing slash, and if not adds it
     *
     * @param $path
     * @return string
     */
    private function addTrailingSlash($path)
    {
        if (strlen($path) > 0 && $path{strlen($path)-1} != '/') {
            $path .= '/';
        }
        return $path;
    }


}

?>
