
import {  fromWei , toWei } from '@/utils/tools'
import airdropABIs from './abis/airdrop.json'
import { toolNumber } from '@/utils/tools'
import  tokenABI from './abis/token.json'

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

/**
 * 领取空投并且建立推荐关系 - 01
 * @param {*} recommand 用户地址或者邀请人地址
 * @param {*} isAirdrop 空投活动是否结束 true：进行中 false：已结束
 * @returns 
 */
export const receiveAirdrop = function (recommand, isAirdrop){
    console.log('receiveAirdrop' , recommand);
    const address = __ownInstance__.$store.state.base.address;
    const contractAddress = __ownInstance__.$store.state.base.airdropAddress;
    const contract = new web3.eth.Contract(airdropABIs, contractAddress);
    // console.log(contract);
    let encodedABI;
    if(isAirdrop) { //领取空投并且建立推荐关系
      encodedABI = contract.methods.draw(recommand).encodeABI();
    } else { //活动已结束 只建立推荐关系
      // console.log(recommand);
      encodedABI = contract.methods.drawlater(recommand).encodeABI();
    }
  
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
            __ownInstance__.$store.dispatch('getAirdropValue')
            __ownInstance__.$store.dispatch('getAirDropDrawed')
            __ownInstance__.$store.dispatch('changeTradeStatus' , { id:timestamp , val:1 ,  hash:hashInfo})
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
 * 领取空投并且建立推荐关系 - 02 H2O-Cake
 * @param {*} recommand 用户地址或者邀请人地址
 * @returns 
 */
 export const h2oCakeReceiveAirdrop = function (recommand){
  console.log('receiveAirdrop' , recommand);
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.cakeH2oAirdropAddress;
  const contract = new web3.eth.Contract(airdropABIs, contractAddress);
  // console.log(contract);
  let encodedABI = contract.methods.draw(recommand).encodeABI(); //空投领取H2O
  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      // let estimateGas = await web3.eth.estimateGas({
      //   from: address,
      //   to: contractAddress, // 池地址
      //   data: encodedABI, // Required
      // });
      // let newEstimateGas = Number(estimateGas) + (Number(estimateGas) * 0.2)
      let estimateGas = 1332418
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
          __ownInstance__.$store.dispatch('getAirdropValue')
          __ownInstance__.$store.dispatch('getAirDropDrawed')
          __ownInstance__.$store.dispatch('changeTradeStatus' , { id:timestamp , val:1 ,  hash:hashInfo})
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

export const receiveAllTokenRewards = function (token){
  const address = __ownInstance__.$store.state.base.address
  const timeLockAddress = __ownInstance__.$store.state.base.timeLockAddress
  const contract = new web3.eth.Contract(TIMELOCKABI, timeLockAddress);
  let encodedABI = contract.methods.withdraw(token).encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = await web3.eth.estimateGas({
        from: address,
        to: timeLockAddress, // 池地址
        data: encodedABI, // Required
      })
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: timeLockAddress, // 池地址
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
          __ownInstance__.$store.dispatch('getAirdropValue')
          __ownInstance__.$store.dispatch('getAirDropDrawed')
          __ownInstance__.$store.dispatch('changeTradeStatus' , { id:timestamp , val:1 ,  hash:hashInfo})
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


// 领取推荐挖矿奖励

export const receiveMintReward = function (){
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.airdropAddress;
  const contract = new web3.eth.Contract(airdropABIs, contractAddress);
  let encodedABI = contract.methods.claim().encodeABI();

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
          __ownInstance__.$store.dispatch('getAirdropValue')
          __ownInstance__.$store.dispatch('getAirDropDrawed')
          __ownInstance__.$store.dispatch('changeTradeStatus' , { id:timestamp , val:1 ,  hash:hashInfo})
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


// 授权
export const approve =  function (tokenAddress, otherAddress ,  amount , decimals) {
  console.log('tokenAddress',tokenAddress);
  console.log('approveAddress',otherAddress);
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


// 存款存入

export const depositPutIn = function (tokenAddress ,decimals ,  amount){
  console.log('depositPutIn' , amount);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  let isCoinbase = tokenAddress === '0x0000000000000000000000000000000000000000'
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.bankAddress;
  const contract = new web3.eth.Contract(bankABI, contractAddress);
  const depositAmount = toWei(amount, decimals)
  const value = isCoinbase? '0x0' :  web3.utils.toHex(depositAmount)
  let encodedABI = contract.methods.deposit(tokenAddress  , value).encodeABI();

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
      if(isCoinbase) estimateParams.value = web3.utils.toHex(depositAmount) 
      let estimateGas = await web3.eth.estimateGas(estimateParams)
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(2000000), // Optional
      }];
      if(isCoinbase) params[0].value =  web3.utils.toHex(depositAmount) 
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


// 提取
export const depositPutOut = function (tokenAddress ,decimals ,  amount){
  console.log('depositPutOut' , amount);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.bankAddress;
  const contract = new web3.eth.Contract(bankABI, contractAddress);
  const depositAmount = toWei(amount, decimals)
  let encodedABI = contract.methods.withdraw(tokenAddress  , web3.utils.toHex(depositAmount)).encodeABI();

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



// 质押

export const pledgePutIn = function (tokenAddress ,decimals ,  amount , type){
  console.log('pledgePutIn' , type);
  console.log('pledgePutInAmount' , amount);
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.fairLaunchAddress;
  const contract = new web3.eth.Contract(fairLaunchABI, contractAddress);
  const pledgeAmount = toWei(amount, decimals)
  // let encodedABI = contract.methods.deposit(tokenAddress  , type , web3.utils.toHex(pledgeAmount)).encodeABI();
  let encodedABI = contract.methods.deposit(address  , type , web3.utils.toHex(pledgeAmount)).encodeABI();

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
        gas: web3.utils.toHex(estimateGas), // Opt/ional
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



// 解押

export const pledgePutOut = function (tokenAddress ,decimals ,  amount , type){
  console.log('pledgePutOut' , type);
  console.log('pledgePutAmount' , amount);
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.fairLaunchAddress;
  const contract = new web3.eth.Contract(fairLaunchABI, contractAddress);
  const pledgeAmount = toWei(amount, decimals)
  // let encodedABI = contract.methods.withdraw(tokenAddress  , type  , web3.utils.toHex(pledgeAmount)).encodeABI();
  let encodedABI = contract.methods.withdraw(address  , type  , web3.utils.toHex(pledgeAmount)).encodeABI();

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
        // gas: web3.utils.toHex(800000), // Optional
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


// IRO领取奖励

export const getIROReward = function (type){
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.fairLaunchAddress;
  const contract = new web3.eth.Contract(fairLaunchABI, contractAddress);
  let encodedABI = contract.methods.harvest(type).encodeABI();

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
        // gas: web3.utils.toHex(800000), // Optional
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
      console.log('getIROReward_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}





// 杠杆挖矿

/**
 * 
 * @param {借款token} borrowToken 
 * @param {非借款token} otherToken 
 * @param {借款token转账金额} borrowTokenAmount 
 * @param {*} otherTokenAmount 
 * @param {*} borrowTokenDecimals 
 * @param {*} otherTokenDecimals 
 * @param {借款金额} borrowAmount 
 * @param {*} posId 
 * @param {*} pid 
 * @returns 
 */


export const mintWork = function (borrowToken ,otherToken ,borrowTokenAmount ,  otherTokenAmount , borrowTokenDecimals , otherTokenDecimals , borrowAmount , posId , pid  , strategyAddress){
  // console.log('borrowToken' , borrowToken);
  // console.log('otherToken' , otherToken);
  // console.log('borrowTokenAmount' , borrowTokenAmount);
  // console.log('borrowTokenAmount' , borrowTokenAmount);
  // console.log('otherTokenAmount' , otherTokenAmount);
  // console.log('borrowAmount', borrowAmount);
  // console.log('borrowTokenDecimals', borrowTokenDecimals);
  // console.log('posId' , posId);
  // console.log('pid' , pid);
  // console.log('strategyAddress' , strategyAddress);
  // console.log('借款金额' , borrowAmount);
  // console.log('转账金额' , otherTokenAmount);
  const address = __ownInstance__.$store.state.base.address;
  
  let borrowTokenWei = toWei(borrowTokenAmount ,borrowTokenDecimals )
  let otherTokenWei = toWei(otherTokenAmount ,otherTokenDecimals )
  let borrowWei = toWei(borrowAmount ,borrowTokenDecimals )
  console.log('borrowWei' , borrowWei); 
  // let a = web3.eth.abi.encodeParameters(['address', 'address', 'uint256', 'uint256', 'uint256'], [token1, '0x0000000000000000000000000000000000000000', 0,  amount0Wei, 0]);
  console.log("a原始值>>>"+"borrowToken:"+borrowToken, "otherToken:"+otherToken, "borrowTokenWei:"+borrowTokenWei,  "otherTokenWei:"+otherTokenWei, 0);
  let a = web3.eth.abi.encodeParameters(['address', 'address', 'uint256', 'uint256', 'uint256'], [borrowToken, otherToken, borrowTokenWei,  otherTokenWei, 0]);
  let b = strategyAddress
  console.log("b参数:"+b);
  let c = web3.eth.abi.encodeParameters(['address', 'bytes'], [b, a])
  console.log("c:"+c);
  const contractAddress = __ownInstance__.$store.state.base.bankAddress;
  const contract = new web3.eth.Contract(bankABI, contractAddress);

  let amount = web3.utils.toHex(borrowWei)
  console.log('work参数>>>'+'posId:'+posId,  'pid:'+pid , 'amount:'+amount);
  let encodedABI = contract.methods.work( posId,  pid , amount, c).encodeABI();
  // console.log(encodedABI);
  // console.log(c);
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
      let borrow = 0
      if(borrowToken === '0x0000000000000000000000000000000000000000'){
        borrow = borrowTokenWei
      }else if(otherToken === '0x0000000000000000000000000000000000000000'){
        borrow = otherTokenWei
      }
      console.log("work Value:"+ borrow);
      estimateParams.value = web3.utils.toHex(borrow) 
      let estimateGas
      try{
        // console.log("estimateParams",estimateParams);
        estimateGas = await web3.eth.estimateGas(estimateParams)
        // estimateGas = 10000000
        console.log(estimateGas);
      }catch(err){
        let errStr = err.toString()
        let errMessage = ''
        mintBankErr.forEach(item=>{
          if(errStr.indexOf(item.key)!==-1){
            errMessage = item.val
          }
        })
        __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo  , errMessage: errMessage})
        reject(err)
        console.log('错误' ,errMessage);
      }
      if(!estimateGas) return 
      console.log('estimateGas' , (Number(estimateGas)*1.6).toFixed(0));
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex((Number(estimateGas)*1.4).toFixed(0)), // Optional
        // gas: web3.utils.toHex(8000000), // Opti/onal
      }];
      // let borrow = borrowToken === '0x0000000000000000000000000000000000000000' ? borrowTokenWei:otherTokenWei
      params[0].value =  web3.utils.toHex(borrow) 
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
          __ownInstance__.$store.commit('setPositionFinish',true)
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo, errMessage: err.message})
          console.log('err' , err)
          reject(err)
        })
    })
    .catch(err=>{
      let errStr = err.toString()
      let errMessage = ''
      mintBankErr.forEach(item=>{
        if(errStr.indexOf(item.key)!==-1){
          errMessage = item.val
        }
      })
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo  , errMessage:errMessage})
      reject(err)
    })
  })
}




