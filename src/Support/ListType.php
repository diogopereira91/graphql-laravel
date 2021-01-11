<?php

namespace GraphQLCore\GraphQL\Support;

use GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class ListType extends ObjectType
{
    public function __construct($type, $optional = [])
    {
        $typeName = ucfirst($type);
        $conf     = [
            'name' => $typeName . 'List',
            'description' => 'List of ' . $typeName,
            'fields' => [
                'items' => [
                    'type' => Type::listOf(GraphQL::type($type)),
                ],
                'listInfo' => [
                    'type' => GraphQL::type('ListInfo'),
                    'resolve'=> function ($root, $args, $context, $info) {
                        $query    =$root->query;
                        $listArgs =$root->listQueryArgs;
                        $items    = $info->getFieldSelection(1);

                        $res =new \stdClass;
                        if (isset($items['total']) || isset($items['hasMore'])) {
                            $res->total = $query->count();
                        }
                        if (isset($items['endCursor']) && !empty($root->items)) {
                            $lastItem =end($root->items);
                            $cursor   =['id'=>$lastItem->id];
                            foreach ($listArgs['order'] as $key => $order) {
                                if (isset($lastItem['cursor_key' . $key])) {
                                    $order['value'] = $lastItem['cursor_key' . $key];
                                } else {
                                    $order['value'] = $lastItem[$order['field']];
                                }
                                $cursor['order'][] = $order;
                            }
                            $res->endCursor =\Crypt::encrypt(serialize($cursor));
                        }
                        if (isset($items['hasMore'])) {
                            $count =sizeof($root->items);
                            $skip  =$listArgs['skip'] ?? 0;
                            if (!isset($listArgs['take']) || $listArgs['take']>$count) {
                                $res->hasMore =false;
                            } else {
                                $count       +=$skip;
                                $res->hasMore =$count!=$res->total;
                            }
                        }

                        return $res;
                    }
                ],
            ],
        ];

        $conf['args'] = self::args();

        if (!empty($optional)) {
            foreach ($optional as $key => $value) {
                //replace
                if (\in_array($key, ['name', 'description'], true)) {
                    $conf[$key] = $value;
                } elseif (\in_array($key, ['fields', 'args'], true) && !empty($value)) { //merge
                    $conf[$key] = array_merge($default[$key], $value);
                }
            }
        }
        parent::__construct($conf);
    }

    /**
     * Get default arguments
     *
     * @author Guilherme Henriques
     */
    public static function args()
    {
        return [
            'skip' => [
                'type' => Type::int(),
                'description' => 'Skip X registers',
                'rules' => ['min:0', 'numeric'],
                'resolve' => function ($query, $value) {
                    return $query->skip($value);
                },
            ],
            'take' => [
                'type' => Type::int(),
                'description' => 'Items number to return',
                'rules' => ['min:0', 'numeric'],
                'resolve' => function ($query, $value) {
                    return $query->take($value);
                },
            ],
            'after' => [
                'type' => Type::string(),
                'description' => 'Results after getting information from cursor (infinite scroll)',
                'resolve' => function ($query, $value, $orderByOptions) {
                    if (null === $value) {
                        return $query;
                    }
                    $cursor = unserialize(\Crypt::decrypt($value));

                    if (empty($cursor['order'])) {
                        $query->afterId($cursor['id']);
                    } else {
                        $orderFields = []; //prever duplicados
                        $fields      = []; //filtros para paginação
                        foreach ($cursor['order'] as $order) {
                            if (in_array($order['field'], $orderFields)) {
                                continue;
                            }
                            $orderFields[] = $order['field'];

                            $order['compare'] = $order['dir']=="DESC" ? "<":">";

                            $fields[] = $order;
                            //se chegar ao campo id (unique) ignorar os próximos
                            if ($orderByOptions['ID']['value']==$order['field']) {
                                break;
                            }
                        }

                        $paginateFilter =function ($query, $fields, $key = 0) use (&$paginateFilter) {
                            $query->where(function ($q) use ($fields, $key, $paginateFilter) {
                                foreach ($fields as $k => $o) {
                                    if ($k<$key) {
                                        $q->where($o['field'], "=", $o['value']);
                                    } elseif ($k==$key) {
                                        $q->where($o['field'], $o['compare'], $o['value']);
                                    } else {
                                        $q->orWhere(function ($q) use ($fields, $key, $paginateFilter) {
                                            $q = $paginateFilter($q, $fields, ($key+1));
                                        });
                                        break;
                                    }
                                }
                            });
                            return $query;
                        };

                        $query = $paginateFilter($query, $fields);
                    }
                    return $query;
                },
            ],
        ];
    }
}
