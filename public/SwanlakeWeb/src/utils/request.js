import axios from 'axios'

axios.defaults.baseURL = process.env.VUE_APP_BASE_API

// 请求拦截器
axios.interceptors.request.use(config => {
  config.headers["Accept"] = "application/json";
  if (localStorage.token) {
    config.headers["Authorization"] = localStorage.getItem('token');
  }
  return config
}, error => {
  return Promise.reject(error)
})

// 响应拦截器
axios.interceptors.response.use(
  function (response) {
    if (response.data.code == 80001) {
      window.location.href = '/#/okx/login'
    }
    return response.data
  },
  function (error) {
    return Promise.reject(error)
  }
)

export const $post = async (path, data) => await axios.post(path, data)
export const $get = async (path, params = {}) => await axios.get(path, { params })

export const $axios = axios