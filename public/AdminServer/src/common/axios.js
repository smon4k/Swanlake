import qs from 'qs';
import { Message } from 'element-ui';
export default function (axios,router) {

  axios.defaults.timeout = 180000; //超时时间
  axios.defaults.baseURL = ''; //默认地址
  axios.defaults.withCredentials = true;//允许携带cookie数据
  axios.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8'; //post的默认请求头
  //响应器，获取数据成功还是失败
  axios.interceptors.request.use(function (config) {
      config.headers.common.Accept = "*/*"
      config.headers.common["X-Requested-With"] = "XMLHttpRequest"
      if (config.method === 'post') {
        if(config.headers['Content-Type'] !== undefined && config.headers['Content-Type'] == 'multipart/form-data') { //如果是上传文件形式的话
        } else {
            config.data = qs.stringify(config.data) //将post数据拼接为参数形式
        }
    
        config.params = {
          token: localStorage.getItem('token')
        }
      } else {
        config.params = {
          token: localStorage.getItem('token'),
          ...config.params
        }
      }
      config.transformResponse = [
        function (data) {
          if (data.indexOf("\\u767b\\u5f55\\u5931\\u8d25\\uff0c\\u8bf7\\u91cd\\u65b0\\u767b\\u5f55") != -1) {
            localStorage.removeItem('token')
            router.replace("/login")
            return false
          }
          if (!data) {
            return false
          }
          // if (JSON.parse(data)) {
          //   return JSON.parse(data)
          // } else {
          //   return data
          // }
          try {
            let res = JSON.parse(data)
            return res
          } catch (err) {
            return data
          }
        }
      ]
      return config;
    })
    return axios
}

/**
 * 封装get/delete方法
 * @param {请求接口路径} url
 * @param {请求接口参数} params
 * @param {请求回调方法} callback
 */
export function get(url, params={}, callback) {
    axios.get(url,{
        params:params
    })
    .then(response => {
        callback && callback(response);
    },err => {
        Message({
            showClose:true,
            message: "请求数据异常",
            type: 'error'
        });
    })
}
/**
 * 封装patch请求
 * @param {请求接口路径} url
 * @param {请求接口参数} params
 * @param {请求回调方法} callback
 */
export function patch(url, params={}, callback) {
    axios.patch(url,{
        params:params
    })
    .then(response => {
        callback && callback(response);
    }).catch(err => {
        Message({
            showClose:true,
            message: "请求数据异常",
            type: 'error'
        });
    })
}
/**
 * 封装post请求
 * @param {请求接口路径} url
 * @param {请求接口参数} params
 * @param {请求回调方法} callback
 */
export function post(url, params={}, callback) {
    axios.post(url,{
        ...params
    })
    .then(response => {
        callback && callback(response);
    },err => {
        Message({
            showClose:true,
            message: "请求数据异常",
            type: 'error'
        });
    })
}
/**
 * 封装put请求
 * @param {请求接口路径} url
 * @param {请求接口参数} params
 * @param {请求回调方法} callback
 */
export function put(url, params={}, callback) {
    axios.put(url,{
        params:params
    })
    .then(response => {
        callback && callback(response);
    }).catch(err => {
        Message({
            showClose:true,
            message: "请求数据异常",
            type: 'error'
        });
    })
}

/**
 * 封装图片上传
 * @param {请求接口路径} url
 * @param {请求接口参数} params
 * @param {请求回调方法} callback
 */
export function upload(url, formData = {}, callback) {
    axios.post(url, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
    .then(response => {
        callback && callback(response);
    }).catch(err => {
        Message({
            showClose: true,
            message: "请求异常",
            type: 'error'
        });
    })
}
