<template>
  <div>
    <quill-editor class="editor" ref="myQuillEditor" v-model="content" :disabled="disabled" :options="options"></quill-editor>
    <el-upload
      action
      :before-upload="beforeUpload"
      :file-list="fileList"
      :accept="accept"
      style="display: none;"
      ref="upload"
    >
      <el-button :id="imgInput" v-loading.fullscreen.lock="fullscreenLoading"></el-button>
    </el-upload>
  </div>
</template>

<script>
import "quill/dist/quill.core.css";
import "quill/dist/quill.snow.css";
import "quill/dist/quill.bubble.css";
import { quillEditor } from "vue-quill-editor";
// import { uploadFile } from "@/common/upload.js";
import { post, upload } from "@/common/axios.js";
export default {
  name: "editor",
  props: {
    value: String,
    // options: Object,
    disabled: {
      type: Boolean,
      default: false
    },
    imgInput: "", //上传按钮id
  },
  data: function() {
    return {
      content: "",
      options: {
        modules: {
          toolbar: [
            // ["bold", "italic", "underline", "strike"], // 加粗 斜体 下划线 删除线
            // ["blockquote", "code-block"], // 引用  代码块
            // [{ header: 1 }, { header: 2 }], // 1、2 级标题
            // [{ list: "ordered" }, { list: "bullet" }], // 有序、无序列表
            // [{ script: "sub" }, { script: "super" }], // 上标/下标
            // [{ indent: "-1" }, { indent: "+1" }], // 缩进
            // [{'direction': 'rtl'}],                         // 文本方向
            // [{ size: ["small", false, "large", "huge"] }], // 字体大小
            // [{ header: [1, 2, 3, 4, 5, 6, false] }], // 标题
            // [{ color: [] }, { background: [] }], // 字体颜色、字体背景颜色
            // [{ font: [] }], // 字体种类
            // [{ align: [] }], // 对齐方式
            ["link"], // 链接、图片、视频
            ["clean"], // 清除文本格式
            // ["link", "image", "video"] // 链接、图片、视频
          ]
        },
        placeholder: "请输入关键字回复内容"
      },
      fullscreenLoading: false,
      uploadType: "",
      addRange: [],
      fileList: [],
      accept: "image/*"
    };
  },
  components: {
    quillEditor
  },
  methods: {
    imgHandler(state) {
      // console.log(this.imgInput);
      // 点击图片ICON触发事件
      this.addRange = this.$refs.myQuillEditor.quill.getSelection();
      this.accept = "image/*";
      this.uploadType = "image";
      if (state) {
        setTimeout(() => {
          let fileInput = document.getElementById(this.imgInput);
          fileInput.click(); // 加一个触发事件
        }, 100);
      }
    },
    videoHandler(state) {
      // 点击视频ICON触发事件
      this.addRange = this.$refs.myQuillEditor.quill.getSelection();
      this.accept = "video/*";
      this.uploadType = "video";
      if (state) {
        setTimeout(() => {
          let fileInput = document.getElementById(this.imgInput);
          fileInput.click(); // 加一个触发事件
        }, 100);
      }
    },
    // 图片上传前获得数据token数据
    beforeUpload(file) {
      this.fullscreenLoading = true;
      let filename = "";
      const suffix = file.name.split(".");
      const ext = suffix.splice(suffix.length - 1, 1)[0];
      if (this.uploadType === "image") {
        // 如果是点击插入图片
        filename = `/ser/ueditor/image/${
          file.size
        }-${new Date().getTime()}.${ext}`;
      } else if (this.uploadType === "video") {
        // 如果是点击插入视频
        filename = `/ser/ueditor/video/${
          file.size
        }-${new Date().getTime()}.${ext}`;
      }
      // this.upScuccess({
      //   name: "/ser/ueditor/image/178357-1542771859371.jpeg",
      //   url: "http://jiali-bucket.oss-cn-beijing.aliyuncs.com//ser/ueditor/image/178357-1542771859371.jpeg",
      // });
        var formData = new FormData();
        formData.append("file", file); //‘file’是後台接收文件名
        upload('/api/files/upload', formData, json => {
          if(json) {
            this.upScuccess(json);
          } else {
            this.$message.error("图片上传失败");
          }
        })
      // uploadFile({
      //   file: file,
      //   name: filename,
      //   onSuccess: ret => {
      //     // ret = {
      //     //   name: "app/content/1234-1234.png"
      //     //   res: {status: 200, statusCode: 200, headers: {…}, size: 0, aborted: false, …}
      //     //   url: "http://jiali-bucket.oss-cn-beijing.aliyuncs.com/app/content/1234-1234.png"
      //     // }
      //     this.upScuccess(ret);
      //   },
      //   onError: err => {
      //     this.$message.error(`图片添加失败`);
      //   }
      // });
      return false;
    },
    upScuccess(ret) {
      // 图片上传成功回调   插入到编辑器中
      //console.log(ret)
      this.fullscreenLoading = false;
      let vm = this;
      let url = ret.url;
      if (this.uploadType === "image") {
        // 获得文件上传后的URL地址
        // url = STATICDOMAIN + e.key
      } else if (this.uploadType === "video") {
        // url = STATVIDEO + e.key
      }
      if (url != null && url.length > 0) {
        // 将文件上传后的URL地址插入到编辑器文本中
        let value = url; // 获取光标位置对象，里面有两个属性，一个是index 还有 一个length，这里要用range.index，即当前光标之前的内容长度，然后再利用 insertEmbed(length, 'image', imageUrl)，插入图片即可。
        vm.addRange = vm.$refs.myQuillEditor.quill.getSelection();
        vm.$refs.myQuillEditor.quill.insertEmbed(
          vm.addRange !== null ? vm.addRange.index : 0,
          vm.uploadType,
          value
        ); // 调用编辑器的 insertEmbed 方法，插入URL
      } else {
        this.$message.error(`${vm.uploadType}插入失败`);
      }
      this.$refs["upload"].clearFiles(); // 插入成功后清除input的内容
    }
  },
  mounted() {
    // 编辑器初始赋值
    if (this.value) {
      this.content = this.value;
    }

    // 为图片ICON绑定事件  getModule 为编辑器的内部属性
    this.$refs.myQuillEditor.quill
      .getModule("toolbar")
      .addHandler("image", this.imgHandler);
    this.$refs.myQuillEditor.quill
      .getModule("toolbar")
      .addHandler("video", this.videoHandler); // 为视频ICON绑定事件
  },
  watch: {
    content(newVal, oldVal) {
      if (newVal && newVal !== this.value) {
        this.$emit("input", newVal);
      }
    },
    value(newVal, oldVal) {
      if (newVal && newVal !== this.content) {
        this.content = newVal;
      } else if (!newVal) {
        this.content = "";
      }
    }
  }
};
</script>
<style scoped>
.editor-btn {
  margin-top: 20px;
}
</style>
