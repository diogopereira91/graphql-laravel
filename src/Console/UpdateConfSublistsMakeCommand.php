<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class UpdateConfSublistsMakeCommand extends AbstractMakeCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:updateConfigSublists {schema?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update config about sublists.';

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $schemaFolder = self::preparePath([base_path('app/GraphQL/Schemas')]);
            $schema       = '';

            if ($this->argument('schema')) {
                $schema = $this->argument('schema');
            }

            if (!empty($schema)) {
                $subListSchema =  self::preparePath([$schemaFolder, $schema, 'SubList']);

                $this->initQueryGen([
                    'folderConfs' => $subListSchema,
                    'fileConf'    => self::preparePath([base_path('config/graphql_schemas.php')]),
                ]);

                $this->queryGen->generateSublist($schema);
            } else {
                $schemasFolders = glob($schemaFolder . '/*', GLOB_ONLYDIR);
                $schemas        = [];

                foreach ($schemasFolders as $dir) {
                    $dirPieces = explode('/', $dir);
                    $schemas[] = end($dirPieces);
                }

                foreach ($schemas as $schemaName) {
                    $subListSchema =  self::preparePath([$schemaFolder, $schemaName, 'SubList']);

                    $this->initQueryGen([
                        'folderConfs' => $subListSchema,
                        'fileConf'    => self::preparePath([base_path('config/graphql_schemas.php')]),
                    ]);

                    $this->queryGen->generateSublist($schemaName);
                }
            }
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['schema', InputOption::VALUE_OPTIONAL, 'Schema\'s name.']
        ];
    }
}
