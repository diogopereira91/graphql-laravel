<?php
namespace GraphQLCore\GraphQL;

use GraphQLCore\GraphQL\Exception\SchemaNotFound;
use GraphQLCore\GraphQL\Support\ListQuery;
use GraphQLCore\GraphQL\Support\ListType;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Type\Definition\ObjectType;
use GraphQLCore\GraphQL\Type\Definition\Type;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class GraphQL extends GraphQLHelper
{
    /**
     * Responsavel to return data that was collected to front-end.
     *
     * @param array $opts - additional options, like 'schema', 'context' or 'operationName'
     */
    public function query($query, $params = [], $opts = [])
    {
        $executionResult = $this->queryAndReturnResult($query, $params, $opts);

        $data         = config('api_structure');
        $data['data'] = $executionResult->data;

        // Add errors
        if (!empty($executionResult->errors)) {
            $errorFormatter = config('graphql.error_formatter', ['\GraphQLCore\GraphQL', 'formatError']);
            $data['errors'] = array_map($errorFormatter, $executionResult->errors);
        }

        return $data;
    }

    /**
     * Process submitted query
     *
     * @param [type] $query
     * @param array $params
     * @param array $opts
     * @return void
     */
    public function queryAndReturnResult($query, $params = [], $opts = [])
    {
        $context       = Arr::get($opts, 'context');
        $schemaName    = Arr::get($opts, 'schema');
        $operationName = Arr::get($opts, 'operationName');

        $schema = $this->schema($schemaName, $query);

        if (!is_null($schema)) {
            $result = GraphQLBase::executeQuery($schema, $query, null, $context, $params, $operationName);
        } else {
            $result = new ExecutionResult(null, [new Error('Schema not found')]);
        }

        return $result;
    }

    /**
     * Schema builder. It get all schema
     *
     * @param [type] $schema
     * @param string $query
     * @return void
     */
    public function schema($schema = null, $query = '')
    {
        if ($schema instanceof Schema) {
            return $schema;
        }

        $schemaName       = is_string($schema) ? $schema : config('graphql.default_schema', 'default');
        $this->schemaName = $schemaName;

        // Get custom types associated to grapqhl project.
        $this->getCustomTypes();

        // Check if the schema is defined
        if (!is_array($schema) && !isset($this->schemas[$schemaName])) {
            throw new SchemaNotFound('Schema ' . $schemaName . ' not found.');
        }

        // Get all classes associated to a specific schema
        $schemaClasses = is_array($schema) ? $schema : $this->schemas[$schemaName];
        $queryObj      = \GraphQL\Language\Parser::parse(new \GraphQL\Language\Source($query ?: '', 'GraphQL'));

        $this->schemaTypes    = $schemaClasses['types'] ?? [];
        $this->schemaSublists = $schemaClasses['sublists'] ?? [];

        if (!empty($queryObj->definitions)) {
            $schema = [];

            foreach ($queryObj->definitions as $node) {
                if (empty($node->operation)) {
                    break;
                }

                $operationName = $node->operation;

                if (!empty($node->selectionSet)) {
                    $subNodes = $node->selectionSet->selections;

                    foreach ($subNodes as $subNode) {
                        if (!empty($schemaClasses[$operationName][$subNode->name->value])) {
                            $schema[$operationName][$subNode->name->value] = $schemaClasses[$operationName][$subNode->name->value];
                        }
                    }
                }
            }
        }

        $schema = !empty($schema) ? $schema : $schemaClasses;

        if (empty($schema['types'])) {
            $globalTypes = config('graphql_types');

            if (!is_null($globalTypes)) {
                $schema['types'] = $globalTypes;
            }
        }

        $schema = $this->getSchemaAvailableClasses($schemaName, $schema);

        $schemaQuery        = Arr::get($schema, 'query', []);
        $schemaMutation     = Arr::get($schema, 'mutation', []);
        $schemaSubscription = Arr::get($schema, 'subscription', []);

        if (empty($schemaQuery) && empty($schemaMutation) && empty($schemaSubscription)) {
            return null;
        }

        $queryClasses = $this->objectType($schemaQuery, [
            'name' => 'Query',
        ]);

        $mutationClasses = $this->objectType($schemaMutation, [
            'name' => 'Mutation',
        ]);

        $subscriptionClasses = $this->objectType($schemaSubscription, [
            'name' => 'Subscription',
        ]);

        $schemaParams = [
            'query' => !empty($schemaQuery) ? $queryClasses : null,
            'mutation' => !empty($schemaMutation) ? $mutationClasses : null,
            'subscription' => !empty($schemaSubscription) ? $subscriptionClasses : null,
        ];

        if (!(strpos($query, 'IntrospectionQuery') !== false)) {
            $listTypeLoader = ['typeLoader' => function ($name) {
                try {
                    $result = $this->type($name);
                } catch (\Exception $e) {
                    $list = [
                        'OrderField',
                        'Order',
                        'Filters'
                    ];

                    $oriName = $name;

                    foreach ($list as $listValue) {
                        if (Str::startsWith($name, $listValue)) {
                            $oriName = Str::replaceFirst($listValue, '', $oriName);
                        }

                    }

                    $result = $this->sublist($oriName, $name);

                    if (empty($result)) {
                        throw new \Exception('Type ' . $name . ' not found.');
                    }
                }

                return $result;
            }];

            $schemaParams = array_merge($schemaParams, $listTypeLoader);
        } else {
            // Just in case of graphql-playground
            $schemaTypes = Arr::get($schema, 'types', []);

            //Get the types either from the schema, or the global types.
            $types = [];

            foreach ($schemaTypes as $name => $type) {
                $types[] = $this->type($name);
            }

            $schemaParams['types'] = $types;
        }

        return new Schema($schemaParams);
    }

    /**
     * Load special types. These type are equivalent with Type::string().
     * Ex: Type::password()
     *
     * @return array
     */
    public function loadSpecialTypes(String $type): void
    {
        $method   = strtolower($type);
        $isMethod = method_exists('GraphQLCore\GraphQL\Type\Definition\Type', $method);

        if ($isMethod) {
            $obj = Type::$method();

            $this->typesInstances[$obj->name] = $obj;
        }
    }

    /**
     * Process types
     *
     * @param [type] $name
     * @param boolean $fresh
     * @return object
     */
    public function type($name, $fresh = false)
    {
        if (is_object($name)) {
            $name = $this->updateTypes($name);
        }

        if (isset($this->schemaTypes[$name]) && !isset($this->typesInstances[$name])) {
            $type     = $this->schemaTypes[$name];
            $instance = $this->objectType($type);

            $name = $this->updateTypes($instance);

            // make sure that the global schema is replace with schema type.
            $this->addType($type, $name);
        }

        $name = ucfirst(Str::camel($name));

        if (!isset($this->types[$name])) {
            $this->loadSpecialTypes($name);

            if (empty($this->typesInstances[$name])) {
                throw new \Exception('Type ' . $name . ' not found.');
            }

            return $this->typesInstances[$name];
        }

        if (!$fresh && isset($this->typesInstances[$name])) {
            return $this->typesInstances[$name];
        }

        $type = $this->types[$name];

        if (!is_object($type)) {
            $type = app($type);
        }

        if ($this->enabledScopes) {
            $type->setRequestScope($this->scopeRequestString);
            $type->setSchemaName($this->schemaName);
        }

        $instance = $type->toType();

        $this->typesInstances[$name] = $instance;

        return $instance;
    }

    public function objectType($type, $opts = [])
    {
        // If it's already an ObjectType, just update properties and return it.
        // If it's an array, assume it's an array of fields and build ObjectType
        // from it. Otherwise, build it from a string or an instance.
        $objectType = null;

        if (is_array($type)) {
            $objectType = $this->buildObjectTypeFromFields($type, $opts);
        } else {
            $objectType = $this->buildObjectTypeFromClass($type, $opts);
        }

        return $objectType;
    }

    /**
     * Load sublist
     *
     * @param string $name
     * @param string $specificObj
     * @return object
     */
    public function sublist(string $name, string $specificObj = '')
    {
        $result = null;

        if (!empty($this->schemaSublists[$name])) {
            $type = $this->schemaSublists[$name];
        } elseif (!empty($this->schemaName) && !empty($this->schemas[$this->schemaName])) {
            $type = $this->schemas[$this->schemaName]['query'][$name];
        }

        if (!empty($type)) {
            $instance   = new $type();
            $result     = $instance->getAttributes();
            $objLoaded  = $this->objectType($result['args'], ['name' => $name]);

            $this->types[$name] = $type;
            $this->typesInstances[$name] = $objLoaded;

            $result = $this->listQuery($instance);

            if (!empty($specificObj)) {
                $result = null;

                if (!empty($this->typesInstances[$specificObj])) {
                    $result = $this->typesInstances[$specificObj];
                }
            }
        }

        return $result;
    }

    /**
     * Check if the schema expects a nest URI name and return the formatted version
     * Eg. 'user/me'
     * will open the query path /graphql/user/me
     *
     * @param $name
     * @param $schemaParameterPattern
     * @param $queryRoute
     *
     * @return mixed
     */
    public static function routeNameTransformer($name, $schemaParameterPattern, $queryRoute)
    {
        $multiLevelPath = explode('/', $name);
        $routeName      = null;

        if (count($multiLevelPath) > 1) {
            foreach ($multiLevelPath as $multiName) {
                $routeName = !$routeName ? null : $routeName . '/';
                $routeName =
                $routeName
                . preg_replace($schemaParameterPattern, '{' . $multiName . '}', $queryRoute);
            }
        }

        return $routeName ?: preg_replace($schemaParameterPattern, '{' . $name . '}', $queryRoute);
    }

    /**
     * Search List of dynamic types
     * @param  ObjectType $type     [description]
     * @param  array      $optional [description]
     * @return [type]               [description]
     */
    public function listType($type, $optional = [])
    {
        // If the instace type of the given pagination does not exists, create a new one!
        if (!isset($this->typesInstances[$type->name . 'ListType'])) {
            $this->typesInstances[$type->name . 'ListType'] = new ListType($type->name, $optional);
        }

        return $this->typesInstances[$type->name . 'ListType'];
    }

    /**
     * Search List dynamic field
     * @param  ListQuery $query
     * @param  array     $optional
     * @return array
     */
    public function listQuery(ListQuery $query, $optional = [])
    {
        $queryAttr = $query->getAttributes();

        unset($queryAttr['name']);
        unset($queryAttr['resolve']);

        foreach ($optional as $key => $value) {
            $queryAttr[$key] = $value;
        }

        return $queryAttr;
    }
}
