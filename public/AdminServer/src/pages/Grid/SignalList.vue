<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
      <el-breadcrumb-item>信号列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-select v-model="strategy_name" clearable placeholder="选择策略" @change="handleFilterChange" @clear="handleFilterChange">
        <el-option v-for="item in strategyOptions" :key="item.name" :label="item.name" :value="item.name">
          <span style="float: left">{{ item.name }}</span>
          <span style="float: right; color: #8492a6; font-size: 13px">{{ item.label }}</span>
        </el-option>
      </el-select>
      <el-select
        v-model="symbol"
        clearable
        filterable
        placeholder="选择币种"
        style="margin-left: 10px;"
        @change="handleFilterChange"
        @clear="handleFilterChange"
      >
        <el-option
          v-for="item in symbolOptions"
          :key="item"
          :label="normalizeSymbol(item)"
          :value="item"
        />
      </el-select>
      <el-button type="primary" @click="refreshSignalList()">刷新列表</el-button>
    </div>
    <el-table :span-method="objectSpanMethod" :data="signalList" border style="width: 100%; margin-top: 20px;"
      v-loading="loading">
      <el-table-column prop="pair_id" label="配对ID">
      </el-table-column>
      <!-- <el-table-column prop="id" label="ID"></el-table-column> -->
      <el-table-column prop="name" label="策略名称">
      </el-table-column>
      <el-table-column label="类型" align="center">
        <template slot-scope="scope">
          <span>{{ positionTypeLabel(scope.row) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="symbol" label="交易对" align="center" width="150">
        <template slot-scope="scope">
          {{ normalizeSymbol(scope.row.symbol) || '--' }}
        </template>
      </el-table-column>
      <el-table-column prop="direction" label="方向" align="center">
        <template slot-scope="scope">
          <el-tag :type="orderSideLabel(scope.row) === 'buy' ? 'success' : 'danger'">
            {{ orderSideLabel(scope.row) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="size" label="信号类型" align="center">
        <template slot-scope="scope">
          {{ normalizeSignalSize(scope.row.size) === 0 ? '平仓' : '开仓' }}
        </template>
      </el-table-column>
      <el-table-column prop="price" label="价格" align="center">
        <template slot-scope="scope">
          {{ formatNumber(scope.row.price) }}
        </template>
      </el-table-column>
      <el-table-column prop="timestamp" label="日期/时间" align="center" width="180">
        <template slot-scope="scope">
          {{ scope.row.position_at }}
        </template>
      </el-table-column>
      <el-table-column prop="loss_profit" label="交易盈亏" align="center">
        <template slot-scope="scope">
          {{ formatNumber(scope.row.loss_profit) || 0 }}
        </template>
      </el-table-column>
      <el-table-column prop="stage_profit_loss" label="阶段盈亏" align="center">
        <template slot-scope="scope">
          {{ formatNumber(scope.row.stage_profit_loss) || 0 }}
        </template>
      </el-table-column>
      <el-table-column prop="count_profit_loss" label="总盈亏" align="center">
        <template slot-scope="scope">
          {{ formatNumber(scope.row.count_profit_loss) || 0 }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" align="center">
        <template slot-scope="scope">
          <el-tag :type="getSignalStatusTagType(scope.row)">
            {{ getSignalStatusText(scope.row) }}
          </el-tag>
        </template>
      </el-table-column>
    </el-table>

    <el-row class="pages">
      <el-col :span="24">
        <div style="float:right;">
          <wbc-page :total="total" :pageSize="pageSize" :currPage="currentPage" @changeLimit="limitPaging"
            @changeSkip="skipPaging"></wbc-page>
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import Page from "@/components/Page.vue";
import { get } from "@/common/axios.js";

export default {
  data() {
    return {
      currentPage: 1,
      pageSize: 10,
      total: 0,
      PageSearchWhere: {
        page: 1,
        limit: 10,
      },
      signalList: [],
      loading: false,
      strategyOptions: [],
      strategy_name: 'Y1.1',
      symbol: '',
      symbolOptions: ['BTC-USDT', 'ETH-USDT', 'BNB-USDT', 'DOGE-USDT'],
    };
  },
  created() {
    this.getSignalList();
    this.getAllStrategyList();
  },
  components: {
    "wbc-page": Page, //加载分页组件
  },
  methods: {
    objectSpanMethod({ row, column, rowIndex, columnIndex }) {
      if (columnIndex === 0) {
        const pairId = row.pair_id;

        // 确保 signalList 是按 pair_id 排好序的
        const firstIndex = this.signalList.findIndex(item => item.pair_id === pairId);
        const count = this.signalList.filter(item => item.pair_id === pairId).length;

        if (rowIndex === firstIndex) {
          return {
            rowspan: count,
            colspan: 1
          };
        } else {
          return {
            rowspan: 0,
            colspan: 0
          };
        }
      }
    },
    getAllStrategyList() {
      get("/Grid/grid/getAllStrategyList", {}, json => {
        if (json.data.code == 10000) {
          this.strategyOptions = json.data.data;
        } else {
          this.$message.error("加载策略数据失败");
        }
      });
    },
    handleFilterChange() {
      this.currentPage = 1;
      this.getSignalList();
    },
    getSignalList() {
      this.loading = true;
      const params = {
        page: this.currentPage,
        limit: this.pageSize,
        strategy_name: this.strategy_name,
        symbol: this.symbol,
      };

      get("/Grid/grid/getSignalsList", params, response => {
        this.loading = false;
        if (response.data.code == 10000) {
          this.signalList = response.data.data.lists || [];
          this.total = response.data.data.count || 0;
        } else {
          this.$message.error(response.data.msg || '获取信号列表失败');
        }
      });
    },

    async refreshSignalList() {
      // 延迟1-2秒再继续执行
      this.loading = true;
      const delay = Math.random() * 1000; // 1000~2000ms
      await new Promise(resolve => setTimeout(resolve, delay));
      this.getSignalList();
    },

    formatNumber(num) {
      if (isNaN(num)) return '--';
      // Format number with 4 decimal places
      return parseFloat(num).toFixed(1);
    },

    normalizeSymbol(symbol) {
      if (!symbol) return '';
      const normalizedSymbol = String(symbol).toUpperCase();
      return normalizedSymbol.endsWith('-SWAP')
        ? normalizedSymbol.slice(0, -5)
        : normalizedSymbol;
    },

    normalizeSignalSize(size) {
      const numericSize = Number(size);
      if (isNaN(numericSize)) return 0;
      if (numericSize > 0) return 1;
      if (numericSize < 0) return -1;
      return 0;
    },

    /**
     * 与 api_service / 新 leader 一致：direction 为委托侧（buy→long, sell→short）。
     * 平仓 long+0=平空→空头出场；short+0=平多→多头出场。
     */
    positionTypeLabel(row) {
      const sz = this.normalizeSignalSize(row.size);
      const dir = (row.direction || '').toLowerCase();
      if (sz === 1) return '多头进场';
      if (sz === -1) return '空头进场';
      if (sz === 0) {
        if (dir === 'long') return '空头出场';
        if (dir === 'short') return '多头出场';
        return '平仓';
      }
      return '--';
    },

    /** 委托方向 buy/sell（与 direction 一致） */
    orderSideLabel(row) {
      return (row.direction || '').toLowerCase() === 'long' ? 'buy' : 'sell';
    },

    parseJsonArray(value) {
      if (!value) return [];
      if (Array.isArray(value)) return value;
      try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        return [];
      }
    },

    getFailedAccountCount(row) {
      return this.parseJsonArray(row.failed_accounts).length;
    },

    getSignalStatusTagType(row) {
      const status = (row.status || '').toLowerCase();
      const statusMap = {
        pending: 'warning',
        processing: 'warning',
        partial: '',
        processed: 'success',
        failed: 'danger'
      };
      return statusMap[status] || 'info';
    },

    getSignalStatusText(row) {
      const status = (row.status || '').toLowerCase();
      const failedCount = this.getFailedAccountCount(row);

      if (status === 'pending') return '待处理';
      if (status === 'processing') return '处理中';
      if (status === 'processed') return '已完成';
      if (status === 'failed') return failedCount > 0 ? `失败(${failedCount}账户)` : '失败';
      if (status === 'partial') return failedCount > 0 ? `部分完成(${failedCount}待恢复)` : '部分完成';

      return row.status || '--';
    },
    limitPaging(limit) {
      //赋值当前条数
      this.pageSize = limit;
      this.getSignalList(); //刷新列表
    },
    skipPaging(page) {
      //赋值当前页数
      this.currentPage = page;
      this.getSignalList(); //刷新列表
    },
  }
};
</script>

<style scoped>
.project-top {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  margin-top: 20px;
  justify-content: space-between;
}
.el-breadcrumb {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

.el-tag {
  margin: 2px;
}

.pages {
  margin-top: 0 !important;
  margin-bottom: 80px !important;
}
</style>
