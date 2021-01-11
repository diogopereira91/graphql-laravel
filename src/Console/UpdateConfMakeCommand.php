<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class UpdateConfMakeCommand extends AbstractMakeCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:updateConfigs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update configs about schemas and global types.';

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $this->call('GraphQL:updateConfigSchemas');
            $this->call('GraphQL:updateConfigTypes');
            $this->call('GraphQL:updateConfigSublists');
        }
    }
}
