<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item>币种列表</el-breadcrumb-item>
    </el-breadcrumb>

    <el-table :data="tableData" style="width: 100%;" v-loading="loading">
      <el-table-column prop="name" label="币种名称" align="center" />
      <el-table-column prop="exchange" label="交易所" align="center" />
      <el-table-column prop="transaction_currency" label="交易币种" align="center" />
      <el-table-column label="操作" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="text" @click="handleDeposit(scope.row)">出/入金</el-button>
          <el-button size="mini" type="text" @click="viewDetails(scope.row)">详情</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { get } from "@/common/axios.js";

export default {
  data() {
    return {
      tableData: [],
      loading: false,
    };
  },
  methods: {
    fetchPiggybankList() {
      this.loading = true;
      get("/Piggybank/index/getCurrencyList", {}, (json) => {
        console.log(json);
        this.loading = false;
        if (json.data.code === 10000) {
          this.tableData = json.data.data.lists;
        } else {
          this.$message.error("加载币种列表失败");
        }
      });
    },
    handleDeposit(row) {
      this.$router.push({ path: `/home/gold/index`, query: { currency_id: row.id } });
    },
    viewDetails(row) {
      this.$router.push({ path: `/home/piggybank/detail`, query: { currency_id: row.id } });
    },
  },
  created() {
    this.fetchPiggybankList();
  },
};
</script>
