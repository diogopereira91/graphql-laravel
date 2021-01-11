<?php

namespace GraphQLCore\GraphQL\Helper;

use Cache;

class GraphQLCache
{

    /**
     * Update time for caching
     */
    protected static $minToKeep = 10;

    /**
     * Main cache name
     *
     * @var string
     */
    protected static $cacheName = '';

    /**
     * Store name
     *
     * @var string
     */
    protected static $store = '';

    /**
     * Add more tags for filtering cache data.
     *
     * @var array
     */
    protected static $tags = [];

    /**
     * This function is executed always we want to execute a static method. This class is used to save some information
     * about graphql in cache.
     *
     * @return executed method.
     */
    public static function __callStatic($name, $args)
    {
        $graphqlConfig     = config('graphql');
        $scopesCacheConfig = $graphqlConfig['scopes']['cache'];

        if (!empty($scopesCacheConfig['minToKeep'])) {
            self::$minToKeep = $scopesCacheConfig['minToKeep'];
        }

        self::$cacheName = $scopesCacheConfig['name'];
        self::$store     = $scopesCacheConfig['storageName'];

        $tags = [];

        if (!empty($scopesCacheConfig['tags']) && is_array($scopesCacheConfig['tags'])) {
            $tags = $scopesCacheConfig['tags'];
        }

        $tags[] = self::$cacheName;

        self::$tags = $tags;

        return call_user_func_array([self::class, $name], $args);
    }

    /**
     * Get value from cache
     *
     * @param string $key
     */
    private static function get(string $key)
    {
        return Cache::store(self::$store)->tags(self::$tags)->get($key);
    }

    /**
     * Set value to cache
     *
     * @param string $key
     * @param mixed $value     This value can be anything like string, array, object, int...
     * @param int   $minToKeep Minutes to keep key on cache
     */
    private static function set(string $key, $value, int $minToKeep = null): void
    {
        $minToKeep = $minToKeep ?? self::$minToKeep;

        Cache::store(self::$store)->tags(self::$tags)->put($key, $value, $minToKeep);
    }

    /**
     * Delete a specific key from cache
     *
     * @param string $key
     */
    private static function deleteByKey(string $key): void
    {
        Cache::store(self::$store)->tags(self::$tags)->forget($key);
    }

    /**
     * Delete a specific key from cache using specific tags
     *
     * @param mixed $tags This tags can be string or array
     * @param string $key
     * @return void
     */
    private static function deleteByKeyUsingTags($tags, string $key): void
    {
        $tags = self::mergedTags($tags);

        Cache::store(self::$store)->tags($tags)->forget($key);
    }

    /**
     * Delete a multiple keys using their tags
     *
     * @param mixed $tags This tags can be string or array
     */
    private static function deleteByTags($tags): void
    {
        $tags = self::mergedTags($tags);

        Cache::store(self::$store)->tags($tags)->flush();
    }

    /**
     * Set value to cache with specific tags
     *
     * @param mixed $tags This tags can be string or array
     * @param string $key
     * @param mixed $value This value can be anything like string, array, object, int...
     * @param int   $minToKeep Minutes to keep key on cache
     */
    private static function setByTags($tags, string $key, $value, int $minToKeep = null): void
    {
        $minToKeep = $minToKeep ?? self::$minToKeep;
        $tags      = self::mergedTags($tags);

        Cache::store(self::$store)->tags($tags)->put($key, $value, $minToKeep);
    }

    /**
     * Get value from cache with specific tags
     *
     * @param mixed $tags This tags can be string or array
     * @param string $key
     */
    private static function getByTags($tags, string $key)
    {
        $tags = self::mergedTags($tags);

        return Cache::store(self::$store)->tags($tags)->get($key);
    }

    /**
     * Merge submitted tags with env tags.
     *
     * @param  mixed $tags This tags can be string or array
     * @return array
     */
    private static function mergedTags($tags): array
    {
        if (is_array($tags)) {
            $tags = array_merge(self::$tags, $tags);
        } else {
            $tags[] = $tags;
        }

        return $tags;
    }
}
