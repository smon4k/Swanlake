<template>
  <div class="container">
      <!-- <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item :to="{ path: '/my/finances' }">我的理财</el-breadcrumb-item>
        <el-breadcrumb-item>质押</el-breadcrumb-item>
      </el-breadcrumb> -->
      <el-card class="box-card">
        <div slot="header" class="clearfix">
          <el-page-header @back="goBack"></el-page-header>
        </div>
        <el-row class="app-left">
          <el-col :span="24">
            <p class="title" v-if="kind || isDemand">
              <span>{{ $t('public:likeTo') }}{{ this.type == 1 ? $t('public:unstake') : $t('public:stake') }}</span>
            </p>
            <p class="title" v-else>
              <span>{{ $t('public:likeTo') }}{{ this.type == 2 ? $t('public:unstake') : $t('public:stake') }}</span>
            </p>

            <div class="input-area">
              <div class="balance">
                <span class="info">{{ $t('public:Balance') }}：</span>
                <span class="num"
                  >{{ toFixed(tokenBalance, 6) || "--" }} {{ name }}</span
                >
              </div>
              <div :class="['inputBox', { overMax: isConnected && isOverMax }]">
                <!-- <img :src="currentTokenImage('H2O')" alt="" /> -->
                <input
                  type="text"
                  minLength="1"
                  maxLength="79"
                  v-model="depositNum"
                  @input="inputEvent"
                />
                <button @click="clickAllBtn">{{ $t('public:MAX') }}</button>
              </div>
            </div>
            <p class="warn-tips" v-show="isConnected && isOverMax">
              {{ $t('public:balanceInsufficient') }}
            </p>

            <div class="submitBtns">
              <button
                :class="['enter', { disabled: btnDisabled }]"
                :disabled="btnDisabled"
                @click="submitOrder"
                v-loading="GettingApprove || trading"
              >
                {{ submitBtnText }}
              </button>
              <!-- <button class="cancal" @click="returnPage">BACK</button> -->
            </div>
          </el-col>
        </el-row>
      </el-card>
  </div>
