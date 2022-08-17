<template>
  <div class="scale-title">
    <div class="scale-name">
      <span v-if="index">{{index}}.</span>
      {{title}}
      &nbsp;
      <el-tag type="primary" v-if="match && type !== 'preview'">{{match}}</el-tag>
      <el-tag type="success" v-if="reverse!=='false' && reverse && type !== 'preview'">反</el-tag>
    </div>
    <el-radio-group v-model="checked">
      <el-radio v-for="(item,index) in items" :key="index" :label="item.val">{{index+1}}.&nbsp;{{item.name}}</el-radio>
    </el-radio-group>
  </div>
</template>
<script>
export default {
  props: {
    title: {
      type: String,
      required: true
    },
    isAble: {
      type: Boolean,
      default: true
    },
    index: [Number, String],
    reverse: {
      type: [Boolean, String],
      default: false
    },
    match: [Number, String],
    type: [String]
  },
  data() {
    return {
      items: [
        {
          name: "非常不符合",
          val: 1
        },
        {
          name: "不符合",
          val: 2
        },
        {
          name: "有点不符合",
          val: 3
        },
        {
          name: "有点符合",
          val: 4
        },
        {
          name: "符合",
          val: 5
        },
        {
          name: "非常符合",
          val: 6
        }
      ],
      checked: ""
    };
  }
};
</script>
<style scoped>
.scale-title {
  margin-bottom: 15px;
}
.scale-name {
  margin-bottom: 20px;
}
</style>


