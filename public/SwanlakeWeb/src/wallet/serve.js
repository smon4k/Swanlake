import {  fromWei , toWei , toolNumber , toFixed, byDecimals, keepDecimalNotRounding, scientificNotationToString} from '@/utils/tools'
// import { $get } from '@/utils/request'
import  tokenABI from './abis/token.json'
import gameFillingABI from './abis/gameFillingABI.json'
import goblinPoolsABI from './abis/goblinPools.json'
import fairLaunchABI from './abis/fairLaunch.json'
import H2OPoolsABI from './abis/H2OPoolsABI.json'
import cakeRouterABI from './abis/cakeRouter.json'
import mdexABI from './abis/mdexABI.json'
import hashpowerABI from './abis/hashpowerABI.json'
import { get, post } from "@/common/axios.js";
import { $get } from '@/utils/request'
import axios from 'axios'
import { getUrlParams, getQueryString } from '@/utils/tools'
import router from '@/router'
import Address from '@/wallet/address.json'

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
      let data = await $get(apiUrl + '/Api/User/getUserAddressInfo?address='+address+'&re_address='+re_address);
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
    await post(apiUrl + '/Api/User/saveUserInfo', params, async (json) => {
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

// 获取杠杆价格
export const getToken2TokenPrice = async function (token0 , token1 ,type , amount = 1, routerContractAddress=''){

  if(type === 'TEST'){
    // if(token0 === Address.BUSDT) return 1
    return 1
  }


  if(token1 === 'USDT') return 1
  if(token0 === '0x0000000000000000000000000000000000000000') token0 = Address.WBNB
  if(token1 === '0x0000000000000000000000000000000000000000') token1 = Address.WBNB
  // console.log('token0' , token0);
  // console.log('token1' , token1);
  // console.log('Address.BUSDT' , Address.BUSDT);
  // if(token0 === Address.BUSDT || token1 === Address.BUSDT) return 1
  let contractAddress;
  if(token0 === Address.H2O || token1 === Address.H2O) {
    contractAddress = Address.cakeRouter;
  } else {
    contractAddress = Address.cakeRouter
  }
  // console.log('contractAddress' , contractAddress);
  const contract = new web3.eth.Contract(cakeRouterABI, contractAddress);
  let price = 0
  await contract.methods.getAmountsOut(web3.utils.toHex(toWei(amount,18)) , [token0 , token1]).call(function(error , result){
    if (!error && Array.isArray(result) && result[1] ) {
      // console.log(token0, result);
      price = fromWei(result[1], 18);
    } else {
      // console.log(token0, token1, type);
      console.log('getToken2TokenPrice_err',error)
    }
  })
  return price
}


//获取 HashPowerPools 池子数据
export async function getHashPowerPoolsTokensData(goblinAddress, currencyToken, id){
  const address = __ownInstance__.$store.state.base.address
  const decimals = __ownInstance__.$store.state.base.tokenDecimals
  let HashpowerDetail = await getHashpowerDetail(id); //获取算力币详情
  if(!HashpowerDetail || HashpowerDetail.length < 0) {
    return false;
  }
  let totalTvl = await getPoolsTotalShare(goblinAddress, decimals);
  let tokenPrice = 50;
  // tokenPrice = await getToken2TokenPrice(currencyToken, Address.BUSDT) //获取池子价格
  let userBalance = 0;
  let reward = 0;
  let btcbReward = 0;
  let h2oReward = 0;
  let YearPer = 0
  let H2OYearPer = 0
  let BTCBYearPer = 0
  let btcbPrice = 0
  let btcb19ProBalance = 0
  let cost_revenue = 0
  let daily_income = 0
  let currency = 0
  let annualized_income = 0
  let harvest_btcb_amount = 0
  let is_give_income = 0
  let daily_expenditure_usdt = 0
  let daily_expenditure_btc = 0
  let daily_income_usdt = 0
  let daily_income_btc = 0
  let daily_output_usdt = 0
  let daily_output_btc = 0
  let power_consumption_ratio = 0
  let chain_address = ''
  let h2oPrice = 0;
  let h2o_income_number = 0;
  let hashpower_price = 0;
  let hash_rate = 0;
  let price_unit = '';
  let stock = 0;
  if(id) {
    if(address && address !== undefined && address !== '') {
      userBalance = await getH2OUserInfo(goblinAddress);
    }
    btcbReward = await getBTCBPendingBonus(goblinAddress, 8); //获取BTCB奖励
    if(id == 2) {
      h2oPrice = await getToken2TokenPrice(Address.H2O, Address.BUSDT) //获取btcb价格
      h2oReward = await getH2OPendingBonus(goblinAddress, 8); //获取H2O奖励
      // console.log(pId, h2oReward, btcbReward)
    }
    // console.log(id, totalTvl, tokenPrice, userBalance)
    // let bonusPerShare = await getH2OAccBonusPerShare(goblinAddress); //累计收益
    // let lastAccBonusPerShare = await getH2OLastAccBonusPerShare(goblinAddress); //上次累计收益
    // let cakePrice = await getToken2TokenPrice("0x0E09FaBB73Bd3Ade0a17ECC321fD13a19e81cE82", Address.BUSDT) //获取Cake价格
    // let btcbPrice = await getSwapPoolsAmountsOut(publicAddress.routerContractAddress, Address.BTCB , Address.BUSDT ); //获取水价格
    btcbPrice = await getToken2TokenPrice(Address.BTCB, Address.BUSDT) //获取btcb价格
    // console.log(btcbPrice);
    // console.log(reptileBtcData);
    btcb19ProBalance = await getBalance(currencyToken, 18); //获取购买算力币余额
    cost_revenue = HashpowerDetail.cost_revenue; //估值
    daily_income = HashpowerDetail.daily_income; //日收益率
    currency = HashpowerDetail.currency; //交易币种
    annualized_income = HashpowerDetail.annualized_income < 0 ? 0 : HashpowerDetail.annualized_income; //年化收益率
    harvest_btcb_amount = HashpowerDetail.harvest_btcb_amount; //已收割奖励数量
    is_give_income = HashpowerDetail.is_give_income; //是否显示昨日收益 大于第一次质押时间一天 给收益
    daily_expenditure_usdt = HashpowerDetail.daily_expenditure_usdt //日支出 usdt
    daily_expenditure_btc = HashpowerDetail.daily_expenditure_btc //日支出 btc
    daily_income_usdt = HashpowerDetail.daily_income_usdt < 0 ? 0 : HashpowerDetail.daily_income_usdt; //日收益 usdt
    daily_income_btc = HashpowerDetail.daily_income_btc < 0 ? 0 : HashpowerDetail.daily_income_btc; //日收益 btc
    daily_output_usdt = HashpowerDetail.daily_output < 0 ? 0 : HashpowerDetail.daily_output; //日产出 usdt
    daily_output_btc = HashpowerDetail.daily_output_btc < 0 ? 0 : HashpowerDetail.daily_output_btc; //日产出 btc
    power_consumption_ratio = HashpowerDetail.power_consumption_ratio //功耗比
    chain_address = HashpowerDetail.chain_address //合约地址
    h2o_income_number = HashpowerDetail.h2o_income_number; //总的自定义收益数量
    hashpower_price = HashpowerDetail.price;
    hash_rate = HashpowerDetail.hash_rate;
    price_unit = HashpowerDetail.price_unit; //价格单位
    stock = HashpowerDetail.stock; //库存
  } 
  let reObj = {
    totalTvl: totalTvl,
    tokenPrice: tokenPrice,
    userBalance: userBalance,
    reward: reward,
    btcbReward: btcbReward,
    h2oReward: h2oReward,
    yearPer: YearPer,
    h2oYearPer: H2OYearPer,
    btcbYearPer: BTCBYearPer,
    btcbPrice: btcbPrice,
    h2oPrice: h2oPrice,
    btcb19ProBalance: btcb19ProBalance,
    cost_revenue: cost_revenue,
    daily_income: daily_income,
    currency: currency,
    annualized_income: annualized_income,
    harvest_btcb_amount: harvest_btcb_amount,
    is_give_income: is_give_income,
    daily_expenditure_usdt: daily_expenditure_usdt,
    daily_expenditure_btc: daily_expenditure_btc,
    daily_income_usdt: daily_income_usdt,
    daily_income_btc: daily_income_btc,
    daily_output_usdt: daily_output_usdt,
    daily_output_btc: daily_output_btc,
    power_consumption_ratio: power_consumption_ratio,
    chain_address: chain_address,
    h2o_income_number: h2o_income_number,
    hashpower_price: hashpower_price,
    hash_rate: hash_rate,
    price_unit: price_unit,
    stock: stock,
  };
  return reObj;
}

//获取 HashPowerPools 总的TVL
export const getPoolsTotalShare = async function (goblinAddress, decimals) {
  const contract = new web3.eth.Contract(goblinPoolsABI, goblinAddress);
  let total = 0;
  await contract.methods.totalShare().call(function (error, result) {
    if (!error) {
      // console.log(result);
      total = fromWei(result, decimals);
    }else {
      console.log('totalShareErr' , error);
    }
  });
  return total;
}

// 获取持仓H2O奖励
export async function getPositionRewardBalance(pid, decimals ) {
  const address = __ownInstance__.$store.state.base.address;
  if(!address || address == undefined || address == '') {
    return 0;
  }
  const contractAddress = __ownInstance__.$store.state.base.fairLaunchAddress;
  const contract = new web3.eth.Contract(fairLaunchABI, contractAddress);
  let balance = 0;
  await contract.methods.pendingH2O(pid , address).call(function (error, result) {
    if (!error) {
      balance = fromWei(result, decimals);
      // console.log('持仓H2O奖励balance' , balance);
    }else {
      console.log('pendingH2O' ,error);
    }
  });
  return balance;
}

// 获取BTCB奖励
export async function getBTCBPendingBonus(goblinAddress, number=6) {
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = goblinAddress || __ownInstance__.$store.state.base.h2oPoolAddress
  const contract = new web3.eth.Contract(H2OPoolsABI, contractAddress);
  let num = 0;
  if(!address || address == undefined || address == '') {
    return num;
  }
  await contract.methods.pendingBonus(address).call((error, result) => {
    if (!error) {
      // console.log(result);
      // console.log(fromWei(result, 18));
      // num = keepDecimalNotRounding(fromWei(result, 18), number, true)
      num = fromWei(result, 18);
    }else{
      console.log('pendingBonus' ,error);
    }
  });
  return num;
}

// 获取H2O奖励
export async function getH2OPendingBonus(goblinAddress, number=6) {
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = goblinAddress || __ownInstance__.$store.state.base.h2oPoolAddress
  const contract = new web3.eth.Contract(H2OPoolsABI, contractAddress);
  let num = 0;
  if(!address || address == undefined || address == '') {
    return num;
  }
  await contract.methods.pendingBonus2(address).call((error, result) => {
    if (!error) {
      num = fromWei(result, 18);
    }else{
      console.log('pendingBonus2' ,error);
    }
  });
  return num;
}

// 获取H2O池子我的存款余额
export async function getH2OUserInfo(contractAddr, userAddress) {
  const address = userAddress || __ownInstance__.$store.state.base.address;
  const contractAddress = contractAddr || __ownInstance__.$store.state.base.h2oPoolAddress
  const contract = new web3.eth.Contract(H2OPoolsABI, contractAddress);
  let balance = 0;
  const Gwei1 = 1000000000;
  await contract.methods.userInfo(address).call(function (error, result) {
    // console.log(contractAddr, result);
    if (!error) {
      if(result && result['shares']) {
        balance = keepDecimalNotRounding(byDecimals(result['shares'], 18), 6, true)
      }
      
    }else{
      console.log('userInfo' ,error);
    }
  });
  return balance;
}

//获取估值
export const getSwapPoolsAmountsOut = async function (routerContractAddress, tk0Address, tk1Address, bnbAddress, isTest) {
  // console.log(routerContractAddress, tk0Address, tk1Address);

  const contract = new web3.eth.Contract(mdexABI, routerContractAddress);
  let amountsOut = 0;
  let path = [];
  if(bnbAddress && bnbAddress !== '') {
    path = [tk0Address, bnbAddress, tk1Address];
  } else {
    path = [tk0Address, tk1Address];
  }
  // console.log(path);
  const Gwei1 = 1000000000;
  await contract.methods.getAmountsOut(Gwei1, path).call(function (error, result) {
    if (!error) {
      if(isTest) {
        // console.log(result);
      }
      if(bnbAddress && bnbAddress !== '') {
        amountsOut = result[2] ? result[2] / Gwei1 : 0;
      } else {
        amountsOut = result[1] ? result[1] / Gwei1 : 0;
      }
    }else {
      console.log('getAmountsOutErr' , error);
    }
  });
  return amountsOut;
}

//获取算力币价格
export async function getHashpowerPrice(hashpowerAddress, functionName='US23Ratio', decimals=18) {
  const contract = new web3.eth.Contract(hashpowerABI, hashpowerAddress);
  let price = 0;
  await contract.methods[functionName]().call(function (error, result) {
    if (!error) {
      price = fromWei(result, decimals);
    }
  });
  return price;
}

//获取游戏-充提系统-充提余额
export async function getGameFillingBalance(decimals=18, gamesFillingAddress='') {
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = gamesFillingAddress || __ownInstance__.$store.state.base.gamesFillingAddress;
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
  const apiUrl = __ownInstance__.$store.state.base.apiUrl;
  const address = __ownInstance__.$store.state.base.address;
  await $get(apiUrl + '/Api/Depositwithdrawal/saveNotifyStatus?address='+address+'&status='+status+'&type='+type);
}

//获取游戏-充提系统-修改充提记录日志状态
export const setDepWithdrawStatus = async function(deWithId, status, type=true){
  const apiUrl = __ownInstance__.$store.state.base.apiUrl;
  const address = __ownInstance__.$store.state.base.address;
  await $get(apiUrl + '/Api/Depositwithdrawal/setDepWithdrawStatus?address='+address+'&deWithId='+deWithId+'&status='+status+'&type='+type);
}

//获取游戏-充提系统-监听充提状态是否执行完成
export const getGameFillingWithdrawStatus = async function(withdrawId){
  const apiUrl = __ownInstance__.$store.state.base.apiUrl;
  const address = __ownInstance__.$store.state.base.address;
  let status = 0;
  let data = await $get(apiUrl + '/Api/Depositwithdrawal/getGameFillingWithdrawStatus?address='+address+'&withdrawId='+withdrawId);
  if(data && data.code == 10000) {
    status = data.data;
  }
  return status;
}


//记录充提记录到数据库 修改充提状态为充提中
export const setUSDTDepositWithdraw = async (params={}) => {
  const apiUrl = __ownInstance__.$store.state.base.apiUrl;
  if(params && params.hash !== '') {
    await axios.post(apiUrl + '/Api/Depositwithdrawal/depositWithdraw', params).then((json) => {
      if(json && json.code == 10000) {
        return true;
      }
    }).catch((error) => {
        console.log(error);
        return false;
    });
  }
  return false;
};

//获取游戏-充提系统-获取充提下一个自增id
export const getFillingIncreasingId = async function(){
  const apiUrl = __ownInstance__.$store.state.base.apiUrl;
  const address = __ownInstance__.$store.state.base.address;
  let status = 0;
  let data = await $get(apiUrl + '/Api/Depositwithdrawal/getIncreasingId?address='+address);
  if(data && data.code == 10000) {
    status = data.data;
  }
  return status;
}

/**
 * 获取BTC爬虫数据
 * @param {*} type 
 * @returns 
 */
 export const getPoolBtcData = async function(){
  const apiUrl = __ownInstance__.$store.state.base.apiUrl
  const address = __ownInstance__.$store.state.base.address;
  let result = [];
  let data = await $get('https://pacx.h2opower.site/getPoolBtc')
  if(data) {
    result = data;
  }
  return result;
}

//获取算力币列表
export const getHashpowerList = async function(){
  const nftUrl = __ownInstance__.$store.state.base.nftUrl;
  let result = [];
  let data = await $get(nftUrl + '/Hashpower/Hashpower/getHashpowerData?page=1&limit=100');
  if(data && data.code == 10000) {
    result = data.data.lists;
  }
  return result;
}

//获取算力币详情
export const getHashpowerDetail = async function(hashId){
  const nftUrl = __ownInstance__.$store.state.base.nftUrl;
  const address = __ownInstance__.$store.state.base.address;
  let result = [];
  let data = await $get(nftUrl + '/Hashpower/Hashpower/getHashpowerDetail?hashId='+hashId+'&address='+address);
  if(data && data.code == 10000) {
    result = data.data;
  }
  return result;
}

//记录算力币数据统计
export const setStatiscData = async function(type=0, hashId=0){
  const nftUrl = __ownInstance__.$store.state.base.nftUrl;
  const address = __ownInstance__.$store.state.base.address;
  let result = [];
  let data = await $get(nftUrl + '/Hashpower/Hashpower/setStatiscData?hashId='+hashId+'&type='+type);
  if(data && data.code == 10000) {
    result = data.data;
  }
  return result;
}