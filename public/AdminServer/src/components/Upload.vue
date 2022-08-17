<template>
  <div style="display:inline-block;">
    <a href="javascript:;" @click="upload()" :id="id">
      {{text}}
      <slot name="content"></slot>
    </a>
  </div>
</template>
<script>
import { Loading } from 'element-ui';
export default {
  props: {
    text: {
      type: String,
      default: ""
    },
    id: {
      type: String
    },
    cb: {
      type: Function
    },
    label: {
      type: String
    }
  },
  data() {
    return {
      uploader: null,
      token: {},
    };
  },
  methods: {
    fileSizeFormat(bytes) {
      var label = ["B", "KB", "MB", "GB", "TB", "PB"];
      for (
        var i = 0;
        bytes >= 1024 && i < label.length - 1;
        bytes /= 1024, i++
      ) {}
      return parseFloat(bytes).toFixed(2) + " " + label[i];
    },
    upload() {}
  },
  mounted() {
    let plupload = window.plupload;
    let staticOption = { //配置uploader
      runtimes: "html5,flash,silverlight,html4",
      browse_button: this.id,
      id: this.id,
      url: "/Api/File/uploadFile/token/" + localStorage.getItem("token"),
      multi_selection: false,
      multiple_queues: true,
      unique_names: true,
      resize: {},
      filters: {
        mime_types: [
          {
            title: "选择文件",
            // extensions: "xls,xlsx,xlsm"
            extensions: "csv"
          }
        ],
        max_file_size : '100mb',
      }
    };

    this.uploader = new plupload.Uploader(staticOption);
    this.uploader.init();
    var that = this;
    this.uploader.bind('Error', function (uploader, errObject) { //异常处理
        var message = '';
        if (errObject.code === -600) {
            message = '上传的文件太大';
        } else if (errObject.code === -200) {
            message = '由于网络原因,文件没有上传成功';
        } else if (errObject.code === -601) {
            message = '请上传csv文件格式类型表格数据';
        }
        that.$notify({
          message: message,
          type: "error"
        });
    });

    this.uploader.bind("FilesAdded", function(uploader, files) { //选中文件触发
      that.uploader.start();
    });

    this.uploader.bind("FileUploaded", function(uploader, file, resObj) { //文件上传后触发
      var msg = JSON.parse(resObj.response);
      if(msg.result) {
        that.$emit("importData", {
          file_url: msg.file,
          type: that.label
        });
      }
    });

    // this.axios
    //   .get(this.utils.basepath + "/Api/Import/Config")
    //   .then(res => {
    //     this.token = res.data.data;
    //     // console.log(this.token);
    //     let staticOption = { //配置uploader
    //       runtimes: "html5,flash,silverlight,html4",
    //       browse_button: this.id,
    //       id: this.id,
    //       url: "http://" + this.token.upload_token.host + "/",
    //       multi_selection: false,
    //       multiple_queues: true,
    //       unique_names: true,
    //       resize: {},
    //       filters: {
    //         mime_types: [
    //           {
    //             title: "选择文件",
    //             extensions: "xls,xlsx,xlsm"
    //           }
    //         ]
    //       }
    //     };
    //     this.uploader = new plupload.Uploader(staticOption);
    //     this.uploader.init();
    //   })
    //   .then(() => {
    //     let that = this;
    //     this.uploader.bind('Error', function (uploader, errObject) { //异常处理
    //         var message = '';
    //         if (errObject.code === -600) {
    //             message = '上传的文件太大';
    //         } else if (errObject.code === -200) {
    //             message = '由于网络原因,文件没有上传成功';
    //         } else if (errObject.code === -601) {
    //             message = '请上传csv文件格式类型表格数据';
    //         }
    //         that.$notify({
    //           message: message,
    //           type: "error"
    //         });
    //     });
    //     this.uploader.bind("FilesAdded", function(uploader, files) { //选中文件触发
    //       let filename =
    //         that.token.pre + files[0].id + "." + files[0].name.split(".")[1];
    //       that.filePath =
    //         "http://" + that.token.upload_token.host + "/" + filename;
    //       that.uploader.setOption({
    //         multipart_params: {
    //           key: filename,
    //           policy: that.token.upload_token.policy,
    //           OSSAccessKeyId: that.token.upload_token.accessid,
    //           success_action_status: "200",
    //           signature: that.token.upload_token.signature
    //         }
    //       });
    //       that.uploader.start();
    //     });
    //     this.uploader.bind("FileUploaded", function(uploader, file, resObj) { //文件上传后触发
    //       var msg = JSON.parse(resObj.response);
    //       if(msg.result) {
    //         that.$emit("importData", {
    //           file_url: that.filePath,
    //           type: that.label
    //         });
    //       }
    //     });
    //   });
  }
};
</script>

