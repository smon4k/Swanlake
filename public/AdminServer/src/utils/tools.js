export function deepCopy(state){
  let result
  if (typeof state === 'object'){
    if(Array.isArray(state)){
      result = []
      for(let i in state){
        result.push(deepCopy(state[i]))
      }
    }else if(state === null){
      result = null
    }else if(state.constructor===RegExp){
      result = state
    }else{
      result = {}
      for(let key in state){
        result[key] = deepCopy(state[key])
      }
    }
  }else {
    result = state
  }

  return result
}

// 小数点位数处理
export function toFixed(val,len) {
  val = toolNumber(val || 0)
  var f = parseFloat(val);
  if (isNaN(f)) {
      return '--';
  }
  var s=val.toString();
  if(s.indexOf(".")>0){
      var f = s.split(".")[1].substring(0,len)
      s=s.split(".")[0]+"."+f
  }
  var rs = s.indexOf('.');
  if (rs < 0) {
      rs = s.length;
      s += '.';
  }
  while (s.length <= rs + len) {
      s += '0';
  }
  return s || '--';
}
export function toWei(amount, decimal) {
  let str = 'ether'
  switch (Number(decimal)) {
    case 18:
      str = 'ether'
      break;
    case 6:
      str = 'mwei';
      break;
  }
  return web3.utils.toWei(amount.toString(), str)
}
export function fromWei(amount, decimal) {
  let str = 'ether'
  switch (Number(decimal)) {
    case 18:
      str = 'ether'
      break;
    case 6:
      str = 'mwei';
      break;
  }
  if(amount) {
    return web3.utils.fromWei(amount.toString(), str);
  } else {
    return '0';
  }
}

export const $inputLimit = function (e, point , isModel) {
  if (point > 0) { //小数 ^(\-)*(\d+)\.(\d{1,3}).*$
    e.target.value = e.target.value.replace(/[^\d.]/g, "")  //清除“数字”和“.”以外的字符
      .replace(/^\./g, "") //验证第一个字符是数字
      .replace(/\.{2,}/g, ".") //只保留第一个. 清除多余的
      .replace(".", "$#$").replace(/\./g, "").replace("$#$", ".")
      .replace(new RegExp(`^(\\-)*(\\d+)\\.(\\d{1,${point}}).*$`), '$1$2.$3');//只能输入两个小数
  } else { //整数
    if (e.target.value.length == 1) {
      e.target.value = e.target.value.replace(/[^0-9]/g, "");
    } else {
      e.target.value = e.target.value.replace(/[^\d]/g, "");
    }
  }
  if (e.target.value.indexOf(".") < 0 && e.target.value != "") { //此处控制的是如果没有小数点，首位不能为类似于 01、02的金额
    // e.target.value = parseFloat(e.target.value);
    let text = e.target.value
    let leng = text.length
    for(let i=0;i<leng;i++){
      if(text.charAt(i) !== '0' || i === leng-1){
        e.target.value = text.substring(i)
        break
      }
    }
  }
  if(isModel) return String(e.target.value)
};

export function toolNumber(num_str) {
  num_str = num_str.toString();
  if (num_str.indexOf("+") != -1) {
    num_str = num_str.replace("+", "");
  }
  if (num_str.indexOf("E") != -1 || num_str.indexOf("e") != -1) {
    var resValue = "",
      power = "",
      result = null,
      dotIndex = 0,
      resArr = [],
      sym = "";
    var numStr = num_str.toString();
    if (numStr[0] == "-") {
      //如果为负数，转成正数处理，先去掉‘-’号，并保存‘-’.
      numStr = numStr.substr(1);
      sym = "-";
    }
    if (numStr.indexOf("E") != -1 || numStr.indexOf("e") != -1) {
      var regExp = new RegExp(
        "^(((\\d+.?\\d+)|(\\d+))[Ee]{1}((-(\\d+))|(\\d+)))$",
        "ig"
      );
      result = regExp.exec(numStr);
      if (result != null) {
        resValue = result[2];
        power = result[5];
        result = null;
      }
      if (!resValue && !power) {
        return false;
      }
      dotIndex = resValue.indexOf(".") == -1 ? 0 : resValue.indexOf(".");
      resValue = resValue.replace(".", "");
      resArr = resValue.split("");
      if (Number(power) >= 0) {
        var subres = resValue.substr(dotIndex);
        power = Number(power);
        //幂数大于小数点后面的数字位数时，后面加0
        for (var i = 0; i <= power - subres.length; i++) {
          resArr.push("0");
        }
        if (power - subres.length < 0) {
          resArr.splice(dotIndex + power, 0, ".");
        }
      } else {
        power = power.replace("-", "");
        power = Number(power);
        //幂数大于等于 小数点的index位置, 前面加0
        for (var i = 0; i < power - dotIndex; i++) {
          resArr.unshift("0");
        }
        var n = power - dotIndex >= 0 ? 1 : -(power - dotIndex);
        resArr.splice(n, 0, ".");
      }
    }
    resValue = resArr.join("");
    return sym + resValue;
  } else {
    return num_str;
  }
}

