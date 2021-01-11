<?php
namespace GraphQLCore\GraphQL;

use GraphQLCore\GraphQL\Error\ValidationError;
use GraphQLCore\GraphQL\Helper\GraphQLCache;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLCore\GraphQL\Support\ListType;
use GraphQL\Type\Definition\ListOfType;

abstract class GraphQLHelper
{
    /**
     * GraphQL loaded
     *
     * @var [type]
     */
    protected $app = null;

    /**
     * List of schemas
     *
     * @var array
     */
    protected $schemas = [];

    /**
     * List Of types
     *
     * @var array
     */
    protected $types = [];

    /**
     * List of types instances
     *
     * @var array
     */
    protected $typesInstances = [];

    /**
     * Schema scope into a string
     *
     * @var string
     */
    protected $scopeSchemaString = '';

    /**
     * List of data about schema scope.
     *
     * @var array
     */
    protected $scopeSchemaArray = [];

    /**
     * Scope request into a string
     *
     * @var string
     */
    protected $scopeRequestString = '';

    /**
     * List of data about request scope.
     *
     * @var array
     */
    protected $scopeRequestArray = [];

    /**
     * Check if scope is enabled.
     *
     * @var boolean
     */
    protected $enabledScopes = true;

    /**
     * Flag to enable cache
     *
     * @var [type]
     */
    protected $enabledCache = false;

    /**
     * Schema name
     *
     * @var string
     */
    protected $schemaName = '';

    /**
     * List of types associated to schema
     *
     * @var array
     */
    protected $schemaTypes = [];

    /**
     * List of sublists associated to schema
     *
     * @var array
     */
    protected $schemaSublists = [];

    public function __construct($app)
    {
        $this->app = $app;

        $graphqlConfig = config('graphql');

        if (isset($graphqlConfig['scopes']['enabled']) && $graphqlConfig['scopes']['enabled'] == false) {
            $this->enabledScopes = false;
        } else {
            if (!empty($graphqlConfig['scopes']['cache']['enabled'])) {
                $this->enabledCache = true;
            }
        }
    }

    /**
     * Set scope flag
     *
     * @param boolean $flag
     * @return void
     */
    public function setScopeFlag(bool $flag)
    {
        $this->enabledScopes = $flag;
    }

    /**
     * Set cache flag
     *
     * @param boolean $flag
     * @return void
     */
    public function setCacheFlag(bool $flag)
    {
        $this->enabledCache = $flag;
    }

    /**
     * Set scope to memory
     *
     * @param string $scope
     * @return void
     */
    public function setScope(string $scope): void
    {
        $this->scopeRequestString = $scope;
        $this->scopeRequestArray  = $this->convertScopeToArray($scope);
    }

    /**
     * Get information about specific schema. It will contains all information about available classes that external
     * entity can access.
     *
     * @param string $schemaName
     * @param array $schemaData
     * @return array
     */
    protected function getSchemaAvailableClasses(string $schemaName, array $schemaData): array
    {
        $list = null;

        if ($this->enabledScopes) {
            $tags = [
                'schema_name_' . $schemaName,
                'scope_entity_' . $this->scopeRequestString,
            ];

            if ($this->enabledCache) {
                $list = GraphQLCache::getByTags($tags, 'list');
            }

            if (is_null($list)) {
                if (empty($schemaData['scope'])) {
                    $graphqlConfig = config('graphql');
                    $schema        = $graphqlConfig['schemas'][$schemaName];

                    $schemaData['scope'] = $schema['scope'];
                }

                $list = $this->filterSchemaByScope($schemaData);

                if ($this->enabledCache) {
                    GraphQLCache::setByTags($tags, 'list', $list);
                }
            }
        } else {
            $list = $schemaData;
        }

        return $list;
    }

