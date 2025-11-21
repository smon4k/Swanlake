
import {  fromWei , toWei } from '@/utils/tools'
import { toolNumber } from '@/utils/tools'
import  tokenABI from './abis/token.json'
import gameFillingABI from './abis/gameFillingABI.json'
import gameFillingABIV2 from './abis/gameFillingABIV2.json'
import hashpowerABI from './abis/hashpowerABI.json'
import goblinPoolsABI from './abis/goblinPools.json'
import {saveNotifyStatus, setUSDTDepositWithdraw} from "@/wallet/serve";

// 领取空投奖励

const mintBankErr = [
  {
    key:'Debt scale is out of scope',
    val:'Debt scale is out of scope'
  },
  {
    key:'bad work factor',
    val:'Price matters too much'
  },
]

// 授权

export const approve =  function (tokenAddress  , otherAddress ,  amount , decimals) {
  const account = __ownInstance__.$store.state.base.address;
  const approveAmount = (amount &&  decimals)? web3.utils.toHex(toWei(amount , decimals))  : web3.utils.toHex(toolNumber(1.157920892373163*Math.pow(10 , 59)))
  const tokenContract = new web3.eth.Contract(tokenABI, tokenAddress);
  const contract = otherAddress || __ownInstance__.$store.state.base.bankAddress
  const approveEncodedABI = tokenContract.methods
    .approve(contract, approveAmount)
    .encodeABI();
  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(account).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateParams ={
        from: account,
        to: tokenAddress, // 池地址
        data: approveEncodedABI, // Required
      }
      let estimateGas = await web3.eth.estimateGas(estimateParams)
        const params = [{
          chainId: __ownInstance__.$store.state.base.chainId,
          nonce: web3.utils.toHex(transactionNonce),
          // gasLimit: web3.utils.toHex(6000000),
          gasLimit: web3.utils.toHex(estimateGas),
          gasPrice: web3.utils.toHex(gasPrice),
          to: tokenAddress,
          from: account,
          data: approveEncodedABI
        }];
        web3.eth.sendTransaction(params[0])
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
      
    });
  })
}

/**
 * 游戏系统-充提清算系统-充值
 * @param {*} gTokenAmt 充值的数量
 * @param {*} buyToken 提的Token地址
 * @param {*} decimals 长度
 * @param {*} fillingRecordParams 记录数据库参数
 * @returns 
 */
export const gamesBuyTokenTogToken = function (gTokenAmt=0, buyToken='', decimals=18, fillingRecordParams={}) {
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.gamesFillingAddress;
  const contract = new web3.eth.Contract(gameFillingABI, contractAddress);
  const depositAmount = toWei(gTokenAmt, decimals);
  let encodedABI = contract.methods.BuyTokenTogToken(depositAmount, buyToken).encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = await web3.eth.estimateGas({
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
      })
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(400000), // Optional
      }];
      web3.eth.sendTransaction(params[0])
        .on('transactionHash', async function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
            //开始记录数据库充提记录 修改用户充提状态为充提进行中
            fillingRecordParams.hash = hash;
            await setUSDTDepositWithdraw(fillingRecordParams);
            //开始修改用户充提状态为充提进行中
            // saveNotifyStatus(1);
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}

/**
 * 游戏系统-充提清算系统-提取
 * @param {*} gTokenAmt 提的数量
 * @param {*} buyToken 提的Token地址
 * @param {*} decimals 长度
 * @param {*} fillingRecordParams 记录数据库参数
 * @returns 
 */
export const gamesGTokenToBuyToken = function (gTokenAmt=0, buyToken='', decimals=18, fillingRecordParams={}, orderId='') {
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.gamesFillingAddress;
  const withdrawAmount = toWei(gTokenAmt, decimals);
  const contract = new web3.eth.Contract(gameFillingABIV2, contractAddress);
  const encodedABI = contract.methods.gTokenToBuyToken(withdrawAmount, buyToken, orderId).encodeABI();
  // const contract = new web3.eth.Contract(gameFillingABI, contractAddress);
  // const encodedABI = contract.methods.gTokenToBuyToken(withdrawAmount, buyToken).encodeABI();
  // let value = toWei('0.00201', decimals);
  let value = toWei('0', decimals);

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = await web3.eth.estimateGas({
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        value: web3.utils.toHex(value)
      })
      // estimateParams.value = web3.utils.toHex(borrow) 
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(400000), // Optional
      }];
      params[0].value =  web3.utils.toHex(value) 
      web3.eth.sendTransaction(params[0])
        .on('transactionHash', async function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
            fillingRecordParams.hash = hash;
            await setUSDTDepositWithdraw(fillingRecordParams);
            //开始修改用户充提状态为充提进行中
            // saveNotifyStatus(1);
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}

// 算力币购买
export const BuyTokenToSFunction = function (hashpowerAddress='', amount=0, functionName='BuyTokenToS19', decimals=18) {
  // console.log(amount);
  console.log(functionName);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = hashpowerAddress || __ownInstance__.$store.state.base.hashpowerAddress;
  console.log(contractAddress);
  const contract = new web3.eth.Contract(hashpowerABI, contractAddress);
  const depositAmount = toWei(amount, decimals);
  let encodedABI = contract.methods[functionName](depositAmount).encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = await web3.eth.estimateGas({
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
      })
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(400000), // Optional
      }];
      web3.eth.sendTransaction(params[0])
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}

// 设置算力币价格
export const setBuyTokenToSRatio = function (hashpowerAddress='', price=0, updatePricefun='', decimals=18) {
  // console.log(amount);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = hashpowerAddress || __ownInstance__.$store.state.base.hashpowerAddress;
  // console.log(contractAddress);
  const contract = new web3.eth.Contract(hashpowerABI, contractAddress);
  const depositAmount = toWei(price, decimals);
  let encodedABI = contract.methods[updatePricefun](depositAmount).encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = await web3.eth.estimateGas({
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
      })
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(400000), // Optional
      }];
      web3.eth.sendTransaction(params[0])
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}


// 算力币 存款存入
export const depositPoolsIn = function (goblinAddress, decimals, amount){
  console.log('amount' , amount);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  // let isCoinbase = tokenAddress === '0x0000000000000000000000000000000000000000'
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = goblinAddress;
  const contract = new web3.eth.Contract(goblinPoolsABI, contractAddress);
  const depositAmount = toWei(amount, decimals)
  const value = web3.utils.toHex(depositAmount)
  let encodedABI = contract.methods.deposit(value).encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateParams ={
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
      }
      // if(isCoinbase) estimateParams.value = web3.utils.toHex(depositAmount) 
      let estimateGas = parseInt(await web3.eth.estimateGas(estimateParams) * 1.4)
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(2000000), // Optional
      }];
      // if(isCoinbase) params[0].value =  web3.utils.toHex(depositAmount) 
      web3.eth.sendTransaction(params[0])
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}

// 算力币 提取
export const depositPoolsOut = function (goblinAddress, decimals, amount){
  console.log('depositPoolsOut' , amount);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = goblinAddress;
  const contract = new web3.eth.Contract(goblinPoolsABI, contractAddress);
  const depositAmount = toWei(amount, decimals)
  let encodedABI = contract.methods.withdraw(web3.utils.toHex(depositAmount)).encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = parseInt(await web3.eth.estimateGas({
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
      }) * 1.4)
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(400000), // Optional
      }];
      web3.eth.sendTransaction(params[0])
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
          }
        })
        .on('receipt', function (receipt) {
          // 交易成功
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:1 , hash:hashInfo})
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}
