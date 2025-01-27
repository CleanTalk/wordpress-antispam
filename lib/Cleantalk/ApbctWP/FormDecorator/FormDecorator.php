<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class FormDecorator
{
    /**
     * @var DecorationSet
     */
    private $decoration_set;
    /**
     * @var DecorationRegistry
     */
    private $decoration_registry;
    /**
     * @var string[]
     */
    private $errors = array();

    /**
     * @var string
     */
    public static $error_type = 'form_decoration';

    /**
     * @return void
     */
    public function __construct()
    {
        $this->decoration_registry = DecorationRegistry::getInstance();
        add_action('comment_form_before', array($this, 'handleForm'));
        //@toDo removes style classes when adding ours for background decorations. Currently the class is added via js setDecorationBackground()
        //add_action('comment_form_defaults', array($this, 'changeFormArguments'));
    }

    /**
     * @param $localized_set_name string
     *
     * @return void
     */
    public function setDecorationSet($localized_set_name)
    {
        global $apbct;
        $registered_name = $this->decoration_registry->getRegisteredNameByLocalizedName($localized_set_name);
        $this->decoration_set = $this->decoration_registry->getDecoration($registered_name);
        if (false === $this->decoration_set) {
            $this->addError(': ' . __('No decoration set registered with name', 'cleantalk-spam-protect') . '[' . esc_html($localized_set_name) . ']');
            return;
        }
        $this->decoration_set->localizeCSS();
        $this->decoration_set->text = $apbct->settings['comments__form_decoration_text'];
        $this->decoration_set->color = !empty($apbct->settings['comments__form_decoration_color'])
        ? $apbct->settings['comments__form_decoration_color']
        : $this->decoration_set->color;
        $apbct->errorDelete(static::$error_type);
    }

    /**
     * @return void
     */
    public function handleForm()
    {
        global $apbct;
        if (!$this->hasErrors()) {
            echo $this->decoration_set->header();
        } else {
            $apbct->errorAdd(static::$error_type, $this->getLastError());
        }
    }

    /**
     * @param $args array
     *
     * @return array
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function changeFormArguments($args)
    {
        if (!$this->hasErrors()) {
            $args['class_container'] .= ' ' . $this->decoration_set->getCssClass();
        }
        return $args;
    }

    /**
     * @return bool
     */
    private function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * @return string
     */
    private function getLastError()
    {
        if ($this->hasErrors()) {
            $last_error = end($this->errors);
            return is_string($last_error) ? $last_error : '';
        }
        return '';
    }

    /**
     * @param $error_text
     *
     * @return void
     */
    private function addError($error_text)
    {
        if (!is_string($error_text)) {
            if (is_scalar($error_text)) {
                $error_text = (string)$error_text;
            }
        }
        $this->errors[] = $error_text;
    }
}
