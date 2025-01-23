<template>
  <div class="pledge-mining-page">
    <div class="app-container">
        <el-row class="public-info">
          <el-col :span="24" style="float:right;">
              <span>
                  {{ $t('subscribe:MinimumElectricityBill') }}
                  <span>0.68 USDT</span> 
              </span>
              &nbsp;&nbsp;&nbsp;&nbsp;
              <span>
                  {{ $t('subscribe:DailyEarnings') }}/T
                  <span>{{toFixed(Number(poolBtcData.daily_income), 4) || "--"}} USDT</span> 
                  <span>{{ toFixed(poolBtcData.daily_income / poolBtcData.currency_price, 8)}} BTC</span>
              </span>
              &nbsp;&nbsp;&nbsp;&nbsp;
              <span>
                {{ $t('subscribe:Hashrate') }}
                <span> {{toFixed(Number(poolBtcData.power),3) || "--"}} EH/s</span> 
              </span>
              &nbsp;&nbsp;&nbsp;&nbsp;
              <span>
                {{ $t('subscribe:CurrencyPrice') }}
                <span> $ {{toFixed(Number(poolBtcData.currency_price), 2) || "--"}}</span> 
              </span>
        </el-col>
        </el-row>
      <div class="info" v-if="poolBtcData">
          <el-row>
            <el-col :span="24">
              <el-row style="line-height:30px;">
                    <el-col :span="5" align="center">{{ $t('subscribe:outputYesterday') }}<br /> 
                      <span>{{toFixed(Number(yester_output), 2) || "--"}} USDT</span>
                      <br>
                       <span>{{toFixed(Number(yester_output) / Number(poolBtcData.currency_price), 8)}} BTC</span>
                    </el-col>
                    <el-col :span="5" align="center">{{ $t('subscribe:cumulativeOutput') }}<br /> 
                      <span>{{toFixed(Number(count_output), 2) || "--"}} USDT</span>
                      <br>
                      <span>{{toFixed(Number(count_output) / Number(poolBtcData.currency_price), 8) || "--"}} BTC</span>
                    </el-col>
                    <el-col :span="5" align="center">{{ $t('subscribe:EstimatedElectricityCharge') }}/T<br /> 
                    <span>{{ estimatedElectricityCharge(poolBtcData) }} USDT</span>
                    <br>
                    <span>{{ dailyExpenditure(poolBtcData) }} BTC</span>
                  </el-col>
                  <el-col :span="5" align="center">{{ $t('subscribe:NetProfit') }}/T<br /> 
                    <span>{{ netProfit(poolBtcData) }} USDT</span>
                    <br>
                    <span></span>{{ netProfitBtcNumber(poolBtcData) }} BTC
                  </el-col>
                  <el-col :span="4" align="center">{{ $t('subscribe:onlineDays') }}<br /> {{Number(online_days) || "--"}}</el-col>
                </el-row>
                <!-- <el-divider></el-divider> -->
                <!-- <br> -->
            </el-col>
          </el-row>
          <!-- <el-divider></el-divider> -->
        </div>

      <div class="commin-title">
        <div class="title-inner">
          <img src="@/assets/pledge_tit.png" alt="" />
          <span class="tit">{{ $t('subscribe:poolsHeader') }}</span>
          <div 
            :class="[ 'btn' , {'active':activeNav === 'live'}]"
            @click="buyHashpower()">{{ $t('subscribe:BuyBTCS19Pro') }}</div>
            <!-- 
          <div
            :class="['btn',{'active':activeNav === 'ding'}]"
            @click="changeNav('ding')">Time stake</div> -->
        </div>
      </div>
      <div class="app-inner">
        <div class="common-assets-list">
          <div class="head live">
            <div class="kind">{{ $t('public:pool') }}</div>
            <div class="days">{{ $t('public:APR') }}</div>
            <div class="pledge">{{ $t('subscribe:TotalPledgedHashpower') }}</div>
            <div class="deposit">{{ $t('subscribe:MyPledged') }}</div>
            <div class="reward">H2O {{ $t('subscribe:Income') }}</div>
            <div class="reward">BTCB {{ $t('subscribe:Income') }}</div>
            <div class="opera">{{ $t('public:Action') }}</div>
          </div>
          <transition-group name="fade-transform" mode="out-in" tag="div">
            <div class="body" key="live">
              <div
                v-for="(item, index) in fixedList"
                :key="index"
                class="item live"
                v-loading="item.loading"
              >
                <div class="kind">
                  <div>
                    <img :src="currentTokenImage('H2O')" alt="" />
                    <span>{{ item.name }}</span>
                  </div>
                </div>
                <div class="return-rate">
                  <div style="line-height: 16px;">
                    <!-- <span class="bold green" v-if="item.pId == 0">{{ toFixed(item.yearPer || 0, 2) }}% <span>(APY)</span></span> -->
                    H2O: <span class="bold green">{{ toFixed(item.h2oYearPer || 0, 2) }}%</span>
                    <br />
                    <font color="#FFEB03">
                      BTCB: <span class="">{{ toFixed(calcBtcIncome() || 0, 2) }}%</span>
                    </font>
                  </div>
                </div>
                <div class="pledge">
                  <p class="bold">{{ toFixed(item.total, 2) || "--" }} T</p>
                </div>
                <div class="deposit">
                  <p>
                    <!-- <span class="bold green">{{ toFixed(Number(item.balance), 6) }}</span> | -->
                    <!-- <span class="bold">${{toFixed(Number(item.balance * item.tokenPrice), 6)}}</span> -->
                    <span class="bold">{{toFixed(Number(item.balance), 2)}} T</span>
                  </p>
                </div>
                <div class="reward">
                  <div>
                    <span class="bold">{{toFixed(Number(item.h2oReward), 4) || "--"}}
                      <!-- <span>{{'H2O'}}</span> -->
                    </span>
                  </div>
                  <template>
                    <!-- <span class="plus"> + </span> -->
                    <!-- <span>{{toFixed(rewardAmount , 6) || '--'}} H2O</span> -->
                    <el-tooltip effect="dark" placement="right" v-show="getIsH2OPools(item.currencyToken)">
                      <div slot="content">
                        <div>
                          <div class="tooltipsItem">
                            <div class="left">
                              <img :src="currentTokenImage('H2O')" alt="" />
                              <span>H2O{{ $t('subscribe:Vesting') }}</span>
                            </div>
                            <div class="right">
                              <span class="amount">{{
                                toFixed(toolTips0(item), 6) || "--"
                              }}</span>
                              <span>{{'H2O'}}</span>
                            </div>
                          </div>
                        </div>
                        {{ $t('public:H2OVesting-01') }}<br />
                        {{ $t('public:H2OVesting-02') }}<br />
                        {{ $t('public:H2OVesting-03') }}<br />
                        {{ $t('public:H2OVesting-04') }}
                      </div>
                      <i class="el-icon-question"></i>
                    </el-tooltip>
                  </template>
                </div>
                <div class="reward">
                  <div>
                    <span class="bold">{{toFixed(Number(item.btcbReward), 8) || "--"}}
                      <!-- <span>{{'BTCB'}}</span> -->
                      &nbsp;
                    </span>
                    <br />
                    <span class="bold">${{toFixed(Number(item.btcbReward) * Number(poolBtcData.currency_price ? poolBtcData.currency_price : item.btcbPrice), 2) || "--"}}
                      <!-- <span>{{'BTCB'}}</span> -->
                      &nbsp;
                    </span>
                  </div>
                </div>
                <!-- 操作 -->
                <div class="opera">
                  <div class="items">
                    <div :class="['live' , {'disabled':!Number(item.h2oReward)} ]" @click="receiveH2OReward(item)" v-loading="item.claimLoading"><nobr>{{ $t('public:Harvest') }}H2O</nobr></div>
                    <div :class="['live' , {'disabled':!Number(item.btcbReward)} ]" @click="receiveBTCBReward(item)" v-loading="item.claimLoading"><nobr>{{ $t('public:Harvest') }}BTCB</nobr></div>
                  </div>
                  <br>
                  <div class="items">
                    <div class="live" @click="toDetail(1, item)">{{ $t('public:Deposit') }}</div>
                    <div class="live" @click="toDetail(2, item)">{{ $t('public:Withdraw') }}</div>
                  </div>
                </div>
              </div>
            </div>
          </transition-group>
        </div>
        <div class="common-page-outer">
          <el-pagination layout="prev, pager, next" :total="1"> </el-pagination>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import axios from 'axios'
