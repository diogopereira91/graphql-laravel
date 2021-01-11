<?php

namespace GraphQLCore\GraphQL\Support;

use GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Rebing\GraphQL\Support\SelectFields;
use GraphQL\Type\Definition\ObjectType;
use GraphQLCore\GraphQL\Support\Mutation;
use GraphQLCore\GraphQL\Helper\Functions;
use Illuminate\Support\Str;

class FunctionsMutation extends Mutation
{
    protected $attributes = [
        'name'        => 'Functions',
        'description' => 'List of internal functions',
    ];

    /**
     * Returns type of mutation
     * Each field is the same function that is defined from class. Ex: System.php
     *
     * @return void
     */
    public function type()
    {
        $obj = [
            'name'        => ucfirst($this->attributes['name'] . "Type"),
            'description' => $this->attributes['description'],
            'fields'      => [],
        ];

        //list functions as fields
        $class   = new \ReflectionClass(\get_class($this));
        $methods = $class->getMethods();
        foreach ($methods as $method) {
            $name = $method->getName();
            if (Str::startsWith($name, 'exec')) {
                $fieldName  = lcfirst(Str::after($name, 'exec'));
                $docComment = $method->getDocComment();
                $dados      = Functions::parsePHPdoc($docComment);
                $typeParam  = '';

                $functionParams = $method->getParameters();
                $graphqlArgs    = [];

                foreach ($functionParams as $param) {
                    $type = $param->getType()->getName();

                    $graphqlArgs[$param->getName()] = [
                        'name'         => $param->getName(),
                        'type'         => GraphQL::stringToType($type),
                    ];
                }

                $type = Type::boolean();

                if (!empty($dados['type'])) {
                    $type = GraphQL::stringToType($dados['type'][0]);
                }

                $field                = [];
                $field['type']        = $type;
                $field['description'] = $dados['description'];
                $field['args']        = $graphqlArgs;

                if (!empty($dados['param'])) {
                    foreach ($dados['param'] as $param) {
                        $arg =[
                            'type' =>  GraphQL::stringToType($param['type']),
                            'description' => $param['description']
                        ];

                        $name                 =$param['varName'];
                        $field['args'][$name] =$arg;
                    }
                }

                $field['resolve'] = function ($root, $args) use ($method) {
                    return $method->invokeArgs($this, $args);
                };

                $obj['fields'][$fieldName] = $field;
            }
        }

        $obj = new ObjectType($obj);
        return GraphQL::type($obj);
    }

    /**
     * List of arguments for mutation
     *
     * @return void
     */
    public function args()
    {
        return [];
    }

    /**
     * Resolve for mutation
     *
     * @param [type] $root
     * @param [type] $args
     * @param [type] $context
     * @param ResolveInfo $info
     * @return void
     */
    public function resolve($root, $args, $context, ResolveInfo $info)
    {
        return new \stdClass;
    }
}
