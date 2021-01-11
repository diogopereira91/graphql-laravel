<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CreateMakeCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GraphQL:create';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create classes about GraphQL. Ex: Types, ListQuery, Query and Mutation.';

    /**
     * Execute script
     *
     * @return void
     */
    public function handle()
    {
        if (\App::environment('local')) {
            $typeClass = [
                'Query',
                'Mutation',
                'ListQuery',
                'Type',
                'SubList',
            ];

            $args    = [];
            $typeGen = $this->choice('What kind of generating do you want to do?', $typeClass, 0);

            if ($typeGen != 'Type' && $typeGen != 'SubList') {
                $args    = ['--type' =>  $typeGen];
                $typeGen = 'Query';
            }

            $this->call('GraphQL:create' . $typeGen, $args);
        }
    }
}
