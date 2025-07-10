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

    public function __construct(array $allowed_public_names, $description = '')
    {
        $this->allowed_public_names = $allowed_public_names;
        $this->description = $description;
    }

    /**
     * If defined, returns the name of the first defined constant from the allowed names. Return false if none of the constants are defined.
     * @return false|string
     */
    public function isDefined()
    {
        foreach ($this->allowed_public_names as $name) {
            if (defined($name)) {
                return $name;
            }
        }
        return false;
    }

    /**
     * Returns the value of the first defined constant from the allowed names. Return null if none of the constants are defined.
     *
     * @return string|null
     */
    public function getValue()
    {
        foreach ($this->allowed_public_names as $name) {
            if (defined($name)) {
                return (string)constant($name);
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return array(
            'is_defined'           => $this->isDefined(),
            'value'                => $this->getValue(),
            'description'          => $this->description,
        );
    }
}
