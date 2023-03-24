module.exports = {
  env: {
    browser: true,
    es2021: true
  },
  extends: 'google',
  overrides: [
  ],
  parserOptions: {
    ecmaVersion: 'latest'
  },
  rules: {
    "indent": ["error", 4],
    "max-len": ["error", { "code": 120 }]
  },
}
