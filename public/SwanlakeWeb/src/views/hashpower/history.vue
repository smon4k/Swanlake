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
      <div class="common-assets-list">
        <div class="head live">
          <div class="kind">{{ $t('subscribe:Time') }}</div>
          <div class="kind">{{ $t('subscribe:Amount') }}</div>
          <div class="kind">{{ $t('subscribe:Directions') }}</div>
          <div class="kind">{{ $t('subscribe:ViewBSCscan') }}</div>
        </div>
        <transition-group name="fade-transform" mode="out-in" tag="div" v-loading="listLoading">
          <div class="body" key="live">
            <div v-for="(item, index) in hashPowerList" :key="index" class="item live" v-loading="item.loading">
              <div class="kind">
                <div>
                  <span>{{ item.time }}</span>
                </div>
              </div>
              <div class="kind">
                <div>
                  <span>{{ item.amount }}</span>
                </div>
              </div>
              <div class="kind">
                <p class="bold">{{ item.status == 1 ? $t('subscribe:buy') : '' }}</p>
              </div>
              <div class="kind">
                <a :href="domainHostAddress + item.hash" target="_blank">
                  <img :src="require(`@/assets/view-data.png`)" width="20" />
                </a>
              </div>
              <!-- <div class="opera">
                <div
                  :class="['live', { disabled: !Number(item.reward) }]"
                  @click="receiveH2OReward(item)"
                  v-loading="item.claimLoading"
                >
                  Harvest
                </div>
                <div class="live" @click="toDetail(1, item)">Deposit</div>
                <div class="pick" @click="toDetail(2, item)">Withdraw</div>
              </div> -->
            </div>
            <div class="noresult" v-if="!hashPowerList.length && !listLoading">
              <!-- {{ $t('public:nothing') }} -->
              <el-empty :description="$t('public:nothing')"></el-empty>
            </div>
          </div>
        </transition-group>
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
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  color: #fff;
  border: 1px solid rgba(0, 232, 137, 0.1);
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

  .common-assets-list {
    .head {
      >div {
        width: 10.5%;
        color: #fff;
        font-weight: 600;
      }
    }

    .head.live {
      >div {
        width: 100%;
        text-align: center;
        color: #fff;
        font-weight: 600;
      }

      .opera {
        width: 25%;
      }
    }

    .body {
      .item {
        .kind {
          span {
            color: #fff;
            font-weight: 600;
          }
        }
        .reward {
          flex-direction: row;
          justify-content: flex-start;
          align-items: center;
        }

        .el-icon-question {
          display: inline-block;
          width: 12px;
        }

        >div {
          width: 10.5%;
        }

        .days {
          width: 14%;
        }

        .opera {
          width: 16%;

          .live {
            margin-right: 5px;
            position: relative;

            ::v-deep {
              .el-loading-mask {
                border-radius: 15px;
              }

              .el-loading-spinner .circular {
                height: 22px;
                width: 22px;
              }

              .el-loading-spinner {
                margin-top: -11px;
              }
            }
          }

          .live.disabled {
            @include enterDisabled($enterDisabled-light);
            color: #fff;
            cursor: not-allowed;
          }
        }
      }

      .item.live {
        >div {
          width: 100%;
          text-align: center;
          font-size: 15px;
          color: #fff;
        }
      }
    }
  }

  .common-assets-list.IRO {
    .body {
      .item {
        >div {
          width: 42%;
        }

        .opera {
          width: 16%;
        }
      }
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