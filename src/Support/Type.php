<?php

namespace GraphQLCore\GraphQL\Support;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use Illuminate\Support\Fluent;
use GraphQLCore\GraphQL\Helper\GraphQLCache;
use GraphQL;
use Illuminate\Support\Str;

class Type extends Fluent
{

    protected static $instances = [];

    protected $inputObject    = false;
    protected $enumObject     = false;
    protected $unionType      = false;
    protected $requestedScope = '';
    protected $schemaName     = '';
    protected $enabledCache   = false;
    protected $cacheTags      = [];

    /**
     * Create a new fluent container instance.
     *
     * @param  array|object    $attributes
     * @return void
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $graphqlConfig = config('graphql');

        if (!empty($graphqlConfig['scopes']['cache']['enabled'])) {
            $this->enabledCache = true;
        }
    }

    public function getScope()
    {
        $attrs = $this->attributes;

        return $attrs['scope'] ?? '';
    }

    public function setSchemaName(string $schemaName)
    {
        $this->schemaName = $schemaName;
    }

    public function setRequestScope(string $scope)
    {
        $this->requestedScope = $scope;
    }

    public function getName()
    {
        $name = '';

        if (!empty($this->attributes['name'])) {
            $name = $this->attributes['name'];
        }

        return $name;
    }

    public function attributes()
    {
        return [];
    }

    public function fields()
    {
        return [];
    }

    public function interfaces()
    {
        return [];
    }

    protected function getFieldResolver($name, $field)
    {
        if (isset($field['resolve'])) {
            return $field['resolve'];
        }

        $resolveMethod = 'resolve' . Str::studly($name) . 'Field';

        if (method_exists($this, $resolveMethod)) {
            $resolver = array($this, $resolveMethod);
            return function () use ($resolver) {
                $args = func_get_args();
                return call_user_func_array($resolver, $args);
            };
        }

        return null;
    }

    public function getFields(array $fieldsParam = [])
    {
        $fields    = !empty($fieldsParam) ? $fieldsParam : $this->fields();
        $allFields = [];

        foreach ($fields as $name => $field) {
            if (is_string($field)) {
                $field            = app($field);
                $field->name      = $name;
                $allFields[$name] = $field->toArray();
            } elseif ($field instanceof FieldDefinition) {
                $allFields[$field->name] = $field;
            } else {
                $resolver = $this->getFieldResolver($name, $field);
                if ($resolver) {
                    $field['resolve'] = $resolver;
                }
                $allFields[$name] = $field;
            }
        }

        if (!empty($this->requestedScope) && empty($fieldsParam)) {
            $allFields = $this->checkFieldsByScope($allFields);
        }

        return $allFields;
    }

    /**
     * Get schema scope
     *
     * @return void
     */
    public function getSchemaScope(): string
    {
        $graphqlConfig = config('graphql');
        $schema        = $graphqlConfig['schemas'][$this->schemaName];

        return $schema['scope'] ?? '';
    }

    /**
     * Get schema types
     *
     * @return array
     */
    public function getSchemaTypes(): array
    {
        $graphqlConfig = config('graphql');
        $schema        = $graphqlConfig['schemas'][$this->schemaName];

        return $schema['types'] ?? [];
    }

    /**
     * Get general tags for cache
     *
     * @return array
     */
    private function tagsCache(): array
    {
        return [
            'schema_name_' . $this->schemaName,
            'schema_scope_' . $this->getSchemaScope(),
        ];
    }

    /**
     * Get tags for types
     *
     * @param string $fieldName
     * @return array
     */
    private function getTagsForTypes(string $fieldName): array
    {
        return array_merge($this->tagsCache(), [
            'type_name' . $this->name,
            'type_field_name_' . $fieldName,
        ]);
    }

    /**
     * Set type scope information to cache
     *
     * @param string $fieldName
     * @param array $scopeList
     * @return void
     */
    public function setTypeCache(string $fieldName, array $scopeList): void
    {
        if ($this->enabledCache) {
            GraphQLCache::setByTags($this->getTagsForTypes($fieldName), 'list', $scopeList);
        }
    }

    /**
     * Get type scope information from cache
     *
     * @param string $fieldName
     * @return array
     */
    public function getTypeCache(string $fieldName): array
    {
        $result = [];

        if ($this->enabledCache) {
            $list = GraphQLCache::getByTags($this->getTagsForTypes($fieldName), 'list');

            if (!is_null($list) && is_array($list)) {
                $result = $list;
            }
        }

        return $result;
    }

