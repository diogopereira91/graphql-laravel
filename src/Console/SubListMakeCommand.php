<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class SubListMakeCommand extends AbstractMakeCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:createSubList';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new SubList';

    /**
     * SubList folder location
     * @var string
     */
    private $subListFolder = '';

    /**
     * Schemas folder
     *
     * @var string
     */
    private $schemasFolder = '';

    public function __construct()
    {
        parent::__construct();
        $this->schemasFolder = self::preparePath([base_path('app/GraphQL/Schemas')]);
    }

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $schemaFolder = $this->schemasFolder;

            $schemasFolders = glob($schemaFolder . '/*', GLOB_ONLYDIR);
            $schemas        = [];

            foreach ($schemasFolders as $dir) {
                $dirPieces = explode('/', $dir);
                $schemas[] = end($dirPieces);
            }

            $schema = $this->choice('What schema do you want to associate?', $schemas, 0);

            if (empty($schema)) {
                throw new \Exception('Not valid schema');
            }

            $subListSchema          = self::preparePath([$schema, 'SubList']);
            $subListSchemaNamespace = self::convertNamespaceFormat([$subListSchema]);

            $this->initQueryGen([
                'folderConfs' => self::preparePath([$this->schemasFolder, $subListSchema]),
                'fileConf'    => self::preparePath([base_path('config/graphql_schemas.php')]),
            ]);

            if (empty($this->queryGen)) {
                throw new \Exception('Cannot create a query generator');
            }

            $nameSpaceTypes = self::convertNamespaceFormat(['\App\GraphQL\Schemas', $subListSchemaNamespace]);

            /**
             * Set the class name
             */
            $checkName = true;

            while ($checkName) {
                $className = trim($this->ask('What is the class name?'));

                if (empty($className)) {
                    $this->info('Please insert a class name!');
                    continue;
                }

                $className = self::convertNamespaceFormat([$className]);

                $classNamePieces = explode('\\', $className);
                $classnameReal   = ucfirst(Str::camel(end($classNamePieces)));

                array_pop($classNamePieces);

                $classNamePieces[] = $classnameReal;
                $className         = implode('\\', $classNamePieces);
                $class             = self::convertNamespaceFormat([$nameSpaceTypes, $subListSchema, $className]);

                if (class_exists($class)) {
                    $this->info('There is a class with the same name!');
                    continue;
                }

                $checkName = false;
            }

            $data = [
                'schemaName'   => '',
                'typeTemplate' => 'subList',
                'className'    => $className,
            ];

            $this->queryGen->createSchemaClass($data);
            $this->queryGen->generateSublist($schema);
        }
    }
}
