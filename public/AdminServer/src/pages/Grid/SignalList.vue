<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
      <el-breadcrumb-item>信号列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-select v-model="strategy_name" clearable placeholder="选择策略" @change="getSignalList" @clear="getSignalList">
        <el-option v-for="item in strategyOptions" :key="item.name" :label="item.name" :value="item.name">
          <span style="float: left">{{ item.name }}</span>
          <span style="float: right; color: #8492a6; font-size: 13px">{{ item.label }}</span>
        </el-option>
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
          <div v-if="scope.row.direction === 'long'">
            <span v-if="scope.row.size == '1'">{{ '多头进场' }}</span>
            <span v-else>{{ '多头出场' }}</span>
          </div>
          <div v-else>
            <span v-if="scope.row.size == '-1'">{{ '空头进场' }}</span>
            <span v-else>{{ '空头出场' }}</span>
          </div>
        </template>
      </el-table-column>
      <!-- <el-table-column prop="symbol" label="交易对" align="center"></el-table-column> -->
      <el-table-column prop="direction" label="方向" align="center">
        <template slot-scope="scope">
          <el-tag :type="scope.row.direction === 'long' ? 'success' : 'danger'">
            {{ scope.row.direction === 'long' ? 'buy' : 'sell' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="size" label="信号类型" align="center">
        <template slot-scope="scope">
          {{ scope.row.size == '1' || scope.row.size == '-1' ? '开仓' : '平仓' }}
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
          <el-tag
            :type="scope.row.status === 'pending' ? 'warning' : (scope.row.status === 'processed' ? 'success' : 'info')">
            {{ scope.row.status === 'pending' ? '进行中' : (scope.row.status === 'processed' ? '已完成' : scope.row.status) }}
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
    getSignalList() {
      this.loading = true;
      const params = {
        page: this.currentPage,
        limit: this.pageSize,
        strategy_name: this.strategy_name,
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

    getStatusTagType(status) {
      const statusMap = {
        'pending': 'warning',
        'executed': 'success',
        'canceled': 'info',
        'failed': 'danger'
      };
      return statusMap[status] || 'info';
    },

    getStatusText(status) {
      const statusTextMap = {
        'pending': '待执行',
        'executed': '已执行',
        'canceled': '已取消',
        'failed': '失败'
      };
      return statusTextMap[status] || status;
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