/// 赎回 仓位


export const RedeemWork = function (borrowToken ,otherToken ,type , posId ,withDraw ){
  console.log('borrowToken' , borrowToken);
  console.log('otherToken' , otherToken);
  console.log('type' , type);
  console.log('posId' , posId);
  console.log('withDraw' , withDraw);
  const address = __ownInstance__.$store.state.base.address;
 
  
  let a = web3.eth.abi.encodeParameters(['address', 'address', 'uint', ], [borrowToken, otherToken, type]);
  let b = withDraw
  let c = web3.eth.abi.encodeParameters(['address', 'bytes'], [b, a])
  const contractAddress = __ownInstance__.$store.state.base.bankAddress;
  const contract = new web3.eth.Contract(bankABI, contractAddress);

  let encodedABI = contract.methods.work(posId , 0  , 0 , c).encodeABI();

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
      let estimateGas = 1300000
      // let estimateGas = await web3.eth.estimateGas(estimateParams)
      // console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: contractAddress, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(30000), // Optional
      }];
      // console.log(params);
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
          __ownInstance__.$store.commit('setPositionFinish',true)
          resolve(hashInfo)
        })
        .on('confirmation', function (confirmationNumber, receipt) {
        })
        .on('error', function (err) {
          let isUserDeny = err.code === 4001 
          __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2 , isUserDeny, hash:hashInfo, errMessage: err.message})
          console.log('err' , err)
          reject(err)
          __ownInstance__.$store.commit('setPositionFinish',true)

        })
    })
    .catch(err=>{
      console.log('receiveAirdrop_err',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      __ownInstance__.$store.commit('setPositionFinish',true)

      reject(err)
    })
  })
}

