<?php

namespace its4u\lumenAngularCodeGenerator;

/**
 * Interface RenderableInterface
 * @package its4u\lumenAngularCodeGenerator
 */
interface RenderableInterface
{
    /**
     * @param int $indent
     * @param string $delimiter
     * @return string
     */
    public function render($indent = 0, $delimiter = PHP_EOL);
}
