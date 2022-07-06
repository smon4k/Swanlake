import {  fromWei , toWei , toolNumber , toFixed, byDecimals, keepDecimalNotRounding, scientificNotationToString} from '@/utils/tools'
import { $get } from '@/utils/request'
import axios from 'axios'
import { getUrlParams, getQueryString } from '@/utils/tools'
import router from '@/router'

/**
 * 获取用户信息
 * @param {*} type 
 * @returns 
 */
 export const getUserAddressInfo = async function(userAddress){
    const apiUrl = __ownInstance__.$store.state.base.apiUrl
    const address = userAddress || __ownInstance__.$store.state.base.address;
    const re_address = getQueryString('re');
    let result = [];
    if(address && address !== '') {
      let data = await $get(apiUrl + '/api/User/getUserAddressInfo?address='+address+'&invite_address='+re_address)
      if(data && data.code == 10000) {
        result = data.data;
      }
    }
    return result;
  }

/**
 * 绑定户钱包地址
 * @param {*} type 
 * @returns 
 */
 export const saveUserInfo = async function(userId=0, address=''){
  const apiUrl = __ownInstance__.$store.state.base.apiUrl
  let result = false;
  if (userId > 0 && address && address !== '') {
    const params = {
      userId: userId,
      address: address,
    };
    await axios.post(apiUrl + '/api/User/saveUserInfo', params).then(async (json) => {
      console.log(json);
      if (json && json.code == 10000) {
        result = true;
      } else if (json && json.code == 70001) {
          // Notify({ type: 'warning', message: json.msg });
          result = false;
      } else {
          // Notify({ type: 'warning', message: 'Error' });
          result = false;
      }
    }).catch((error) => {
      // Notify({ type: 'danger', message: error });
      result = false;
    });
  }
  return result;
}