/**
 * 保留指定位数小数 不四舍五入
 * @param value  任意数值
 * @param count  大于0的整数
 * @param isZero  是否去除多余的0 默认保留
 * @returns { string }
 */
export function keepDecimalNotRounding(value, count, isZero) {
  let countNum = count;
  if (!count || count <= 0) {
    countNum = 18;
  }
  const numberString = value.toString();
  let numbers = 0;
  if ((numberString.indexOf('E') != -1) || (numberString.indexOf('e') != -1)) {   //验证是否为科学计数法
    value = toNonExponential(value);
  }
  // console.log(value);
  const [integer, decimal = '0'.repeat(countNum)] = value.toString().split('.');
  // 加0是为了第二种情况
  let number = integer + '.' + (decimal + '0'.repeat(countNum - 1)).substr(0, countNum);
  if (isZero) {
    number = parseFloat(number.replace(/(\.\d+?)0*$/, '$1'));
  }

  return number;
};

export function toNonExponential(num) {
  // console.log(num);
  var m = num.toExponential().match(/\d(?:.(\d*))?e([+-]\d+)/);
  return num.toFixed(Math.max(0, (m[1] || "").length - m[2]));
};

export function toNumberStr(num, digits) {
  // 正则匹配小数科学记数法
  if (/^(\d+(?:\.\d+)?)(e)([\-]?\d+)$/.test(num)) {
    // 正则匹配小数点最末尾的0
    var temp = /^(\d{1,}(?:,\d{3})*\.(?:0*[1-9]+)?)(0*)?$/.exec(num.toFixed(digits));
    if (temp) {
      return temp[1];
    } else {
      return num.toFixed(digits)
    }
  } else {
    return "" + num
  }
}

//获取url参数值
export const getUrlParams = (name) => {
  var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
  var r = window.location.search.substr(1).match(reg);
  // console.log(window.location, r);
  if (r != null) return unescape(r[2]);
  return null;
  // return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(createHistory.location.href) || [, ""])[1].replace(/\+/g, '%20')) || null
}

 //获取url参数
 export const getQueryString = (name) => {  
  // 通过 ? 分割获取后面的参数字符串
  let url = window.location.href;
  let urlStr = url.split('?')[1]
  // 创建空对象存储参数
  let obj = {};
  if(urlStr && urlStr !== undefined) {
    // 再通过 & 将每一个参数单独分割出来
    let paramsArr = urlStr.split('&')
    for(let i = 0,len = paramsArr.length;i < len;i++){
        // 再通过 = 将每一个参数分割为 key:value 的形式
        let arr = paramsArr[i].split('=')
        obj[arr[0]] = arr[1];
    }
    if(obj[name] && obj[name] !== '') {
      try {
        let checksumAddress = web3.utils.toChecksumAddress(obj[name]);
        if(checksumAddress) {
          let isAddress = web3.utils.isAddress(checksumAddress);
          if(isAddress) {
            return checksumAddress;
          }
        }
      } catch (error) {
        return '';
      }
    } else {
      return '';
    }
  } 
  return '';
}

//替换指定传入参数的值,paramName为参数,replaceWith为新值
export const replaceParamVal = (paramName, replaceWith) => {
  // var oUrl = createHistory.location.search.toString();
  const oUrl = window.location.hash.toString();
  const re = eval('/('+ paramName+'=)([^&]*)/gi');
  const nUrl = oUrl.replace(re,paramName+'='+replaceWith);
  // console.log(oUrl, nUrl, window.location);
  window.history.replaceState('', '', window.location.origin + window.location.pathname + nUrl)
// 　window.location.href = window.location.origin + window.location.pathname + nUrl;
}
//修改url参数
export const changeURLPar = (uri, par, par_value) => {
  const pattern = par + '=([^&]*)';
  const replaceText = par + '=' + par_value;
  let newUrl = "";
  if (uri.match(pattern)) {//如果连接中带这个参数
      var tmp = '/\\' + par + '=[^&]*/';
      tmp = uri.replace(eval(tmp), replaceText);
      newUrl = tmp;
  } else {
      if (uri.match('[\?]')) {//如果链接中不带这个参数但是有其他参数
        newUrl = uri + '&' + replaceText;
      } else {//如果链接中没有带任何参数
        newUrl = uri + '?' + replaceText;
      }
  }
  window.history.replaceState('', '', newUrl)
  // window.location.href = newUrl;
}

