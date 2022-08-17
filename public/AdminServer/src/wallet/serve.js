import mdexABI from './abis/mdexABI.json'

//获取估值
// export const getSwapPoolsAmountsOut = async function (routerContractAddress, tk0Address, tk1Address, bnbAddress) {
//   // console.log(oracleContractAddress, tk0Address, tk1Address);
//   const contract = new web3.eth.Contract(mdexABI, routerContractAddress);
//   let amountsOut = 0;
//   let path = [];
//   if(bnbAddress && bnbAddress !== '') {
//     path = [tk0Address, bnbAddress, tk1Address];
//   } else {
//     path = [tk0Address, tk1Address];
//   }
//   const Gwei1 = 1000000000;
//   await contract.methods.getAmountsOut(Gwei1, path).call(function (error, result) {
//     if (!error) {
//       // console.log(result);
//       if(bnbAddress && bnbAddress !== '') {
//         amountsOut = result[2] ? result[2] / Gwei1 : 0;
//       } else {
//         amountsOut = result[1] ? result[1] / Gwei1 : 0;
//       }
//     }else {
//       console.log('getAmountsOutErr' , error);
//     }
//   });
//   return amountsOut;
// }