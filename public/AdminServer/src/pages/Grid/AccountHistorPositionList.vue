<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item>账户持仓历史</el-breadcrumb-item>
    </el-breadcrumb>

    <div class="project-top">
      <el-select v-model="account_id" clearable placeholder="选择账户" @change="refreshList" @clear="clearAccount">
        <el-option v-for="item in accountList" :key="item.id" :label="item.name" :value="item.id" />
      </el-select>
      <el-button type="primary" @click="refreshList">刷新</el-button>
    </div>

    <el-table :data="historyList" border v-loading="loading" style="width: 100%; margin-top: 20px;">
      <el-table-column prop="id" label="ID" align="center" />
      <el-table-column prop="sign_id" label="信号ID" align="center" />
      <el-table-column prop="name" label="账户名称" align="center" />
      <el-table-column prop="amount" label="数量" align="center" />
      <el-table-column prop="datetime" label="更新时间" align="center" />
    </el-table>

    <el-row class="pages">
      <el-col :span="24">
        <div style="float: right;">
          <wbc-page :total="total" :pageSize="pageSize" :currPage="currentPage"
            @changeLimit="limitPaging" @changeSkip="skipPaging" />
        </div>
      </el-col>
    </el-row>
  </div>
</template>


<script>
import Page from "@/components/Page.vue";
import { get } from "@/common/axios.js";

export default {
  components: {
    "wbc-page": Page,
  },
  data() {
    return {
      currentPage: 1,
      pageSize: 10,
      total: 0,
      historyList: [],
      accountList: [],
      account_id: '',
      loading: false,
    };
  },
  created() {
    this.getAccountList();
    this.getHistoryList();
  },
  methods: {
    getAccountList() {
      get("/Grid/grid/getAccountList", {}, res => {
        if (res.data.code === 10000) {
          this.accountList = res.data.data;
        } else {
          this.$message.error("账户列表获取失败");
        }
      });
    },
    getHistoryList() {
      this.loading = true;
      const params = {
        page: this.currentPage,
        limit: this.pageSize,
        account_id: this.account_id || undefined,
      };
      get("/Grid/grid/getAccountHistorPositionList", params, res => {
        this.loading = false;
        if (res.data.code === 10000) {
          this.historyList = res.data.data.lists || [];
          this.total = res.data.data.count || 0;
        } else {
          this.$message.error(res.data.msg || "获取持仓历史失败");
        }
      });
    },
    refreshList() {
      this.currentPage = 1;
      this.getHistoryList();
    },
    clearAccount() {
      this.account_id = '';
      this.refreshList();
    },
    limitPaging(limit) {
      this.pageSize = limit;
      this.getHistoryList();
    },
    skipPaging(page) {
      this.currentPage = page;
      this.getHistoryList();
    }
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