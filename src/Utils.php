<?php

namespace Cpx;

class Utils
{
    /**
     * @template TKey of array-key
     * @template TValue
     * @template TReturnKey of array-key
     * @template TReturnValue
     *
     * @param  callable(TKey, TValue): array<TReturnKey, TReturnValue>  $f
     * @param  array<TKey, TValue>  $a
     * @return array<TReturnKey, TReturnValue>
     */
    public static function arrayMapAssoc(callable $f, array $a): array
    {
        return array_merge(...array_map($f, array_keys($a), $a));
    }
}
