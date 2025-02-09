<?php

namespace its4u\lumenAngularCodeGenerator\Model;

use its4u\lumenAngularCodeGenerator\Exception\ValidationException;
use its4u\lumenAngularCodeGenerator\Model\Traits\AbstractModifierTrait;
use its4u\lumenAngularCodeGenerator\Model\Traits\FinalModifierTrait;
use its4u\lumenAngularCodeGenerator\RenderableModel;

/**
 * Class Name
 * @package its4u\lumenAngularCodeGenerator\Model
 */
class ClassNameModel extends RenderableModel
{
    use AbstractModifierTrait;
    use FinalModifierTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $extends;

    /**
     * @var array
     */
    protected $implements = [];

    /**
     * PHPClassName constructor.
     * @param string $name
     * @param string|null $extends
     */
    public function __construct($name, $extends = null, $type = 'lumen')
    {
        $this->setName($name)
            ->setExtends($extends);
        $this->type = $type;
    }

    /**
     * {@inheritDoc}
     */
    public function toLines()
    {
        $lines = [];

        if($this->type === 'lumen') {
            $name = '';
        } else {
            $name = 'export ';
        }
        
        if ($this->final) {
            $name .= 'final ';
        }
        if ($this->abstract) {
            $name .= 'abstract ';
        }
        $name .= 'class ' . $this->name;

        if ($this->extends !== null) {
            $name .= sprintf(' extends %s', $this->extends);
        }
        if (count($this->implements) > 0) {
            $name .= sprintf(' implements %s', implode(', ', $this->implements));
        }

        if($this->type === 'lumen') {
            $lines[] = $name;
            $lines[] = '{';
        } else {
            $lines[] = $name . ' {';
            $lines[] = '';
        }

        return $lines;
    }

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

    /**
     * @return string
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * @param string $extends
     *
     * @return $this
     */
    public function setExtends($extends)
    {
        $this->extends = $extends;

        return $this;
    }

    /**
     * @return array
     */
    public function getImplements()
    {
        return $this->implements;
    }

    /**
     * @param string $implements
     *
     * @return $this
     */
    public function addImplements($implements)
    {
        $this->implements[] = $implements;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function validate()
    {
        if ($this->final && $this->abstract) {
            throw new ValidationException('Entity cannot be final and abstract at the same time');
        }

        return parent::validate();
    }
}