    /**
     * Get all queries, mutations and subscription and filter by scope.
     *
     * @param array $schemaData
     * @return array
     */
    private function filterSchemaByScope(array $schemaData): array
    {
        $result = $schemaData;

        if ($this->enabledScopes) {
            $scopeSchema         = !empty($schemaData['scope']) ? $schemaData['scope'] : '';
            $schemaOriginalScope = $this->convertScopeToArray($scopeSchema);

            $this->scopeSchemaString = $scopeSchema;
            $this->scopeSchemaArray  = $schemaOriginalScope;

            foreach ($schemaData as $dataKey => $dataValue) {
                if ($dataKey != 'scope' && $dataKey != 'middleware') {
                    $queryClasses = $dataValue;

                    foreach ($queryClasses as $classKey => $classValue) {
                        $scopeSchemaArray = $schemaOriginalScope;

                        $classInstance = new $classValue;
                        $classScope    = $classInstance->getScope();

                        $scopeArray = $this->convertScopeToArray($classScope);

                        foreach ($scopeArray as $scopeKey => $scopeValue) {
                            $scopeSchemaArray[$scopeKey] = $scopeValue;
                        }

                        if (!(GraphQLHelper::validScope($this->scopeRequestArray, $scopeSchemaArray))) {
                            unset($result[$dataKey][$classKey]);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Check if the requested is valid comparing with schema scope.
     *
     * @param array $requestedScope
     * @param array $schemaScope
     * @return boolean
     */
    public static function validScope(array $requestedScope, array $schemaScope): bool
    {
        $result = true;

        foreach ($schemaScope as $schemaScopeKey => $schemaScopeValue) {
            if (empty($requestedScope[$schemaScopeKey])) {
                $result = false;
                break;
            }

            $listOfValues = $requestedScope[$schemaScopeKey];
            $aux = count($listOfValues);

            foreach ($listOfValues as $requestVal) {
                if (!in_array($requestVal, $schemaScopeValue)) {
                    $aux--;
                }
            }

            if ($aux == 0) {
                $result = false;
                break;
            }
        }

        return $result;
    }


    /**
     * Convert string scope to array
     *
     * @param String $scope
     * @return array
     */
    private function convertScopeToArray(String $scope): array
    {
        $result = null;

        if ($this->enabledCache) {
            $result = GraphQLCache::get($scope);
        }

        if (is_null($result)) {
            $result = GraphQLHelper::convertScopeToArrayLogic($scope);

            if ($this->enabledCache) {
                GraphQLCache::set($scope, $result);
            }
        }

        return $result;
    }

    /**
     * Logic to convert scope to array
     *
     * @param String $scope
     * @return void
     */
    public static function convertScopeToArrayLogic(String $scope)
    {
        $result      = [];
        $scopePieces = explode('|', $scope);
        $valueKey    = '';

        foreach ($scopePieces as $scopeValue) {
            $value        = [];
            $valuesScopes = explode('=', $scopeValue);
            $valueKey     = $valuesScopes[0];

            unset($valuesScopes[0]);

            foreach ($valuesScopes as $valueValue) {
                $value = explode('#', $valueValue);
            }

            if (!empty($value)) {
                $result[$valueKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Add a specific schema to a list
     *
     * @param [type] $name
     * @param [type] $schema
     * @return void
     */
    public function addSchema($name, $schema)
    {
        $this->schemas[$name] = $schema;
    }

    /**
     * Clear specific type from list
     *
     * @param [type] $name
     * @return void
     */
    public function clearType($name)
    {
        if (isset($this->types[$name])) {
            unset($this->types[$name]);
        }
    }

    /**
     * Clear specific schema
     *
     * @param [type] $name
     * @return void
     */
    public function clearSchema($name)
    {
        if (isset($this->schemas[$name])) {
            unset($this->schemas[$name]);
        }
    }

    /**
     * Clear list of types
     *
     * @return void
     */
    public function clearTypes()
    {
        $this->types = [];
    }

    /**
     * Clear schemas list
     *
     * @return void
     */
    public function clearSchemas()
    {
        $this->schemas = [];
    }

    /**
     * Get list of types
     *
     * @return void
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * Get list of schemas
     *
     * @return void
     */
    public function getSchemas()
    {
        return $this->schemas;
    }

    /**
     * Clear types instances list
     *
     * @return void
     */
    protected function clearTypeInstances()
    {
        $this->typesInstances = [];
    }

    /**
     * Add specific type to in list
     *
     * @param [type] $class
     * @param [type] $name
     * @return void
     */
    public function addType($class, $name = null)
    {
        if (!$name) {
            $type = is_object($class) ? $class : app($class);
            $name = $type->name;
        }

        $this->types[$name] = $class;
    }

    /**
     * Update list of types and list of types Instances.
     *
     * @param Object $obj
     * @return string
     */
    protected function updateTypes($obj): string
    {
        $types    = $this->getTypes();
        $nameType = $obj->name;

        if (empty($types[$nameType])) {
            $this->typesInstances[$nameType] = $obj;
            $this->types[$nameType]          = $nameType;
        }

        return $nameType;
    }

    /**
     * Get custom types that are defined. Ex: ListInfoType.php
     *
     * @return void
     */
    protected function getCustomTypes()
    {
        $folder = __DIR__ . '/Support/CustomTypes';

        if (is_dir($folder)) {
            $files = scanDir($folder);

            foreach ($files as $file) {
                $fullPathFile = $folder . '/' . $file;

                if (is_file($fullPathFile)) {
                    $filesPieces = explode('.php', $file);
                    $className   = 'GraphQLCore\\GraphQL\\Support\\CustomTypes\\' . $filesPieces[0];

                    $classLoaded = new $className;
                    $attrs       = $classLoaded->getAttributes();
                    $name        = !empty($attrs['name']) ? $attrs['name'] : $filesPieces[0];

                    $this->types[$name] = $className;

                    $this->type($name);
                }
            }
        }
    }

    /**
     * Build an object type from a specific class
     *
     * @param [type] $type
     * @param array $opts
     * @return void
     */
    protected function buildObjectTypeFromClass($type, $opts = [])
    {
        if (!is_object($type)) {
            $type = $this->app->make($type);
        }

        foreach ($opts as $key => $value) {
            $type->{$key} = $value;
        }

        if ($this->enabledScopes) {
            $type->setRequestScope($this->scopeRequestString);
            $type->setSchemaName($this->schemaName);
        }

        return $type->toType();
    }

    /**
     * Get types name from his class.
     *
     * @param [type] $class
     * @param [type] $name
     * @return void
     */
    protected function getTypeName($class, $name = null)
    {
        if ($name) {
            return $name;
        }

        $type = is_object($class) ? $class : $this->app->make($class);
        return $type->name;
    }

    /**
     * Process error and prepare it for output
     *
     * @author Guilherme Henriques
     * @param  Error  $e [description]
     * @return [type]    [description]
     */
    public static function formatError(Error $e)
    {
        $error = [
            'message' => $e->getMessage(),
        ];

        $previous = $e->getPrevious();
        if (!empty($previous) && !empty($previous->getCode())) {
            $error['code'] = $previous->getCode();
        }

        if ($previous && $previous instanceof ValidationError) {
            $error['validation'] = $previous->getValidatorMessages();
        }

        $env = strtolower(env('APP_ENV'));

        if ($env != 'production') {
            $traceInfo     = $e->getTrace()[0];
            $argsTraceInfo = $traceInfo['args'][0];

            if (method_exists($argsTraceInfo, 'getTrace')) {
                $moreSpecific = $argsTraceInfo->getTrace()[0];

                $error['trace'] = [
                    'file' => $argsTraceInfo->getFile(),
                    'line' => $argsTraceInfo->getLine(),
                    'class' => $moreSpecific['class'] ?? null,
                    'functionClass' => $moreSpecific['function'] ?? null,
                    'typeException' => get_class($argsTraceInfo),
                ];
            }
        }

        $locations = $e->getLocations();

        if (!empty($locations)) {
            $error['locations'] = array_map(function ($loc) {
                return $loc->toArray();
            }, $locations);
        }

        return $error;
    }

    /**
     * Build an object type from a specific field
     *
     * @param [type] $fields
     * @param array $opts
     * @return void
     */
    protected function buildObjectTypeFromFields($fields, $opts = [])
    {
        $typeFields = [];
        foreach ($fields as $name => $field) {
            if (is_string($field)) {
                $field       = $this->app->make($field);
                $name        = is_numeric($name) ? $field->name : $name;
                $field->name = $name;
                $field       = $field->toArray();
            } else {
                $name          = is_numeric($name) ? $field['name'] : $name;
                $field['name'] = $name;
            }
            $typeFields[$name] = $field;
        }

        return new ObjectType(array_merge([
            'fields' => $typeFields
        ], $opts));
    }

    /**
     * Get a type name and define the type of object
     * Ex.:
     *      'string' => Type::string()
     *      'int' => Type::int();
     *
     * @param string $typeParam
     * @return void
     */
    public function stringToType(string $typeParam)
    {
        $listOf = false;
        if ('[' === substr($typeParam, 0, 1) && ']' === substr($typeParam, -1)) {
            $typeParam = substr($typeParam, 1, -1);
            $listOf    = true;
        }

        switch (strtolower($typeParam)) {
            case 'string':
                $type = Type::string();

                break;
            case 'integer':
            case 'int':
                $type = Type::int();

                break;
            case 'id':
                $type = Type::id();

                break;
            case 'float':
                $type = Type::float();

                break;
            case 'bool':
            case 'boolean':
                $type = Type::boolean();
                break;
            default:
                if (class_exists("\App\GraphQL\Input\\" . $typeParam)) {
                    $class = "\App\GraphQL\Input\\" . $typeParam;
                    $type  = \GraphQL::type($class::type());
                } else {
                    $type = \GraphQL::type($typeParam);
                }

                break;
        }
        if ($listOf) {
            $type = Type::listOf($type);
        }

        return $type;
    }
}
