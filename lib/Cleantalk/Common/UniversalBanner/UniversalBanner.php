<?php

namespace Cleantalk\Common\UniversalBanner;

class UniversalBanner
{
    protected $banner_body = '';

    private $replaces = [];

    private static $template_url = __DIR__ . DIRECTORY_SEPARATOR . 'universal_banner_template.html';
    private static $template_without_btn_url = __DIR__ . DIRECTORY_SEPARATOR . 'universal_banner_without_btn_template.html';

    /**
     * @var array
     */
    private static $tags_slugs = array(
        'BANNER_CLASS',
        'BANNER_ID',
        'BANNER_TEXT',
        'BANNER_SECONDARY_TEXT',
        'BUTTON_HREF',
        'BUTTON_TEXT',
        'BANNER_ADDITIONAL_TEXT'
    );

    /**
     * Init the new universal banner instance
     *
     * @param BannerDataDto $banner_data
     *
     * @throws \Exception
     */
    public function __construct(BannerDataDto $banner_data)
    {
        if ($banner_data->is_show_button) {
            $this->banner_body = @file_get_contents(self::$template_url);
        } else {
            $this->banner_body = @file_get_contents(self::$template_without_btn_url);
        }
        if (false === $this->banner_body) {
            throw new \Exception('Banner template missing on ' . self::$template_url);
        }

        $this->preRender($banner_data);
    }

    /**
     * Actions before render.
     * @return void
     * @throws \Exception
     */
    private function preRender(BannerDataDto $banner_data)
    {
        foreach (self::$tags_slugs as $tag_slug) {
            switch ($tag_slug) {
                case 'BANNER_CLASS':
                    $classes = 'apbct-notice notice notice-' . $banner_data->level;
                    $classes .= $banner_data->is_dismissible ? ' is-dismissible' : '';
                    $this->replaces[$tag_slug] = $classes;
                    break;
                case 'BANNER_ID':
                    $this->replaces[$tag_slug] = 'cleantalk_notice_' . $banner_data->type;
                    break;
                case 'BANNER_TEXT':
                    $this->replaces[$tag_slug] = $banner_data->text;
                    break;
                case 'BANNER_SECONDARY_TEXT':
                    $this->replaces[$tag_slug] = $banner_data->secondary_text;
                    break;
                case 'BUTTON_HREF':
                    $this->replaces[$tag_slug] = $banner_data->button_url;
                    break;
                case 'BUTTON_TEXT':
                    $this->replaces[$tag_slug] = $banner_data->button_text;
                    break;
                case 'BANNER_ADDITIONAL_TEXT':
                    $this->replaces[$tag_slug] = $banner_data->additional_text;
                    break;
            }
        }
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
            $this->doReplacements($this->replaces);
            $this->setBannerButtonVisibility($this->showBannerButton());
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
     * Hide the banner button on the state.
     * @param bool $state
     *
     * @return void
     */
    private function setBannerButtonVisibility($state = true)
    {
        if (!$state) {
            $regex = '/<a.*href.*id="apbct_button_.*a>/';
            $this->banner_body = preg_replace($regex, '', $this->banner_body);
        }
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

    /**
     * Set banner button visibility. If this method returns false, the button wil be hidden.
     * @return bool
     */
    protected function showBannerButton()
    {
        return true;
    }
}
