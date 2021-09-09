<?php

namespace its4u\lumenAngularCodeGenerator\Model;

use its4u\lumenAngularCodeGenerator\RenderableModel;

/**
 * Class BaseProperty
 * @package its4u\lumenAngularCodeGenerator\Model
 */
abstract class BasePropertyModel extends RenderableModel
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
