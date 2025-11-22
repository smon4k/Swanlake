<template>
  <div>
    <el-breadcrumb separator="/">
      <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item to="">门票管理</el-breadcrumb-item>
      <el-breadcrumb-item to="">门票统计</el-breadcrumb-item>
    </el-breadcrumb>
    <div class="project-top">
      <el-form :inline="true" class="demo-form-inline" size="mini">
        <el-form-item label="时间:">
          <el-date-picker
            v-model="date"
            type="daterange"
            align="right"
            unlink-panels
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :picker-options="pickerOptions"
            value-format="yyyy-MM-dd"
          >
          </el-date-picker>
          <!-- <el-date-picker
            v-model="date"
            type="date"
            placeholder="选择日期"
            value-format="yyyy-MM-dd"
            :picker-options="pickerOptions"
          >
          </el-date-picker> -->
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="SearchClick()">搜索</el-button>
        </el-form-item>
        <!-- <el-form-item style="float:right;">最新币价：${{ keepDecimalNotRounding(h2oPrice, 4) }}</el-form-item> -->
      </el-form>
    </div>

    <el-descriptions title="">
      <el-descriptions-item label="H2O价格："
        >$ {{ keepDecimalNotRounding(h2oPrice, 4) || 0 }} </el-descriptions-item
      >
      <!-- <el-descriptions-item label="手机号">18100000000</el-descriptions-item> -->
    </el-descriptions>
    <!-- <el-collapse v-model="activeName" accordion v-for="(item, index) in tableData" :key="index">
        <el-collapse-item :title="item.date" :name="item.date"> -->
    <el-table height="500" :data="tableData.list" style="width: 100%" v-loading="loading">
      <el-table-column prop="date" label="日期" align="center" />
      <el-table-column prop="count_watch_bonus" label="观看奖励" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.count_watch_bonus, 2) }} H2O</span>
        </template>
      </el-table-column>
      <el-table-column prop="count_notes_award" label="笔记奖励" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.count_notes_award, 2) }} H2O</span>
        </template>
      </el-table-column>
      <el-table-column prop="count_notes_award" label="奖励合计" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.count_watch_notes, 2) }} H2O</span>
        </template>
      </el-table-column>
      <el-table-column prop="" label="释放压力" align="center">
        <template slot-scope="scope">
          <span>{{ calcReleaseStress(scope.row) }} USDT</span>
        </template>
      </el-table-column>
      <el-table-column prop="ticket_count_capped" label="门票封顶合计" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.ticket_count_capped, 2) }} H2O</span>
        </template>
      </el-table-column>
      <el-table-column prop="ticket_sell_num" label="门票销售数量" align="center">
        <template slot-scope="scope">
          <span>{{ scope.row.ticket_sell_num }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="ticket_sales" label="门票销售额" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.ticket_sales, 2) }} USDT</span>
        </template>
      </el-table-column>
      <el-table-column prop="ticket_sales" label="门票销售总额" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.count_ticket_sales, 2) }} USDT</span>
        </template>
      </el-table-column>
      <el-table-column prop="ticket_sales_premium" label="门票销售保费" align="center">
        <template slot-scope="scope">
          <span>{{ keepDecimalNotRounding(scope.row.ticket_sales_premium, 2) }} USDT</span>
        </template>
      </el-table-column>
    </el-table>
    <!-- </el-collapse-item>
    </el-collapse> -->
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
      class="preview-class"
    >
      <div v-if="fileTempType == 1">
        <el-carousel :interval="5000" :autoplay="false" arrow="always">
          <el-carousel-item v-for="item in imagesUrls" :key="item">
            <!-- <h3>{{ item }}</h3> -->
            <img :src="item" alt="" srcset="" />
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
          disablePictureInPicture="true"
        ></video>
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
import Address from '@/wallet/address.json'
import { getDayDate } from "@/utils/tools.js";
export default {
  data() {
    return {
      currPage: 1, //当前页
      pageSize: 10, //每页显示条数
      total: 100, //总条数
      PageSearchWhere: [], //分页搜索数组
      name: "",
      address: "",
      status: "",
      class_id: "",
      imageUrl: "",
      fileObjData: {},
      tableData: [],
      loading: true,
      srcList: [], //列表存放大图路径
      dialogVisibleShow: false,
      DialogTitle: "添加",
      is_save_add_start: 1, //1：添加 2：修改
      is_img_upload: 0,
      id: "", //球队id
      type: "", //球队类型
      GradeArr: [], //等级数据
      ClassArr: [], //分类数据
      UserAuthUid: 0, //当前登录用户id
      previewFilesShow: false,
      fileTempType: 2,
      dialogWidth: "50%",
      imagesUrls: [],
      videoUrl: "",
      videoPoster: "",
      activeName: "1",
      ticketName: "",
      date: "",
      pickerOptions: {
        disabledDate(time) {
          return time.getTime() > Date.now();
        },
        shortcuts: [{
            text: '最近一周',
            onClick(picker) {
                const end = new Date();
                const start = new Date();
                start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                picker.$emit('pick', [start, end]);
            }
        }, {
            text: '最近一个月',
            onClick(picker) {
              const end = new Date();
              const start = new Date();
              start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
              picker.$emit('pick', [start, end]);
            }
          }, {
            text: '最近三个月',
            onClick(picker) {
              const end = new Date();
              const start = new Date();
              start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
              picker.$emit('pick', [start, end]);
            }
        }]
      },
      h2oPrice: 0,
    };
  },
  created() {
    // const start = getDayDate(-7);
    // const end = getDayDate(0);
    // this.date = [start, end];

    this.UserAuthUid = localStorage.getItem("UserAuthUid");
    const ticketId = this.$route.query.ticket_id;
    this.getListData();

    const ticketName = this.$route.query.ticket_name;
    if (ticketName) {
      this.ticketName = ticketName;
    }
    this.getH2OPrice();
  },
  components: {
    "wbc-page": Page, //加载分页组件
  },
  methods: {
    async getH2OPrice() {
        get("/Admin/Ticket/getH2OPrice", {
            token0: Address.H2O,
            token1: Address.BUSDT
        }, (json) => {
            if (json.data.code == 10000) {
                this.h2oPrice = json.data.data;
            } else {
                this.$message.error("获取H2O价格失败");
            }
        });
        // this.H2OPrice = 1;
    },
    getListData(ServerWhere) {
      this.loading = true;
      var that = this.$data;
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: that.pageSize,
          page: that.currPage,
          start_date: this.date[0],
          end_date: this.date[1],
        };
      }
      get("/Admin/Ticket/getAllTicketDetailsList", ServerWhere, (json) => {
        console.log(json);
        this.loading = false;
        if (json.data.code == 10000) {
          this.tableData = json.data.data.data;
          console.log(json.data.data);
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
    //   console.log(this.date);
      if (this.date && this.date.length > 0) {
        SearchWhere["start_date"] = this.date[0];
        SearchWhere["end_date"] = this.date[1];
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
    resetForm(formName) {
      // console.log(this.$refs[formName])
      this.$refs[formName].resetFields();
      this.dialogVisibleShow = false;
    },
    previewFilesShowClose() {
      if (this.fileTempType == 2) {
        this.$refs.player.pause(); //暂停
      }
      this.previewFilesShow = false;
    },
    calcReleaseStress(row) { //计算释放压力
        let num = row.count_watch_notes * this.h2oPrice;
        return this.toFixed(num, 2);
    }
    
  },
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
  border-color: #409eff;
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
  margin-top: 0 !important;
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

.el-carousel__item:nth-child(2n + 1) {
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
