<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item to="">SIG持仓管理</el-breadcrumb-item>
            <el-breadcrumb-item>策略列表</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- <div style="text-align:right;margin-bottom: 10px;">
            <el-button type="primary" @click="fetchStrategyList">刷新列表</el-button>
        </div> -->

        <el-table :data="strategyList" border v-loading="loading" style="width: 100%; margin-top: 20px;">
            <el-table-column prop="name" label="策略名称" align="center" />
            <el-table-column prop="loss_number" label="亏损次数" align="center" />
            <el-table-column prop="max_position" label="最大仓位数" align="center" />
            <el-table-column prop="min_position" label="最小仓位数" align="center" />
            <el-table-column prop="count_profit_loss" label="总盈亏" align="center" />
            <el-table-column prop="stop_loss_percent" label="止损率" align="center">
                <template slot-scope="scope">
                    <span>{{ keepDecimalNotRounding(scope.row.stop_loss_percent, 3) }}%</span>
                </template>
            </el-table-column>
            <el-table-column label="操作" align="center" width="100">
                <template slot-scope="scope">
                    <el-button type="text" size="small" @click="openEditDialog(scope.row)">编辑</el-button>
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

        <!-- 编辑弹窗 -->
        <el-dialog title="编辑最大/最小仓位" :visible.sync="dialogVisible" width="30%">
            <el-form :model="editForm" label-width="100px">
                <el-form-item label="策略名称">
                    <span>{{ editForm.name }}</span>
                </el-form-item>
                <el-form-item label="最大仓位数">
                    <el-input-number v-model="editForm.max_position" :min="1" />
                </el-form-item>
                <el-form-item label="最小仓位数">
                    <el-input-number v-model="editForm.min_position" :min="0" />
                </el-form-item>
                <el-form-item label="止损率(%)">
                    <el-input-number v-model="editForm.stop_loss_percent" :min="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitUpdate">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import Page from "@/components/Page.vue";
import { get, post } from "@/common/axios.js";
export default {
    data() {
        return {
            loading: false,
            strategyList: [],
            total: 0,
            pageSize: 10,
            currentPage: 1,
            dialogVisible: false,
            editForm: {
                id: null,
                name: '',
                max_position: 0,
                min_position: 0,
                stop_loss_percent: 0
            }
        };
    },
    components: {
        "wbc-page": Page, //加载分页组件
    },
    created() {
        this.fetchStrategyList();
    },
    methods: {
        async fetchStrategyList() {
            this.loading = true;
            try {
                get("/Grid/grid/getStrategyList", {
                    page: this.currentPage,
                    limit: this.pageSize
                }, response => {
                    this.loading = false;
                    console.log(response)
                    if (response.data.code == 10000) {
                        this.strategyList = response.data.data.lists || [];
                        this.total = response.data.data.count || 0;
                    } else {
                        this.$message.error(response.data.msg || '获取信号列表失败');
                    }
                })
            } catch (err) {
                this.$message.error("网络错误");
            } finally {
                this.loading = false;
            }
        },

        limitPaging(limit) {
            //赋值当前条数
            this.pageSize = limit;
            this.fetchStrategyList(); //刷新列表
        },
        skipPaging(page) {
            //赋值当前页数
            this.currentPage = page;
            this.fetchStrategyList(); //刷新列表
        },

        openEditDialog(row) {
            this.editForm = {
                id: row.id,
                name: row.name,
                max_position: row.max_position,
                min_position: row.min_position,
                stop_loss_percent: row.stop_loss_percent
            };
            this.dialogVisible = true;
        },

        async submitUpdate() {
            try {
                const params = {
                    id: this.editForm.id,
                    max_position: this.editForm.max_position,
                    min_position: this.editForm.min_position,
                    stop_loss_percent: this.editForm.stop_loss_percent
                };
                post("/Grid/grid/updateStrategyMaxMinPosition", params, response => {
                    if (response.data.code === 10000) {
                        this.$message.success("修改成功");
                        this.dialogVisible = false;
                        this.fetchStrategyList();
                    } else {
                        this.$message.error(response.data.msg || "修改失败");
                    }
                })
            } catch (err) {
                console.error(err);
                this.$message.error("请求失败");
            }
        }
    }
};
</script>

<style scoped>
.el-breadcrumb {
    margin-bottom: 20px;
}
</style>