import imagePointer from "@/utils/images";
import { mapState } from "vuex";
import { getBalance, getPoolBtcData } from "@/wallet/Inquire";
import { getStakeRewardH2O, depositPoolsIn } from "@/wallet/trade";
import PLEdGE from "@/wallet/pledge";
import TOKEN from '@/wallet/tokens'
import { byDecimals,keepDecimalNotRounding } from '@/utils/tools'
export default {
  name: "PledgeMining",
  data() {
    return {
      activeNav: "live",
      receiveLoading: false,
      timeInterval: null,
      refreshTime: 10000, //数据刷新间隔时间
      poolBtcData: {},
      count_output: 0,
      online_days: 0, //上线天数
      to_output: 0, //今日产出
      yester_output: 0, //昨日产出
      cost_revenue: 0, //收益成本
      btcb_income: 0, //BTCB收益率
    };
  },
  activated() { //页面进来
      this.refreshData();
  },
  beforeRouteLeave(to, from, next){ //页面离开
      next();
      if (this.timeInterval) {
          clearInterval(this.timeInterval);
          this.timeInterval = null;
      }
  },
  watch: {
    isConnected: {
      immediate: true,
      handler(val) {
        if (val && !this.fixedList.length) {
          setTimeout(async() => {
            this.$store.dispatch("getHashPowerPoolsList");

            // if(this.timeInterval) clearInterval(this.timeInterval)
            // setInterval(()=>{
            //     this.$store.dispatch('refreshPoolsList')
            // },10000)

            this.getPoolBtcData();

            this.refreshData();

            this.getOutputDetail();

            console.log(this.fixedList);
          }, 300);
        }
      },
    },
    fixedList: {
      immediate: true,
      async handler(val) {
        console.log(val);
      },
    },
    // btcIncomeChangeData: {
    //     immediate: true,
    //     handler(val){
    //       if(val.fixedList.length > 0 && val.to_output > 0 && val.cost_revenue > 0) {
    //         // this.calcBtcIncome();
    //       }
    //     },
    // },
  },
  mounted() {
    setTimeout(()=>{
        this.getPoolBtcData();
    } , 300)
    // setTimeout(()=>{
    //     this.getIRObalance()
    // } , 300)
    // setTimeout(()=>{
    //     this.$store.dispatch('pledgeTokenList')
    // } , 300)
    console.log("IROPid", PLEdGE.IROPid);
  },
  beforeDestroy() {
    if (this.timeInterval) {
      clearInterval(this.timeInterval);
    }
  },
  computed: {
    ...mapState({
      isConnected: (state) => state.base.isConnected,
      fixedList: (state) => state.base.hashPowerPoolsList,
      apiUrl:state=>state.base.apiUrl,
    }),
    // btcIncomeChangeData() {
    //     const {fixedList, to_output, cost_revenue} = this
    //     return {
    //         fixedList, to_output, cost_revenue
    //     };
    // },
  },
  methods: {
    refreshData() { //定时刷新数据
        this.timeInterval = setInterval(async () => {
            this.$store.dispatch('refreshHashPowerPoolsList')
            await this.getPoolBtcData();
            await this.getOutputDetail();
        }, this.refreshTime)
    },
    toolTips0(item) {
      // let reward = Number(item.reward)
      // let tips0 = isNaN(reward) ? 0 : reward/210*209
      // return tips0
      return item.reward * 0.7;
    },
    toolTips1(item) {
      let reward = Number(item.reward);
      let tips1 = isNaN(reward) ? 0 : reward / 210;
      return tips1;
    },
    currentTokenImage(token) {
      let point = this.mainTheme === "light" ? "L" : "D";
      return imagePointer[token] && imagePointer[token][point]
        ? imagePointer[token][point]
        : imagePointer.default;
    },
    changeNav(val) {
      this.activeNav = val;
    },
    toDetail(type, item) {
      //type 1=>存入 2=>提取
      // let address = route === 'pl'?item.address:item.address_h
      // let decimals = route === 'pl'?item.decimals : item.decimals_h
      console.log(item);
      if (!item.address_h)
        return this.$notify.error({
          message: "Failed to get data, please refresh and try again",
          duration: 6000,
        });

      // this.$store.commit('setDepositCurrent' , item.address)
      let query = {
        pId: item.pId,
        token: item.address_h,
        currencyToken: item.currencyToken,
        goblin: item.goblin,
        decimals: item.decimals_h,
        name: item.name,
      };

      // if(route === 'un'){
      //     query.reward = item.reward
      // }
      sessionStorage.setItem("hashpowerPoolsDetailInfo", JSON.stringify(query));
      this.$router.push({
        path: "/hashpower/detail",
        query: {
          type: type,
          // kind:item.name ,
          // token:address,
          // decimals,
        },
      });
    },
    receiveBTCBReward(item) { //收获BTCB
      console.log("item", item)
      if (!Number(item.btcbReward)) return;
      this.$store.commit("sethashPowerPoolsListClaimLoading", {
        goblin: item.goblin,
        val: true,
      });
      depositPoolsIn(
        item.goblin,
        item.decimals,
        0,
        item.pId
      ).then(() => {
          this.$store.dispatch("getHashPowerPoolsList");
      }).finally(() => {
          this.$store.commit("sethashPowerPoolsListClaimLoading", {
            goblin: item.goblin,
            val: false,
          });
      });
    },
    receiveH2OReward(item) { //收获H2O
      // console.log("item", item);
      if (!Number(item.h2oReward)) return;
      this.$store.commit("sethashPowerPoolsListClaimLoading", {
        goblin: item.goblin,
        val: true,
      });
      getStakeRewardH2O(item.pId).then(() => {
        this.$store.dispatch("getHashPowerPoolsList");
      }).finally(() => {
        this.$store.commit("sethashPowerPoolsListClaimLoading", {
            goblin: item.goblin,
            val: false,
          });
      });
    },
    getIsH2OPools(token) { //判断是否H2O池子
      if(token === TOKEN.H2O) {
        return false;
      } else {
        return true;
      }
    },
    estimatedElectricityCharge(item) { //预估电费->日支出 预估电费=29.55*0.065/美元币价
      // let num = (24 * 29.55 * 0.065) / item.currency_price;
      let num = 0.065 * 29.55 * 24 / 1000;
      return num.toFixed(4);
    },
    dailyExpenditure(item) { //日支出 BTC数量
      let num = this.estimatedElectricityCharge(item) / item.currency_price;
      return this.toFixed(num, 8);
    },
    netProfit(item) { //净收益 = 收益-电费
      let estimatedElectricityNum = this.estimatedElectricityCharge(item);
      let num = (item.daily_income - estimatedElectricityNum) * 0.95;
      if(num > 0) {
        return keepDecimalNotRounding(Number(keepDecimalNotRounding(num, 3)) + Number(0.001), 3);
      } else {
        return 0;
      }
    },
    netProfitBtcNumber(item) { // 净收益 BTC 数量
      let num = this.netProfit(item) / item.currency_price;
      if(num > 0) {
        return num.toFixed(8);
      } else {
        return 0;
      }
    },
    async getPoolBtcData() { //获取BTC爬虫数据
        let data = await getPoolBtcData();
        if(data && data.length > 0) {
          this.poolBtcData = data[0];
        }
    },
    BTCQuantity(item) { //获取BTC数量
      let num = 0.0461 / item.currency_price
      return this.toFixed(num, 8);
    },
    buyHashpower() { //购买算力币
       this.$router.push('/hashpower/buy');
    },
    getOutputDetail() {
        axios.get(this.apiUrl + "/hashpower/hashpower/getHashpowerOutput",{
            params: {}
        }).then((json) => {
            console.log(json);
            if (json.code == 10000) {
                this.count_output = json.data.count_output;
                this.online_days = json.data.online_days;
                this.to_output = json.data.to_output;
                this.yester_output = json.data.yester_output;
                this.cost_revenue = json.data.cost_revenue;
                // this.detailData = json.data;
            } 
            else {
                // this.$message.error("error");
                console.log("get Data error");
            }
        }).catch((error) => {
            this.$message.error(error);
        });
    },
    calcBtcIncome() { //计算BTC收益
        // 新增的收益（U）/总的算力T数（目前是1605.70T）/单价（目前是30U/每T）
        // let total_power = this.fixedList[0].total; //总的算力
        // console.log(this.to_output, total, this.cost_revenue);
        let profit = this.netProfit(this.poolBtcData);
        // console.log(profit);
        let num = 0;
        if(profit > 0) {
          num = (Number(profit) / Number(this.cost_revenue)) * 365 * 100;
        }
        // this.btcb_income = num;
        return num;
    },
  },
};
</script>
<style lang="scss" scoped>
.public-info {
  padding: 10px;
  @include mainFont($color-mainFont-light);
  font-size: 10px;
  text-align: right;
  margin-right: 30px;
}
.info {
  padding: 30px;
  @include mainFont($color-mainFont-light);
  .el-divider {
    background-color: rgba(255,255,255,0.1);
    width: 80%;
    // margin: 0 auto;
    top: 10px;
  }
} 
.commin-title {
  .btn {
    display: inline-block;
    padding: 0 17px;
    height: 30px;
    // border: 1px solid #333333;
    // color: #333333;
    border-style: solid;
    border-width: 1px;
    border-radius: 15px;
    line-height: 28px;
    vertical-align: middle;
    margin-left: 8px;
    cursor: pointer;
    position: relative;
    @include commonbtn($commonbtn-light);
    box-sizing: border-box;
  }
  .btn.active {
    background: linear-gradient(90deg, #0096ff, #0024ff);
    color: #fff;
    // border-color: transparent;
    border: none;
    line-height: 30px;
  }
  .tit {
    padding-right: 14px;
    display: inline-block;
  }
}
.commin-title.IRO {
  margin-top: 30px;
  display: flex;
  justify-content: space-between;
  padding-right: 23px;
  font-size: 14px;
  .boxtit {
    color: #999999;
    font-weight: 600;
    display: inline-block;
    margin-right: 10px;
  }
  .num {
    font-weight: 600;
  }
  .btn {
    display: inline-block;
    width: 72px;
    height: 32px;
    background: linear-gradient(90deg, #0096ff, #0024ff);
    border-radius: 15px;
    text-align: center;
    line-height: 32px;
    font-size: 14px;
    color: #fff;
    margin-left: 15px;
    cursor: pointer;
    border: none;
    padding: 0;
  }
  .btn.disable {
    @include enterDisabled($enterDisabled-light);
    color: #fff;
    cursor: not-allowed;
  }
}
.common-assets-list {
  .head {
    > div {
      width: 10.5%;
    }
    .days {
      width: 14%;
    }
    .opera {
      // width: 20%;
    }
  }
  .head.live {
    > div {
      // border: 1px solid red;
      width: 16%;
    }
    .opera {
      width: 25%;
    }
    .reward {
      // width: 13%;
    }
  }
  .body {
    .item {
      height: 100px;
      .reward {
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
      }
      .el-icon-question {
        display: inline-block;
        width: 12px;
      }
      > div {
        width: 10.5%;
      }
      .days {
        width: 14%;
      }
      .opera {
        .items {
          color: none;
          border: 0;
          margin-top: -45px;
          padding: 5px;
        }
        .live {
          margin-right: 5px;
          margin-bottom: 10px;
          position: relative;
          cursor: pointer;
          // width: 70px;
          width: 100%;
          // height: 30px;
          // border: 1px solid #0024FF;
          line-height: 30px;
          text-align: center;
          font-size: 14px;
          border-radius: 15px;
          // color: #0024FF;
          @include btnBorderColor($color-btnBorderColor-light);
          ::v-deep {
            .el-loading-mask {
              border-radius: 15px;
            }
            .el-loading-spinner .circular {
              height: 22px;
              width: 22px;
            }
            .el-loading-spinner {
              margin-top: -11px;
            }
          }
        }
        .live.disabled {
          @include enterDisabled($enterDisabled-light);
          color: #fff;
          cursor: not-allowed;
          border-radius: 15px;
        }
      }
    }
    .item.live {
      > div {
        // border: 1px solid red;
        width: 16%;
      }
      .opera {
        width: 25%;
      }
    }
  }
}
.common-assets-list.IRO {
  .body {
    .item {
      > div {
        width: 42%;
      }
      .opera {
        width: 16%;
      }
    }
  }
}
</style>