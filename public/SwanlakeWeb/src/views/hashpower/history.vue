<template>
  <div class="container">
    <div class="commin-title">
      <div class="title-inner">
        <!-- <img src="@/assets/pledge_tit.png" alt="" /> -->
        <!-- <span class="tit">{{ $t('subscribe:YourHistory') }}</span> -->
        <!-- <el-page-header @back="$router.go(-1)" :content="$t('subscribe:YourHistory')"></el-page-header> -->
        <!-- <div 
          :class="[ 'btn' , {'active':activeNav === 'live'}]"
          @click="changeNav('live')">Demand stake</div>
      <div
          :class="['btn',{'active':activeNav === 'ding'}]"
          @click="changeNav('ding')">Time stake</div> -->
      </div>
    </div>
    <div class="app-inner">
      <!-- 桌面端表格布局 -->
      <div v-if="!isMobel">
        <el-table
          v-loading="listLoading"
          :data="hashPowerList"
          style="width: 100%">
          <el-table-column
            prop="time"
            :label="$t('subscribe:Time')"
            align="center"
            width="">
            <template slot-scope="scope">
              <span>{{ scope.row.time }}</span>
            </template>
          </el-table-column>
          <el-table-column
            prop="amount"
            :label="$t('subscribe:Amount')"
            align="center"
            width="">
            <template slot-scope="scope">
              <span>{{ scope.row.amount }}</span>
            </template>
          </el-table-column>
          <el-table-column
            prop="price"
            :label="$t('subscribe:Price')"
            align="center"
            width="">
            <template slot-scope="scope">
              <span>{{ scope.row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column
            prop="count_price"
            :label="$t('subscribe:CountPrice')"
            align="center"
            width="">
            <template slot-scope="scope">
              <span>{{ toFixed(scope.row.count_price, 2) }}</span>
            </template>
          </el-table-column>
          <!-- <el-table-column
            prop="status"
            :label="$t('subscribe:Directions')"
            align="center"
            width="">
            <template slot-scope="scope">
              <span class="bold">{{ scope.row.status == 1 ? $t('subscribe:buy') : '' }}</span>
            </template>
          </el-table-column> -->
          <el-table-column
            prop="hash"
            :label="$t('subscribe:ViewBSCscan')"
            align="center"
            width="">
            <template slot-scope="scope">
              <a :href="domainHostAddress + scope.row.hash" target="_blank">
                <img :src="require(`@/assets/view-data.png`)" width="20" />
              </a>
            </template>
          </el-table-column>
        </el-table>
      </div>
      
      <!-- 移动端描述列表布局 -->
      <div v-else>
        <div v-if="hashPowerList.length" v-loading="listLoading">
          <el-descriptions :colon="false" :border="false" :column="1" title="" v-for="(item, index) in hashPowerList" :key="index">
            <el-descriptions-item :label="$t('subscribe:Time')">{{ item.time }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscribe:Amount')">{{ item.amount }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscribe:Directions')">{{ item.status == 1 ? $t('subscribe:buy') : '' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscribe:ViewBSCscan')">
              <a :href="domainHostAddress + item.hash" target="_blank">
                <img :src="require(`@/assets/view-data.png`)" width="20" />
              </a>
            </el-descriptions-item>
          </el-descriptions>
        </div>
        <div v-else-if="!listLoading">
          <el-empty :description="$t('public:nothing')"></el-empty>
        </div>
      </div>
      <div class="common-page-outer">
          <el-row class="pages" v-if="total > pageSize">
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
        <!-- <el-pagination layout="prev, pager, next" :total="1"> </el-pagination> -->
      </div>
    </div>
  </div>
</template>
<script>
import axios from 'axios'
import { mapState } from "vuex";
import Page from "@/components/Page.vue";
// import { get, post, upload } from "@/components/axios.js";
export default {
  name: "PledgeMining",
  data() {
    return {
      currPage: 1, //当前页
      pageSize: 20, //每页显示条数
      total: 10, //总条数
      hashPowerList: [],
      listLoading: true,
      PageSearchWhere: [], //分页搜索数组
    };
  },
  created() {
  },
  activated() {
    //页面进来
    //   this.refreshData();
  },
  beforeRouteLeave(to, from, next) {
    //页面离开
    next();
  },
  watch: {
    isConnected: {
      immediate: true,
      async handler(val) {
        if (val) {
          this.getListData();
        }
      }
    }
  },
  mounted() { },
  computed: {
    ...mapState({
      isConnected: state => state.base.isConnected,
      address: state => state.base.address,
      nftUrl: state => state.base.nftUrl,
      domainHostAddress: state => state.base.domainHostAddress,
      isMobel: state => state.comps.isMobel,
    }),
  },
  components: {
    "wbc-page": Page, //加载分页组件
  },
  methods: {
    getListData(ServerWhere) {
      if (!ServerWhere || ServerWhere == undefined || ServerWhere.length <= 0) {
        ServerWhere = {
          limit: this.pageSize,
          page: this.currPage,
          address: this.address,
        };
      }
      axios.get(this.nftUrl + "/hashpower/Hashpower/getHashPowerList", {
        params: ServerWhere
      }).then((json) => {
        // console.log(json);
        // console.log(this.address);
        if (json.code == 10000) {
          this.hashPowerList = json.data.lists;
          this.total = json.data.count;
          this.listLoading = false;
        } else {
          this.$message.error("加载数据失败");
        }
      }).catch((error) => {
        this.$message.error(error);
      });
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
      this.PageSearchWhere.address = this.address;
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
      this.PageSearchWhere.address = this.address;
      this.getListData(this.PageSearchWhere); //刷新列表
    },
  },
};
</script>
<style lang="scss" scoped>
.container {
  border-radius: 38px;
  // min-height: 268px;
  color: #fff;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);

  // padding: 30px;
  .noresult {
    line-height: 60px;
    text-align: center;
    font-weight: 600;
    padding-top: 20px;
    color: #fff;
  }

  .commin-title {
    .btn {
      display: inline-block;
      padding: 0 17px;
      height: 30px;
      // border: 1px solid #333333;
      // color: #333333;
      border-style: solid;
      border-width: 1px;
      border-radius: 15px;
      line-height: 28px;
      vertical-align: middle;
      margin-left: 8px;
      cursor: pointer;
      position: relative;
      @include commonbtn($commonbtn-light);
      box-sizing: border-box;
    }

    .btn.active {
      background: linear-gradient(90deg, #0096ff, #0024ff);
      color: #fff;
      // border-color: transparent;
      border: none;
      line-height: 30px;
    }

    .tit {
      padding-right: 14px;
      display: inline-block;
      font-weight: 800;
      font-size: 13px;  
      color: #fff;
    }
  }

  .commin-title.IRO {
    margin-top: 30px;
    display: flex;
    justify-content: space-between;
    padding-right: 23px;
    font-size: 14px;

    .boxtit {
      color: #999999;
      font-weight: 600;
      display: inline-block;
      margin-right: 10px;
    }

    .num {
      font-weight: 600;
    }

    .btn {
      display: inline-block;
      width: 72px;
      height: 32px;
      background: linear-gradient(90deg, #0096ff, #0024ff);
      border-radius: 15px;
      text-align: center;
      line-height: 32px;
      font-size: 14px;
      color: #fff;
      margin-left: 15px;
      cursor: pointer;
      border: none;
      padding: 0;
    }

    .btn.disable {
      @include enterDisabled($enterDisabled-light);
      color: #fff;
      cursor: not-allowed;
    }
  }

  // 表格样式
  ::v-deep .el-table {
    background: transparent;
    color: #fff;
    
    .el-table__header-wrapper {
      .el-table__header {
        background: transparent;
        
        th {
          background: transparent;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          color: #fff;
          font-weight: 600;
        }
      }
    }
    
    .el-table__body-wrapper {
      .el-table__body {
        background: transparent;
        
        tr {
          background: transparent;
          
          &:hover {
            background: rgba(255, 255, 255, 0.05);
          }
          
          td {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #fff;
            
            .bold {
              font-weight: 600;
            }
          }
        }
      }
    }
  }
  
  // 移动端描述列表样式
  ::v-deep .el-descriptions {
    margin-bottom: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.02);
    
    .el-descriptions__label {
      color: #fff;
      font-weight: 600;
    }
    
    .el-descriptions__content {
      color: #fff;
    }
  }

  .common-page-outer {
    margin-top: 20px;
    padding: 20px;
    
    ::v-deep .wbc-page {
      color: #fff;
    }
    .el-pager li.active {
      color: #fff !important;
    }
  }
}
</style>