/**
 * @description 科学计数法转为string
 * @param {string, number} param
 */
 export const scientificNotationToString = (param) => {
  let strParam = String(param)
  let flag = /e/.test(strParam)
  if (!flag) return param

  // 指数符号 true: 正，false: 负
  let sysbol = true
  if (/e-/.test(strParam)) {
    sysbol = false
  }
  // 指数
  let index = Number(strParam.match(/\d+$/)[0])
  // 基数
  let basis = strParam.match(/^[\d\.]+/)[0].replace(/\./, '')

  if (sysbol) {
    return basis.padEnd(index + 1, 0)
  } else {
    return basis.padStart(index + basis.length, 0).replace(/^0/, '0.')
  }
}

//根据apy求apr
export const calcDailyDefault0 = apy => {
  if (!apy) return `0%`;

  // const g = Math.pow(10, Math.log10(apy + 1) / 365) - 1;
  const g = (Math.pow(10, Math.log10(apy + 1) / 365) - 1) * 365;
  if (isNaN(g)) {
    return '- %';
  }

  return `${(g * 100).toFixed(2)}%`;
};

//根据Apr求Apy
export const accordingToAprSeekApy = (apr, isPer) => {
  if (!apr) return `0%`;
  const apy = Math.pow(10, Math.log10( apr / 365 + 1) * 365) -1
  if (isNaN(apy)) {
    return '- %';
  }

  return isPer ? `${(apy * 100).toFixed(2)}%` : apy;
};

 /*
  * 参数说明：
  * number：要格式化的数字
  * decimals：保留几位小数
  * dec_point：小数点符号
  * thousands_sep：千分位符号
  * */
 export const numberFormat = (number, decimals, dec_point, thousands_sep) => {
  number = (number + '').replace(/[^0-9+-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
      prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
      sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
      dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
      s = '',
      toFixedFix = function (n, prec) {
          var k = Math.pow(10, prec);
          return '' + Math.ceil(n * k) / k;
      };

  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  var re = /(-?\d+)(\d{3})/;
  while (re.test(s[0])) {
      s[0] = s[0].replace(re, "$1" + sep + "$2");
  }

  if ((s[1] || '').length < prec) {
      s[1] = s[1] || '';
      s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
};

//计算日化
export const calcDaily = (apy, guruAPR, isPer) => {
  if (!apy || !guruAPR) return `???`;

  const g = Math.pow(10, Math.log10(apy + 1) / 365) - 1;
  if (isNaN(g)) {
    return '- %';
  }
  const apyNum = (g * 100).toFixed(2);
  guruAPR *= 100;
  const countNum = Number(apyNum) + Number(guruAPR);

  return isPer ? `${countNum.toFixed(2)}%` : countNum;
};

export const toCrc32 = (str, crc) => {
  let table = "00000000 77073096 EE0E612C 990951BA 076DC419 706AF48F E963A535 9E6495A3 0EDB8832 79DCB8A4 E0D5E91E 97D2D988 09B64C2B 7EB17CBD E7B82D07 90BF1D91 1DB71064 6AB020F2 F3B97148 84BE41DE 1ADAD47D 6DDDE4EB F4D4B551 83D385C7 136C9856 646BA8C0 FD62F97A 8A65C9EC 14015C4F 63066CD9 FA0F3D63 8D080DF5 3B6E20C8 4C69105E D56041E4 A2677172 3C03E4D1 4B04D447 D20D85FD A50AB56B 35B5A8FA 42B2986C DBBBC9D6 ACBCF940 32D86CE3 45DF5C75 DCD60DCF ABD13D59 26D930AC 51DE003A C8D75180 BFD06116 21B4F4B5 56B3C423 CFBA9599 B8BDA50F 2802B89E 5F058808 C60CD9B2 B10BE924 2F6F7C87 58684C11 C1611DAB B6662D3D 76DC4190 01DB7106 98D220BC EFD5102A 71B18589 06B6B51F 9FBFE4A5 E8B8D433 7807C9A2 0F00F934 9609A88E E10E9818 7F6A0DBB 086D3D2D 91646C97 E6635C01 6B6B51F4 1C6C6162 856530D8 F262004E 6C0695ED 1B01A57B 8208F4C1 F50FC457 65B0D9C6 12B7E950 8BBEB8EA FCB9887C 62DD1DDF 15DA2D49 8CD37CF3 FBD44C65 4DB26158 3AB551CE A3BC0074 D4BB30E2 4ADFA541 3DD895D7 A4D1C46D D3D6F4FB 4369E96A 346ED9FC AD678846 DA60B8D0 44042D73 33031DE5 AA0A4C5F DD0D7CC9 5005713C 270241AA BE0B1010 C90C2086 5768B525 206F85B3 B966D409 CE61E49F 5EDEF90E 29D9C998 B0D09822 C7D7A8B4 59B33D17 2EB40D81 B7BD5C3B C0BA6CAD EDB88320 9ABFB3B6 03B6E20C 74B1D29A EAD54739 9DD277AF 04DB2615 73DC1683 E3630B12 94643B84 0D6D6A3E 7A6A5AA8 E40ECF0B 9309FF9D 0A00AE27 7D079EB1 F00F9344 8708A3D2 1E01F268 6906C2FE F762575D 806567CB 196C3671 6E6B06E7 FED41B76 89D32BE0 10DA7A5A 67DD4ACC F9B9DF6F 8EBEEFF9 17B7BE43 60B08ED5 D6D6A3E8 A1D1937E 38D8C2C4 4FDFF252 D1BB67F1 A6BC5767 3FB506DD 48B2364B D80D2BDA AF0A1B4C 36034AF6 41047A60 DF60EFC3 A867DF55 316E8EEF 4669BE79 CB61B38C BC66831A 256FD2A0 5268E236 CC0C7795 BB0B4703 220216B9 5505262F C5BA3BBE B2BD0B28 2BB45A92 5CB36A04 C2D7FFA7 B5D0CF31 2CD99E8B 5BDEAE1D 9B64C2B0 EC63F226 756AA39C 026D930A 9C0906A9 EB0E363F 72076785 05005713 95BF4A82 E2B87A14 7BB12BAE 0CB61B38 92D28E9B E5D5BE0D 7CDCEFB7 0BDBDF21 86D3D2D4 F1D4E242 68DDB3F8 1FDA836E 81BE16CD F6B9265B 6FB077E1 18B74777 88085AE6 FF0F6A70 66063BCA 11010B5C 8F659EFF F862AE69 616BFFD3 166CCF45 A00AE278 D70DD2EE 4E048354 3903B3C2 A7672661 D06016F7 4969474D 3E6E77DB AED16A4A D9D65ADC 40DF0B66 37D83BF0 A9BCAE53 DEBB9EC5 47B2CF7F 30B5FFE9 BDBDF21C CABAC28A 53B39330 24B4A3A6 BAD03605 CDD70693 54DE5729 23D967BF B3667A2E C4614AB8 5D681B02 2A6F2B94 B40BBE37 C30C8EA1 5A05DF1B 2D02EF8D";
  if( crc == window.undefined ) crc = 0;
    var n = 0; //a number between 0 and 255
    var x = 0; //an hex number
    crc = crc ^ (1);
    for( var i = 0, iTop = str.length; i < iTop; i++ ) {
      n = ( crc ^ str.charCodeAt( i ) ) & 0xFF;
      x = "0x" + table.substr( n * 9, 8 );
      crc = ( crc >>> 8 ) ^ x;
    }
    return crc ^ (1);
}

/**
 * 针对图片进行压缩,如果图片大小超过压缩阈值,则执行压缩,否则不压缩
 * compressThreshold: 5,  //压缩的阈值,图片大小超过5M,则需要进行压缩
 * isPictureCompress: false, //是否开启图片压缩
 * pictureQuality: 0.92, //指定压缩的图片质量,取值范围为0~1,quality值越小,图像越模糊,默认图片质量为0.92
 * @param {*} file 
 * @returns 
 */
 export const transformFile = async (file) => {
  // console.log(file);
  //判断是否是图片类型
  // if (this.checkIsImage(file.name)) {
      const compressThreshold = 1;
      const isPictureCompress = true;
      const pictureQuality = 0.92;
      let fileSize = file.size / 1024 / 1024;
      console.log('压缩前，文件大小为: ', fileSize + "M");
      //当开启图片压缩且图片大小大于等于压缩阈值,进行压缩
      // console.log(compressThreshold);
      if ((fileSize >= compressThreshold) && isPictureCompress) {
          // console.log("开始压缩");
          //判断浏览器内核是否支持base64图片压缩
          if (typeof (FileReader) === 'undefined') {
              return file;
          } else {
              try {
                  // this.setState({
                  //     spinLoading: true
                  // });
                  return new Promise(async resolve => {
                      //声明FileReader文件读取对象
                      const reader = new FileReader();
                      reader.readAsDataURL(file);
                      reader.onload = () => {
                          // 生成canvas画布
                          const canvas = document.createElement('canvas');
                          // 生成img
                          const img = document.createElement('img');
                          let initSize = img.src.length;
                          img.src = reader.result;
                          img.onload = () => {
                              const ctx = canvas.getContext('2d');
                              //原始图片宽度、高度
                              let originImageWidth = img.width, originImageHeight = img.height;
                              //默认最大尺度的尺寸限制在（1920 * 1080）
                              let maxWidth = 1920, maxHeight = 1080, ratio = maxWidth / maxHeight;
                              //目标尺寸
                              let targetWidth = originImageWidth, targetHeight = originImageHeight;
                              //当图片的宽度或者高度大于指定的最大宽度或者最大高度时,进行缩放图片
                              if (originImageWidth > maxWidth || originImageHeight > maxHeight) {
                                  //超过最大宽高比例
                                  if ((originImageWidth / originImageHeight) > ratio) {
                                      //宽度取最大宽度值maxWidth,缩放高度
                                      targetWidth = maxWidth;
                                      targetHeight = Math.round(maxWidth * (originImageHeight / originImageWidth));
                                  } else {
                                      //高度取最大高度值maxHeight,缩放宽度
                                      targetHeight = maxHeight;
                                      targetWidth = Math.round(maxHeight * (originImageWidth / originImageHeight));
                                  }
                              }
                              // canvas对图片进行缩放
                              canvas.width = targetWidth;
                              canvas.height = targetHeight;
                              // 清除画布
                              ctx.clearRect(0, 0, targetWidth, targetHeight);
                              // 绘制图片
                              ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
                              // quality值越小,图像越模糊,默认图片质量为0.92
                              const imageDataURL = canvas.toDataURL(file.type || 'image/jpeg', pictureQuality);
                              // 去掉URL的头,并转换为byte
                              const imageBytes = window.atob(imageDataURL.split(',')[1]);
                              // 处理异常,将ascii码小于0的转换为大于0
                              const arrayBuffer = new ArrayBuffer(imageBytes.length);
                              const uint8Array = new Uint8Array(arrayBuffer);
                              for (let i = 0; i < imageBytes.length; i++) {
                                  uint8Array[i] = imageBytes.charCodeAt(i);
                              }
                              let mimeType = imageDataURL.split(',')[0].match(/:(.*?);/)[1];
                              let newFile = new File([uint8Array], file.name, {type: mimeType || 'image/jpeg'});
                              console.log('压缩后，文件大小为 : ', (newFile.size / 1024 / 1024) + "M");
                              resolve(newFile);
                          };
                      };
                      reader.onerror = async () => {
                          // this.setState({
                          //     spinLoading: false
                          // });
                          return file;
                      }
                  }).then(res => {
                      // this.setState({
                      //     spinLoading: false
                      // });
                      return res;
                  }).catch(() => {
                      // this.setState({
                      //     spinLoading: false
                      // });
                      return file;
                  });
              } catch (e) {
                  // this.setState({
                  //     spinLoading: false
                  // });
                  //压缩出错,直接返回原file对象
                  return file;
              }
          }
      } else {
          //不需要压缩，直接返回原file对象
          return file;
      }
  // } else {
  //     //非图片文件,不进行压缩,直接返回原file对象
  //     return file;
  // }
};

export function getDayDate(day){ //获取几天前日期
  let today = new Date();
  let targetday_milliseconds=today.getTime() + 1000 * 60 * 60 * 24 * day;
  today.setTime(targetday_milliseconds); //注意，这行是关键代码
  let tYear = today.getFullYear();
  let tMonth = today.getMonth();
  let tDate = today.getDate();
  tMonth = doHandleMonth(tMonth + 1);
  tDate = doHandleMonth(tDate);
  return tYear+"-"+tMonth+"-"+tDate;
};

export function doHandleMonth(month){
  let m = month;
  if(month.toString().length == 1){
      m = "0" + month;
  }
  return m;
}