<?php
namespace GraphQLCore\GraphQL\Support;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Pagination\LengthAwarePaginator;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL;

class FunctionsType extends ObjectType
{
    public function __construct($functions)
    {
        $conf                =[];
        $conf['name']        =$name;
        $conf['description'] ='Options for sorting';
        $conf['enumObject']  =true;

        $conf['fields'] = [
                'total' => [
                    'type' => Type::int(),
                    'description' => 'Total of registers'
                ],
            ];

        $conf['values'] =[];
        foreach ($orderOptions as $key => $value) {
            $conf['values'][$key . "_ASC"]  =$value . " ASC";
            $conf['values'][$key . "_DESC"] =$value . " DESC";
        }

        parent::__construct($conf);
    }
}
