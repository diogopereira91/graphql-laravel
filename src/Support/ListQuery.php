<?php

namespace GraphQLCore\GraphQL\Support;

use GraphQL;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQLCore\GraphQL\Support\Query;
use GraphQLCore\GraphQL\Helper\Functions;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class ListQuery extends Query
{
    protected $attributes = [];

    private $prefix = '';

    public function __construct(array $attributes = [])
    {
        if (!empty($attributes)) {
            $this->attributes = array_merge($this->attributes, $attributes);
        }

        parent::__construct();
    }

    /**
     * Defines type of list
     * Exemple:
     *         return GraphQL::type('utilizador');
     *
     * @return [type]
     */
    public function itemType()
    {
        throw new Exception('Define List type', 1);
    }

    /**
     * Set custom arguments
     *
     * @author Guilherme Henriques
     * @return Array
     */
    public function customArgs()
    {
        return [];
    }

    /**
     * Define a new query for search
     * Example:
     *         return UtilizadorModel::select();
     *
     * @return [type]
     */
    public function newModelQuery()
    {
        throw new Exception('Define new Model Query', 1);
    }

    public function type()
    {
        return GraphQL::type(GraphQL::listType($this->itemType()));
    }

    public function args()
    {
        $args = ListType::args();

        $orderByOptions = $this->orderByOptions();

        $listName = ucfirst($this->attributes['name']);

        if (!empty($orderByOptions)) {
            $fieldOrderEnum = new EnumType([
                'name'        => 'OrderField' . $listName,
                'description' => 'Field responsable for sorting list',
                'values'      => $orderByOptions,
            ]);

            $orderInputType = new InputObjectType([
                'name'   => 'Order' . $listName,
                'fields' => [
                    'field' => [
                        'type'        => Type::nonNull(GraphQL::type($fieldOrderEnum)),
                        'description' => 'Field name to sort registers',
                    ],
                    'dir'   => [
                        'type'         => GraphQL::type('EnumsOrderDirection'),
                        'description'  => 'Sorting by (ASC/DESC). Default is ASC',
                        'defaultValue' => 'ASC',
                    ],
                ],
            ]);

            $args['order'] = [
                'description' => 'Sorting list',
                'type'        => Type::listOf(GraphQL::type($orderInputType)),
                'resolve'     => function ($query, $ordem) {
                    foreach ($ordem as $key => $value) {
                        //field é especifico de uma tabela ou não existe ainda na query
                        if (Str::contains($value['field'], '.') || !$query->hasColumn($value['field'])) {
                            $query->addSelect($value['field'] . ' as cursor_key' . $key);
                        }
                        $query->orderByRaw($value['field'] . ' ' . $value['dir']);
                    }

                    return $query;
                },
            ];
        }

        /**
         * Get fields from "filter"
         */
        $filterOptions = $this->filterOptions();

        /**
         * Get custom fields for filters using function customArgs
         */
        $customArgs = $this->getCustomArgs();

        /**
         * Merge theses fields
         */
        $filterOptions = array_merge($filterOptions, $customArgs);

        if (!empty($filterOptions)) {
            $filtersInputType = new InputObjectType([
                'name'   => 'Filters' . $listName,
                'fields' => $filterOptions,
            ]);

            $args['filters'] = [
                'description' => 'List filters',
                'type'        => GraphQL::type($filtersInputType),
                'resolve'     => function ($query, $filtros) use ($filterOptions) {
                    foreach ($filtros as $key => $value) {
                        if (!empty($filterOptions[$key]['resolve'])) {
                            $query = $filterOptions[$key]['resolve']($query, $value);
                        }
                    }

                    return $query;
                },
            ];
        }

        return $args;
    }

    /**
     * Create custom args for filter
     *
     * @author Guilherme Henriques
     * @return list of args.
     */
    private function getCustomArgs(): array
    {
        $result      = [];
        $customField = $this->customArgs();

        foreach ($customField as $arg_key => $args_value) {
            if (empty($args_value['type'])) {
                $obj = new InputObjectType([
                    'name' => $args_value['name'],
                    'fields' => $args_value['fields'],
                ]);

                $filters = GraphQL::type($obj);
            } else {
                $filters = $args_value['type'];
            }

            $result[$arg_key] = [
                'description' => $args_value['description'],
                'type'        => $filters,

            ];

            if (!empty($args_value['resolve'])) {
                $result[$arg_key]['resolve'] = $args_value['resolve'];
            }
        }

        return $result;
    }

    /**
     * List of field for list sorting
     * Lista de campos para ordenação da listagem.
     *
     * @return array
     */
    public function orderByOptions()
    {
        return ['ID' => 'id'];
    }

    /**
     * Options list that can be filtered.
     *
     * @return array
     */
    public function filterOptions()
    {
        return $this->filterOptionsReflection();
    }

    /**
     * Check if on class exists static functions. Their name should be start with filterBy. This will define all
     * filters.
     * It can be disabled by using the function "filterOptions".
     *
     * @return array
     */
    public function filterOptionsReflection()
    {
        $filters = [];
        $class   = new \ReflectionClass(\get_class($this));
        $methods = $class->getMethods();
        foreach ($methods as $method) {
            $name = $method->getName();
            if (Str::startsWith($name, 'filterBy')) {
                $filter      = lcfirst(Str::after($name, 'filterBy'));
                $test        = $method->getDocComment();
                $dados       = Functions::parsePHPdoc($test);
                $typeParam   = '';
                $countParams = count($dados['param']) - 1;
                $i           = 1;
                $isArgObj    = (strpos($test, 'object') !== false) || (strpos($test, 'stdClass') !== false);
                $args        = [];

                while ($i <= $countParams) {
                    $type = Type::string();

                    if (!empty($dados['param'][$i]['type'])) {
                        $typeParam = $dados['param'][$i]['type'];
                        $listOf    = false;
                        if ('[' === substr($typeParam, 0, 1) && ']' === substr($typeParam, -1)) {
                            $typeParam = substr($typeParam, 1, -1);
                            $listOf    = true;
                        }
                        switch (strtolower($typeParam)) {
                            case 'string':
                                $type = Type::string();

                                break;
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
                    }

                    $nameField = $dados['param'][$i]['varName'];

                    $args[$nameField] = [
                        'type' => $type,
                        'description' => $dados['param'][$i]['description'] ?? ''
                    ];

                    $i++;
                }

                if ($isArgObj) {
                    $objType = new InputObjectType([
                        'name'   => ucfirst($this->attributes['name']) . ucfirst($filter),
                        'fields' => $args,
                    ]);

                    $type = GraphQL::type($objType);
                }

                $filters[$filter] = [
                    'type' => $type,
                    'description' => $dados['description'],
                    'resolve'     => function ($query, $value) use ($method, $isArgObj) {
                        $result = [$query, $value];

                        if (is_array($value) && $isArgObj) {
                            $result = array_merge([$query], $value);
                        }

                        return $method->invokeArgs($this, $result);
                    },
                ];
            }
        }

        return $filters;
    }

    /**
     * Filter by an ID
     *
     * @param [type] $query
     * @param id     $id
     *
     * @return [type]
     */
    public function filterById($query, string $id)
    {
        return $query->id($id);
    }

    /**
     * Filter by multiple ids
     *
     * @param [type] $query
     * @param [id]   $ids
     *
     * @return [type]
     */
    public function filterByIds($query, array $ids)
    {
        return $query->ids($ids);
    }

    /**
     * Generate a initial query for list.
     * Apply all resolves for each arguments.
     *
     * @param [type] $root
     * @param [type] $args
     * @param mixed  $query
     * @param mixed  $context
     *
     * @return [type]
     */
    public function resolve($query, $args, $context, ResolveInfo $info)
    {
        $args['take'] = $args['take'] ?? PHP_INT_MAX;

        if (empty($query)) {
            $query = $this->newModelQuery();
        }
        $query = $this->modelQuerySetup($query);

        $orderByOptions = $this->orderByOptions();
        //aplicar ordem por defeito do ID para resolver o cursor
        if (empty($args['order']) || $args['order'][0]['field'] !== $orderByOptions['ID']['value']) {
            $args['order'][] = [
                'field' => $orderByOptions['ID']['value'],
                'dir'   => 'ASC',
            ];
        }

        $argsTypes    = $this->args();
        $afterArgType = Arr::pull($argsTypes, 'after');
        foreach ($argsTypes as $name => $arg) {
            if (isset($args[$name], $arg['resolve'])) {
                $query = $arg['resolve']($query, $args[$name]);
            }
        }

        //aplicar after cursor no fim
        if (isset($args['after'])) {
            $query = $afterArgType['resolve']($query, $args['after'], $orderByOptions);
        }

        $res = new \stdClass();

        $fields = $info->getFieldSelection(1);
        if (!empty($fields['items']) || !empty($fields['listInfo']['hasMore']) || !empty($fields['listInfo']['endCursor'])) {
            if (!empty($fields['items'])) {
                //obter dados associados aos items necessarios
                $query = $this->setQueryWith($query, $fields['items']);
            }

            $res->items = $this->processItems($query->get())->all();
        }

        $res->query         = $query;
        $res->listQueryArgs = $args;

        return $res;
    }

    /**
     * Process the query result.
     *
     * @param [type] $items
     * @return void
     */
    public function processItems($items)
    {
        return $items;
    }

    /**
     * Populate a query with needed data to each requested fields
     *
     * @param [type] $query  query SQL do pedido
     * @param [type] $fields dados pedidos
     * @return $query
     */
    public function setQueryWith($query, $fields)
    {
        $typeFields = $this->itemType()->getFields();
        foreach ($fields as $field => $boolean) {
            if (!empty($typeFields[$field]->config['queryWith'])) {
                $query->with($typeFields[$field]->config['queryWith']);
            }
        }

        return $query;
    }

    /**
     * Apply some share configurations to query.
     * This function can be called by other queries inside in other types.
     *
     * @param [type] $query
     *
     * @return [type]
     */
    public function modelQuerySetup($query)
    {
        return $query;
    }
}
