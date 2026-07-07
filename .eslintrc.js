module.exports = {
    env: {
        browser: true,
        es2021: true,
    },
    extends: 'google',
    ignorePatterns: [
        'js/src/cleantalk-comments-editscreen.js',
        'js/src/cleantalk-dashboard-widget.js',
        'js/src/cleantalk-public-admin.js',
        'js/src/cleantalk-users-checkspam.js',
        'js/src/cleantalk-users-editscreen.js',
    ],
    overrides: [
        {
            files: ['js/src/react/**/*.js'],
            extends: [
                'plugin:react/recommended',
                'plugin:react/jsx-runtime',
            ],
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                ecmaFeatures: {
                    jsx: true,
                },
            },
            plugins: ['react'],
            settings: {
                react: {
                    version: 'detect',
                },
            },
            rules: {
                'react/prop-types': 'off',
                'react/react-in-jsx-scope': 'off',
                'react/jsx-uses-react': 'off',
                'require-jsdoc': 'off',
                'valid-jsdoc': 'off',
                'linebreak-style': 'off',
                'no-invalid-this': 'off',
                'max-len': 'off',
                'no-unused-vars': ['error', {varsIgnorePattern: 'React', args: 'none'}],
            },
            globals: {
                wp: 'readonly',
                cleantalkModal: 'readonly',
            },
        },
    ],
    parserOptions: {
        ecmaVersion: 'latest',
    },
    rules: {
        'indent': ['error', 4],
        'max-len': ['error', {'code': 120}],
        'prefer-const': 'off',
    },
    globals: {
        'ctSetCookie': 'readonly',
    },
};
