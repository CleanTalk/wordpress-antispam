<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\ExternalForms\Submission;

class CleantalkExternalFormsBuffer extends ExternalFormsRelayBase
{
    protected $action_field = Submission::BUFFER_ACTION;
    protected $method_field = Submission::BUFFER_METHOD;
}
