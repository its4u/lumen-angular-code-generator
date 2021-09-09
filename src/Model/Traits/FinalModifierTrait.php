<?php

namespace its4u\lumenAngularCodeGenerator\Model\Traits;

/**
 * Trait FinalModifierTrait
 * @package its4u\lumenAngularCodeGenerator\Model\Traits
 */
trait FinalModifierTrait
{
    /**
     * @var boolean
     */
    protected $final;

    /**
     * @return boolean
     */
    public function isFinal()
    {
        return $this->final;
    }

    /**
     * @param boolean $final
     *
     * @return $this
     */
    public function setFinal($final = true)
    {
        $this->final = boolval($final);

        return $this;
    }
}