<?php

namespace Cleantalk\Common\UniversalBanner;

class BannerTagReplaces
{
    /**
     * @var string
     */
    public $slug;
    /**
     * @var string
     */
    public $id;
    /**
     * @var string
     */
    public $id_value;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $name_value;
    /**
     * @var string
     */
    public $class;
    /**
     * @var string
     */
    public $class_value;
    /**
     * @var string
     */
    public $style;
    /**
     * @var string
     */
    public $style_value;
    /**
     * @var string
     */
    public $banner_name;

    /**
     * @param $slug
     * @param $banner_name
     *
     * @throws \Exception
     */
    public function __construct($slug, $banner_name)
    {
        if (empty($slug) || empty($banner_name)) {
            throw new \Exception('Banner slug or name is empty.');
        }
        $this->slug = $slug;
        $this->id = $slug . '__ID';
        $this->name = $slug . '__NAME';
        $this->class = $slug . '__CLASS';
        $this->style = $slug . '__STYLE';
        $this->banner_name = $banner_name;
        $this->setDefaults();
    }

    /**
     * Fill defaults tag attributes values.
     * @return void
     * @throws \Exception
     */
    public function setDefaults()
    {
        $this->setTagAttribute('id', '', true);
        $this->setTagAttribute('class', '', true);
        $this->setTagAttribute('name', '', true);
        $this->setTagAttribute('style', '');
    }

    /**
     * Set the attribute value for the tag
     * @param $attribute
     * @param $value
     * @param $default
     *
     * @return void
     * @throws \Exception
     */
    public function setTagAttribute($attribute, $value, $default = false)
    {
        $attribute_value_address = $attribute . '_value';
        if (property_exists($this, $attribute_value_address)) {
            $this->$attribute_value_address = $default ? 'apbct_' . $this->banner_name . '_' . strtolower($this->slug) . '_' . $attribute : $value;
        } else {
            throw new \Exception('Attribute ' . $attribute . ' does not exist');
        }
    }

    /**
     * Get prepared replaces ready for the template
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getReplaces()
    {
        return array(
            $this->id => $this->id_value,
            $this->name => $this->name_value,
            $this->class => $this->class_value,
            $this->style => $this->style_value
        );
    }
}
