/**
 * @param {mixed} msg
 * @param {string} url
 */
function ctProcessError(msg, url) {
    let log = {};
    if (msg && msg.message) {
        log.err = {
            'msg': msg.message,
            'file': !!msg.fileName ? msg.fileName : false,
            'ln': !!msg.lineNumber ? msg.lineNumber : !!lineNo ? lineNo : false,
            'col': !!msg.columnNumber ? msg.columnNumber : !!columnNo ? columnNo : false,
            'stacktrace': !!msg.stack ? msg.stack : false,
            'cause': !!url ? JSON.stringify(url) : false,
            'errorObj': !!error ? error : false,
        };
    } else {
        log.err = {
            'msg': msg,
        };

        if (!!url) {
            log.err.file = url;
        }
    }

    log.url = window.location.href;
    log.userAgent = window.navigator.userAgent;

    let ctJsErrors = 'ct_js_errors';
    let errArray = localStorage.getItem(ctJsErrors);
    if (errArray === null) errArray = '[]';
    errArray = JSON.parse(errArray);
    for (let i = 0; i < errArray.length; i++) {
        if (errArray[i].err.msg === log.err.msg) {
            return;
        }
    }

    errArray.push(log);
    localStorage.setItem(ctJsErrors, JSON.stringify(errArray));
}

if (Math.floor(Math.random() * 100) === 1) {
    window.onerror = function(exception, url) {
        let filterWords = ['apbct', 'ctPublic'];
        let length = filterWords.length;
        while (length--) {
            if (exception.indexOf(filterWords[length]) !== -1) {
                ctProcessError(exception, url);
            }
        }

        return false;
    };
}
