<?php

declare(strict_types=1);

namespace Cpx\Support;

class Arr
{
    /**
     * @template TKey of array-key
     * @template TValue
     * @template TReturnKey of array-key
     * @template TReturnValue
     *
     * @param  callable(TValue, TKey): array<TReturnKey, TReturnValue>  $callback
     * @param  array<TKey, TValue>  $array
     * @return array<TReturnKey, TReturnValue>
     */
    public static function mapWithKeys(callable $callback, array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            foreach ($callback($value, $key) as $mappedKey => $mappedValue) {
                $result[$mappedKey] = $mappedValue;
            }
        }

        return $result;
    }
}
