<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class FormDecorator {

    /**
     * @var DecorationSet
     */
    private $decoration_set;
    private $form_html;

    /**
     * @param DecorationSet $decoration_set
     * @return void
     */
    public function __construct($decoration_set)
    {
        $this->decoration_set = $decoration_set;
        add_action('comment_form', array($this, 'handleForm'));
    }

    private function decorate($content)
    {
        error_log('CTDEBUG: [' . __FUNCTION__ . '] [$content]: ' . var_export($content,true));
        //do there all the stuff
        $this->form_html = $content;
        $this->setStringBeforeString('<div id="respond" class="comment-respond">',
            $this->decoration_set->header());
        return $this->form_html;
    }

    private function setStringBeforeString($string_to_search, $string_to_add)
    {
        $this->form_html = str_replace($string_to_search, $string_to_add, $this->form_html);
        $this->form_html .= $string_to_search;
    }

    public function handleForm($arg)
    {
        $content = ob_get_contents();
        ob_clean();
        echo $this->decorate($content);
    }
}
