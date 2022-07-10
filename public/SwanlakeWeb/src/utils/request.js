import axios from 'axios'

axios.defaults.baseURL = process.env.VUE_APP_BASE_API

// 请求拦截器
axios.interceptors.request.use(config => {
  if (localStorage.token) {
    config.headers = {
      "exchange-token": localStorage.getItem('token')
    }
  }
  return config
}, error => {
  Promise.reject(error)
})
// 响应拦截器
axios.interceptors.response.use(
  function (response) {
    return response.data
  }
)

export const $post = async (path, data) => await axios.post(path, data)
export const $get = async (path, params = {}) => await axios.get( path, { params })

export const $axios = axios;