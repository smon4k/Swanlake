<template>
    <div 
        class="container"
    >
        <el-row class="main">
            <el-col :span="24">
                <el-tabs v-model="activeName" @click="handleClick" type="border-card" :stretch="true">
                    <el-tab-pane label="存入" name="1" :disabled="trading" :style="{padding: isMobel ? '0' : '50px'}">
                        <el-row class="balance">
                            <el-col :span="24">
                                <div>
                                    <!-- <span>平台余额：{{ Math.trunc(Number(localBalance) + Number(walletBalance)) }} USDT</span> -->
                                    <span>平台余额：{{ toFixed(Number(localBalance) + Number(walletBalance), 4) }} USDT</span>
                                    <br />
                                    <span>钱包余额：{{ toFixed(Number(usdtBalance), 4) }} USDT</span>
                                    <!-- <span>GS Balance：{{localBalance}}</span> -->
                                    <!-- <span v-else>GS Balance：<el-skeleton-item variant="text" style="width: 5%;" /></span> -->
                                </div>
                            </el-col>
                            <!-- <el-col :span="24">
                                <div>
                                    <div>
                                        <span v-if="!isStatus && !isWithdraw">CS Balance：{{walletBalance}}</span>
                                        <span v-else>
                                            CS Balance：<span v-loading="true"></span>
                                            <span style="font-size:10px;color:#909399;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;The transfer speed on the chain is slow, please be patient for 5 minutes. .</span>
                                        </span>
                                    </div>
                                </div>
                            </el-col> -->
                        </el-row>
                        <el-row>
                            <el-col :span="24">
                                <el-form :model="depositForm" ref="depositForm">
                                    <el-input 
                                        type="number" 
                                        label="H2O:" 
                                        v-model="depositForm.amount" 
                                        placeholder="请输入充值金额" 
                                        onkeypress="return(/[\d]/.test(String.fromCharCode(event.keyCode)))" 
                                        :rules="[{ validator: checkDepositAmount, message: '请输入正确内容' }]"
                                        >
                                            <template slot="prepend">USDT</template>
                                        </el-input>
                                    <el-row class="button-amount">
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(100)">100</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(200)">200</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(500)">500</el-button>
                                        </el-col>
                                    </el-row>
                                    <el-row class="button-amount">
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(1000)">1000</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(2000)">2000</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(50000)">50000</el-button>
                                        </el-col>
                                    </el-row>
                                    <div class="submit-name">
                                        <el-button type="primary" :loading="trading" :disabled="trading" @click="startApprove" v-if="!approve">批准</el-button>
                                        <el-button type="primary" :loading="trading" :disabled="trading || isStatus || isWithdraw" @click="submitForm('depositForm')" v-else>存入</el-button>
                                        <!-- <el-button @click="resetForm('depositForm')">Cancel</el-button> -->
                                    </div>
                                </el-form>
                            </el-col>
                        </el-row>
                    </el-tab-pane>
                    <el-tab-pane label="提取" name="2" :disabled="trading" :style="{padding: isMobel ? '0' : '50px'}">
                        <el-row class="balance">
                            <el-col :span="24">
                                <div>
                                    <span>平台余额：{{ toFixed(Number(localBalance) + Number(walletBalance), 4) }} USDT</span>
                                    <br />
                                    <span>钱包余额：{{ toFixed(Number(usdtBalance), 4) }} USDT</span>
                                    <!-- <span>GS Balance：{{localBalance}}</span> -->
                                </div>
                            </el-col>
                            <!-- <el-col :span="24">
                                <div>
                                    <span v-if="!isStatus && !isWithdraw">CS Balance：{{walletBalance}}</span>
                                    <span v-else>
                                        CS Balance：<span :loading="true"></span>
                                        <span style="font-size:10px;color:#909399;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;The transfer speed on the chain is slow, please be patient for 5 minutes. .</span>
                                    </span>
                                </div>
                            </el-col> -->
                        </el-row>
                        <el-row>
                            <el-col :span="24">
                                <el-form  :model="withdrawForm" ref="withdrawForm">
                                    <el-input 
                                        label="H2O:" 
                                        type="number" 
                                        v-model="withdrawForm.amount" 
                                        placeholder="请输入金额" 
                                        min="0" 
                                        :max="maxWithdrawableBalance()" 
                                        onkeypress="return(/[\d]/.test(String.fromCharCode(event.keyCode)))" 
                                        :rules="[{ validator: checkWithdrawalAmount, message: '请输入正确内容' }]"
                                    >
                                        <template slot="prepend">USDT</template>
                                    </el-input>
                                    <el-row class="button-amount">
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(100)">100</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(200)">200</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(500)">500</el-button>
                                        </el-col>
                                    </el-row>
                                    <el-row class="button-amount">
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(1000)">1000</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(2000)">2000</el-button>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-button type="primary" plain @click="buttonAmount(50000)">50000</el-button>
                                        </el-col>
                                    </el-row>
                                    <div class="submit-name">
                                        <el-button type="primary" :loading="trading" :disabled="trading" @click="startApprove" v-if="!approve">批准</el-button>
                                        <el-button type="primary" :loading="trading" :disabled="trading || isStatus || isGame || isWithdraw" @click="submitForm('withdrawForm')" v-else>提取</el-button>
                                        <!-- <el-button @click="resetForm('withdrawForm')">Cancel</el-button> -->
                                    </div>
                                </el-form>
                            </el-col>
                        </el-row>
                    </el-tab-pane>
                </el-tabs>
            </el-col>
        </el-row>
        <!-- <el-overlay :show="trading">
            <div class="wrapper">
                <el-loading type="spinner" color="#1989fa" />
            </div>
        </el-overlay> -->
    </div>
