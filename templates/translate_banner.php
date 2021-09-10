<?php

$ct_translate_banner_template = '
<div class="ct_settings_banner" id="ct_translate_plugin">
    <div class="ct_rate_block">'
                                . '<p>'
                                . __('Help others use the plugin in your language.', 'cleantalk-spam-protect')
                                . __(
                                    'We ask you to help with the translation of the plugin in your language. 
                                    Please take a few minutes to make the plugin more comfortable.',
                                    'cleantalk-spam-protect'
                                )
                                . '</p>
        <div>
            <a 
            href="https://translate.wordpress.org/locale/%s/default/wp-plugins/cleantalk-spam-protect" 
            target="_blank">' . __('TRANSLATE', 'cleantalk-spam-protect') . '
            </a>
        </div>
    </div>
</div>';
