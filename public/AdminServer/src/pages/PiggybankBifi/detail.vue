<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">BIFI 存钱罐管理</el-breadcrumb-item>
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
                <span>BIFI价格:</span>
                <span>{{ detailData.tradingPrice }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>BUSD余额:</span>
                <span>{{ detailData.busdBalance }}</span>
            </el-col>
            <el-col :span="12">
                <span>BUSD估值:</span>
                <span>{{ detailData.busdValuation }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>BIFI余额:</span>
                <span>{{ detailData.bifiBalance }}</span>
            </el-col>
            <el-col :span="12">
                <span>BIFI估值:</span>
                <span>{{ detailData.bifiValuation }}</span>
            </el-col>
        </el-row>
        <el-row>
            <el-col :span="12">
                <span>默认比例:</span>
                <span v-if="!isDefaultRatio">{{ keepDecimalNotRounding(detailData.defaultRatio, 1, true) }}%</span>
                <el-input v-else v-model="default_ratio" placeholder="请输入涨跌比例" style="width:100px;">
                    <template slot="append">
                        <span>%</span>
                    </template>
                </el-input>

                <el-link v-if="!isDefaultRatio" type="primary" @click="isDefaultRatio = true">修改</el-link>
                <span v-else>
                    <el-link type="primary" @click="saveDefaultRatio">保存</el-link>
                    <el-link type="primary" @click="isDefaultRatio = false">取消</el-link>
                </span>
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
        <div v-if="Object.keys(detailData.pendingOrder).length">
            <h3>挂单信息</h3>
            <el-row>
                <el-col :span="6">
                    <span>买入价：</span>
                    <span>{{ detailData.pendingOrder.buy.price || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>买入数量：</span>
                    <span>{{ detailData.pendingOrder.buy.amount || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>BIFI估值：</span>
                    <span>{{ detailData.pendingOrder.buy.bifiValuation || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>BUSD估值：</span>
                    <span>{{ detailData.pendingOrder.buy.busdValuation || 0 }}</span>
                </el-col>
            </el-row>
            <el-row>
                <el-col :span="6">
                    <span>卖出价：</span>
                    <span>{{ detailData.pendingOrder.sell.price || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>卖出数量：</span>
                    <span>{{ detailData.pendingOrder.sell.amount || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>BIFI估值：</span>
                    <span>{{ detailData.pendingOrder.sell.bifiValuation || 0 }}</span>
                </el-col>
                <el-col :span="6">
                    <span>BUSD估值：</span>
                    <span>{{ detailData.pendingOrder.sell.busdValuation || 0 }}</span>
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
        loading: true,
        default_ratio: 0,
        isDefaultRatio: false,
    };
  },
  methods: {
    getListData() { //获取U本位数据
        this.loading = true;
        get("/Admin/Binancepiggybank/testBalancePosition", {}, json => {
            console.log(json);
            if (json.data.code == 10000) {
                this.detailData = json.data.data;
                this.default_ratio = this.detailData.defaultRatio;
            } else {
                this.$message.error("加载数据失败");
            }
            this.loading = false;
        });
    },
    saveDefaultRatio() { //开始修改涨跌比例
        this.loading = true;
        get("/Admin/Binancepiggybank/setChangeRatio", {
            default_ratio: this.default_ratio
        }, json => {
            console.log(json);
            if (json.data.code == 10000) {
                setTimeout(() => {
                    this.getListData();
                }, 3000)
            } else {
                this.$message.error("加载数据失败");
            }
            this.isDefaultRatio = false;
        });

    }
  },
  created() {
    this.getListData();
  },
  components: {
  }
};
</script>
<style lang="scss" scoped>
 /deep/ {
  .el-row {
    margin-top: 20px;
  }
  .el-input-group__append {
    padding: 0 10px !important;
  }
 }
</style>
