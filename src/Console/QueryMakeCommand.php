<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class QueryMakeCommand extends AbstractMakeCommand
{
    /**
     * Command signature.
     *
     * @var string
     */
    protected $signature = 'GraphQL:createQuery {--type= : This type could be "Query", "Mutation" and "ListQuery"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new query or mutation';

    /**
     * Schemas folder.
     *
     * @var string
     */
    private $schemasFolder = '';

    public function __construct()
    {
        parent::__construct();
        $this->schemasFolder = self::preparePath([base_path('app/GraphQL/Schemas')]);

        $this->initQueryGen([
            'folderConfs' => $this->schemasFolder,
            'fileConf' => self::preparePath([base_path('config/graphql_schemas.php')]),
        ]);

        if (empty($this->queryGen)) {
            throw new \Exception('Cannot create a query generator');
        }
    }

    /**
     * Execute script.
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $schemasFolder  = $this->schemasFolder;
            $queryGen       = $this->queryGen;
            $nameSpaceTypes = '\App\GraphQL\Schemas';

            $schema = '';

            if (!empty($this->option('type'))) {
                $typeTemplate = $this->option('type');
                $typeTemplate = ucfirst($typeTemplate);
            } else {
                $typesTemplate = ['Query', 'Mutation', 'ListQuery'];
                $typeTemplate  = $this->choice('What kind of generating that you want to do?', $typesTemplate, 0);
            }

            /**
             * Get schemas list.
             */
            $schemasToShow   = $this->getFoldersList($schemasFolder . '/*');
            $schemasToShow[] = 'other';

            /**
             * Select a specific schema to insert folder.
             */
            $schemaName  = $this->choice('What schema do you want to associate?', $schemasToShow, 0);
            $dirSchema   = self::preparePath([$schemasFolder, $schemaName]);
            $checkFolder = true;

            if ($schemaName == 'other') {
                while ($checkFolder) {
                    $schemaName = trim($this->ask('Set the schema name?'));

                    if (empty($schemaName)) {
                        $this->info('Please insert a schema name!');

                        continue;
                    }

                    $dirSchema = self::preparePath([$schemasFolder, $schemaName]);

                    if (!is_dir($dirSchema)) {
                        mkdir($dirSchema, 0775, true);
                        $checkFolder = false;
                    }
                }
            }

            /**
             * Set the class name.
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

                $dirSchema = self::preparePath([$schemasFolder, $schemaName]);

                if (is_dir($dirSchema)) {
                    $class = self::convertNamespaceFormat([$nameSpaceTypes, $className]);

                    if (class_exists($class)) {
                        $this->info('There is a class with the same name!');

                        continue;
                    }
                }

                $checkName = false;
            }

            /**
             * Build schema.
             */
            $data = [
                'schemaName' => $schemaName,
                'typeTemplate' => $typeTemplate,
                'className' => $className,
            ];

            $this->queryGen->createSchemaClass($data);
            $this->queryGen->generateSchema();
        }
    }
}
