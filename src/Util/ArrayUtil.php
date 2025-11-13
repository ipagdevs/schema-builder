<?php

namespace IpagDevs\Util;

abstract class ArrayUtil
{
    public const ACCESS_SEPARATOR = '.';

    /**
     * Undocumented function
     *
     * @param string $path
     * @param array<mixed> $array
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $path, array $array, mixed $default = null): mixed
    {
        $splitPath = explode(self::ACCESS_SEPARATOR, $path);

        while (is_array($array) && ($key = array_shift($splitPath))) {
            if (array_key_exists($key, $array)) {
                $array = &$array[$key];
            } else {
                $array = null;
            }
        }

        return is_null($array) || !empty($splitPath) ? $default : $array;
    }

    /**
     * Undocumented function
     *
     * @param string $path
     * @param array<mixed> $array
     * @param mixed $value
     * @return void
     */
    public static function set(string $path, array &$array, mixed $value = null): void
    {
        $splitPath = explode(self::ACCESS_SEPARATOR, $path);

        // @phpstan-ignore-next-line
        while (is_array($array) && ($key = array_shift($splitPath))) {
            if (!$splitPath) {
                $array[$key] = $value;
            } else {
                if (array_key_exists($key, $array) && is_array($array[$key])) {
                    $array = &$array[$key];
                } else {
                    $array[$key] = [];
                    $array = &$array[$key];
                }
            }
        }
    }
}
