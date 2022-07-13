import {  fromWei , toWei , toolNumber , toFixed, byDecimals, keepDecimalNotRounding, scientificNotationToString} from '@/utils/tools'
// import { $get } from '@/utils/request'
import  tokenABI from './abis/token.json'
import gameFillingABI from './abis/gameFillingABI.json'

import { get, post } from "@/common/axios.js";
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
      let data = await $get('/Api/User/getUserAddressInfo?address='+address+'&re_address='+re_address);
        if (data && data.code == 10000) {
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
    await post('/Api/User/saveUserInfo', params, async (json) => {
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
    })
  }
  return result;
}

// 获取余额
export async function getBalance(tokenAddress, decimals , poolAddress) {
  const address = poolAddress || __ownInstance__.$store.state.base.address
  if(!address || address == undefined || address == '') {
    return 0;
  }
  let balance = 0;
  if(tokenAddress === '0x0000000000000000000000000000000000000000'){
    balance = await new web3.eth.getBalance(address)
    return fromWei(balance, decimals)
  }


  const contract = new web3.eth.Contract(tokenABI, tokenAddress);
  await contract.methods.balanceOf(address).call(function (error, result) {
    if (!error) {
      balance = fromWei(result, decimals);
    }else {
      console.log('balanceErr' , error);
    }
  });
  return balance;
}

// 是否授权
export const isApproved = async function (tokenAddress, decimals, amount , otherAddress) {
  if(tokenAddress === '0x0000000000000000000000000000000000000000') return true
  const account = __ownInstance__.$store.state.base.address;
  if(!account || account == undefined || account == '') {
    return false;
  }
  const tokenContract = new web3.eth.Contract(tokenABI, tokenAddress);
  let contract = otherAddress || __ownInstance__.$store.state.base.bankAddress
  let approveAmount = 0;
  await tokenContract.methods.allowance(account, contract).call(function (error, result) {
    if (error) {
      return false;
    }
    approveAmount = result;
    // console.log('检查授权' , approveAmount);

  })
  return Number(toWei(amount.toString(), decimals)) < approveAmount;
}

//获取游戏-充提系统-充提余额
export async function getGameFillingBalance(decimals=18) {
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.gamesFillingAddress;
  const contract = new web3.eth.Contract(gameFillingABI, contractAddress);
  let num = 0;
  await contract.methods.userInfo(address).call((error, result) => {
    if (!error) {
      // console.log(result);
      num = fromWei(result.shares, decimals);
      if(result.minus) {
        num = -num;
      }
    } else {
      console.log('userInfo' ,error);
    }
  });
  return num;
}

//获取游戏-充提系统-修改充提状态
export const saveNotifyStatus = async function(status, type=true){
  const apiUrl = __ownInstance__.$store.state.base.nftUrl;
  const address = __ownInstance__.$store.state.base.address;
  await $get('/Api/Depositwithdrawal/saveNotifyStatus?address='+address+'&status='+status+'&type='+type);
}

//获取游戏-充提系统-修改充提记录日志状态
export const setDepWithdrawStatus = async function(deWithId, status, type=true){
  const apiUrl = __ownInstance__.$store.state.base.nftUrl;
  const address = __ownInstance__.$store.state.base.address;
  await $get('/Api/Depositwithdrawal/setDepWithdrawStatus?address='+address+'&deWithId='+deWithId+'&status='+status+'&type='+type);
}

//获取游戏-充提系统-监听充提状态是否执行完成
export const getGameFillingWithdrawStatus = async function(withdrawId){
  const apiUrl = __ownInstance__.$store.state.base.nftUrl;
  const address = __ownInstance__.$store.state.base.address;
  let status = 0;
  let data = await $get('/Api/Depositwithdrawal/getGameFillingWithdrawStatus?address='+address+'&withdrawId='+withdrawId);
  if(data && data.code == 10000) {
    status = data.data;
  }
  return status;
}