// 获取positionH2O奖励

export const getPositionRewardH2O = function (pId){
  console.log('getPositionReward' , pId);
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.fairLaunchAddress;
  const contract = new web3.eth.Contract(fairLaunchABI, contractAddress);
  let encodedABI = contract.methods.harvest(pId).encodeABI();

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
        // gas: web3.utils.toHex(800000), // Optional
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
      console.log('getPositionRewardH2O',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}

export const getPositionRewardBNB= function (Goblin){
  console.log('getPositionRewardBNB' );
  const address = __ownInstance__.$store.state.base.address;
  const contract = new web3.eth.Contract(goblinMDEXABI, Goblin);
  let encodedABI = contract.methods.harvest().encodeABI();

  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      let estimateGas = await web3.eth.estimateGas({
        from: address,
        to: Goblin, // 池地址
        data: encodedABI, // Required
      })
      console.log('estimateGas' ,estimateGas)
      const params = [{
        from: address,
        to: Goblin, // 池地址
        data: encodedABI, // Required
        gasPrice: web3.utils.toHex(gasPrice), // Optional
        gas: web3.utils.toHex(estimateGas), // Optional
        // gas: web3.utils.toHex(800000), // Optional
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
      console.log('getPositionRewardH2O',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}


// stake领取H2O奖励


export const getStakeRewardH2O = function (pId){
  console.log('getStakeRewardH2O' , pId);
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.fairLaunchAddress;
  const contract = new web3.eth.Contract(fairLaunchABI, contractAddress);
  let encodedABI = contract.methods.harvestStake(pId).encodeABI();

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
        // gas: web3.utils.toHex(800000), // Optional
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
      console.log('getStakeRewardH2O',err)
      __ownInstance__.$store.dispatch('changeTradeStatus' , {  id:timestamp , val:2, hash:hashInfo})
      reject(err)
    })
  })
}

