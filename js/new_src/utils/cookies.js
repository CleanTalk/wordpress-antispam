/**
 * Get cookie by name
 * @param {string} name
 * @return {string|undefined}
 */
export function ctGetCookie(name) {
    let matches = document.cookie.match(new RegExp(
        '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)',
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}

/**
 * Delete native cookie. Other cookie types are not stored in cookies.
 * @param {string} cookieName
 */
export function ctDeleteCookie(cookieName) {
    if (ctPublicFunctions.data__cookies_type !== 'native') {
        return;
    }
    let ctSecure = location.protocol === 'https:' ? '; secure' : '';
    document.cookie = cookieName + '=""; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; samesite=lax' + ctSecure;
}
