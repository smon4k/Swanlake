import router from '@/router'
import { getUserAddressInfo, getPoolBtcData, getHashPowerPoolsTokensData, getHashpowerDetail } from '@/wallet/serve'
import {
    deepCopy,
    fromWei,
    toolNumber,
    numberFormat,
    accordingToAprSeekApy,
    calcDaily
} from '@/utils/tools'
import hashPoolsList from '@/wallet/hashpower_pools.js'
import Address from '@/wallet/address.json'
import Web3 from 'web3'
import Vue from 'vue'
import { keepDecimalNotRounding } from "@/utils/tools";
let copyBaseState;







export default {
    state :{
        address:'',
        isConnected:false,
        accounts:'',
        userId: 0, //用户ID
        userInfo: {}, //用户ID
        isAdmin: false, //是否管理员
        chainId:'',
        tradeStatus:{
            current:"",
            list:[],
            SuccessHash:'',
            FailedHash:'',
            userDenyId:''
        },
        domainHostAddress:'https://bscscan.com/tx/',
        apiUrl: window.location.host === 'localhost:8008' ? 'http://www.swan.com' : 'https://www.swanlake.club',
        // apiUrl: 'https://www.swanlake.club',
        nftUrl: window.location.host === 'localhost:8008' || window.location.host === '192.168.1.3:8007' ? 'http://www.api.com' : 'https://api.bitguru.finance',
        // nftUrl: 'https://api.h2o.live',
        // Env: window.location.host === 'localhost:8001' || window.location.host === '192.168.1.6:8001' ? 'dev' : 'prod',
        Env: 'dev',
        // gamesFillingAddress: '0x079bDC8845D0C6878716A3f5219f1D0DcdF15308', //游戏系统-充提清算系统-合约地址
        gamesFillingAddress: '0xB433036377478dD94f94e4467C14835438b648Db', //游戏系统-充提清算系统-合约地址-升级增加订单id
        btcbFillingAddress: '0x92403c4dddC9275C186d1aFBE2ff64D003aa13f1', //算力币短期租赁-BTCB充提池子合约地址
        hashpowerAddress: '0x5fE319Cad2B7203891AC9a9536A4a054636A2340', //算力币合约地址 测试
        fairLaunchAddress:'0x08D027B330F3A8ace9290235D575475150EA14Ff', //_launch
        hashPowerPoolsList: [],
        totalPledgePower: 0, //总的质押算力
    },
    mutations: {
        isConnected(state , val ){
            state.isConnected = val
        },
        disconnect(state){
          for(let key in state){
            if(key === 'accounts'){
              state[key] = ''
            }else [
              state[key] = copyBaseState[key]
            ]
          }
          console.log('state',state)
          
        },
        copyDefaultState(state){
            copyBaseState = deepCopy(state)
        },
        getChainId(state , value) {
            state.chainId = value;
        },
        async getAddress(state, value) { //设置钱包地址
            state.address = value;
            let userInfo = await getUserAddressInfo(value);
            console.log(userInfo)
            if(userInfo) {
                state.userInfo = userInfo;
                state.isAdmin = userInfo.is_admin;
            }
        },  
        async removeAddress(state) {
            state.address = '';
            state.userId = 0;
        },
        getAccounts(state, value) {
            state.accounts = value;
        },
        setUserInfo(state, value) {
            state.userInfo = value;
            state.userId = value.uid;
            if(value.address && value.address !== '') {
                state.address = value.address;
            }
            console.log(value);
        },
        createOrder(state , {id , val}){
            state.tradeStatus.list.push({id , val })
            state.tradeStatus.current = id
        },
        change_TradeStatus(state , {id , val , isUserDeny , hash , errMessage}){
            if(!id) return 
            let index = state.tradeStatus.list.findIndex(item=>{
                return item.id === id
            })
            console.log(id, errMessage);
            if(errMessage){
                state.errMessage = errMessage
            }
            if(index !== -1){
                state.tradeStatus.list[index].val = val 
                state.tradeStatus.list[index].hash = hash // 1成功 2失败

                if(val === 1){
                    state.tradeStatus.SuccessHash = hash
                }else {
                    state.tradeStatus.list[index].isUserDeny = isUserDeny
                    if(isUserDeny){
                        state.tradeStatus.userDenyId = id
                        console.log('userDenyId' , state.tradeStatus.userDenyId);
                    }
                    state.tradeStatus.FailedHash = hash || id
                }
            }
        },
        setHashPowerPoolsList(state, {fixed}){
            state.hashPowerPoolsList = [];
            // console.log(fixed);
            if(fixed.length > 0) {
                fixed.forEach(async item => {
                    state.hashPowerPoolsList.push({
                        id: item.id,
                        hashpowerAddress: item.hashpowerAddress,
                        currencyToken: item.currencyToken,
                        goblin: item.goblin,
                        address:item.originToken,
                        pId:item.pId,
                        name:item.name,
                        decimals:18,
                        balance:'',
                        reward:'',
                        total:'',
                        tokenPrice: 0,
                        yearPer: 0,
                        h2oYearPer: 0,
                        btcbYearPer: 0,
                        btcb19ProBalance: 0,
                        cost_revenue: 0,
                        annualized_income: 0,
                        daily_income: 0,
                        harvest_btcb_amount: 0,
                        yest_income_usdt: 0,
                        yest_income_btcb: 0,
                        total_income_usdt: 0,
                        total_income_btcb: 0,
                        daily_expenditure_usdt: 0,
                        daily_expenditure_btc: 0,
                        daily_income_usdt: 0,
                        daily_income_btc: 0,
                        power_consumption_ratio: 0,
                        currency: 0,
                        loading:false,
                        btcbPrice: 0,
                        h2oPrice: 0,
                        chain_address: '',
                        yest_income_h2o: 0,
                        yest_income_h2ousdt: 0,
                        yest_total_income: 0,
                        yest_total_incomerate: 0,
                        annualized_rate: 0,
                        claimLoading:false
                    })
                })
            }
            state.hashPowerPoolsList.loading = false
        },
        setHashPowerPoolsListLoading(state, val){
            state.hashPowerPoolsList.loading = val
        },
        setHashPowerPoolsBalance(state, info) {
            if(!state.hashPowerPoolsList) return 
            let index = state.hashPowerPoolsList.findIndex(item=>item.goblin === info.t)
            if(index !== -1 ){
                state.hashPowerPoolsList[index].tokenPrice = info.tokenPrice
                state.hashPowerPoolsList[index].btcbPrice = info.btcbPrice
                state.hashPowerPoolsList[index].h2oPrice = info.h2oPrice
                state.hashPowerPoolsList[index].balance = info.userBalance
                state.hashPowerPoolsList[index].total = info.totalTvl
                state.hashPowerPoolsList[index].btcbReward = info.btcbReward
                state.hashPowerPoolsList[index].h2oReward = info.h2oReward
                state.hashPowerPoolsList[index].reward = info.reward
                state.hashPowerPoolsList[index].yearPer = info.yearPer
                state.hashPowerPoolsList[index].h2oYearPer = info.h2oYearPer
                state.hashPowerPoolsList[index].btcbYearPer = info.btcbYearPer
                state.hashPowerPoolsList[index].btcb19ProBalance = info.btcb19ProBalance
                state.hashPowerPoolsList[index].cost_revenue = info.cost_revenue
                state.hashPowerPoolsList[index].annualized_income = info.annualized_income
                state.hashPowerPoolsList[index].daily_income = info.daily_income
                state.hashPowerPoolsList[index].currency = info.currency
                state.hashPowerPoolsList[index].harvest_btcb_amount = info.harvest_btcb_amount
                state.hashPowerPoolsList[index].yest_income_usdt = info.yest_income_usdt
                state.hashPowerPoolsList[index].yest_income_btcb = info.yest_income_btcb
                state.hashPowerPoolsList[index].total_income_usdt = info.total_income_usdt
                state.hashPowerPoolsList[index].total_income_btcb = info.total_income_btcb
                state.hashPowerPoolsList[index].daily_expenditure_usdt = info.daily_expenditure_usdt
                state.hashPowerPoolsList[index].daily_expenditure_btc = info.daily_expenditure_btc
                state.hashPowerPoolsList[index].daily_income_usdt = info.daily_income_usdt
                state.hashPowerPoolsList[index].daily_income_btc = info.daily_income_btc
                state.hashPowerPoolsList[index].power_consumption_ratio = info.power_consumption_ratio
                state.hashPowerPoolsList[index].chain_address = info.chain_address
                state.hashPowerPoolsList[index].yest_income_h2o = info.yest_income_h2o;
                state.hashPowerPoolsList[index].yest_income_h2ousdt = info.yest_income_h2ousdt;
                state.hashPowerPoolsList[index].yest_total_income = info.yest_total_income;
                state.hashPowerPoolsList[index].yest_total_incomerate = info.yest_total_incomerate;
                state.hashPowerPoolsList[index].annualized_rate = info.annualized_rate;
                state.hashPowerPoolsList[index].loading = false
            }
        },
        sethashPowerPoolsListClaimLoading(state , {goblin , val}){
            let index = state.hashPowerPoolsList.findIndex(item=>item.goblin === goblin)
            if(index !== -1){
                state.hashPowerPoolsList[index].loading = val
            }
        },
    },
    getters:{
        pendingOrderAmount: state=>{
            let filter = state.tradeStatus.list.filter(ele=> ele.val === 0)
            return filter.length
        },
        successOrderAmount: state=>{
            let filter = state.tradeStatus.list.filter(ele=> ele.val === 1)
            return filter.length
        },
        failedOrderAmount: state=>{
            let filter = state.tradeStatus.list.filter(ele=> ele.val === 2)
            return filter.length
        },
        MDEXMintingList:state=>{
            return state.totalMint.list.filter(item=>item.type === 'MDEX')
        },
        PSMintingList:state=>{
            return state.totalMint.list.filter(item=>item.type === 'PS' || item.type === 'HS')
        },
        MDEXPositionList:state=>{
            return state.userPosition.list.filter(item=>item.type === 'MDEX')
        },
        PSPositionList:state=>{
            return state.userPosition.list.filter(item=>item.type === 'PS' || item.type === 'HS')
        }
    },
    actions:{
        async disconnectMetaMask({commit}){
            commit('disconnect')
        },
        async getInviteList({commit}){
            let res = await getOneLevelLists()
            commit('setInviteList'  ,res)
        },
        async getAirdropValue({commit}){
            let res = await getAirdropValue()
            commit('getAirdropBalance'  ,res)
        },
        async getTokenDecimals({commit}){
            let res = await getDecimals()
            if(res){
                commit('setTokenDecimals' , res)
            }
        },
        async getAirDropDrawed({commit}){
            let res = await getAirDropDrawed()
            commit('setAirDropDrawed' , res)
        },
        createOrderForm({commit } , info){
            commit('createOrder' , info)
        },
        changeTradeStatus({commit} , status){
            commit('change_TradeStatus' , status)
        },
        // 获取算力币Pools数据
        async getHashPowerPoolsList({commit , state}, isLoding){
            if(state.hashPowerPoolsList.loading) return 
            if(!isLoding || isLoding == undefined) {
                commit('setHashPowerPoolsListLoading' , true);
            }
            const { fixed } = hashPoolsList;
            if(!isLoding || isLoding == undefined) {
                // console.log(fixed);
                commit('setHashPowerPoolsList' , {fixed} )
            }
            // console.log(fixed);
            if(fixed.length) {
                let poolBtcData = await getPoolBtcData();
                // console.log(poolBtcData);
                let fixedList = [...fixed]
                fixedList.forEach(async item => {
                    // console.log(item);
                    let info = await getHashPowerPoolsTokensData(item.goblin, item.currencyToken, item.pId, item.id);
                    // console.log(info);
                    if(info) {
                        if(info.is_give_income && info.is_give_income > 0) { //大于第一次购买 第二天 给收益
                            let yest_income_usdt = Number(info.userBalance) * Number(info.daily_income); //昨日收益 usdt
                            let yest_income_btcb = keepDecimalNotRounding(Number(info.userBalance) * (Number(info.daily_income) / Number(poolBtcData[0].currency_price))); //昨日收益 btcb
                            info.yest_income_usdt = yest_income_usdt; //昨日BTCB收益转USDT
                            info.yest_income_btcb = yest_income_btcb; //昨日BTCB收益
                            // if(item.id == 2) {
                                let hashpower_price = (Number(info.hashpower_price) / Number(info.hash_rate));
                                let yest_income_h2o =  keepDecimalNotRounding(Number(info.h2o_income_number) * Number(info.userBalance) / Number(info.totalTvl)); //昨日H2O收益
                                let yest_income_h2ousdt =  keepDecimalNotRounding(yest_income_h2o * Number(info.h2oPrice));//昨日H2O收益usdt
                                let yest_total_income = keepDecimalNotRounding(Number(yest_income_usdt) + Number(yest_income_h2ousdt));//昨日总收益
                                let yest_total_incomerate = keepDecimalNotRounding(Number(yest_total_income) / (Number(info.userBalance) * hashpower_price));// 昨日总收益率=昨日总收益/（我的质押*算力币价格；  
                                info.yest_income_h2o = yest_income_h2o;
                                info.yest_income_h2ousdt = yest_income_h2ousdt;
                                info.yest_total_income = yest_total_income;
                                info.yest_total_incomerate = yest_total_incomerate;
                                
                                // let yest_income_h2o_total = Number(info.totalTvl) * Number(info.daily_income); //昨日总质押算力收益 H2O
                                info.annualized_rate = info.annualized_income;
                                if(item.id == 2) {
                                    let btcb_number = keepDecimalNotRounding(Number(info.totalTvl) * Number(info.daily_income_btc)); // BTCB数量 = 总质押算力 * 日收益/T
                                    let h2o_number =  keepDecimalNotRounding(Number(info.h2o_income_number)); //H2O数量
                                    let annualized_rate_xp = keepDecimalNotRounding(((btcb_number * Number(poolBtcData[0].currency_price)) + (h2o_number * Number(info.h2oPrice))) / (Number(info.totalTvl) * hashpower_price));
                                    info.annualized_rate = annualized_rate_xp * 365 * 100;
                                }
                                console.log(info);
                            // }
                        }
                        let countIncome = keepDecimalNotRounding(Number(info.btcbReward) + Number(info.harvest_btcb_amount)); // 总的收益 = 奖励收益数量 + 已收割奖励数量
                        info.total_income_btcb = countIncome; //btcb总收益
                        let total_income_usdt = countIncome * Number(poolBtcData[0].currency_price); //usdt总收益
                        info.total_income_usdt = total_income_usdt;
                        info.t = item.goblin
                        commit('setHashPowerPoolsBalance', info)
                    }
                })
            }
            commit("setHashPowerPoolsListLoading" , false)
            // console.log('state.poolsList' , state.poolsList);
        },
        async refreshHashPowerPoolsList({commit , state , dispatch}){
            console.log('更新算力数据')
            // commit('setUserPositionLoading' , false);
            dispatch('getHashPowerPoolsList', true)
        },
    }
}