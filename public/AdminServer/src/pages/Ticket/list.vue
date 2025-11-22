<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">门票管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">门票列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="门票名称:">
          <el-input clearable placeholder="名称" v-model="name"></el-input>
        </el-form-item>
        <!-- <el-form-item label="状态">
          <el-select v-model="status" clearable placeholder="请选择状态">
            <el-option label="待审批" value="1">待审批</el-option>
            <el-option label="已上架" value="2">已上架</el-option>
            <el-option label="已拒绝" value="3">已拒绝</el-option>
          </el-select>
        </el-form-item> -->
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
          <!-- <el-button class="pull-right" type="primary" @click="AddUserInfoShow()">添加NFT</el-button> -->
        </el-form-item>
      </el-form>
      <!-- <el-button class="pull-right" type="primary" @click="AddUserInfoShow()">添加NFT</el-button> -->
      <!-- <el-button @click="AddUser()">添加管理员</el-button> -->
    </div>
    <el-table :data="tableData" style="width: 100%;">
      <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left"></el-table-column>
      <el-table-column prop="name" label="名称" align="center"></el-table-column>
      <el-table-column prop="price" label="售价" width="150" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.price || 0, 1) }} USDT</span>
        </template>
      </el-table-column>
      <el-table-column prop="payback_period" label="回本周期" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.payback_period }} 天</span>
        </template>
      </el-table-column>
      <el-table-column prop="capped" width="150" label="封顶值" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.capped, 2, true) }} H2O</span>
        </template>
      </el-table-column>
      <el-table-column sortable prop="browse_amount" label="预览奖励" width="100" align="center"></el-table-column>
      <el-table-column sortable prop="like_amount" label="点赞奖励" width="100" align="center"></el-table-column>
      <el-table-column sortable prop="comment_amount" label="评论奖励" width="100" align="center"></el-table-column>
      <el-table-column sortable prop="reward_amount" label="打赏奖励" width="100" align="center"></el-table-column>
      <el-table-column sortable prop="count_number" label="总量" width="100" align="center"></el-table-column>
      <el-table-column sortable prop="sell_number" label="出售数量" width="100" align="center"></el-table-column>
      <el-table-column label="类型" align="center">
        <template slot-scope="scope">
            <!-- <el-switch v-model="scope.row.status" :active-value="1" :inactive-value="0" @change="startOnShow(scope.row)"></el-switch> -->
            <span v-if="scope.row.state == 0" style="color:#909399;">无奖励</span>
            <span v-if="scope.row.state == 1" style="color:#E6A23C;">奖励</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="100" align="center">
        <template slot-scope="scope">
          <div>
            <el-button type="text" @click="getTicketDetails(scope.row)">明细</el-button>
          </div>
          <!-- <el-button type="text" @click="UpdateAdminUserInfo(scope.row)">修改</el-button> -->
        </template>
      </el-table-column>
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
export default {
  data() {
    return {
      currPage: 1, //当前页
      pageSize: 20, //每页显示条数
      total: 100, //总条数
      PageSearchWhere: [], //分页搜索数组
      name: "",
      address: "",
      status: "",
      class_id: "",
      imageUrl: '',
      fileObjData: {},
      tableData: [],
      srcList: [], //列表存放大图路径
      dialogVisibleShow: false,
      DialogTitle: '添加',
      is_save_add_start: 1, //1：添加 2：修改
      is_img_upload: 0, 
      id: '', //球队id
      type: '', //球队类型
      GradeArr: [], //等级数据
      ClassArr: [], //分类数据
      UserAuthUid: 0, //当前登录用户id
      previewFilesShow: false,
      fileTempType: 2,
      dialogWidth: '50%',
      imagesUrls: [],
      videoUrl: '',
      videoPoster: '',
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
      get("/Admin/Ticket/getTicketList", ServerWhere, json => {
          console.log(json);
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
      if (this.name && this.name !== "") {
        SearchWhere["name"] = this.name;
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
    getTicketDetails(row) { //预览
      // console.log(row);
      this.$router.push({
          name: 'TicketListDetails',
          query: {
              ticket_id: row.id,
              ticket_name: row.name
          }
      })
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
  .avatar-uploader .el-upload {
    border: 1px dashed #d9d9d9;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
  }
  .avatar-uploader .el-upload:hover {
    border-color: #409EFF;
  }
  .avatar-uploader-icon {
    font-size: 28px;
    color: #8c939d;
    width: 178px;
    height: 178px;
    line-height: 178px;
    text-align: center;
  }
  .avatar {
    /* width: 178px; */
    width: 100%;
    height: 230px;
    display: block;
  }
  .el-radio-group .el-radio + .el-radio {
    margin-left: 0 !important;
  }
  .pages {
    margin-top: 0!important;
    margin-bottom: 80px !important;
  }
  .el-breadcrumb {
    z-index: 10 !important;
  }

  .el-carousel__item h3 {
    color: #475669;
    font-size: 18px;
    opacity: 0.75;
    line-height: 300px;
    margin: 0;
  }
  
  .el-carousel__item:nth-child(2n) {
    background-color: #99a9bf;
  }
  
  .el-carousel__item:nth-child(2n+1) {
    background-color: #d3dce6;
  }

  ::v-deep {
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
