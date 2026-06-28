import {sendAjaxRequest} from '../modules/Http/Ajax';

const {__} = wp.i18n;

export async function runSync(onProgress, onComplete = null) {
    const callStack = [
        {
            data: {action: 'apbct_react_access_key_check'},
            processing_msg: __('Checking access key...', 'cleantalk-spam-protect'),
        },
        {
            data: {action: 'apbct_react_sfw_update'},
            processing_msg: __('Updating SpamFireWall...', 'cleantalk-spam-protect'),
        },
        {
            data: {action: 'apbct_react_send_feedback'},
            processing_msg: __('Sending feedback...', 'cleantalk-spam-protect'),
        },
        {
            data: {action: 'apbct_react_brief_data'},
            processing_msg: __('Updating dashboard data...', 'cleantalk-spam-protect'),
        },
        {
            data: {action: 'apbct_react_run_adjusting_env'},
            processing_msg: __('Adjusting environment...', 'cleantalk-spam-protect'),
        },
        {
            data: {action: 'apbct_react_sync_end'},
            processing_msg: __('Finishing setup...', 'cleantalk-spam-protect'),
        },
    ];

    const totalSteps = callStack.length;
    let call;
    let currentStep = 0;

    while (callStack.length) {
        call = callStack.shift();
        currentStep++;

        const percent = Math.round((currentStep / totalSteps) * 100);

        if (onProgress) {
            onProgress(call.processing_msg, percent);
        }

        await sendAjaxRequest(call.data, true, false);
    }

    if (onComplete && typeof onComplete === 'function') {
        onComplete();
    }

    return true;
}
