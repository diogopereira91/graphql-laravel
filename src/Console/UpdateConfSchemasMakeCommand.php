<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class UpdateConfSchemasMakeCommand extends QueryMakeCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:updateConfigSchemas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update config about schemas.';

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $this->queryGen->generateSchema();
        }
    }
}