</template>
<script>
import { get, post } from "@/common/axios.js";
import { mapState } from "vuex";
import { approve, gamesBuyTokenTogToken, gamesGTokenToBuyToken } from "@/wallet/trade";
import {getBalance,isApproved, getGameFillingBalance, saveNotifyStatus, getGameFillingWithdrawStatus, setDepWithdrawStatus} from "@/wallet/serve";
import { keepDecimalNotRounding } from "@/utils/tools";
import Address from '@/wallet/address.json'
export default {
  name: "Index",
  data() {
    return {
        activeName: "1",
        localBalance: 0, //本地余额
        walletBalance: 0, //清算余额
        isStatus: false, //是否可以充提 0：可以充提 1：不可以充提
        isGame: false, //是否打赏中 true： 打赏中 false：不在打赏中
        isGameInfoNum: 0, //正在打赏中 提示次数
        isWithdraw: false, //是否充提进行中
        approve: false,
        trading: false,
        buttonAmountNum: 0, //按钮选择额度值
        usdtBalance: 0,
        depositForm: {
            amount: '',
        },
        withdrawForm: {
            amount: '',
        },
        balanceTimeInterval: null,
        refreshTime: 5000, //数据刷新间隔时间
    };
  },
  activated() { //页面进来
    this.refreshData();
    // this.getUserInfo();
  },
  beforeRouteLeave(to, from, next){ //页面离开
      if (this.balanceTimeInterval) {
        clearInterval(this.balanceTimeInterval);
          this.balanceTimeInterval = null;
      }
      next();
  },
  created() {
  },
  watch: {
        isConnected: {
            immediate: true,
            async handler(val) {
                if (val && !this.approve) {
                    // this.getIsApprove();
                }
            },
        },
        isConnected: {
            immediate: true,
            async handler(val){
                if(val) {
                    await this.getIsApprove();
                    await this.getUserInfo();
                    await this.refreshData();
                }
            }
        }
  },
  computed: {
      ...mapState({
        isConnected:state=>state.base.isConnected,
        address:state=>state.base.address,
        gamesFillingAddress:state=>state.base.gamesFillingAddress,
        apiUrl:state=>state.base.apiUrl,
        userId:state=>state.base.userId,
        isMobel:state=>state.comps.isMobel,
    }),
    changeData() {
      const {apiUrl,address} = this
      return {
        apiUrl, address
      };
    }
  },
  methods: {
    checkDepositAmount(value) { //充值输入框验证
        console.log(value);
        if (!value) {
            return false;
        }
        let num = this.usdtBalance;
        if(Number(value) > num) {
            return false;
        } else {
            return true;
        }
    },
    checkWithdrawalAmount(value) { //提取输入验证
        console.log(value);
        if (!value) {
            return false;
        }
        let num = this.maxWithdrawableBalance();
        if(Number(value) > num) {
            return false
        } else {
            return true
        }
    },
    pageSwitchChange(evt, hidden) { //浏览器页面 切换事件
        //hidden为false的时候，表示从别的页面切换回当前页面
        //hidden为true的时候，表示从当前页面切换到别的页面
        if(hidden === false) { //页面切换进来
            this.refreshData();
            this.getUserInfo();
        } else { //页面切换离开
            if (this.balanceTimeInterval) {
                clearInterval(this.balanceTimeInterval);
                this.balanceTimeInterval = null;
            }
        }
    },
    async refreshData() {
        // this.walletBalance = await getGameFillingBalance(); //获取余额
        this.timeInterval = setInterval(async() => {
            this.walletBalance = await getGameFillingBalance(); //获取余额
            this.usdtBalance = await getBalance(Address.BUSDT, 18); //获取H2O余额
            // console.log(this.walletBalance);
        }, this.refreshTime)
    },
    maxWithdrawableBalance() {
        let num = Number(this.walletBalance) + Number(this.localBalance);
        return num;
    },
    async handleClick(tab, event) {
    //   if(tab.name == 2) { //提取的话 检测是否在打赏中 提示信息
    //     await this.getIsInTheGame();
    //   }
      this.depositForm.amount = '';
      this.withdrawForm.amount = '';
    },
    async getIsApprove() { //获取余额 查看是否授权
      let balance = await getBalance(Address.BUSDT, 18); //获取余额
      console.log("USDT balance", balance);
      this.tokenBalance = balance;
      isApproved(Address.BUSDT, 18, balance, this.gamesFillingAddress).then((bool) => {
        console.log("isApprove", bool);
        this.approve = bool ? true : false;
      });
    },
    startApprove() { //批准H2O
        const loading = this.$loading({
          lock: true,
          text: 'transaction in progress',
          spinner: 'el-icon-loading',
          background: 'rgba(0, 0, 0, 0.7)'
        });
      this.trading = true;
      approve(Address.BUSDT, this.gamesFillingAddress).then((hash) => {
        // console.log(result);
        loading.close();
        if(hash) {
          this.approve = true;
          this.trading = false;
        }
      }).finally(() => {
        loading.close();
        this.trading = false;
      });
    },
    async submitForm(formName) {//1. 提交调用合约
        // console.log(this.$refs[formName]);
        this.$refs[formName].validate(async (valid) => {
          if (valid) {
            const loading = this.$loading({
                lock: true,
                text: 'transaction in progress',
                spinner: 'el-icon-loading',
                background: 'rgba(0, 0, 0, 0.7)'
            });
            this.trading = true;
            let amount = 0;
            let contractName = '';
            //检测是否有正在执行中的交易
            await this.getIsInTradeProgress();
            // console.log(isInProgress);
            if(this.isStatus == 1 || this.isWithdraw) {
                this.trading = false;
                return false;
            }
            if(this.activeName == 1) {//充值
                amount = this.depositForm.amount;
                contractName = gamesBuyTokenTogToken;
            } else { //提取
                // await this.getIsInTheGame(); //检测是否在打赏中 提示信息
                if(this.isGame) {
                    this.trading = false;
                    return false;
                }
                amount = this.withdrawForm.amount;
                contractName = gamesGTokenToBuyToken;
            }
            // let balance = await getGameFillingBalance();
            // let balance = await this.getGameFillingBalanceFun(this.activeName, amount);
            // console.log(balance);
            // saveNotifyStatus(1);
            // return false;
            //请求合约 充值或者提取
            // let hash = '0x837d2bae18716363a133662bdf8e935d294a1eec6efb147b2537ba0885cf4e87';
            // if(this.activeName == 1) {//充值的话 二次检测是否充值成功
            //     await this.setDepositWithdraw(amount, hash);
            //     await this.getGameFillingBalanceFun(this.activeName, amount, hash);
            // } else { //提取的话 不二次检测是否充值成功 异步机器人扣除 这里直接写入数据库记录
            //     await this.setDepositWithdraw(amount, hash);
            //     this.trading = false;
            //     this.depositForm.amount = '';
            //     this.withdrawForm.amount = '';
            //     // this.resetForm('depositForm');
            //     // this.resetForm('withdrawForm');
            // }
            const fillingRecordParams = { //充提记录参数
                amount: Number(amount),
                address: this.address,
                userId: this.userId,
                type: Number(this.activeName),
                local_balance: this.localBalance,
                wallet_balance: this.walletBalance,
                hash: '',
                source: 1, //渠道： 1：天鹅湖 2：短视频 3：一站到底
            };
            contractName(amount, Address.BUSDT, 18, fillingRecordParams).then(async (hash) => {
                loading.close();
                if(hash) {
                    if(this.activeName == 1) {//充值的话 二次检测是否充值成功
                        // await this.setDepositWithdraw(amount, hash);
                        saveNotifyStatus(0, true);
                        await this.getGameFillingBalanceFun(this.activeName, hash);
                    } else { //提取的话 不二次检测是否充值成功 异步机器人扣除 这里直接写入数据库记录
                        // await this.setDepositWithdraw(amount, hash);
                        // this.trading = false;
                        saveNotifyStatus(0, false); //提取的话 这里不通知GS获取余额
                        this.resetForm('depositForm');
                        this.resetForm('withdrawForm');
                    }
                }
            }).finally(() => {
                loading.close();
                // saveNotifyStatus(0);
                this.trading = false;
            });
          } else {
            console.log('error submit!!');
            return false;
          }
        });
    },
    async setDepositWithdraw(amount, hash='') { // 记录数据库记录
        // let amount = 0;
        // if(this.activeName == 1) {
        //     amount = this.depositForm.amount;
        // } else {
        //     amount = this.withdrawForm.amount;
        // }
        post(this.apiUrl + '/Api/Depositwithdrawal/depositWithdraw', {
            amount: Number(amount),
            address: this.address,
            userId: this.userId,
            type: Number(this.activeName),
            local_balance: this.localBalance,
            wallet_balance: this.walletBalance,
            hash: hash,
            source: 1, //渠道： 1：天鹅湖 2：短视频 3：一站到底
        }, (json) => {
            if (json && json.code == 10000) {
                this.getUserInfo(true);
                if(this.activeName == 1) {
                    //开始修改用户充提状态为充提进行中 通知GS获取余额
                    saveNotifyStatus(0, true);
                } else {
                    saveNotifyStatus(0, false); //提取的话 这里不通知GS获取余额
                }
                this.trading = false;
                this.$notify({ type: 'success', message: this.activeName == 1 ? '存款成功' : '提取成功' });
            } else {
                this.trading = false;
                this.$notify({ type: 'warning', message: error });
            }
            return;
        });
    },
    async getGameFillingBalanceFun(deWithId, hash) { //2. 获取合约中充提绝对余额 检测是否充提成功
        // let repeat = 5;
        // let actualBalance = 0;
        // if(type == 1) {
        //     actualBalance = Number(this.localBalance) + Number(this.walletBalance) + Number(amount)
        // } else {
        //     actualBalance = Number(this.localBalance) + Number(this.walletBalance) - Number(amount)
        // }
        let depositTimer = setInterval(async () => {
            let receipt = await web3.eth.getTransactionReceipt(hash);
            console.log(receipt);
            // let balance = await getGameFillingBalance();
            // console.log(actualBalance, balance);
            // if(repeat <= 0 || actualBalance == balance) {
            if(receipt && receipt.status) {
                await setDepWithdrawStatus(deWithId, 2, true); //修改充值状态为已完成 并通知GS获取余额
                // this.trading = false;
                // saveNotifyStatus(0, true);
                this.trading = false;
                this.isWithdraw = false;
                this.isStatus = false;
                // this.resetForm('depositForm');
                // this.resetForm('withdrawForm');
                clearInterval(depositTimer);
            }
            // if(repeat <= 0) {
            // }
            // repeat--;
        }, 3000);
    },
    timeWithdrawStatus(withdrawId) { //如果充提进行中 监听充提状态是否执行完成以通知GS更新余额
        let withdrawTimer = setInterval(async () => {
            if(withdrawId) {
                let withdrawStatus = await getGameFillingWithdrawStatus(withdrawId);
                console.log(withdrawStatus);
                if(withdrawStatus) {
                    // await this.getUserInfo(true); //更新用户信息
                    clearInterval(withdrawTimer);
                    await saveNotifyStatus(0, true); //通知GS更新余额
                    setTimeout(async () => {
                        this.walletBalance = await getGameFillingBalance(); //重新获取一次余额
                        this.isWithdraw = false;
                        this.isStatus = false;
                    }, 300) //停2秒
                }
            }
        }, 5000);
    },
    resetForm(formName) {
        this.$refs[formName].resetFields();
        this.trading = false;
    },
    allBlanceFun() { //全部余额
        if(this.localBalance > 0 || this.walletBalance > 0) {
            this.withdrawForm.amount = Number(this.localBalance) + Number(this.walletBalance);
            return true;
        }
        return false;
    },
    allWalletBlanceFun() { //全部钱包余额
        if(this.usdtBalance > 0) {
            this.depositForm.amount = Math.trunc(this.usdtBalance);
            return true;
        }
        return false;
    },
    async getUserInfo(isHint=false) { //获取用户信息
        get(this.apiUrl + "/Api/Depositwithdrawal/getFillingRecordUserInfo", {
            address: this.address,
        }, async json => {
            console.log(json);
            if (json.code == 10000) {
                this.localBalance = keepDecimalNotRounding(json.data.local_balance, 4, true);
                // this.walletBalance = json.data.walletBalance;
                this.isGame = json.data.isGame;
                if(!isHint && json.data.isGame) {
                    this.$notify({
                        showClose: true,
                        message: '正在打赏中，无法进行操作', //正在打赏中
                        type: 'warning'
                    });
                }
                console.log('是否打赏中：', this.isGame);
                this.walletBalance = await getGameFillingBalance(); //获取合约余额
                console.log('链上余额：', this.walletBalance);
                this.isStatus = json.data.dw_status == 1 ? true : false;
                this.isWithdraw = json.data.isDeWith; //是否充提中
                console.log('是否充提中：', this.isStatus, this.isWithdraw);
                if(json.data.dw_status == 1 || json.data.isDeWith) { //有交易正在执行中 不能进行充提操作
                    if(!isHint) {
                        this.$notify({
                            showClose: true,
                            message: '有一笔交易正在进行，无法进行存取款操作',
                            type: 'warning'
                        });
                    }
                    if(json.data.isDeWith > 0) { //如果有提取 进行中的任务 监听任务是否完成
                        if(json.data.isDeWithType == 1) {
                            this.getGameFillingBalanceFun(json.data.isDeWithStatusId, json.data.isDeWithHash);
                        } else {
                            this.timeWithdrawStatus(json.data.isDeWithStatusId);
                        }
                    }
                }
                this.usdtBalance = await getBalance(Address.BUSDT, 18); //获取H2O余额
                console.log('USDT 余额：', this.usdtBalance);
            } else {
                console.log("get Data error");
            }
        })
    },
    async getIsInTheGame() { //获取是否打赏中 调用GS第三方接口获取 暂时不使用
        get(this.apiUrl + "/Api/Depositwithdrawal/getIsGameOrNot", {
            params: {
              address: this.address
            }
        }, async (json) => {
            // console.log(json);
            if (json.code == 10000) {
                this.isGame = json.data;
                if(json.data) {
                    this.isGameInfoNum ++;
                    if(this.isGameInfoNum <= 3) {
                        this.$notify({
                            showClose: true,
                            message: 'The game is in progress and the operation cannot be retrieved', //正在打赏中
                            type: 'warning'
                        });
                    }
                    return false;
                } else {
                    this.isGameInfoNum = 0;
                    return true;
                }
            } else {
                console.log("get Data error");
            }
            return false;
        })
    },
    async getIsInTradeProgress() { //实时 获取是否有交易正在进行中
        get(this.apiUrl + "/Api/Depositwithdrawal/getIsInTradeProgress", {
            address: this.address,
            userId: this.userId,
        }, async (json) => {
            console.log(json);
            if (json.code == 10000) {
                this.isStatus = json.data.status;
                this.isWithdraw = json.data.isWithdraw;
                if(json.data.status == 1 || json.data.isWithdraw) { //有交易正在执行中 不能进行充提操作
                    this.$notify({
                        showClose: true,
                        message: '有一笔交易正在进行，无法进行存取款操作',
                        type: 'warning'
                    });
                    return false;
                } else {
                    return true;
                }
            } else {
                console.log("get Data error");
            }
            return false;
        });
    },
    buttonAmount(value) { //选中按钮选择数量
        if(value) {
            if(this.activeName == 1) {//充值
                this.depositForm.amount = value;
            } else {
                this.withdrawForm.amount = value;
            }
        }
    },
    onClickSave() { //编辑资料
        this.$router.go(-1);
    },
  },
};
</script>
<style>
    .el-loading-mask {
        border-radius: 0 !important; 
    }
