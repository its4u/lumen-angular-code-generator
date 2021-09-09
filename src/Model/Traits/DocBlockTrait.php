<?php

namespace its4u\lumenAngularCodeGenerator\Model\Traits;

use its4u\lumenAngularCodeGenerator\Model\DocBlockModel;

/**
 * Trait DocBlockTrait
 * @package its4u\lumenAngularCodeGenerator\Model\Traits
 */
trait DocBlockTrait
{
    /**
     * @var DocBlockModel
     */
    protected $docBlock;

    /**
     * @return DocBlockModel
     */
    public function getDocBlock()
    {
        return $this->docBlock;
    }

    /**
     * @param DocBlockModel $docBlock
     *
     * @return $this
     */
    public function setDocBlock($docBlock)
    {
        $this->docBlock = $docBlock;

        return $this;
    }
}
