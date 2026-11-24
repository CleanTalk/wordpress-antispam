<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\ExternalForms\Submission;
use Cleantalk\ApbctWP\ExternalForms\BufferFormSecurity;
use Cleantalk\ApbctWP\Variables\Post;

/**
 * Require a trusted source for the legacy JS relay without buffer signature checks.
 *
 * Legacy fields also accept Capture Buffer forms already stored in page caches.
 */
class CleantalkExternalForms extends ExternalFormsRelayBase
{
    protected $action_field = Submission::JS_ACTION;
    protected $method_field = Submission::JS_METHOD;

    /**
     * Check the source of legacy submissions, including incomplete routing metadata.
     *
     * Unrelated requests retain the base eligibility checks; foreign sources terminate
     * with HTTP 403 before spam checking or HTML relay output.
     *
     * @return bool
     */
    protected function validateSubmission()
    {
        if ( ! parent::validateSubmission() ) {
            return false;
        }

        if ( (Post::has($this->action_field) || Post::has($this->method_field))
            && ! BufferFormSecurity::hasTrustedSource()
        ) {
            wp_die(
                esc_html(parent::getValidationFailReason('CT05')),
                '',
                array('response' => 403)
            );
        }

        return true;
    }
}
