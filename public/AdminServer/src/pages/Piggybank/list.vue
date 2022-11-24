<template>
  <div>
    <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
        <el-breadcrumb-item to="">存钱罐管理</el-breadcrumb-item>
        <el-breadcrumb-item to="">订单列表</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
        <h2>BTC/USDT 策略详情</h2>
      <!-- <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="产品名称:">
          <el-input clearable placeholder="产品名称" v-model="product_name"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
        </el-form-item>
      </el-form> -->
    </div>

    <el-collapse v-model="activeNames" @change="handleChange" v-for="(item, index) in tableData" :key="index">
        <el-collapse-item :title="item.time" :name="index">
            <template slot="title">
                <div style="margin-right:100px;">{{ item.time }}</div>
                <div style="margin-right:100px;">价差：{{ keepDecimalNotRounding(item.price, 4, true) }} USDT</div>
                <div>利润：{{ keepDecimalNotRounding(item.profit, 8, true) }} USDT</div>
            </template>
            <el-table :data="item.lists" style="width: 100%;" v-show="true">
                <!-- <el-table-column sortable prop="id" label="ID" width="100" align="center" fixed="left"></el-table-column> -->
                <!-- <el-table-column prop="product_name" label="产品名称" align="center"></el-table-column> -->
                <!-- <el-table-column prop="order_number" label="订单号" align="center" width="200"></el-table-column> -->
                <!-- <el-table-column prop="td_mode" label="交易模式" align="center"></el-table-column>
                <el-table-column prop="order_type" label="订单类型" align="center">
                    <template slot-scope="scope">
                    <span v-if="scope.row.order_type == 'market'">市价单</span>
                    <span v-else>其他</span>
                    </template>
                </el-table-column> -->
                <!-- <el-table-column prop="base_ccy" label="交易货币币种" align="center"></el-table-column>
                <el-table-column prop="quote_ccy" label="计价货币币种" align="center"></el-table-column> -->
                <el-table-column prop="" label="时间" align="center" width="200">
                    <template slot-scope="scope">
                      <span>{{ scope.row.time ? scope.row.time : "--"}}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="type" label="方向" align="center">
                    <template slot-scope="scope">
                      <div v-if="scope.row.type">
                        <span v-if="scope.row.type == 1" style="color:#05C48E">买入</span>
                        <span v-else style="color:#df473d;">卖出</span>
                      </div>
                      <div v-else>{{ scope.row.type_str }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="amount" label="委托数量" align="center" width="150">
                    <template slot-scope="scope">
                      <div v-if="scope.row.clinch_number">
                        <div v-if="scope.row.type == 1">
                            <span>{{ keepDecimalNotRounding(scope.row.clinch_number, 8, true) }} {{scope.row.base_ccy}}</span>
                            <br>
                            <span>{{ keepDecimalNotRounding(scope.row.clinch_number * scope.row.price, 8, true) }} {{scope.row.quote_ccy}}</span>
                        </div>
                        <div v-else>
                            <span>{{ keepDecimalNotRounding(scope.row.clinch_number, 8, true) }} {{scope.row.base_ccy}}</span>
                            <br>
                            <span>{{ keepDecimalNotRounding(scope.row.clinch_number * scope.row.price, 8, true) }} {{scope.row.quote_ccy}}</span>
                        </div>
                      </div>
                      <div v-else>——</div>
                    </template>
                </el-table-column>
                <el-table-column prop="capped" label="成交数量" align="center">
                    <template slot-scope="scope">
                        <div v-if="scope.row.clinch_number">
                          <span>{{ keepDecimalNotRounding(scope.row.clinch_number, 8, true) }} {{scope.row.base_ccy}}</span>
                        </div>
                        <div v-else>——</div>
                    </template>
                </el-table-column>
                <el-table-column prop="capped" label="价格/均价" align="center">
                    <template slot-scope="scope">
                      <div v-if="scope.row.price">
                        <span>{{ keepDecimalNotRounding(scope.row.price, 4, true) }}/{{ keepDecimalNotRounding(scope.row.make_deal_price, 8, true) }}  {{scope.row.quote_ccy}}</span>
                      </div>
                      <div v-else>——</div>
                    </template>
                </el-table-column>
                <el-table-column prop="" label="币种权益" align="center" width="200">
                    <template slot-scope="scope">
                      <div v-if="scope.row.currency1 && scope.row.currency2">
                        <span>{{ keepDecimalNotRounding(scope.row.currency1, 4, true) }} | ${{ keepDecimalNotRounding(scope.row.currency1 * scope.row.price, 4, true)}} {{scope.row.base_ccy}}</span>
                        <br>
                        <span>{{ keepDecimalNotRounding(scope.row.currency2, 4, true) }} | ${{ keepDecimalNotRounding(scope.row.currency2, 4, true) }} {{scope.row.quote_ccy}}</span>
                      </div>
                      <div v-else>——</div>
                    </template>
                </el-table-column>
                <el-table-column prop="profit" label="利润" align="center" width="150">
                    <template slot-scope="scope">
                      <div v-if="scope.row.profit">
                        <span>{{ keepDecimalNotRounding(scope.row.profit, 8, true) }} USDT</span>
                      </div>
                      <div v-else>——</div>
                    </template>
                </el-table-column>
            </el-table>
        </el-collapse-item>
    </el-collapse>

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
      product_name: "",
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
      activeNames: ['1'],
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
      get("/Admin/Piggybank/getPiggybankOrderList", ServerWhere, json => {
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
      if (this.product_name && this.product_name !== "") {
        SearchWhere["product_name"] = this.product_name;
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
    },
    handleChange() {

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
  .project-top {
    margin-bottom: 20px;
    margin-top: 20px;
  }
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

  /deep/ {
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