// Pools 存款存入
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

// Pools 提取
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

// 盲盒认购
export const H2OToBlindbox = function (boxId=0, decimals=18){
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.blindBoxAddress;
  const contract = new web3.eth.Contract(blindBoxABI, contractAddress);
  // const depositAmount = toWei(amount, decimals)
  let encodedABI = contract.methods.H2OToBlindbox(boxId).encodeABI();

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

// 算力币购买
export const BuyTokenToS19 = function (amount=0, decimals=18) {
  // console.log(amount);
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.hashpowerAddress;
  const contract = new web3.eth.Contract(hashpowerABI, contractAddress);
  const depositAmount = toWei(amount, decimals);
  let encodedABI = contract.methods.BuyTokenToS19(depositAmount).encodeABI();

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

/**
 * 游戏系统-充提清算系统-充值
 * @param {*} gTokenAmt 充值的数量
 * @param {*} buyToken 提的Token地址
 * @param {*} decimals 长度
 * @returns 
 */
export const gamesBuyTokenTogToken = function (gTokenAmt=0, buyToken='', decimals=18) {
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
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
            //开始修改用户充提状态为充提进行中
            saveNotifyStatus(1);
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
 * @returns 
 */
export const gamesGTokenToBuyToken = function (gTokenAmt=0, buyToken='', decimals=18) {
  // const tokenAddress = __ownInstance__.$store.state.base.tokenAddress
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.gamesFillingAddress;
  const contract = new web3.eth.Contract(gameFillingABI, contractAddress);
  const withdrawAmount = toWei(gTokenAmt, decimals);
  let encodedABI = contract.methods.gTokenToBuyToken(withdrawAmount, buyToken).encodeABI();
  let value = toWei('0.00201', decimals);

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
        .on('transactionHash', function (hash) {
          console.log('hash', hash);
          if (hash) {
            hashInfo = hash
            //开始修改用户充提状态为充提进行中
            saveNotifyStatus(1);
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
 * 领取空投并且建立推荐关系 - 添加白名单
 * @param {*} recommand 白名单地址
 * @returns 
 */
 export const setairWhitelist = function (recommand){
  const address = __ownInstance__.$store.state.base.address;
  const contractAddress = __ownInstance__.$store.state.base.cakeH2oAirdropAddress;
  const contract = new web3.eth.Contract(airdropABIs, contractAddress);
  // console.log(contract);
  let encodedABI = contract.methods.setairWhitelist(recommand, true).encodeABI();
  let timestamp = new Date().getTime().toString()
  __ownInstance__.$store.dispatch('createOrderForm' , {val:0 ,id:timestamp })
  return new Promise((resolve, reject) => {
    let hashInfo
    web3.eth.getTransactionCount(address).then(async transactionNonce => {
      let gasPrice = await web3.eth.getGasPrice();
      // let estimateGas = await web3.eth.estimateGas({
      //   from: address,
      //   to: contractAddress, // 池地址
      //   data: encodedABI, // Required
      // });
      // let newEstimateGas = Number(estimateGas) + (Number(estimateGas) * 0.2)
      let estimateGas = 1332418
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
          __ownInstance__.$store.dispatch('getAirdropValue')
          __ownInstance__.$store.dispatch('getAirDropDrawed')
          __ownInstance__.$store.dispatch('changeTradeStatus' , { id:timestamp , val:1 ,  hash:hashInfo})
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

