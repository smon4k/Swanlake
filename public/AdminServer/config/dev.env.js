'use strict'
const merge = require('webpack-merge')
const prodEnv = require('./prod.env')

module.exports = merge(prodEnv, {
  NODE_ENV: '"development"',
  SERVER_IP: '"127.0.0.1"',
  SIG_URL_NAME: '"sigtest"'
})
