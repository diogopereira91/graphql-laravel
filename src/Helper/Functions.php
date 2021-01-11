<?php

namespace GraphQLCore\GraphQL\Helper;

use Illuminate\Support\Str;

class Functions
{
    /**
     * Parse simple PHPdoc and return data into array
     * @param  string $phpDoc
     * @return [type]         [description]
     */
    public static function parsePHPdoc(string $phpDoc): array
    {

        $phpDoc = explode("\n", $phpDoc);
        foreach ($phpDoc as &$line) {
            $line = trim($line, "\r\\* /");
        }
        $phpDoc = array_filter($phpDoc);

        $info = [];
        foreach ($phpDoc as $line) {
            if (Str::startsWith($line, '@')) {
                list($type, $rest) = explode(' ', substr($line, 1), 2);
                switch ($type) {
                    case 'param':
                        $param = self::parsePHPdocParam($line);

                        if ($param['type'] == 'object' || $param['type'] == 'stdClass') {
                            $params      = self::parsePHPdocObject($line);
                            $info[$type] = array_merge($info[$type], $params);
                            continue 2;
                        }

                        if (isset($info[$type])) {
                            $info[$type][] = $param;
                        } else {
                            $info[$type] = [$param];
                        }
                        break;
                    case 'return':
                        $return         = self::parsePHPdocReturn($line);
                        $info['return'] = $return;
                        break;
                    default:
                        if (isset($info[$type])) {
                            $info[$type][] = $rest;
                        } else {
                            $info[$type] = [$rest];
                        }
                        break;
                }
            } else {
                if (isset($info['description'])) {
                    $info['description'] .= PHP_EOL . $line;
                } else {
                    $info['description'] = $line;
                }
            }
        }

        return $info;
    }

    /**
     * Make parsing of a line with @param
     * @param  string $lineParam
     * @return array
     */
    private static function parsePHPdocParam(string $lineParam): array
    {
        $text = trim(Str::after($lineParam, '@param'));

        $info = [];
        if (!empty($text)) {
            $text                = str_ireplace(["\t"], ' ', $text);
            $aux                 = explode(' ', $text, 3);
            $info['type']        = $aux[0];
            $info['varName']     = $aux[1] ?? '';
            $info['description'] = $aux[2] ?? '';
        }

        return $info;
    }

    /**
     * Parse annotation object to php
     *
     * @param string $lineParam
     * @return array
     */
    private static function parsePHPdocObject(string $lineParam): array
    {
        $result = [];

        $text = trim(Str::after($lineParam, '@param'));
        $text = str_replace(['object', 'stdClass', '}', '{'], '', $text);
        $text = trim($text);

        $textPieces = explode(',', $text);

        foreach ($textPieces as $value) {
            $elementsObj = trim(Str::after($value, '@param'));
            $value       = trim($value);
            $value       = str_ireplace(["\t"], ' ', $value);
            $aux         = explode(' ', $value, 3);

            $result[] = [
                'type' => $aux[1],
                'varName' => $aux[2] ?? '',
                'description' => ''
            ];
        }

        return $result;
    }

    /**
     * Make parsing in a line with @return
     * @param  string $lineReturn
     * @return array
     */
    private static function parsePHPdocReturn(string $lineReturn): array
    {
        $text = trim(Str::after($lineReturn, '@return'));

        $info = [];
        if (!empty($text)) {
            $text                = str_ireplace(["\t"], ' ', $text);
            $aux                 = explode(' ', $text, 2);
            $info['type']        = $aux[0];
            $info['description'] = $aux[1] ?? '';
        }

        return $info;
    }
}
