<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\ExternalForms\Submission;

/**
 * Handle the legacy JS relay without applying Capture Buffer signature checks.
 *
 * Legacy fields also accept Capture Buffer forms already stored in page caches.
 */
class CleantalkExternalForms extends ExternalFormsRelayBase
{
    protected $action_field = Submission::JS_ACTION;
    protected $method_field = Submission::JS_METHOD;
}
