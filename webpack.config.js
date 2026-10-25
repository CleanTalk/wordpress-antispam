const path = require('path');

// Существующий конфиг, без изменений
const reactConfig = {
    entry: './js/src/react/index.js',
    module: {
        rules: [
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env', '@babel/preset-react'],
                    },
                },
            },
            {
                test: /\.css$/,
                use: [
                    'style-loader',
                    'css-loader',
                ],
            },
            {
                test: /\.(gif|png|jpe?g|webp)$/i,
                use: [
                    'file-loader',
                    {
                        loader: 'image-webpack-loader',
                        options: {
                            bypassOnDebug: true,
                            disable: true,
                        },
                    },
                ],
            },
        ],
    },
    output: {
        path: path.resolve(__dirname, 'js/public'),
        filename: 'apbct-react-bundle.js',
    },
    mode: 'production',
    devtool: false,
};

const publicNewConfig = {
    entry: './js/new_src/index.js',
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env'],
                    },
                },
            },
        ],
    },
    output: {
        path: path.resolve(__dirname, 'js'),
        filename: 'apbct_public_new.min.js',
        chunkFilename: '[name].min.js',
    },
    mode: 'production',
    devtool: false,
};

module.exports = [reactConfig, publicNewConfig];
