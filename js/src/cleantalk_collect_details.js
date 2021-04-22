/*
 Assign default values for backend variables.
*/
if (typeof ctCollectDetails === 'undefined') {
    var ctCollectDetails = {};
    ctCollectDetails.set_cookies_flag = true;
}

function ct_getCookie(name) {
    var matches = document.cookie.match(new RegExp(
        "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}

function ct_setCookie(name, value)
{
    if (ctNocache.set_cookies_flag) {
        document.cookie = name+" =; expires=Thu, 01 Jan 1970 00:00:01 GMT; path = /; samesite=lax";
        document.cookie = name+" =; expires=Thu, 01 Jan 1970 00:00:01 GMT; samesite=lax";

        var date = new Date;
        date.setDate(date.getDate() + 1);
        setTimeout(function() { document.cookie = name+"=" + value + "; expires=" + date.toUTCString() + "; path = /; samesite=lax"}, 500);
    }

    return null;
}

if (!Date.now) {
    Date.now = function() { return new Date().getTime(); }
}

if( ct_collect_details === undefined )
{
    var ct_collect_details = true;

    var cleantalk_user_info={};

    var cleantalk_screen_info={};

    for(var prop in screen)
    {
        if (navigator[prop] instanceof Object || screen[prop]==='')
            continue;
        cleantalk_screen_info[prop]=screen[prop];
    }

    cleantalk_user_info.screen=cleantalk_screen_info;

    var cleantalk_plugins=Array();
    var prev;
    var cnt=0;
    for(var i=0;i<navigator.plugins.length;i++)
    {
        var plugin = navigator.plugins[i];
        var plugin = plugin.name+" "+(plugin.version || '')
        if (prev == plugin ) continue;
        cleantalk_plugins[cnt]=plugin;
        cnt++;
        prev = plugin;
    }
    cleantalk_user_info.plugins=cleantalk_plugins;

    cleantalk_user_info.timezone_offset = -new Date().getTimezoneOffset()/60;
    cleantalk_user_info.datetime = Math.round((new Date().getTime())/1000);

    cleantalk_user_info.browser_x=document.documentElement.clientWidth;
    cleantalk_user_info.browser_y=document.documentElement.clientHeight;

    var ua = navigator.userAgent.toLowerCase();
    var flashInstalled = 0;
    if (typeof(navigator.plugins)!="undefined"&&typeof(navigator.plugins["Shockwave Flash"])=="object")
    {
        flashInstalled = 1;
    }
    else if (typeof window.ActiveXObject != "undefined")
    {
        try
        {
            if (new ActiveXObject("ShockwaveFlash.ShockwaveFlash"))
            {
                flashInstalled = 1;
            }
        } catch(e) {};
    };

    cleantalk_user_info.is_flash=flashInstalled;

    isVisitedMain=-1;
    if(location.href=='http://'+location.hostname+'/' || location.href=='https://'+location.hostname+'/')
    {
        isVisitedMain=1;
        setTimeout(function () {
            ct_setCookie('ct_visited_main',
                '1')
        }, 1500);
    }


    ct_visited_main = ct_getCookie('ct_visited_main');
    if(ct_visited_main==undefined && isVisitedMain==-1)
    {
        isVisitedMain=0;
    }
    else
    {
        isVisitedMain=1;
    }

    cleantalk_user_info.is_main=isVisitedMain;

    setTimeout(function () {
        ctSetCookie(
            'ct_user_info',
            escape(JSON.stringify(cleantalk_user_info)));
    }, 1500);

}