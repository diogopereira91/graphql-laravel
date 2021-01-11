<?php
namespace GraphQLCore\GraphQL\Support\CustomTypes;

use GraphQLCore\GraphQL\Support\Type as GraphQLType;

class OrderDirection extends GraphQLType
{
    protected $enumObject = true;

    protected $attributes = [
        'name' => 'EnumsOrderDirection',
        'description' => 'Ascending or descending order',
        'values' => [
            'ASC' => ['value' => 'ASC', 'description' => 'Ascending'],
            'DESC' =>['value' => 'DESC', 'description' => 'Descending']
        ]
    ];
}
