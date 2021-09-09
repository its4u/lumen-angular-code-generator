<?php

namespace its4u\lumenAngularCodeGenerator;

/**
 * Interface LineableInterface
 * @package its4u\lumenAngularCodeGenerator
 */
interface LineableInterface
{
    /**
     * @return string|string[]
     */
    public function toLines();
}