</template>
<script>
import imagePointer from "@/utils/images";
import { mapState } from "vuex";
import { approve, depositPoolsIn, depositPoolsOut } from "@/wallet/trade";
import {
  getBalance,
  isApproved,
  getH2OUserInfo
} from "@/wallet/serve";
import { get, post } from "@/common/axios.js";
export default {
  name: "PledegDetail",
  data() {
    return {
      propertyList: [{ id: 1, name: "币种" }],
      activeDayIndex: 4,
      depositNum: "",
      // toolTips0:'--',
      // toolTips1:'--',
      toolTipsLoading: false,
      hashId: 1,
      tokenBalance: "--",
      ableReceive: "--",
      rewardAmount: "",
      tokenAddress: "",
      currencyToken: "",
      goblin: "",
      name: "",
      approved: 0,
      decimals: 18,
      isDemand: false,
      isIRO: false,
      trading: false,
    };
  },
  created() {
    try {
      let JsonInfo = sessionStorage.getItem("hashpowerPoolsDetailInfo");
      let info = JSON.parse(JsonInfo);
      console.log(info);
      // this.tokenAddress = info.token;
      this.currencyToken = info.currencyToken;
      this.goblin = info.goblin;
      this.isDemand = info.demand;
      this.decimals = info.decimals;
      this.name = info.name;
      this.originName = info.originName;
      this.hashId = info.hashId;
    } catch (err) {}
  },
  mounted() {},
  watch: {
    isConnected: {
      immediate: true,
      async handler(val) {
        if (val && this.goblin && !this.approve) {
          this.type == 1
            ? this.getTokenBalanceApprove()
            : this.getTokenBalance(this.pid);
          // this.getRewardInfo()
        }
      },
    },
    goblin: {
      handler(val) {
        if (this.isConnected && val) {
          this.type == 1
            ? this.getTokenBalanceApprove()
            : this.getTokenBalance(this.pid);
          // this.getRewardInfo()
        }
      },
    },
  },
  computed: {
    ...mapState({
      address:state=>state.base.address,
      isConnected: (state) => state.base.isConnected,
      fairLaunchAddress: (state) => state.base.fairLaunchAddress,
      mainTheme: (state) => state.comps.mainTheme,
      apiUrl:state=>state.base.apiUrl,
      nftUrl:state=>state.base.nftUrl,
    }),
    btnDisabled() {
      return (
        (this.isConnected &&
          !(this.approved === 2 && this.type == 1) &&
          !Boolean(Number(this.depositNum))) ||
        this.isOverMax
      );
    },
    themeText() {
      return this.type == 1 ? this.$t('public:Deposit') : this.$t('public:Withdraw');
    },
    type() {
      return this.$route.query.type;
    },
    kind() {
      return this.$route.query.kind;
    },
    GettingApprove() {
      return this.isConnected && this.approved === 0;
    },
    submitBtnText() {
      return this.isConnected
        ? this.approved === 2 && this.type == 1
          ? this.$t('public:Approve')
          : this.themeText
        : this.$t('public:ConnectWallet');
    },
    isOverMax() {
      let bool = false;
      if (this.isDemand) {
        bool =
          (this.tokenBalance === "--" && Number(this.depositNum)) ||
          (this.tokenBalance !== "--" &&
            Number(this.depositNum) > Number(this.tokenBalance));
      } else {
        bool =
          (this.tokenBalance === "--" && Number(this.depositNum)) ||
          (this.tokenBalance !== "--" &&
            Number(this.depositNum) > Number(this.tokenBalance)) ||
          Number(this.depositNum) > Number(this.ableReceive);
      }
      return bool;
    },
    pid() {
      if (!this.isDemand) {
        return this.activeDayIndex;
      }
      return this.fixedpId;
    },
  },
  methods: {
    currentTokenImage(token) {
      let point = this.mainTheme === "light" ? "L" : "D";
      return imagePointer[token] && imagePointer[token][point]
        ? imagePointer[token][point]
        : imagePointer.default;
    },
    inputEvent(e) {
      this.depositNum = this.$inputLimit(e, 6, true);
    },
    returnPage() {
      this.depositNum = "";
      this.$router.go(-1);
    },
    submitOrder() { //开始存或者取
      if (!this.isConnected) return this.$connect();
      if (this.trading) return;
      if (this.approved === 2 && this.type == 1) { 
          this.trading = true;
          // console.log("currencyToken", this.currencyToken);
          // console.log("fairLaunchAddress", this.fairLaunchAddress);
          approve(this.currencyToken, this.goblin).then(() => {
            this.approved = 1;
            this.trading = false;
          }).finally(() => {
            this.trading = false;
          });
          return;
      }
      // let index = this.isDemand === 'hBNB' ? 6 : ( (this.kind || this.isDemand )? 5 : this.activeDayIndex  )
      let tradeFunc = this.type == 1 ? depositPoolsIn : depositPoolsOut;
      let index = this.activeDayIndex;
      this.trading = true;
      // post(this.apiUrl + '/Hashpower/Hashpower/startInvestNow', { 
      //     address: this.address, 
      //     hashId: this.hashId, 
      //     number: this.depositNum,
      //     type: this.type
      // }, (json) => {
      //   this.loading = false;
      //   console.log(json);
      //   if (json && json.code == 10000) {
      //       this.trading = false;
      //       this.depositNum = "";
      //       this.approve = 0;
      //       setTimeout(() => {
      //         this.$store.dispatch("getHashPowerPoolsList");
      //         this.$router.push({path:'/my/finances'})
      //       }, 2000)
      //   } else {
      //       this.$message.error(json.msg);
      //   }
      // })
      tradeFunc(
        this.goblin,
        this.decimals,
        this.depositNum,
        this.pid
      ).then(() => {
        post(this.nftUrl + '/Hashpower/Hashpower/startInvestNow', { 
            address: this.address, 
            hashId: this.hashId, 
            number: this.depositNum,
            type: this.type
        }, (json) => {
          this.loading = false;
          console.log(json);
          if (json && json.code == 10000) {
              this.trading = false;
              this.depositNum = "";
              this.approve = 0;
              setTimeout(() => {
                this.$store.dispatch("getHashPowerPoolsList");
                this.$router.push({path:'/hashpower/list'})
              }, 2000)
          } else {
              this.$message.error(json.msg);
          }
        })
      }).finally(() => {
        this.trading = false;
      });
    },
    clickAllBtn() {
      if (!this.isConnected || !this.tokenBalance) return;
      this.depositNum = this.tokenBalance;
    },
    async getTokenBalanceApprove() { //获取余额 查看是否授权
      let balance = await getBalance(this.currencyToken, this.decimals); //获取余额
      // console.log("balance", balance, "currencyToken", this.currencyToken, "goblin", this.goblin);
      this.tokenBalance = balance;
      isApproved(
            this.currencyToken,
            this.decimals,
            balance,
            this.goblin
        ).then((bool) => {
            this.approved = bool ? 1 : 2;
            // this.approved = 2
        });
    },
    // unfoldString(index){
    //     let kind = 'day365'
    //     switch (index){
    //         case 0 :
    //             kind = 'day90'
    //             break;
    //         case 1 :
    //             kind = 'day180'
    //             break;
    //     }
    //     return kind
    // },
    async getTokenBalance() { //获取已存入余额
        let balance = 0;
        // console.log(this.currencyToken , TOKEN.H2O);
        balance = await getH2OUserInfo(this.goblin);
        // console.log(balance);
        this.tokenBalance = balance;
        isApproved(
            this.currencyToken,
            this.decimals,
            balance,
            this.goblin
        ).then((bool) => {
            this.approved = bool ? 1 : 2;
            // this.approved = 2
        });
    },
    goBack() {
      this.$router.go(-1);
    }
  },
};
</script>
<style lang="scss" scoped>
.container {
  /deep/ {
    background-color: transparent !important;
    .el-breadcrumb {
        height: 25px;
        font-size: 16px;
    }
    .warn-tips {
      color: #f00;
      font-size: 14px;
      margin-top: 5px;
    }
    .box-card {
      .el-card__body {
        padding-bottom: 80px;
      }
      border-radius: 30px;
      .app-left {
      }
      .title {
        // color: #1C1C1B;
        font-size: 18px;
        font-weight: 600;
        padding-bottom: 10px;
        @include mainFont($color-mainFont-light);
      }
      .input-area {
        .receInfo {
          text-align: right;
          font-size: 12px;
          margin-bottom: 8px;
          span {
            font-weight: 600;
            font-size: 14px;
          }
        }
        .balance {
          font-size: 12px;
          line-height: 12px;
          padding-bottom: 8px;
          text-align: right;
          .info {
            color: #999999;
          }
          .num {
            // color: #333333;
            @include balanceFont($balanceFont-light);
          }
        }
        .inputBox {
          height: 45px;
          background-color: rgba(0, 49, 255, 0.06);
          border-radius: 8px;
          position: relative;
          img {
            position: absolute;
            height: 30px;
            width: 30px;
            left: 14px;
            top: 8px;
          }
          button {
            position: absolute;
            height: 30px;
            width: 80px;
            text-align: center;
            line-height: 26px;
            font-size: 12px;
            // border: 1px solid #0031FF;
            // color: #002EFF;
            @include btnBorderColor($color-btnBorderColor-light);
            background-color: transparent;
            right: 15px;
            top: 8px;
            border-radius: 19px;
            cursor: pointer;
          }
          input {
            width: 100%;
            height: 45px;
            // padding: 0;
            margin: 0;
            border: none;
            outline: none;
            background-color: transparent;
            font-size: 18px;
            // color: #333333;
            // @include balanceFont($balanceFont-light);
            // padding: 0 120px 0 62px;
            padding-left: 10px;
            box-sizing: border-box;
          }
        }
        .overMax {
          border: 1px solid #f00;
        }
      }
      .submitBtns {
        padding-top: 30px;
        text-align: center;
        button {
          width: 180px;
          height: 38px;
          line-height: 36px;
          // border: 1px solid #0031FF;
          // color: #002EFF;
          @include btnBorderColor($color-btnBorderColor-light);
          @include sideBarBgc($color-bgc-sideBar-light);
          border-radius: 19px;
          // background-color: #fff;
          cursor: pointer;
          position: relative;
          padding: 0;
        }
        .enter {
          background: linear-gradient(90deg, #0096ff, #0024ff);
          border: none;
          color: #fff !important;
          margin-right: 22px;
          height: 40px;
          position: relative;
          overflow: hidden;
          .el-loading-mask {
            @include loadingMask($loadingMask-light);
          }
          .el-loading-spinner .circular {
            width: 30px;
            height: 30px;
            margin-top: 6px;
          }
        }
        .enter.disabled {
          @include enterDisabled($enterDisabled-light);
          color: #fff;
          cursor: not-allowed;
        }
      }
    }
  }
}
</style>