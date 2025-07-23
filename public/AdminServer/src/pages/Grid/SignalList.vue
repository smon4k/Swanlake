<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item>信号列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div style="text-align:right;margin-bottom: 10px;">
      <el-button type="primary" @click="refreshSignalList()">刷新列表</el-button>
    </div>
    <el-table :data="signalList" style="width: 100%; margin-top: 20px;" v-loading="loading">
      <el-table-column prop="name" label="策略名称" align="center"></el-table-column>
      <el-table-column prop="symbol" label="交易对" align="center"></el-table-column>
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
      <el-table-column prop="timestamp" label="时间" align="center">
        <template slot-scope="scope">
          {{ scope.row.timestamp }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" align="center">
        <template slot-scope="scope">
          <el-tag :type="scope.row.status === 'pending' ? 'warning' : (scope.row.status === 'processed' ? 'success' : 'info')">
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
      loading: false
    };
  },
  created() {
    this.getSignalList();
  },
  components: {
    "wbc-page": Page, //加载分页组件
  },
  methods: {
    getSignalList() {
      this.loading = true;
      const params = {
        page: this.currentPage,
        limit: this.pageSize
      };
      
      get("/Grid/grid/getSignalsList", params, response => {
        this.loading = false;
        console.log(response)
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
      return parseFloat(num).toFixed(4);
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