</style>
<style lang="scss" scoped>
.container {
    /deep/ {
        div {
            min-height: 0;
        }
        input[type=number] {
            -moz-appearance:textfield;
        }
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        // height: 100vh;
        .main {
            border-radius: 20px !important;
            // background-color: #fff !important;
            // width: 80%;
            height: 80vh;
            // padding: 20px;
            text-align: center;
            margin: 0 auto;
            // margin-top: 35px;
            .el-form {
                margin-top: 15px;
            }
            .el-form-item {
                // height: 50px;
            }
            .el-tabs__content {
                // padding: 50px;
            }
            .el-tabs__item {
                height: 60px;
                line-height: 60px;
                font-size: 16px;
                font-weight: 800;
                // color: #fff;
                // @include mainFont($color-mainFont-light);
            }
            .el-tabs__item.is-active {
                color: #409EFF;
            }
            .el-loading-spinner {
                margin-top: -11px;
                .circular {
                    width: 20px;
                    height: 20px;
                }
            }
            .el-form-item__label {
                color: #fff;
                // @include mainFont($color-mainFont-light);
            }
            .el-input {
                // background-color: #333257;
                // color: #fff;
                // border: 1px solid #333257;
                // padding: 0 10px;
                font-size: 16px;
                .el-input__label {
                    width: 50px;
                    margin-right: 0;
                    // text-align: right;
                }
            }
            .el-input__inner {
                // background-color: #333257;
                // color: #fff;
                // border: 1px solid #333257;
                padding: 10px;
                height: 50px;
                line-height: 50px;
                font-size: 16px;
            }
            .el-input-group__prepend {
                background-color: #F5F7FA !important;
                color: #333 !important;
                border: 1px solid #DCDFE6 !important;
            }
            .el-input-group__append button.el-button {
                color: #409EFF;
            }
            .el-input-group__append, .el-input-group__prepend {
                background-color: #333257;
                color: #fff;
                border: 1px solid #333257;
                // padding: 0;
            }
            .balance {
                text-align: left;
                height: 30px;
                line-height: 30px;
                // color: #fff;
                // @include mainFont($color-mainFont-light);
            }
            .el-button {
                border-radius: 30px;
                width: 100px;
                border: 0;
            }
            .button-amount {
                margin-top: 30px;
                line-height: 30px;
                .el-button--primary.is-plain {
                    background: #3ab293;
                    color: #fff;
                }
                .el-button {
                    border-radius: 5px;
                }
                .el-button::after {
                    background-color: #409EFF !important;
                }
                .el-button:focus,.el-button:hover {
                    background-color: #409EFF !important;
                    color: #fff;
                }
            }
            .submit-name {
                margin-top: 30px;
                .el-button--primary {
                    width: 200px;
                    height: 50px;
                    border-radius: 50px;
                    background-color: #409EFF;
                    border: 0;
                }
                .el-button--primary.is-disabled {
                    background: #c8c9cc;
                    color: #fff;
                }
                .el-loading-mask {
                    border-radius: 5px;
                    background-color: rgba(0,0,0,0.8);
                    border: 0;
                }
            }
        }
        .wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
    }
}
</style>
