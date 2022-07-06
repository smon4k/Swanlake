import BigNumber from 'bignumber.js';
export function deepCopy(state) {
  let result
  if (typeof state === 'object') {
    if (Array.isArray(state)) {
      result = []
      for (let i in state) {
        result.push(deepCopy(state[i]))
      }
    } else if (state === null) {
      result = null
    } else if (state.constructor === RegExp) {
      result = state
    } else {
      result = {}
      for (let key in state) {
        result[key] = deepCopy(state[key])
      }
    }
  } else {
    result = state
  }

  return result
}

// 小数点位数处理
export function toFixed(val, len) {
  val = toolNumber(val || 0)
  var f = parseFloat(val);
  if (isNaN(f)) {
    return '--';
  }
  var s = val.toString();
  if (s.indexOf(".") > 0) {
    var f = s.split(".")[1].substring(0, len)
    s = s.split(".")[0] + "." + f
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
  // console.log(amount, str, web3.utils.fromWei('12', str));
  if(amount) {
    return web3.utils.fromWei(amount.toString(), str);
  } else {
    return '0';
  }
}

export const $inputLimit = function (e, point, isModel) {
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
    for (let i = 0; i < leng; i++) {
      if (text.charAt(i) !== '0' || i === leng - 1) {
        e.target.value = text.substring(i)
        break
      }
    }
  }
  if (isModel) return String(e.target.value)
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
        for (var i = 0; i <= power - dotIndex; i++) {
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

export function byDecimals(number, tokenDecimals = 18) {
  const decimals = new BigNumber(10).exponentiatedBy(tokenDecimals);
  return new BigNumber(number).dividedBy(decimals);
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
export const calcDailyDefault0 = (apy, isPer) => {
  if (!apy) return `0%`;

  // const g = Math.pow(10, Math.log10(apy + 1) / 365) - 1;
  const g = (Math.pow(10, Math.log10(apy + 1) / 365) - 1) * 365;
  if (isNaN(g)) {
    return '- %';
  }

  return isPer ? `${(g * 100).toFixed(2)}%` : `${(g * 100).toFixed(2)}`;
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