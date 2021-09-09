<?php

namespace its4u\lumenAngularCodeGenerator\Helper;

/**
 * Class ClassHelper
 * @package its4u\lumenAngularCodeGenerator\Helper
 */
class ClassHelper
{
    /**
     * @param string $fullClassName
     * @return string
     */
    public static function getShortClassName($fullClassName)
    {
        $pieces = explode('\\', $fullClassName);

        return end($pieces);
    }
}
