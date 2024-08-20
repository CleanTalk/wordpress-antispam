<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class FormDecorator
{
    /**
     * @var DecorationSet
     */
    private $decoration_set;

    /**
     * @param DecorationSet $decoration_set
     * @return void
     */
    public function __construct($decoration_set)
    {
        global $apbct;
        $this->decoration_set = $decoration_set;
        $this->decoration_set->text = $apbct->settings['comments__form_decoration_text'];
        $this->decoration_set->color = $apbct->settings['comments__form_decoration_color'];
        add_action('comment_form_before', array($this, 'handleForm'));
        add_action('comment_form_defaults', array($this, 'changeFormArguments'));
    }

    public function handleForm()
    {
        echo $this->decoration_set->header();
    }

    public function changeFormArguments($args)
    {
        $args['class_container'] .= ' apbct_holiday_decoration';
        return $args;
    }
}
