'use strict'
// Template version: 1.1.3
// see http://vuejs-templates.github.io/webpack for documentation.

const path = require('path')

module.exports = {
  build: {
    env: require('./prod.env'),
    index: path.resolve(__dirname, '../../../application/admin/view/user/login.html'),
    assetsRoot: path.resolve(__dirname, '../dist'),
    assetsSubDirectory: 'static',
    assetsPublicPath: '/AdminServer/dist/',
    productionSourceMap: true,
    // Gzip off by default as many popular static hosts such as
    // Surge or Netlify already gzip all static assets for you.
    // Before setting to `true`, make sure to:
    // npm install --save-dev compression-webpack-plugin
    productionGzip: false,
    productionGzipExtensions: ['js', 'css'],
    // Run the build command with an extra argument to
    // View the bundle analyzer report after build finishes:
    // `npm run build --report`
    // Set to `true` or `false` to always turn it on or off
    bundleAnalyzerReport: process.env.npm_config_report
  },
  dev: {
    env: require('./dev.env'),
    port: process.env.PORT || 8009,
    autoOpenBrowser: false, //浏览器自动运行
    assetsSubDirectory: 'static',
    assetsPublicPath: '/',
    proxyTable: {
      "/Api": {
        target: "http://www.swan.com",
        changeOrigin: true,
        pathRewrite: {
          "^/Api": "/Api"
        },
        timeout: 600000,
      },
      "/Admin": {
        target: "http://www.swan.com",
        changeOrigin: true,
        pathRewrite: {
          "^/Admin": "/Admin"
        },
        timeout: 600000,
      },
      "/Grid": {
        target: "http://www.swan.com",
        changeOrigin: true,
        pathRewrite: {
          "^/Grid": "/Grid"
        },
        timeout: 600000,
      },
      "/Piggybank": {
        target: "http://www.swan.com",
        changeOrigin: true,
        pathRewrite: {
          "^/Piggybank": "/Piggybank"
        },
        timeout: 600000,
      },
    },
    // CSS Sourcemaps off by default because relative paths are "buggy"
    // with this option, according to the CSS-Loader README
    // (https://github.com/webpack/css-loader#sourcemaps)
    // In our experience, they generally work as expected,
    // just be aware of this issue when enabling this option.
    cssSourceMap: false
  }
}
