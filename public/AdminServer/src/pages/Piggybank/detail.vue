<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">存钱罐管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">项目详情</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="mian" v-loading="loading">
        <el-row>
            <el-col :span="12">
                <span>最小下单量:</span>
                <span>{{ detailData.minSizeOrderNum }}</span>
            </el-col>
            <el-col :span="12">
                <span>交易货币币种:</span>
                <span>{{ detailData.base_ccy }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>计价货币币种:</span>
                <span>{{ detailData.quote_ccy }}</span>
            </el-col>
            <el-col :span="12">
                <span>{{ tradingPairData.transaction_currency }}价格:</span>
                <span>{{ detailData.tradingPrice }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>USDT余额:</span>
                <span>{{ detailData.usdtBalance }}</span>
            </el-col>
            <el-col :span="12">
                <span>USDT估值:</span>
                <span>{{ detailData.usdtValuation }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>{{ tradingPairData.transaction_currency }}余额:</span>
                <span>{{ detailData.btcBalance }}</span>
            </el-col>
            <el-col :span="12">
                <span>{{ tradingPairData.transaction_currency }}估值:</span>
                <span>{{ detailData.btcValuation }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>默认比例:</span>
                <span>{{ detailData.defaultRatio }}%</span>
            </el-col>
            <el-col :span="12">
                <span>涨跌比例:</span>
                <span>{{ detailData.changeRatio }}%</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>{{ detailData.sellOrdersNumberStr }}</span>
            </el-col>
            <el-col :span="12">
                <span>最近一次成交价格：</span>
                <span>{{ detailData.lastTimePrice }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>待卖出价：</span>
                <span>{{ detailData.sellingPrice }}</span>
            </el-col>
            <el-col :span="12">
                <span>待买入价：</span>
                <span>{{ detailData.buyingPrice }}</span>
            </el-col>
        </el-row>
        <el-divider></el-divider>
        <div v-if="detailData && detailData.pendingOrder && typeof detailData.pendingOrder === 'object' &&  Object.keys(detailData.pendingOrder).length">
            <h3>挂单信息</h3>
            <el-row>
                <el-col :span="6">
                    <span>买入价：</span>
                    <span>{{ detailData.pendingOrder.buy.price || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>买入数量：</span>
                    <span>{{ keepDecimalNotRounding(detailData.pendingOrder.buy.amount, 8) || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>{{ tradingPairData.transaction_currency }}估值：</span>
                    <span>{{ keepDecimalNotRounding(detailData.pendingOrder.buy.btcValuation, 8) || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>USDT估值：</span>
                    <span>{{ keepDecimalNotRounding(detailData.pendingOrder.buy.usdtValuation, 8) || 0 }}</span>
                </el-col>
            </el-row>
            <el-row>
                <el-col :span="6">
                    <span>卖出价：</span>
                    <span>{{ detailData.pendingOrder.sell.price || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>卖出数量：</span>
                    <span>{{ keepDecimalNotRounding(detailData.pendingOrder.sell.amount, 8) || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>{{ tradingPairData.transaction_currency }}估值：</span>
                    <span>{{ keepDecimalNotRounding(detailData.pendingOrder.sell.btcValuation, 8) || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>USDT估值：</span>
                    <span>{{ keepDecimalNotRounding(detailData.pendingOrder.sell.usdtValuation, 8) || 0 }}</span>
                </el-col>
            </el-row>
        </div>
    </div>
  </div>
</template>
<script>
import { get, post } from "@/common/axios.js";
export default {
  data() {
    return {
        detailData: {},
        tradingPairData: {},
        loading: true,
        currency_id: 0,
    };
  },
  mounted() {
    setTimeout(()=>{
        this.getTradingPairData();
        this.getListData();
    } , 300)
},
  methods: {
    getTradingPairData() { //获取交易币种信息
      get("/Piggybank/index/getTradingPairData", {
        currency_id: this.currency_id,
      }, json => {
          console.log(json);
        if (json.data.code == 10000) {
          this.tradingPairData = json.data.data;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    getListData() { //获取U本位数据
        this.loading = true;
        get("/Piggybank/index/testBalancePosition", {
            currency_id: this.currency_id,
        }, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.detailData = json.data.data;
            } else {
                this.$message.error("加载数据失败");
            }
            this.loading = false;
      });
    }

  },
  created() {
    this.currency_id = this.$route.query.currency_id;
  },
  components: {
  }
};
</script>
<style lang="scss" scoped>
  .el-row {
    margin-top: 20px;
  }
</style>
