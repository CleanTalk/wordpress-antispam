module.exports = {
    env: {
        browser: true,
        es2021: true,
    },
    extends: 'google',
    ignorePatterns: [
        'js/src/apbct-public--5--external-forms.js',
        'js/src/apbct-public-bundle.js',
        'js/src/cleantalk-admin-settings-page.js',
        'js/src/cleantalk-admin.js',
        'js/src/cleantalk-comments-checkspam.js',
        'js/src/cleantalk-comments-editscreen.js',
        'js/src/cleantalk-dashboard-widget.js',
        'js/src/cleantalk-debug-ajax.js',
        'js/src/cleantalk-public-admin.js',
        'js/src/cleantalk-users-checkspam.js',
        'js/src/cleantalk-users-editscreen.js',
    ],
    overrides: [],
    parserOptions: {
        ecmaVersion: 'latest',
    },
    rules: {
        'indent': ['error', 4],
        'max-len': ['error', {'code': 120}],
    },
};
