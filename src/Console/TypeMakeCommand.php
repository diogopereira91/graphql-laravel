<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class TypeMakeCommand extends AbstractMakeCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:createType';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new type global/specific by schema';

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $nameSpaceTypes = '\App\GraphQL\Types';
            $typesFolder    = self::preparePath([base_path('app/GraphQL/Types')]);
            $fileConf       = self::preparePath([base_path('config/graphql_types.php')]);
            $schemaName     = '';

            /**
             * Question about type of generating about schemas
             */
            $typesTemplate = ['normal', 'enum', 'interface'];
            $typeTemplate  = $this->choice('What kind of generating that you want to do?', $typesTemplate, 0);


            $kindOfTypes = ['specific', 'global'];
            $kindOfType  = $this->choice('What kind of types it will be?', $kindOfTypes, 0);

            if ($kindOfType == 'specific') {
                $schemasFolder = self::preparePath([base_path('app/GraphQL/Schemas')]);

                /**
                 * Get schemas list
                 */
                $schemasToShow = $this->getFoldersList($schemasFolder . '/*');

                /**
                 * Select a specific schema to insert folder
                 */
                $schemaName = $this->choice('What schema do you want to associate?', $schemasToShow, 0);

                $typesFolder = self::preparePath([$schemasFolder, $schemaName, 'Types']);
                $fileConf    = self::preparePath([base_path('config/graphql_schemas.php')]);
            }

            /**
             * Define QueryGen constructor
             */
            $dataConstructor = [
                'folderConfs' => $typesFolder,
                'fileConf' => $fileConf,
            ];

            /**
             * Question about schema that we want to associate
             */
            $subFolder = '*';

            if ($typeTemplate == 'enum') {
                $subFolder = self::convertNamespaceFormat(['Enums', $subFolder]);
            }

            $folderToFindTypes = self::preparePath([$typesFolder, $subFolder]);
            $savedDirType      = '';

            /**
             * Select a specific schema to insert folder
             */
            while (true) {
                $dirsToShow  = $this->getFoldersList($folderToFindTypes, ['.'], ['Enums']);
                $dirTypeName = $this->choice('What folder do you want to associate?', $dirsToShow, 0);
                $checkFolder = true;

                if ($dirTypeName == '.') {
                    $dirTypeName = '';
                    break;
                }

                if (!empty($savedDirType)) {
                    $savedDirType .= '/';
                }

                $savedDirType     .= $dirTypeName;
                $folderToFindTypes = str_replace('*', $dirTypeName . '/*', $folderToFindTypes);
            }


            /**
             * Check class name and check if the the current class exists
             */
            $checkName = true;

            while ($checkName) {
                $className = trim($this->ask('What is the class name?'));

                if (empty($className)) {
                    $this->info('Please insert a class name!');
                    continue;
                }

                $className = self::convertNamespaceFormat([$className]);

                if (is_dir($typesFolder)) {
                    $className = self::convertNamespaceFormat([$savedDirType, $className]);

                    if ($typeTemplate == 'enum') {
                        $className = self::convertNamespaceFormat(['Enums', $className]);
                    }

                    $classNamePieces = explode('\\', $className);
                    $classnameReal   = ucfirst(Str::camel(end($classNamePieces)));

                    array_pop($classNamePieces);

                    $classNamePieces[] = $classnameReal;
                    $className         = implode('\\', $classNamePieces);

                    $class = self::convertNamespaceFormat([$nameSpaceTypes, $className]);

                    if (class_exists($class)) {
                        $this->info('There is a class with the same name!');
                        continue;
                    }
                }

                $checkName = false;
            }

            $this->initQueryGen($dataConstructor);
            $typeGen = $this->queryGen;

            if (is_null($typeGen)) {
                throw new \Exception('Cannot create a query generator');
            }

            $data = [
                'typeTemplate' => $typeTemplate,
                'className'    => $className,
            ];

            $typeGen->createTypeClass($data);
            $typeGen->generateTypes($schemaName);
        }
    }
}
