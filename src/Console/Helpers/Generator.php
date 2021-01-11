<?php

namespace GraphQLCore\GraphQL\Console\GraphQL\Helpers;

use Illuminate\Support\Str;

class Generator
{
    private $schemasIgnore = [
        'middleware',
        'scope',
    ];
    /**
     * Schema folder location.
     *
     * @author Guilherme Henriques
     *
     * @var string
     */
    private $folderConfs = '';

    /**
     * File that will be generated with schemas result.
     *
     * @author Guilherme Henriques
     *
     * @var string
     */
    private $fileConf = '';

    /**
     * Templates folder.
     *
     * @var string
     */
    private $templateFolders = '';

    /**
     * Construct.
     *
     * @author Guilherme Henriques
     */
    public function __construct(array $data = [])
    {
        $this->folderConfs = $data['folderConfs'];

        if (isset($data['fileConf'])) {
            $this->fileConf = $data['fileConf'];
        }

        $this->templateFolders = self::preparePath([__DIR__ . '/Templates/']);
    }

    /**
     * Set file path for graphql configurations. Ex: graphql_schemas.php.
     *
     * @param string $fileConf
     */
    public function setFileConf(string $fileConf): void
    {
        $this->fileConf = $fileConf;
    }

    /**
     * Set folder path where configs are defined (Types or Schemas).
     *
     * @param string $fileConf
     */
    public function setFolderConfs(string $folderConfs): void
    {
        $this->folderConfs = $folderConfs;
    }

    /**
     * Generate schema file.
     *
     * @author Guilherme Henriques
     */
    public function generateSchema(): void
    {
        $fileConf    = $this->fileConf;
        $folderConfs = $this->folderConfs;

        if (is_dir($folderConfs)) {
            $schemaResult = $this->getSchemas();
            $this->generateSpecificFile($fileConf, $schemaResult);

            /**
             * Update types schema.
             */
            $schemaResult = $this->getSchemas();

            foreach ($schemaResult as $nameSchema => $valueSchema) {
                //Update types from schema
                $this->folderConfs = self::preparePath([$folderConfs, $nameSchema, 'Types']);
                $this->generateTypes($nameSchema);

                //Update sublist from schema
                $this->folderConfs = self::preparePath([$folderConfs, $nameSchema, 'SubList']);
                $this->generateSublist($nameSchema);
            }
        }
    }

    /**
     * Generate type file.
     *
     * @author Guilherme Henriques
     */
    public function generateTypes(string $schemaName = ''): void
    {
        $fileConf    = $this->fileConf;
        $folderConfs = $this->folderConfs;

        if (is_dir($folderConfs)) {
            $typesResult = $this->getListConf();
            $this->generateSpecificFile($fileConf, $typesResult, $schemaName);
        }
    }

    /**
     * Merge classes and their keys into an array.
     *
     * @author Guilherme Henriques
     *
     * @param array $currentListClasses List of classes definided on config file
     * @param array $realListClasses    List of classes definided by project folder
     *
     * @return array
     */
    public function mergedClasses(array $currentListClasses = [], array $realListClasses = [], bool $keyUpper = false): array
    {
        $result = $realListClasses;

        if (!empty($currentListClasses)) {
            $result = [];

            foreach ($realListClasses as $realKey => $realClass) {
                $key = $realKey;

                if (\in_array($realClass, $currentListClasses, true)) {
                    $key = array_search($realClass, $currentListClasses, true);
                } elseif (!empty($currentListClasses[$realKey])) {
                    $key = $realKey . date('YmdHis');
                    self::showMsg('Please rename class key: ' . $key);
                }

                if ($keyUpper) {
                    $key = ucfirst($key);
                }

                $result[$key] = $realClass;
            }
        }

        return $result;
    }

