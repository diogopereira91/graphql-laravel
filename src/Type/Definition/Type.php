<?php

namespace GraphQLCore\GraphQL\Type\Definition;

use GraphQL\Type\Definition\Type as TypeOriginal;
use GraphQLCore\GraphQL\Support\UploadType;

class Type extends TypeOriginal
{

    /**
     * Get all standard types
     *
     * @var [type]
     */
    private static $standardTypes;

    /**
     * Const. These consts is necessary to get the name from specific special type.
     */
    public const PASSWORD = 'Password';
    public const UPLOAD_TYPE = 'Upload';

    /**
     * Get special types. These types is like Type::string(), Type::boolean(), etc.
     * Ex: Type::password()
     *
     * @param string $name
     * @return void
     */
    private static function getSpecialTypes(string $name)
    {
        if (empty(self::$standardTypes)) {
            $defaultTypes = self::getStandardTypes();

            self::$standardTypes = array_merge($defaultTypes, [
                self::PASSWORD => new PasswordType,
                self::UPLOAD_TYPE => new UploadType
            ]);
        }

        $result = null;

        if (!empty($name)) {
            $result = self::$standardTypes[$name];
        }

        return $result;
    }

    /**
     * Get Special type called password
     *
     * @return array
     */
    public static function password()
    {
        return self::getSpecialTypes(self::PASSWORD);
    }

    /**
     * Get upload type
     *
     * @return array
     */
    public static function upload()
    {
    	return self::getSpecialTypes(self::UPLOAD_TYPE);
    }
}
