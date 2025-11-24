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
  </div>
</template>

<script>
import { mapState } from "vuex";
import { setBuyTokenToSRatio } from "@/wallet/trade"; 
import { getHashpowerPrice } from "@/wallet/serve";
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
       return this.hashPowerPoolsList.filter(item => item.hashpowerAddress && item.updatePricefun);
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
  },
  methods: {
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
        background-color: transparent;
        color: #fff;
    }
    
    ::v-deep .el-table th, 
    ::v-deep .el-table tr {
        background-color: transparent;
        color: #fff;
    }
    
    ::v-deep .el-table--enable-row-hover .el-table__body tr:hover > td {
        background-color: rgba(255, 255, 255, 0.1);
    }
  }
}
</style>