    /**
     * Get schema scope processed into list
     *
     * @return array
     */
    private function getSchemaScopeArray(): array
    {
        $scopeSchema      = $this->getSchemaScope();
        $scopeSchemaArray = $this->getTypeCache($scopeSchema);

        if (empty($scopeSchemaArray)) {
            $scopeSchemaArray = GraphQL::convertScopeToArrayLogic($scopeSchema);
            $this->setTypeCache($scopeSchema, $scopeSchemaArray);
        }

        return $scopeSchemaArray;
    }

    /**
     * Check fields by scope
     *
     * @param array $fields
     * @return array
     */
    private function checkFieldsByScope(array $fields): array
    {
        $result           = [];
        $schemaName       = $this->schemaName;
        $scopeSchema      = $this->getSchemaScope();
        $scopeSchemaArray = $this->getSchemaScopeArray();

        foreach ($fields as $fieldKey => $fieldValue) {
            $mergeResult = $this->getTypeCache($fieldKey);

            if (empty($mergeResult)) {
                $scopeType = $this->getScope();

                if (empty($scopeType)) {
                    $scopeType = $scopeSchema;
                }

                if ($fieldValue instanceof FieldDefinition) {
                    $fieldValue        = $fieldValue->config;
                    $fields[$fieldKey] = $fieldValue;
                }

                // Get some scope if there isn't a defined scope
                if (empty($fieldValue['scope'])) {
                    $typeClass = get_class($fieldValue['type']);
                    $typeName  = !empty($fieldValue['type']->name) ? $fieldValue['type']->name : '';

                    if (strpos($typeClass, 'ListOfType') !== false) {
                        $subTypeName = get_class($fieldValue['type']->ofType);
                        $typeName    = $fieldValue['type']->ofType->name;

                        if (!(strpos($typeClass, 'ObjectType') !== false)) {
                            $typeName = '';
                        }
                    }

                    //Check if the object it is ObjectType, if it does then we should get scope definition
                    if (!empty($typeName) && (strpos($typeClass, 'ObjectType') !== false || strpos($typeClass, 'ListOfType') !== false)) {
                        $schemaTypesList = $this->getSchemaTypes();

                        // If there is not defined type on schema, then we should look it in global types.
                        if (!empty($schemaTypesList[$typeName])) {
                            $obj = new $schemaTypesList[$typeName];
                        } else {
                            $graphqlConfig = config('graphql');
                            $globalTypes   = $graphqlConfig['types'];

                            $obj = new $globalTypes[$typeName];
                        }

                        /**
                         * We get scope if the object is defined by developer. The default object like StringType,
                         * PasswordType, etc. should he
                         */
                        // Get scope associated to
                        $scope = $obj->getScope();

                        if (!empty($scope)) {
                            $scopeType = $scope;
                        }
                    }

                    // Update object type
                    $fields[$fieldKey]['scope'] = $scopeType;
                }

                // Make logic for getting scope information
                $fieldScope = $fields[$fieldKey]['scope'];

                $fieldScopeArray = GraphQL::convertScopeToArrayLogic($fieldScope);
                $mergeResult     = array_merge($scopeSchemaArray, $fieldScopeArray);

                $this->setTypeCache($fieldKey, $mergeResult);
            }

            // Check if field can appear on graphql
            if ($this->validField($mergeResult)) {
                $result[$fieldKey] = $fields[$fieldKey];
            }
        }

        if (!empty($result)) {
            $result = $this->getFields($result);
        }

        // Rebuild all information about the current fields
        return $result;
    }

    /**
     * Check if the field is valid to show
     *
     * @param array $fieldScopeArray
     * @return boolean
     */
    private function validField(array $fieldScopeArray): bool
    {
        $result              = false;
        $requestedScopeArray = GraphQL::convertScopeToArrayLogic($this->requestedScope);

        if (GraphQL::validScope($requestedScopeArray, $fieldScopeArray)) {
            $result = true;
        }

        return $result;
    }

    /**
     * Get the attributes from the container.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = $this->attributes();
        $interfaces = $this->interfaces();

        $attributes = array_merge($this->attributes, [
            'fields' => function () {
                return $this->getFields();
            }
        ], $attributes);

        if (sizeof($interfaces)) {
            $attributes['interfaces'] = $interfaces;
        }

        return $attributes;
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

    public function toType()
    {
        if ($this->inputObject) {
            return new InputObjectType($this->toArray());
        }
        if ($this->enumObject) {
            return new EnumType($this->toArray());
        }
        return new ObjectType($this->toArray());
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$key]) ? $attributes[$key]:null;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return void
     */
    public function __isset($key)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$key]);
    }
}
