<?php

namespace GraphQLCore\GraphQL\Console\GraphQL;

use GraphQLCore\GraphQL\Console\GraphQL\Helpers\Generator as QueryGen;
use Illuminate\Console\Command;

abstract class AbstractMakeCommand extends Command
{
    protected $queryGen;

    private $dataGen = [];

    /**
     * Makes sure that path is ready for windows and ubuntu.
     *
     * @param array $parts Path parts
     *
     * @return string
     */
    public static function preparePath(array $parts): string
    {
        return QueryGen::preparePath($parts);
    }

    /**
     * Convert specific path or string to format of namespace.
     *
     * @param string $str
     */
    public static function convertNamespaceFormat(array $str): string
    {
        return QueryGen::convertNamespaceFormat($str);
    }

    /**
     * Init query generator.
     *
     * @param array $data
     */
    protected function initQueryGen(array $data): void
    {
        $this->queryGen = new QueryGen($data);
    }

    /**
     * Get list of subfolder in specific folder.
     *
     * @param string $folder
     *
     * @return array
     */
    protected function getFoldersList(string $folder, array $initialCharactes = [], array $ignoreList = []): array
    {
        return QueryGen::getFoldersList($folder, $initialCharactes, $ignoreList);
    }
}
