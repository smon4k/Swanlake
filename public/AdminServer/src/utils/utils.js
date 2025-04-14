export default {
  dateFormat: function (val) {
    if (!val) {
      return ''
    }
    let year = val.getFullYear()
    let month = (val.getMonth() + 1) >= 10 ? val.getMonth() + 1 : '0' + (val.getMonth() + 1)
    let date = val.getDate() > 9 ? val.getDate() : '0' + val.getDate()
    return `${year}-${month}-${date}`
  },
  
  // basepath: process.env.NODE_ENV === 'production'? (process.env.type === 'testing'?'http://192.168.0.231:78':'http://192.168.0.231:78'):'http://'+window.location.host //接口域名地址 区分测试线上环境
  basepath: 'http://' + window.location.host, //接口域名地址

  // sig_url: 'http://localhost:8083'
  sig_url: 'https://sig.swanlake.club/sigadmin'
}
