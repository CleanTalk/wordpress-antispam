<?php

namespace Cleantalk\ApbctWP;

class ApbctConstant
{
    /**
     * Accepted public names for the constants.
     *
     * @var string[]
     */
    public $allowed_public_names = [];

    /**
     * Description of the constant.
     *
     * @var string
     */
    public $description;

    private $type;

    private $defined_name = false;

    public function __construct(array $allowed_public_names, $type, $description = '')
    {
        $this->allowed_public_names = $allowed_public_names;
        $this->description = $description;
        $this->type = $type;
        $this->defined_name = $this->getDefinedName();
    }

    /**
     * If defined, returns the name of the first defined constant from the allowed names. Return false if none of the constants are defined.
     * @return string|false
     */
    private function getDefinedName()
    {
        foreach ($this->allowed_public_names as $name) {
            if (defined($name)) {
                return $name;
            }
        }
        return false;
    }

    /**
     * If defined and type is correct.
     * @return bool
     */
    public function isDefinedAndTypeOK()
    {
        return $this->defined_name && gettype(constant($this->defined_name)) === $this->type;
    }


    /**
     * Return the fact of definition
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isDefined()
    {
        return (bool)$this->defined_name;
    }

    /**
     * Returns the value of the first defined constant from the allowed names. Return null if none of the constants are defined.
     *
     * @return mixed|null
     */
    public function getValue()
    {
        if ($this->defined_name) {
            return constant($this->defined_name);
        }
        return null;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return array(
            'is_defined'           => $this->defined_name,
            'value'                => $this->getValue(),
            'description'          => $this->description,
        );
    }
}
