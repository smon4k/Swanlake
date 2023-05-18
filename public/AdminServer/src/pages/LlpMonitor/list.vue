<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">用户管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">用户列表</el-breadcrumb-item>
    </el-breadcrumb>
    <!-- <div class="project-top">
      <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="钱包地址:">
          <el-input clearable placeholder="钱包地址" v-model="address"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
        </el-form-item>
      </el-form>
    </div> --> 
    <el-table :data="tableData" style="width: 100%;" v-loading="loading">
      <!-- <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left"></el-table-column> -->
      <el-table-column prop="date" fixed="left" label="Date" align="center" width="120">
        <template slot-scope="scope">
            <span v-if="!scope.row.id">Current</span>
            <span v-else>{{ scope.row.date }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="amount" label="流动性" align="center" width="">
        <template slot-scope="scope">
            <span>${{ numberFormat(scope.row.amount, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="value" label="估值" align="center" width="">
        <template slot-scope="scope">
            <span>${{ numberFormat(scope.row.value, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="valueChange" label="出入金" align="center" width="">
        <template slot-scope="scope">
            <span>${{ numberFormat(scope.row.valueChange, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="fee" label="手续费" align="center" width="">
        <template slot-scope="scope">
            <span v-if="scope.row.fee < 0" style="color:red;">-${{ numberFormat(Math.abs(scope.row.fee), 2) }}</span>
            <span v-else style="color:#0ecb81;">+${{ numberFormat(scope.row.fee, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="pnl" label="输赢" align="center" width="">
        <template slot-scope="scope">
            <span v-if="scope.row.pnl < 0" style="color:red;">-${{ numberFormat(Math.abs(scope.row.pnl), 2) }}</span>
            <span v-else style="color:#0ecb81;">+${{ numberFormat(scope.row.pnl, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="price" label="资产估值变动" align="center" width="">
        <template slot-scope="scope">
            <span v-if="scope.row.price < 0" style="color:red;">-${{ numberFormat(Math.abs(scope.row.price), 2) }}</span>
            <span v-else style="color:#0ecb81;">+${{ numberFormat(scope.row.price, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="totalChange" label="总变化" align="center" width="">
        <template slot-scope="scope">
            <span v-if="scope.row.totalChange < 0" style="color:red;">-${{ numberFormat(Math.abs(scope.row.totalChange), 2) }}</span>
            <span v-else style="color:#0ecb81;">+${{ numberFormat(scope.row.totalChange, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="nominalApr" label="名义利润率" align="center" width="">
        <template slot-scope="scope">
            <span v-if="scope.row.nominalApr < 0" style="color:red;">-{{ numberFormat(Math.abs(scope.row.nominalApr), 2) }}%</span>
            <span v-else style="color:#0ecb81;">+{{ numberFormat(scope.row.nominalApr, 2) }}%</span>
        </template>
      </el-table-column>
      <el-table-column prop="netApr" label="净利润率" align="center" width="">
        <template slot-scope="scope">
            <span v-if="scope.row.netApr < 0" style="color:red;">-{{ numberFormat(Math.abs(scope.row.netApr), 2) }}%</span>
            <span v-else style="color:#0ecb81;">+{{ numberFormat(scope.row.netApr, 2) }}%</span>
        </template>
      </el-table-column>
       <el-table-column prop="btc_price" label="比特币价" align="center" width="">
        <template slot-scope="scope">
            <span>{{ numberFormat(scope.row.btc_price, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="llp_price" label="LLP价格" align="center" width="">
        <template slot-scope="scope">
            <span>{{ numberFormat(scope.row.llp_price, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="netProfit" label="净利润" align="center" width="">
        <template slot-scope="scope">
            <span>{{ numberFormat(scope.row.netProfit, 2) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="totalProfit" label="总净利" align="center" width="">
        <template slot-scope="scope">
            <span>{{ numberFormat(scope.row.totalProfit, 2) }}</span>
        </template>
      </el-table-column>
      <!-- <el-table-column prop="time" label="注册时间" width="180" align="center"></el-table-column> -->
    </el-table>
    <el-row class="pages">
      <el-col :span="24">
        <div style="float:right;">
          <wbc-page
            :total="total"
            :pageSize="pageSize"
            :currPage="currPage"
            @changeLimit="limitPaging"
            @changeSkip="skipPaging"
          ></wbc-page>
        </div>
      </el-col>
    </el-row>


    <el-dialog
      title=""
      :visible.sync="previewFilesShow"
      :width="dialogWidth"
      :before-close="previewFilesShowClose"
      class="preview-class">
      <div v-if="fileTempType == 1">
          <el-carousel :interval="5000" :autoplay="false" arrow="always">
            <el-carousel-item v-for="item in imagesUrls" :key="item">
              <!-- <h3>{{ item }}</h3> -->
              <img :src="item" alt="" srcset="">
            </el-carousel-item>
          </el-carousel>
      </div>
      <div v-if="fileTempType == 2">
        <video 
          id="videoPlay"
          ref="player" 
          class="video"
          controls 
          autoplay
          preload="auto"
          :src="videoUrl"
          :poster="videoPoster"
          v-if="videoUrl"
          controlslist="noplaybackrate nodownload nofullscreen noremoteplayback"
          disablePictureInPicture="true"></video>
      </div>
      <!-- <span slot="footer" class="dialog-footer">
        <el-button @click="previewFilesShow = false">取 消</el-button>
        <el-button type="primary" @click="previewFilesShow = false">确 定</el-button>
      </span> -->
    </el-dialog>
  </div>
</template>
<script>
import Page from "@/components/Page.vue";
import { get, post, upload } from "@/common/axios.js";
import { numberFormat } from "@/utils/tools.js";
export default {
  data() {
    return {
      currPage: 1, //当前页
      pageSize: 20, //每页显示条数
      total: 100, //总条数
      PageSearchWhere: [], //分页搜索数组
      tableData: [],
      loading: false,
    };
  },
  methods: {
    getListData(ServerWhere) {
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
        };
      }
      this.loading = true;
      get("/Admin/Llpfinance/getLlpFinanceList", ServerWhere, json => {
          console.log(json);
          this.loading = false;
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          this.total = json.data.data.count;
        } else {
          this.$message.error("加载数据失败");
        }
      });
    },
    // getClassList() {
    //   get("/H2omarketplace/H2onft/getClassList", {}, (json) => {
    //       // console.log(json);
    //     if (json.data.code == 10000) {
    //       this.ClassArr = json.data.data;
    //     } else {
    //       this.$message.error("加载数据失败");
    //     }
    //   });
    // },
    SearchClick() {
      //搜索事件
      var SearchWhere = {
        page: this.currPage,
        limit: this.pageSize,
      };
      if (this.nickname && this.nickname !== "") {
        SearchWhere["nickname"] = this.nickname;
      }
      if (this.address && this.address !== "") {
        SearchWhere["address"] = this.address;
      }
      if (this.h2oSort && this.h2oSort !== "") {
        SearchWhere["h2oSort"] = this.h2oSort;
      }
      if (this.usdtSort && this.usdtSort !== "") {
        SearchWhere["usdtSort"] = this.usdtSort;
      }
      this.PageSearchWhere = [];
      this.PageSearchWhere = SearchWhere;
      this.getListData(SearchWhere);
    },
    SearchReset() {
      //搜索条件重置
      this.PageSearchWhere = [];
      this.getListData();
    },
    limitPaging(limit) {
      //赋值当前条数
      this.pageSize = limit;
      if (
        this.PageSearchWhere.limit &&
        this.PageSearchWhere.limit !== undefined
      ) {
        this.PageSearchWhere.limit = limit;
      }
      this.getListData(this.PageSearchWhere); //刷新列表
    },
    skipPaging(page) {
      //赋值当前页数
      this.currPage = page;
      if (
        this.PageSearchWhere.page &&
        this.PageSearchWhere.page !== undefined
      ) {
        this.PageSearchWhere.page = page;
      }
      this.getListData(this.PageSearchWhere); //刷新列表
    },
    DelData(row) { //删除管理员
        this.$confirm('此操作将永久删除该数据, 是否继续?', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).then(() => {
            get('/Admin/Notes/delNotesRow', {id: row.id}, (json) => {
                if (json && json.data.code == 10000) {
                    this.getListData();
                    this.$message({
                        type: 'success',
                        message: '删除成功!'
                    });
                } else if(json.data.code == 10007) {
                  this.$message.error(json.data.msg);
                } else {
                    this.$message.error(json.data.msg);
                }
            })
        }).catch(() => {
            this.$message({
                type: 'info',
                message: '已取消删除'
            });
        });
    },
    resetForm(formName) {
      // console.log(this.$refs[formName])
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    },
    rackUpStart(row, status) { //审批 修改状态
        get('/Admin/Notes/rackUpStart', {id: row.id, status: status}, (json) => {
            if(json.data.code == 10000) {
                this.$message({
                    message: '操作成功',
                    type: 'success'
                });
                this.getListData();
            } else if(json.data.code == 10007) {
                this.$message.error(json.data.msg);
            } else {
                this.$message.error('修改失败');
            }
        })
    },
    previewFilesShowClose() {
      if(this.fileTempType == 2) {
        this.$refs.player.pause();//暂停
      }
      this.previewFilesShow = false;
    },
    getPreviewFiles(row) { //预览
        this.imagesUrls = row.avatar;
        console.log(row);
    }

  },
  created() {
    this.getListData();
    this.UserAuthUid = localStorage.getItem("UserAuthUid");
  },
  components: {
    "wbc-page": Page, //加载分页组件
  }
};
</script>
<style lang="scss" scoped>
/deep/ {
    .pages {
        margin-top: 0!important;
        margin-bottom: 80px !important;
    }
    .el-breadcrumb {
        z-index: 10 !important;
    }
    .el-table {
        font-size: 10px;
    }

    .preview-class {
      img {
        width: 100%;
        height: 100%;
        object-fit: contain;
      }
      video {
        // position: absolute;
        width: 100%;
        height: 70vh;
      }
      .el-dialog__headerbtn {
        z-index: 1000;
        color: rgb(3, 3, 3);
        .el-dialog__close {
          color: rgb(3, 3, 3);
          font-size: 20px;
          font-weight: 900;
        }
      }
      .el-dialog__header {
        padding: 0 !important;
      }
      .el-dialog__body {
        padding: 0;
      }
      /* height: 100%; */
    }
  }
</style>
