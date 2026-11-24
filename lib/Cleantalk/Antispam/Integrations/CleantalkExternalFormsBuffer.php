<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\ExternalForms\Submission;
use Cleantalk\ApbctWP\ExternalForms\BufferFormSecurity;
use Cleantalk\ApbctWP\Variables\Post;

/**
 * Relay server-rewritten forms only after verifying their signature and source origin.
 */
class CleantalkExternalFormsBuffer extends ExternalFormsRelayBase
{
    protected $action_field = Submission::BUFFER_ACTION;
    protected $method_field = Submission::BUFFER_METHOD;

    /**
     * Leave unrelated requests to the base eligibility checks.
     *
     * After mixed-flow rejection, buffer verification failures terminate with HTTP 403
     * rather than falling through to another WordPress request handler.
     *
     * @return bool
     */
    protected function validateSubmission()
    {
        if ( ! parent::validateSubmission() ) {
            return false;
        }
        if ( ! Submission::hasBufferFields() ) {
            return true;
        }

        $action = $this->getActionFromRequest();
        $method = $this->getMethodFromRequest();
        $signature = $this->getPostString(Submission::BUFFER_SIGNATURE);
        $code = null;
        if (!apbct_is_post()) {
            $code = 'CT00';
        } elseif (! $this->isAllowedExternalFormAction($action)) {
            $code = 'CT01';
        } elseif (! $this->isAllowedExternalFormMethod($method)) {
            $code = 'CT02';
        } elseif (! BufferFormSecurity::hasValidSignature($action, $method, $signature)) {
            $code = 'CT03';
        } elseif (! BufferFormSecurity::hasTrustedSource()) {
            $code = 'CT04';
        }
        if ($code) {
            wp_die(
                esc_html(parent::getValidationFailReason($code)),
                '',
                array('response' => 403)
            );
        }

        return true;
    }

    /**
     * Read the signed destination without lossy text sanitization.
     *
     * @return string
     */
    protected function getActionFromRequest()
    {
        return $this->getPostString($this->action_field);
    }

    /**
     * @inheritDoc
     */
    protected function getMethodFromRequest()
    {
        return $this->getPostString($this->method_field);
    }

    /**
     * Remove the signature along with relay metadata before spam checking and forwarding.
     *
     * @return void
     */
    protected function removeServiceFields()
    {
        parent::removeServiceFields();
        unset($_POST[Submission::BUFFER_SIGNATURE]);
    }

    /**
     * Preserve URL bytes for signature validation; WordPress adds request slashes.
     *
     * Arrays and missing values become empty strings and fail the submission checks.
     *
     * @param string $field
     * @return string
     */
    private function getPostString($field)
    {
        $value = Post::getRaw($field);
        return is_string($value) ? wp_unslash($value) : '';
    }
}
