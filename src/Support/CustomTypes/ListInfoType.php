<?php
namespace GraphQLCore\GraphQL\Support\CustomTypes;

use GraphQL\Type\Definition\Type;
use GraphQLCore\GraphQL\Support\Type as GraphQLType;

class ListInfoType extends GraphQLType
{
    protected $attributes = [
        'name' => 'ListInfo',
        'description' => 'Information about pagination',
    ];

    public function fields()
    {
        return [
            'total' => [
                'type' => Type::int(),
                'description' => 'Total of register',
            ],
            'endCursor' => [
                'type' => Type::string(),
                'description' => 'Last register cursor',
            ],
            'hasMore' => [
                'type' => Type::boolean(),
                'description' => 'Defines if there is more registers to show',
            ],
        ];
    }
}
