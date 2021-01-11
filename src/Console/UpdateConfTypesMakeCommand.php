<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class UpdateConfTypesMakeCommand extends AbstractMakeCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:updateConfigTypes {schema?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update config about types.';

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $schemaName = '';

            if ($this->argument('schema')) {
                $schemaName = $this->argument('schema')[0];
            }

            $data = [
                'folderConfs' => self::preparePath([base_path('app/GraphQL/Types')]),
                'fileConf'    => self::preparePath([base_path('config/graphql_types.php')]),
            ];

            $this->initQueryGen($data);

            $typesGen = $this->queryGen;
            $typesGen->generateTypes();

            /**
             * Update schema types if schemas had been submitted
             */
            if (!empty($schemaName)) {
                $configSchemas = self::preparePath([base_path('config/graphql_schemas.php')]);
                $folderConfs   = self::preparePath([base_path('app/GraphQL/Schemas/' . $schemaName . '/Types')]);

                $typesGen->setFileConf($configSchemas);
                $typesGen->setFolderConfs($folderConfs);
                $typesGen->generateTypes($schemaName);
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
