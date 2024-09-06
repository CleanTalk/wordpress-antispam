<?php

namespace Cleantalk\Common\UniversalBanner;

class UniversalBanner
{
    //obligatory
    /**
     * @var string
     */
    private $banner_name;
    /**
     * @var string
     */
    private $api_text;
    /**
     * @var string
     */
    private $api_url_template;
    /**
     * @var string
     */
    private $api_button_text;
    /**
     * @var string
     */
    private $user_token = '';
    /**
     * Can be parsed from info/warning to set the banner side color
     * @var string
     */
    private $attention_level = 'info';
    /**
     * @var array
     */
    private $obligatory_replacements = array();

    //other
    private $banner_body = '';
    private static $template_url = __DIR__ . '\universal_banner_template.html';

    /**
     * @var array
     */
    private static $tags_slugs = array(
        'BANNER_WRAPPER',
        'TEXT_WRAPPER',
        'API_TEXT',
        'BUTTON_WRAPPER',
        'URL',
        'BUTTON',
    );
    /**
     * @var BannerTagReplaces
     */
    protected $tag_banner_wrapper;
    /**
     * @var BannerTagReplaces
     */
    protected $tag_text_wrapper;
    /**
     * @var BannerTagReplaces
     */
    protected $tag_button_wrapper;
    /**
     * @var BannerTagReplaces
     */
    protected $tag_button;
    /**
     * @var BannerTagReplaces
     */
    protected $tag_url;
    /**
     * @var BannerTagReplaces
     */
    protected $tag_api_text;

    /**
     * Init the new universal banner instance
     * @param string $banner_name
     * @param string $user_token
     * @param string $api_text
     * @param string $api_button_text
     * @param string $api_url_template
     * @param string $attention_level
     *
     * @throws \Exception
     */
    public function __construct($banner_name, $user_token, $api_text, $api_button_text, $api_url_template, $attention_level = null)
    {
        if (empty($api_text) ||
            empty($api_button_text) ||
            empty($api_url_template) ||
            empty($user_token) ||
            empty($banner_name)
        ) {
            throw new \Exception('Obligatory property missing.');
        }
        $this->user_token = $user_token;
        $this->api_text = $api_text;
        $this->api_button_text = $api_button_text;
        $this->api_url_template = $api_url_template;
        $this->attention_level = isset($attention_level) ? $attention_level : $this->attention_level;
        $this->banner_name = $banner_name;
        $this->preRender();
    }

    /**
     * Prepare values for the obligatory single-wide replaces
     * @return void
     * @throws \Exception
     */
    protected function prepareObligatoryReplaces()
    {
        $this->obligatory_replacements = array(
            'URL__HREF' => sprintf($this->api_url_template, $this->user_token),
            'API_BUTTON_TEXT' => $this->api_button_text,
            'API_BANNER_TEXT' => $this->api_text,
        );
    }

    /**
     * Actions before render.
     * @return void
     * @throws \Exception
     */
    private function preRender()
    {
        $this->banner_body = @file_get_contents(static::$template_url);
        if (false === $this->banner_body) {
            throw new \Exception('Banner template missing on ' . static::$template_url);
        }
        // prepare obligatory props
        $this->prepareObligatoryReplaces();
        // prepare tags
        foreach (static::$tags_slugs as $tag_slug) {
            $property = 'tag_' . strtolower($tag_slug);
            if (property_exists($this, $property)) {
                $this->$property = new BannerTagReplaces($tag_slug, $this->banner_name);
            }
        }
        // prepare default styles
        $this->prepareDefaultStyles();
        $this->customizeTagReplacements();
    }

    /**
     * @throws \Exception
     */
    private function prepareDefaultStyles()
    {

        switch ($this->attention_level) {
            case 'info':
                $side_color = 'blue';
                break;
            case 'warning':
                $side_color = 'red';
                break;
            default:
                $side_color = 'inherit';
        }

        $banner_wrapper_style = sprintf(
            'border: 1px solid black; border-left: 5px solid %s; width:350px; height:120px; padding: 3px; margin: 2px; background: white;',
            $side_color
        );
        $this->tag_banner_wrapper->setTagAttribute('style', $banner_wrapper_style);
        $this->tag_text_wrapper->setTagAttribute('style', 'padding: 10px');
        $this->tag_api_text->setTagAttribute('style', 'font-size: 12px');
        $this->tag_button_wrapper->setTagAttribute('style', 'margin-bottom:15px; width: 100%; text-align: center;');
        $this->tag_url->setTagAttribute('style', '');
        $this->tag_button->setTagAttribute('style', 'background: black; color: white;');
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function mergeReplaces()
    {
        $replaces = array();
        foreach (static::$tags_slugs as $slug) {
            $property = 'tag_' . strtolower($slug);
            if (property_exists($this, $property)) {
                $replaces = array_merge($replaces, $this->$property->getReplaces());
            } else {
                throw new \Exception('Undefined class property ' . $property);
            }
        }
        return array_merge($replaces, $this->obligatory_replacements);
    }

    /**
     *
     */
    protected function customizeTagReplacements()
    {
        // proceed any stuff with tags
    }

    /**
     * Apply final replacements to the instance banner body
     *
     * @param $replaces
     *
     * @return void
     */
    private function doReplacements($replaces)
    {
        if ( !empty($this->banner_body) && !empty($replaces) ) {
            foreach ($replaces as $place_holder => $replace) {
                $this->banner_body = str_replace('{{' . $place_holder . '}}', $replace, $this->banner_body);
            }
        }
    }

    /**
     * Construct the banner HTML with no output.
     * @return void
     * @throws \Exception
     */
    private function render()
    {
        try {
            $replaces = $this->mergeReplaces();
            $this->doReplacements($replaces);
            $this->validateBodyReplacementResult();
        } catch ( \Exception $error) {
            $this->banner_body = $error->getMessage();
        }
    }

    /**
     * Echoing the banner body
     * @return void
     * @throws \Exception
     */
    public function echoBannerBody()
    {
        $this->render();
        echo $this->sanitizeBodyOnEcho($this->banner_body);
    }

    /**
     * Sanitize banner body before echo.
     *
     * @param $body
     *
     * @return mixed
     */
    protected function sanitizeBodyOnEcho($body)
    {
        return $body;
    }

    /**
     * Return the body string
     * @return string
     * @throws \Exception
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function renderString()
    {
        $this->render();
        return $this->sanitizeBodyString($this->banner_body);
    }

    /**
     * Sanitize banner body before returning string.
     * @param $body
     *
     * @return string
     */
    protected function sanitizeBodyString($body)
    {
        return htmlspecialchars($body);
    }

    /**
     * Validate the body for unfilled replacements
     * @return void
     * @throws \Exception
     */
    private function validateBodyReplacementResult()
    {
        if (
            strpos($this->banner_body, '{{') !== false ||
            strpos($this->banner_body, '}}') !== false
        ) {
            throw new \Exception('Template still has unfilled replacements!');
        }
    }
}
