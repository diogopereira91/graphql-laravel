<?php

namespace GraphQLCore\GraphQL\Type\Definition;

use GraphQLCore\GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\StringType;

class PasswordType extends StringType
{
    /** @var string */
    public $name = Type::PASSWORD;

    /** @var string */
    public $description =
        'The `Password` scalar type represents textual password, represented as UTF-8
		character sequences.';
}
