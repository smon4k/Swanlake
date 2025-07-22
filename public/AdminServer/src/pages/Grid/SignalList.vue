<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item>信号列表</el-breadcrumb-item>
    </el-breadcrumb>
    
    <el-table :data="signalList" style="width: 100%; margin-top: 20px;" v-loading="loading">
      <el-table-column prop="strategy_name" label="策略名称" align="center"></el-table-column>
      <el-table-column prop="symbol" label="交易对" align="center"></el-table-column>
      <el-table-column prop="direction" label="方向" align="center">
        <template slot-scope="scope">
          <el-tag :type="scope.row.direction === 'long' ? 'success' : 'danger'">
            {{ scope.row.direction === 'long' ? '做多' : '做空' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="price" label="价格" align="center">
        <template slot-scope="scope">
          {{ formatNumber(scope.row.price) }}
        </template>
      </el-table-column>
      <el-table-column prop="timestamp" label="时间" align="center">
        <template slot-scope="scope">
          {{ formatTime(scope.row.timestamp) }}
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" align="center">
        <template slot-scope="scope">
          <el-tag :type="getStatusTagType(scope.row.status)">
            {{ getStatusText(scope.row.status) }}
          </el-tag>
        </template>
      </el-table-column>
    </el-table>
    
    <el-pagination
      @size-change="handleSizeChange"
      @current-change="handleCurrentChange"
      :current-page="currentPage"
      :page-sizes="[10, 20, 50, 100]"
      :page-size="pageSize"
      layout="total, sizes, prev, pager, next, jumper"
      :total="total"
      class="pagination"
    ></el-pagination>
  </div>
</template>

<script>
import { get } from "@/common/axios.js";

export default {
  data() {
    return {
      currentPage: 1,
      pageSize: 10,
      total: 0,
      signalList: [],
      loading: false
    };
  },
  created() {
    this.getSignalList();
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
        if (response.data.code == 10000) {
          this.signalList = response.data.data.list || [];
          this.total = response.data.data.total || 0;
        } else {
          this.$message.error(response.data.msg || '获取信号列表失败');
        }
      }).catch(error => {
        this.loading = false;
        this.$message.error(error.message || '请求失败');
      });
    },
    
    handleSizeChange(val) {
      this.pageSize = val;
      this.getSignalList();
    },
    
    handleCurrentChange(val) {
      this.currentPage = val;
      this.getSignalList();
    },
    
    formatNumber(num) {
      if (isNaN(num)) return '--';
      // Format number with 4 decimal places
      return parseFloat(num).toFixed(4);
    },
    
    formatTime(timestamp) {
      if (!timestamp) return '--';
      try {
        const date = new Date(timestamp * 1000);
        return date.toLocaleString('zh-CN', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false
        }).replace(/\//g, '-');
      } catch (e) {
        return timestamp;
      }
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
    }
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
</style>