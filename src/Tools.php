<?php

namespace Marem\PayumPaybox;

/**
 * Class Tools.
 *
 * @author Lexik <dev@lexik.fr>
 * @author Olivier Maisonneuve <o.maisonneuve@lexik.fr>
 */
class Tools
{
    /**
     * Makes an array of parameters become a querystring like string.
     *
     * @return string
     */
    public static function stringify(array $array)
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[] = sprintf('%s=%s', $key, $value);
        }

        return implode('&', $result);
    }
}
