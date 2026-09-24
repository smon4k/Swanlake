<template>
  <div class="container">
    <div class="commin-title">
      <div class="title-inner">
        <span class="tit">算力币管理 (管理员)</span>
      </div>
    </div>
    
    <el-card class="box-card">
      <el-table :data="filteredHashPowerPoolsList" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>
        <el-table-column prop="name" label="名称" align="center"></el-table-column>
        <el-table-column label="合约地址" align="center" width="300">
           <template slot-scope="scope">
             <el-link type="primary" :href="scope.row.chain_address" target="_blank">
               {{ scope.row.hashpowerAddress }}
             </el-link>
           </template>
        </el-table-column>
        <el-table-column label="当前价格 (USDT)" align="center">
          <template slot-scope="scope">
            {{ scope.row.price }}
          </template>
        </el-table-column>
         <el-table-column label="算力 (TH/s)" align="center">
          <template slot-scope="scope">
            {{ scope.row.hash_rate }}
          </template>
        </el-table-column>
        <el-table-column label="操作" align="center" fixed="right" width="150">
          <template slot-scope="scope">
            <el-button type="primary" size="small" @click="handleEdit(scope.row)">修改价格</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- BTC 日理论产出折算系数配置卡片 -->
    <el-card class="box-card" style="margin-top: 25px;">
      <el-table :data="[factorInfo]" style="width: 100%" v-loading="factorLoading">
        <el-table-column label="配置名称" align="center">
          <template>
            <span style="color: #fff; font-size: 14px;">BTC 日产出折算系数 (INCOME_FACTOR)</span>
          </template>
        </el-table-column>
        <el-table-column label="当前系数" align="center">
          <template slot-scope="scope">
            <span style="font-size: 15px; color: #fff;">
              {{ scope.row.income_factor }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="说明" align="center" width="380">
          <template>
            <span style="color: #bbb; font-size: 13px;">
              平台实际结算日产出按矿池理论收益折算计入
            </span>
          </template>
        </el-table-column>
        <el-table-column label="最近更新时间" align="center" width="200">
          <template slot-scope="scope">
            <span style="color: #bbb; font-size: 13px;">
              {{ scope.row.updated_at || '--' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="操作" align="center" fixed="right" width="150">
          <template>
            <el-button type="primary" size="small" @click="handleEditFactor">修改系数</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 修改价格弹窗 -->
    <el-dialog title="修改价格" :visible.sync="dialogVisible" width="30%" :close-on-click-modal="false">
      <el-form label-position="top">
        <el-form-item label="当前价格">
           <el-input v-model="currentEditRow.price" disabled></el-input>
        </el-form-item>
        <el-form-item label="新价格 (USDT)">
          <el-input 
            v-model="newPrice" 
            type="number" 
            placeholder="请输入新价格" 
            :min="0.1"
            @blur="validatePrice"
          ></el-input>
          <div style="font-size: 12px; color: #999; margin-top: 5px;">
            注意：点击确定将唤起钱包调用合约 setBuyTokenToS23Ratio 方法
          </div>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmEdit" :loading="submitting">确 定</el-button>
      </span>
    </el-dialog>

    <!-- 修改日理论产出折算系数弹窗 -->
    <el-dialog title="修改日产出折算系数 (INCOME_FACTOR)" :visible.sync="factorDialogVisible" width="32%" :close-on-click-modal="false">
      <el-form label-position="top">
        <el-form-item label="当前系数">
          <el-input :value="factorInfo.income_factor + ' (' + (Number(factorInfo.income_factor) * 100).toFixed(0) + '%)'" disabled></el-input>
        </el-form-item>
        <el-form-item label="新折算系数 (例如 0.85 或 0.9)">
          <el-input 
            v-model="newFactor" 
            type="number" 
            step="0.01"
            placeholder="请输入新折算系数，例如 0.85"
            :min="0.01"
            :max="5"
          ></el-input>
          <div style="font-size: 12px; color: #E6A23C; margin-top: 5px;">
            提示：修改后将保存配置，并立即触发后台重新计算产出数据，全站实时生效。
          </div>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="factorDialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmEditFactor" :loading="factorSubmitting">保 存 并 生 效</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import { mapState } from "vuex";
import { setBuyTokenToSRatio } from "@/wallet/trade"; 
import { getHashpowerPrice } from "@/wallet/serve";
import { $get, $post } from "@/utils/request";
import axios from "axios";

export default {
  name: "HashpowerAdmin",
  data() {
    return {
      loading: true,
      dialogVisible: false,
      currentEditRow: {},
      newPrice: '',
      submitting: false,
      factorLoading: false,
      factorDialogVisible: false,
      factorSubmitting: false,
      newFactor: '',
      factorInfo: {
        income_factor: 0.85,
        updated_at: '',
        updated_by: ''
      }
    };
  },
  computed: {
    ...mapState({
      hashPowerPoolsList: state => state.base.hashPowerPoolsList,
      isConnected:state=>state.base.isConnected,
      nftUrl: state => state.base.nftUrl,
      address: state => state.base.address, // 管理员钱包地址
    }),
    // 过滤出有效的算力币（必须有合约地址）
    filteredHashPowerPoolsList() {
       return this.hashPowerPoolsList.filter(item => item.hashpowerAddress && item.updatePricefun && item.id !== 3 && item.id !== 4);
    }
  },
  watch: {
    address: {
      immediate: true,
      async handler(val) {
        if(val) {
          if (this.hashPowerPoolsList.length === 0) {
              this.loading = true;
              this.$store.dispatch("getHashPowerPoolsList").finally(() => {
                  this.loading = false;
              });
          }
        } else {
          this.loading = false;
        }
      },
    },
    hashPowerPoolsList: {
      immediate: true,
      handler(val) {
        if(val.length > 0) {
          this.loading = false;
        }
      }
    }
  },
  created() {
    this.fetchIncomeFactor();
  },
  methods: {
    async fetchIncomeFactor() {
      this.factorLoading = true;
      try {
        // 请求后台爬虫服务的系数配置接口（带兜底）
        let data = await $get("https://pacx.h2opower.site/v1.0/get_income_factor");
        if (data && data.code === 10000) {
          this.factorInfo = data.data;
        } else if (data && data.income_factor !== undefined) {
          this.factorInfo = data;
        }
      } catch (err) {
        console.error("获取折算系数失败", err);
      } finally {
        this.factorLoading = false;
      }
    },
    handleEditFactor() {
      this.newFactor = this.factorInfo.income_factor;
      this.factorDialogVisible = true;
    },
    async confirmEditFactor() {
      const val = parseFloat(this.newFactor);
      if (isNaN(val) || val <= 0 || val > 5) {
        this.$message.warning("请输入合法的折算系数（建议在 0.1 到 2 之间）");
        return;
      }

      this.factorSubmitting = true;
      try {
        const payload = {
          income_factor: val,
          address: this.address || ""
        };
        // 使用项目公共 $post 方法发起请求
        const res = await $post("https://pacx.h2opower.site/v1.0/set_income_factor", payload);
        if (res && res.code === 10000) {
          this.$message.success("折算系数修改成功并已立即生效！");
          this.factorInfo = {
            ...this.factorInfo,
            income_factor: res.data.income_factor,
            updated_at: res.data.updated_at,
            updated_by: res.data.updated_by
          };
          this.factorDialogVisible = false;
        } else {
          this.$message.error((res && res.msg) || "修改失败，请重试");
        }
      } catch (err) {
        console.error("修改折算系数失败", err);
        const errMsg = (err.response && err.response.data && err.response.data.msg) || "请求失败，请检查服务连接与跨域配置";
        this.$message.error(errMsg);
      } finally {
        this.factorSubmitting = false;
      }
    },
    validatePrice() {
      if (this.newPrice && parseFloat(this.newPrice) < 0.1) {
        this.newPrice = 0.1;
        this.$message.warning('价格不能低于 0.1');
      }
    },
    async handleEdit(row) {
      this.currentEditRow = { ...row }; // 复制对象，避免直接修改列表显示
      this.newPrice = row.price; // 默认显示当前价格
      this.dialogVisible = true;
      const currentPrice = await getHashpowerPrice(row.hashpowerAddress, 'US23Ratio', 18);
      console.log(currentPrice);
    },
    async confirmEdit() {
      if (!this.newPrice || parseFloat(this.newPrice) < 0.1) {
        this.$message.warning("请输入有效的新价格（不能低于 0.1）");
        return;
      }

      this.submitting = true;
      try {
        // 1. 调用合约方法 setBuyTokenToS23Ratio
        // BuyTokenToSFunction 会将传入的数值 (newPrice) 转换为 Wei (乘以 1e18)
        // 第三个参数为合约方法名
        console.log(this.currentEditRow.hashpowerAddress, this.newPrice, this.currentEditRow.updatePricefun);
        const hash = await setBuyTokenToSRatio(
            this.currentEditRow.hashpowerAddress, 
            this.newPrice,
            this.currentEditRow.updatePricefun
        );

        if (hash) {
             this.$message.success("合约调用成功，正在同步数据库...");
             
             // 2. 调用后端接口更新数据库
             const res = await axios.post(this.nftUrl + "/Hashpower/hashpower/updatePrice", {
                 id: this.currentEditRow.id,
                 price: this.newPrice,
                 hash: hash // 传递交易哈希
             });
             if (res && res.code === 10000) {
                 this.$message.success("价格修改成功");
                 this.dialogVisible = false;
                 this.$store.dispatch("getHashPowerPoolsList"); // 刷新列表
             } else {
                 this.$message.error("数据库更新失败: " + (res.data.msg || res.data.message || "未知错误"));
             }
        }
      } catch (error) {
          console.error(error);
          // 用户拒绝或其他合约错误
          // this.$message.error("操作失败: " + (error.message || "请检查钱包连接或合约状态"));
      } finally {
          this.submitting = false;
      }
    }
  }
};
</script>

<style lang="scss" scoped>
.container {
  padding: 20px;
  min-height: 100vh;
  
  .commin-title {
      margin-bottom: 20px;
      .tit {
          color: #fff;
          font-size: 24px;
          font-weight: bold;
      }
  }

  .box-card {
    background: rgba(255, 255, 255, 0.1); // 保持与原项目一致的深色/透明风格
    border: none;
    color: #fff;
    
    ::v-deep .el-table, 
    ::v-deep .el-table__expanded-cell {
        background-color: transparent !important;
        color: #fff !important;
    }
    
    ::v-deep .el-table th, 
    ::v-deep .el-table tr {
        background-color: transparent !important;
        color: #fff !important;
    }

    ::v-deep .el-table th.is-leaf {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }

    ::v-deep .el-table td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        color: #fff !important;
    }
    
    ::v-deep .el-table--enable-row-hover .el-table__body tr:hover > td {
        background-color: rgba(255, 255, 255, 0.1) !important;
    }
  }
}
</style>

