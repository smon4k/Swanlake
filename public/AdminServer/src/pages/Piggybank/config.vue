<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>策略配置</span>
        <el-button v-if="!editing" style="float: right;" type="primary" size="small" @click="editing = true">
          编辑
        </el-button>
        <el-button v-if="editing" style="float: right;" type="success" size="small" @click="saveConfig">
          保存
        </el-button>
        <el-button v-if="editing" style="float: right; margin-right: 10px;" size="small" @click="cancelEdit">
          取消
        </el-button>
      </div>

      <el-form :model="form" label-width="200px">
        <el-form-item label="涨跌比例（%）">
          <el-input v-model="form.change_ratio" :disabled="!editing"></el-input>
        </el-form-item>

        <el-form-item label="平衡比例（如 1:1）">
          <el-input v-model="form.balance_ratio" :disabled="!editing"></el-input>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import { get, post, upload } from "@/common/axios.js";

export default {
  data() {
    return {
      form: {
        change_ratio: '',
        balance_ratio: ''
      },
      originalForm: {},
      editing: false
    }
  },
  created() {
    this.fetchConfig()
  },
  methods: {
    async fetchConfig() {
      try {
        get("/Piggybank/index/getPiggybankConfig", {}, json => {
          console.log(json);
        if (json.data.code == 10000) {
          this.form = { ...json.data.data }
          this.originalForm = { ...json.data.data }
        } else {
          this.$message.error("加载数据失败");
        }
      });
      } catch (error) {
        this.$message.error('获取配置失败')
      }
    },
    async saveConfig() {
      try {
        post("/Piggybank/index/savePiggybankConfig", {
          change_ratio: this.form.change_ratio,
          balance_ratio: this.form.balance_ratio,
        }, (json) => {
            console.log(json);
            if (json && json.data.code == 10000) {
                this.editing = false
                this.$message.success('配置保存成功')
                this.fetchConfig();
            } else {
                this.editing = false;
                this.$message.error(json.data.msg);
            }
        })
      } catch (error) {
        this.$message.error('保存失败')
      }
    },
    cancelEdit() {
      this.form = { ...this.originalForm }
      this.editing = false
    }
  }
}
</script>

<style scoped>
.app-container {
  padding: 20px;
}
</style>