    /**
     * Get types.
     *
     * @author Guilherme Henriques
     *
     * @param array  $result    List of types
     * @param string $typesPath Path to search
     *
     * @return array
     */
    public function getTypes(array $result = [], string $typesPath = ''): array
    {
        $typesPath = !empty($typesPath) ? $typesPath : $this->folderConfs;

        if (is_dir($typesPath)) {
            $folderCheck = scandir($typesPath);

            foreach ($folderCheck as $currentObj) {
                $fullPath = self::preparePath([$typesPath, $currentObj]);

                if ((is_dir($fullPath) && $currentObj != '.' && $currentObj != '..' ) ||
                    (is_file($fullPath) && strpos($fullPath, '.php') !== false)) {
                    if (is_dir($fullPath)) {
                        $result = $this->getTypes($result, $fullPath);
                    } else {
                        $className = str_replace('.php', '', $currentObj);

                        if ($this->folderConfs != $typesPath) {
                            $folderCheckPieces = explode($this->folderConfs . DIRECTORY_SEPARATOR, $typesPath);
                        } else {
                            $folderCheckPieces = explode(DIRECTORY_SEPARATOR, $typesPath);
                        }

                        $folderDadName   = end($folderCheckPieces);
                        $folderDadPieces = explode(DIRECTORY_SEPARATOR, $folderDadName);
                        $folderDadName   = str_replace(DIRECTORY_SEPARATOR, '', $folderDadName);

                        $fileKey = $folderDadName;

                        if (end($folderDadPieces) !== $className) {
                            $fileKey = $folderDadName . $className;
                        }

                        $fileKey = str_replace('types', '', $fileKey);
                        $fileKey = str_replace('Types', '', $fileKey);

                        $result[ucfirst($fileKey)] = $this->getNameSpace($fullPath);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Create a new Type class.
     *
     * @author Guilherme Henriques
     *
     * @param array $data List of parameters do create a new Type class
     */
    public function createTypeClass(array $data): void
    {
        $className       = $data['className'];
        $typeTemplate    = $data['typeTemplate'];
        $templateFileLoc = self::preparePath([$this->templateFolders, $typeTemplate . 'Type.template']);
        $keyConfig       = str_replace('\\', '', $data['className']);

        $classNameData = $this->getNamespaceFile($className);
        $namespace     = !empty($classNameData['namespace']) ? '\\' . $classNameData['namespace'] : '';

        $className = $classNameData['className'];

        $dataReplace = [
            'dataReplace' => [
                'namespace' => $namespace,
                'className' => $className,
                'keyConfig' => ucfirst($keyConfig),
            ],
            'templateFileLoc' => $templateFileLoc,
        ];

        $this->createClass($dataReplace);
    }

    /**
     * Get information about namespace and class.
     *
     * @author Guilherme Henriques
     *
     * @param string $className       Full class name (with namespace)
     * @param string $prefixNamespace More information to add in namespace
     *
     * @return array
     */
    public function getNamespaceFile(string $className = '', string $prefixNamespace = ''): array
    {
        $fullNamespace   = $prefixNamespace;
        $classNamePieces = explode('\\', $className);
        $lenthNamePieces = count($classNamePieces);

        if ($lenthNamePieces > 1) {
            $className = end($classNamePieces);
            unset($classNamePieces[$lenthNamePieces - 1]);
            $nameSpace = implode('\\', $classNamePieces);

            if (!empty($fullNamespace)) {
                $fullNamespace .= '\\';
            }

            $fullNamespace .= $nameSpace;
            $fullNamespace  = self::convertNamespaceFormat([$fullNamespace]);
        }

        return [
            'className' => $className,
            'namespace' => $fullNamespace,
        ];
    }

    /**
     * Create a new Type class.
     *
     * @author Guilherme Henriques
     *
     * @param array $data List of parameters do create a new Type class
     */
    public function createSchemaClass(array $data): void
    {
        $schemaName   = $data['schemaName'];
        $className    = $data['className'];
        $typeTemplate = lcfirst($data['typeTemplate']);
        $folderType   = 'Queries';

        if ($typeTemplate == 'subList') {
            $typeTemplate = 'listQuery';
            $folderType   = '';
        }

        if ($typeTemplate != 'query' && $typeTemplate != 'listQuery') {
            $folderType = 'Mutations';
        }

        $fileTemplate = $typeTemplate;

        if ($fileTemplate == 'mutation') {
            $fileTemplate = 'query';
        }

        if (!empty($folderType)) {
            $folderType .= '\\';
        }

        $folderType         .= $className;
        $templateFileLoc     = self::preparePath([$this->templateFolders, $fileTemplate . 'Schema.template']);
        $dataFile            = $this->getNamespaceFile($folderType, $schemaName);
        $namespace           = !empty($dataFile['namespace']) ? '\\' . $dataFile['namespace'] : '';
        $classNameUnderscore = str_replace('\\', '_', $className);

        $dataReplace = [
            'dataReplace' => [
                'namespace' => $namespace,
                'className' => $dataFile['className'],
                'typeQuery' => ucfirst($typeTemplate),
                'classNameLCF' => lcfirst(Str::camel($classNameUnderscore)),
            ],
            'templateFileLoc' => $templateFileLoc,
        ];

        $this->createClass($dataReplace);
    }

    /**
     * Get folder from specific path.
     *
     * @param string $folder
     * @param array  $initialCharactes
     *
     * @return array
     */
    public static function getFoldersList(string $folder, array $initialCharactes = [], array $ignoreList = []): array
    {
        $result = [];

        if (!empty($initialCharactes)) {
            $result = $initialCharactes;
        }

        $schemasDir = glob($folder, GLOB_ONLYDIR);

        foreach ($schemasDir as $dir) {
            $dir = self::preparePath([$dir]);
            $dirPieces  = explode(DIRECTORY_SEPARATOR, $dir);
            $folderName = end($dirPieces);

            if (empty($ignoreList) || (!empty($ignoreList) && !\in_array($folderName, $ignoreList, true))) {
                $result[] = $folderName;
            }
        }

        return $result;
    }

    /**
     * Geberate sublist list on graphql_schemas.
     *
     * @param [type] $schemaName
     */
    public function generateSublist($schemaName): void
    {
        if (is_dir($this->folderConfs)) {
            $currentSubList = $this->getListConf();
            $this->generateSpecificFile($this->fileConf, $currentSubList, $schemaName . '_sublist');
        } else {
            $msg = 'Folder "' . $this->folderConfs . '" doesn\'t exist. The submitted schema was: ' . $schemaName;
            self::showMsg($msg);
        }
    }

    /**
     * Get merged sublists about specific schema.
     *
     * @param array  $currentList
     * @param array  $realList
     * @param string $schemaName
     *
     * @return array
     */
    public function getMergedSublistSchemas(array $currentList, array $realList, string $schemaName): array
    {
        $currentList[$schemaName]['sublists'] = $realList;

        return $currentList;
    }

    /**
     * Show msg on console.
     *
     * @author Guilherme Henriques
     *
     * @param string $string Message to show
     */
    private static function showMsg(string $string): void
    {
        echo $string . "\n";
    }

    /**
     * Create file with specific content.
     *
     * @author Guilherme Henriques
     *
     * @param string $file File location
     * @param array  $list List of data
     */
    private function generateSpecificFile(string $file, array $list, string $schemaName = ''): void
    {
        if (!empty($list)) {
            $msg      = 'Create file "' . $file . '".';
            $fileFlag = 'x+';

            if (is_file($file)) {
                $currentSchemas = include $file;
                $fileFlag       = 'w+';
                $msg            = 'Update file "' . $file . '".';

                if (strpos($file, 'graphql_schemas') !== false) {
                    /*
                     * if schemaName is empty, it means that we will update all schemas, else we will only update types
                     * on defined schema.
                     */

                    if (empty($schemaName)) {
                        $list = $this->getMergedArraySchemas($currentSchemas, $list);
                    } else {
                        if (strpos($schemaName, 'sublist') !== false) {
                            $schemaName = str_replace('_sublist', '', $schemaName);
                            $list       = $this->getMergedSublistSchemas($currentSchemas, $list, $schemaName);
                        } else {
                            $list = $this->getMergedTypesSchemas($currentSchemas, $list, $schemaName);
                        }
                    }
                } else {
                    $list = $this->getMergedArrayTypes($currentSchemas, $list);
                }
            }

            $file = fopen($this->fileConf, $fileFlag);
            fwrite($file, "<?php\nreturn ");
            fwrite($file, var_export($list, true) . ';');
            fclose($file);

            self::showMsg($msg);
        }
    }

    private function getMergedTypesSchemas(array $currentList, array $realList, string $schemaName): array
    {
        $currentList[$schemaName]['types'] = $realList;

        return $currentList;
    }

    /**
     * Get array merged.
     *
     * @author Guilherme Henriques
     *
     * @param array $currentList list of types/schemas dependending of conf file
     * @param array $projectList get list of types defined for real on project
     *
     * @return array
     */
    private function getMergedArraySchemas(array $currentList, array $realList): array
    {
        $result        = $realList;
        $schemasIgnore = $this->schemasIgnore;

        if (!empty($currentList)) {
            $result = [];

            foreach ($realList as $realListKey => $realListValue) {
                $schemaName      = $realListKey;
                $realListQueries = $realList[$schemaName];

                /*
                 * If the current list as the current schema then is necessary to make some validation about class key and
                 * class location
                 */

                if (!empty($currentList[$schemaName])) {
                    self::showMsg('Processing schema "' . $schemaName . '"...');

                    foreach ($realListQueries as $queryName => $queryValue) {
                        $currentListClasses = [];
                        $realListClasses    = $queryValue;

                        if (!empty($currentList[$schemaName][$queryName])) {
                            $currentListClasses = $currentList[$schemaName][$queryName];
                        }

                        if (!in_array($queryName, $schemasIgnore, true)) {
                            self::showMsg('Checking ' . $queryName . '...');

                            $result[$schemaName][$queryName] = $this->mergedClasses($currentListClasses, $realListClasses);
                        } else {
                            /*
                             * This validation is needed because of middlware. The current folder cannot define middleware,
                             * so the only thing that the script can make is to make sure that middlware is the as developer
                             * defined.
                             */
                            $result[$schemaName][$queryName] = $currentListClasses;
                        }
                    }
                } else {
                    $result[$schemaName] = $realList[$schemaName];
                    self::showMsg('Schema "' . $schemaName . '" had been added!');
                }
            }
        }

        return $result;
    }

    /**
     * Get array merged.
     *
     * @author Guilherme Henriques
     *
     * @param array $currentList list of types/schemas dependending of conf file
     * @param array $projectList get list of types defined for real on project
     *
     * @return array
     */
    private function getMergedArrayTypes(array $currentList, array $realList): array
    {
        $result = $realList;

        if (!empty($currentList)) {
            $result = [];
            $result = $this->mergedClasses($currentList, $realList, true);

            foreach ($currentList as $key => $class) {
                if (false === strpos($class, 'App\GraphQL\Types')) {
                    $result[ucfirst($key)] = $class;
                }
            }
        }

        return $result;
    }

    /**
     * Get schemas for config graphql.
     *
     * @author Guilherme Henriques
     *
     * @return list of schemas
     */
    private function getSchemas(): array
    {
        $result       = [];
        $schemaFolder = $this->folderConfs;

        if (is_dir($schemaFolder)) {
            // Get schemas folders
            $folderCheck = scandir($schemaFolder);

            foreach ($folderCheck as $folder) {
                $currentFolder = self::preparePath([$schemaFolder, $folder]);

                if ((is_dir($currentFolder) && $folder != '.' && $folder != '..')) {
                    // Get folders types (Queries or Mutations)
                    $foldersType = scandir($currentFolder);

                    foreach ($foldersType as $folderType) {
                        $currentType = self::preparePath([$currentFolder, $folderType]);

                        if ($folderType == '.' || $folderType == '..' || $folderType == '.gitKeep') {
                            continue;
                        }

                        if (is_dir($currentType)) {
                            $fullPath = self::preparePath([$schemaFolder, $folder, $folderType]);

                            $key = lcfirst($folder);

                            if (!isset($result[$key])) {
                                $result[$key] = [];
                            }

                            $result[$key] = $result[$key] + $this->buildSchemasList($fullPath);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Build schemas.
     *
     * @author Guilherme Henriques
     *
     * @param string $folderCheck Folder to check
     * @param array  $result
     *
     * @return array
     */
    private function buildSchemasList(
        string $folderCheck = '',
        array $result = [],
        bool $folderName = false,
        string $folderDadParam = ''
    ): array {
        if (empty($result)) {
            $result['middleware'] = [];
            $result['scope']      = '';
        }

        $scanDir = scandir($folderCheck);

        foreach ($scanDir as $currentDir) {
            $folderCheckPieces = explode('/', $folderCheck);
            $folderDadName     = end($folderCheckPieces);

            if ($folderName) {
                $folderDadName = strtolower($folderDadParam . $folderDadName);
            }

            $fullPath = self::preparePath([$folderCheck, $currentDir]);

            if ($currentDir == '.' || $currentDir == '..' || (is_file($fullPath) && !strpos($currentDir, '.php'))) {
                continue;
            }

            if (is_dir($fullPath)) {
                if (!$folderName) {
                    $folderDadName = '';
                }

                $result = $this->buildSchemasList($fullPath, $result, true, $folderDadName);
            } else {
                $nameSpace = $this->getNameSpace($fullPath);
                $key       = $this->getKey($nameSpace);

                if (!empty($key)) {
                    $obj    = new $nameSpace();
                    $subKey = $obj->getName();

                    if (empty($subKey)) {
                        $subKey = $currentDir;

                        if ($folderName) {
                            $subKey = strtolower($folderDadName . $currentDir);
                        }

                        $subKey = str_replace('.php', '', $subKey);
                        $subKey = lcfirst($subKey);
                    }

                    $result[$key][$subKey] = $nameSpace;
                }
            }
        }

        return $result;
    }

    /**
     * Get key from father class.
     *
     * @author Guilherme Henriques
     *
     * @param string $class Class name
     *
     * @return string
     */
    private function getKey(string $class = ''): string
    {
        $result = '';

        if (!empty($class)) {
            $parent       = get_parent_class($class);
            $parentPieces = explode('\\', $parent);
            $result       = strtolower(end($parentPieces));

            if ($parent == 'GraphQLCore\GraphQL\Support\Query' ||
                is_subclass_of($parent, 'GraphQLCore\GraphQL\Support\Query') ||
                Str::contains($class, 'Queries')) {
                $result = 'query';
            } elseif ($parent == 'GraphQLCore\GraphQL\Support\Mutation' ||
                is_subclass_of($parent, 'GraphQLCore\GraphQL\Support\Mutation')) {
                $result = 'mutation';
            } elseif ($parent == 'GraphQLCore\GraphQL\Support\Type' ||
                $parent == 'GraphQLCore\GraphQL\Support\InterfaceType' ||
                is_subclass_of($parent, 'GraphQLCore\GraphQL\Support\Type') ||
                is_subclass_of($parent, 'GraphQLCore\GraphQL\Support\InterfaceType')) {
                $result = 'types';
            }

            if ($parent == 'GraphQLCore\GraphQL\Support\Type' ||
                $parent == 'GraphQLCore\GraphQL\Support\InterfaceType' ||
                is_subclass_of($parent, 'GraphQLCore\GraphQL\Support\Type') ||
                is_subclass_of($parent, 'GraphQLCore\GraphQL\Support\InterfaceType')) {
                $result = 'types';
            }
        }

        return $result;
    }

    /**
     * Check file namespace.
     *
     * @author Guilherme Henriques
     *
     * @param string $path File path
     *
     * @return string
     */
    private function getNameSpace(string $path = ''): string
    {
        $nameSpace = str_replace(base_path('app'), '', $path);
        $nameSpace = self::convertNamespaceFormat(['App', $nameSpace]);

        return str_replace('.php', '', $nameSpace);
    }

    /**
     * Replace a specific content on file.
     *
     * @author Guilherme Henriques
     *
     * @param string $result Content to show
     * @param array  $data   Data to replace
     *
     * @return string
     */
    private function replaceContent(string $result, array $data): string
    {
        foreach ($data as $dataKey => $dataValue) {
            if ($dataKey == 'namespace') {
                $path = self::preparePath([base_path('app/GraphQL/')]) . DIRECTORY_SEPARATOR;
                $strAddNamespace = str_replace($path, '', $this->folderConfs);
                $strAddNamespace = self::convertNamespaceFormat([$strAddNamespace]);
                $dataValue       = $strAddNamespace . $dataValue;
            }

            $result = str_replace('{{ ' . $dataKey . ' }}', $dataValue, $result);
        }

        return $result;
    }

    /**
     * Create a class (ListQuery, Type, Interface, Query,...) by using a template.
     *
     * @param array $data
     */
    private function createClass(array $data = []): void
    {
        $dataReplace     = $data['dataReplace'];
        $templateFileLoc = $data['templateFileLoc'];
        $className       = $dataReplace['className'];
        $namespace       = $dataReplace['namespace'];

        if (is_file($templateFileLoc)) {
            $templateContent = file_get_contents($templateFileLoc);
            $fileContent     = $this->replaceContent($templateContent, $dataReplace);
            $fileLocation    = self::preparePath([
                $this->folderConfs,
                $namespace,
                $className . '.php'
            ]);

            if (!is_dir(dirname($fileLocation))) {
                mkdir(dirname($fileLocation), 0775, true);
            }

            file_put_contents($fileLocation, $fileContent);

            $class = self::convertNamespaceFormat([$namespace, $className]);

            self::showMsg('File "' . $fileLocation . '" had been created!');
        } else {
            self::showMsg('There is no file ' . $templateFileLoc);
        }
    }

    /**
     * Get list of types / sublists.
     */
    private function getListConf(array $result = [], string $folder = ''): array
    {
        $folder = !empty($folder) ? $folder : $this->folderConfs;

        if (is_dir($folder)) {
            $folderCheck = scandir($folder);

            foreach ($folderCheck as $currentObj) {
                $fullPath = self::preparePath([$folder, $currentObj]);

                if ((is_dir($fullPath) && $currentObj != '.' && $currentObj != '..') ||
                    (is_file($fullPath) && strpos($fullPath, '.php') !== false)) {
                    if (is_dir($fullPath)) {
                        $result = $this->getListConf($result, $fullPath);
                    } else {
                        $namespace = $this->getNameSpace($fullPath);

                        try {
                            $obj  = new $namespace();
                            $name = $obj->getName();
                        } catch (\Exception $e) {
                            $name = '';
                        }

                        if (empty($name)) {
                            $className      = str_replace('.php', '', $currentObj);
                            $folderLitePath = str_replace($this->folderConfs, '', $folder);

                            $folderCheckPieces = [];

                            if (!empty($folder)) {
                                $folderCheckPieces = explode('/', $folderLitePath);
                            }

                            $fileKey = ucfirst($className);

                            krsort($folderCheckPieces);
                            $folderCheckPieces = array_values($folderCheckPieces);

                            foreach ($folderCheckPieces as $keyPiece => $pieceName) {
                                if ($keyPiece == 0 && $className == $pieceName) {
                                    continue;
                                }

                                $fileKey = ucfirst($pieceName . $fileKey);
                            }

                            $name = $fileKey;
                        }

                        $result[$name] = $namespace;
                    }
                }
            }
        }

        return $result;
    }



    /**
     * Makes sure that path is ready for windows and ubuntu.
     *
     * @param string $path
     *
     * @return string
     */
    public static function preparePath(array $complement = []): string
    {
        foreach ($complement as $key => $value) {
            unset($complement[$key]);

            if (!empty(trim($value))) {
                $str = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $value);
                $str = rtrim($str, DIRECTORY_SEPARATOR);

                if (!empty($str)) {
                    $complement[$key] = $str;
                }
            }
        }

        return join(DIRECTORY_SEPARATOR, $complement);
    }

    /**
     * Convert specific path or string to format of namespace.
     *
     * @param string $str
     */
    public static function convertNamespaceFormat(array $complement = []): string
    {
        foreach ($complement as $key => $value) {
            unset($complement[$key]);

            if (!empty(trim($value))) {
                $str = str_replace(DIRECTORY_SEPARATOR, '\\', $value);
                $str =  trim($str, '\\');

                if (!empty($str)) {
                    $complement[$key] = $str;
                }
            }
        }

        return join('\\', $complement);
    }
}
