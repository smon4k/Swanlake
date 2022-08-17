<template>
    <div>
        <div v-for="(item, index) in ReplyContentList" :key="index">
            <el-row>
            <el-col :span="24">
                <div @mouseover="item.seen = true" @mouseleave="item.seen = false">
                <span v-if="item.type == 'text'" v-html="item.content" class="media-text"></span>
                <span v-if="item.type == 'image'" class="media-text">
                    <img :src="item.url" alt="" srcset="" width="180">
                </span>
                <span v-if="item.type == 'voice'" class="media-text">
                    <audio controls>
                    <source :src="item.url" type="audio/mpeg">
                    </audio>
                </span>
                <span v-if="item.type == 'miniprogrampage'" class="media-text">
                    <el-card class="box-card">
                    <div slot="header" class="clearfix">
                        <img src="@/assets/Applets02.png" alt="" width="20" style="vertical-align: middle;">
                        <span style="font-size:10px;color:#B2B2B2;">XXX</span>
                        <br>
                        <span>{{item.content.title}}</span>
                    </div>
                    <div class="text item">
                        <img :src="item.url" class="image" width="260" height="208">
                    </div>
                    <div style="margin:5px 0 0 -10px;">
                        <img src="@/assets/Applets01.png" alt="" width="15" style="vertical-align: middle;"><span style="font-size:10px;margin-left:5px;color:#B2B2B2;">小程序</span>
                    </div>
                    </el-card>
                </span>
                <span class="media_button">
                    <el-tooltip v-if="item.type == 'text' || item.type == 'miniprogrampage'" class="item" effect="dark" content="编辑" placement="top-start">
                        <el-button type="primary" icon="el-icon-edit" circle v-if="item.seen && item.seen !== 'false'" @click="onTextSaveIndex(index, item)"></el-button>
                    </el-tooltip>
                    <el-tooltip class="item" effect="dark" content="删除" placement="top-start">
                        <el-button size="danger" icon="el-icon-delete" circle v-if="item.seen && item.seen !== 'false'" @click="onTextDelIndex(index, item)"></el-button>
                    </el-tooltip>
                </span>
                </div>
            </el-col>
            </el-row>
            <el-divider></el-divider>
            <!-- <el-divider v-if="ReplyContentList.length > 0"></el-divider> -->
            <!-- <div style="float:left;font-size:18px;margin-left:20px;"> -->
            <!-- <el-button size="small" icon="el-icon-delete" circle style="border:1px solid red;width: 100px;min-height:100px;"></el-button> -->
            <!-- </div> -->
            <!-- <br> -->
        </div>
        <div style="margin-top:10px;" v-if="ReplyContentList.length < 5">
            <el-popover
                placement="right-start"
                width="330"
                trigger="hover">
                <div>
                    <el-link icon="el-icon-document" :underline="false" style="font-size:15px;" @click="onTextClick">文字</el-link>
                    <el-divider direction="vertical"></el-divider>
                    <el-link icon="el-icon-picture-outline" :underline="false" style="font-size:15px;" @click="onImagesClick">图片</el-link>
                    <el-divider direction="vertical"></el-divider>
                    <el-link icon="el-icon-video-pause" :underline="false" style="font-size:15px;" @click="onVoiceClick">音频</el-link>
                    <el-divider direction="vertical"></el-divider>
                    <el-link icon="el-icon-paperclip" :underline="false" style="font-size:15px;" @click="onMiniprogrampageClick">小程序</el-link>
                    <!-- <el-divider direction="vertical"></el-divider> -->
                    <!-- <el-link icon="el-icon-video-play" :underline="false" style="font-size:15px;">视频</el-link> -->
                </div>
                <el-button slot="reference" icon="el-icon-star-off" circle></el-button>
            </el-popover>
        </div>

        <!-- 回复文字弹框 -->
        <el-dialog
          title="提示"
          :visible.sync="dialogEditor"
          width="80%">
        <editor
            ref="editor"
            v-model="ReplyContent"
            :options="editorOption"
            imgInput="editor"
            style="margin-left:4px;"
        ></editor>
        <span style="float:right;">请输入文字，按下Enter键换行</span>
        <span slot="footer" class="dialog-footer">
          <el-button @click="dialogEditor = false">取 消</el-button>
          <el-button type="primary" @click="EditorTextSubmit">确 定</el-button>
        </span>
      </el-dialog>

      <!-- 回复图片\音频弹框 -->
      <el-dialog
        :title="'选择'+getTitleName()"
        :visible.sync="dialogImages"
        width="50%">
        <div style="color:#9A9A9A;text-align:right;margin-bottom:10px;">
          <span>大小不超过10M，已关闭图片水印</span>
          <el-button @click="updateMater()">上传{{getTitleName()}}</el-button>
        </div>
        <div class="desktop-div-picker">
          <ul v-for="(item,index) in wechatMaterList" :key="index" class="desktop-ul-picker">
            <li class="desktop-li-picker" :class="SelectedImageCurrent.id == item.id ? 'selected' : ''">
              <i v-if="item.type == 1" role="img" aria-describedby="图片描述" title="图片描述" class="desktop-img-picker_img" :class="SelectedImageCurrent.id == item.id ? 'selected' : ''" @click="addImageClass(item)" :style="'background-image: url('+item.url+')'">
              </i>
              <i role="img" v-if="item.type == 2" class="desktop-img-picker_img" :class="SelectedImageCurrent.id == item.id ? 'selected' : ''" @click="addImageClass(item)">
                <img src="@/assets/voice.png" alt="" width="110">
              </i>
              <strong class="desktop-img-picker_img-title">{{item.local_name}}</strong>
            </li>
          </ul>
        </div>
        <el-row>
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
        <span slot="footer" class="dialog-footer">
          <el-button @click="dialogImages = false">取 消</el-button>
          <el-button type="primary" @click="clickImageSubmit">确 定</el-button>
        </span>
      </el-dialog>

      <!-- 回复小程序卡片 -->
      <el-dialog
          title="小程序卡片"
          :visible.sync="dialogMiniprogrampage"
          width="50%">
          <el-form ref="MiniprogrampageForm" :rules="MiniprogrampageRules" :model="MiniprogrampageForm" label-width="180px">
            <el-form-item label="标题：" prop="title">
              <el-input v-model="MiniprogrampageForm.title" placeholder="请输入标题"></el-input>
            </el-form-item>
            <el-form-item label="小程序AppId：" prop="appid">
              <el-input v-model="MiniprogrampageForm.appid" placeholder="请输入小程序AppId"></el-input>
              <span style="color:red;">注意：小程序appid必须与所选公众号为关联关系</span>
            </el-form-item>
            <el-form-item label="小程序卡片跳转路径：" prop="pagepath">
              <el-input v-model="MiniprogrampageForm.pagepath" placeholder="请输入小程序跳转路径"></el-input>
              <span style="color:red;">示例：index?foo=bar</span>
            </el-form-item>
            <el-form-item label="小程序卡片封面图：" prop="thumb_media_id">
              <img :src="MiniprogrampageImage" alt="" srcset="" width="100" v-if="MiniprogrampageForm.thumb_media_id" @click="onMiniprogrampageImgClick">
              <div v-else>
                <el-button size="small" @click="onMiniprogrampageImgClick">选择封面图</el-button>
                <span style="font-size:13px;color:#5F6D81;"><i class="el-icon-warning-outline"></i> 小程序卡片图片建议大小为520*416</span>
              </div>
            </el-form-item>
          </el-form>
        <span slot="footer" class="dialog-footer">
          <el-button @click="dialogMiniprogrampage = false">取 消</el-button>
          <el-button type="primary" @click="MiniprogrampageSubmit('MiniprogrampageForm')">确 定</el-button>
        </span>
      </el-dialog>
    </div>
</template>
<script>
import editor from "@/components/editor.vue";
import Page from "@/components/Page.vue";
import {get,post} from '@/common/axios.js'
export default {
    props: ['wechatSource'],
    data() {
        return {
            editorOption: {
                placeholder: "请输入关键字回复内容"
            },
            MiniprogrampageForm: {
                title: '',
                appid: '',
                pagepath: '',
                thumb_media_id: '',
            },
            MiniprogrampageRules: {
                title: [
                    { required: true, message: '请输入标题', trigger: 'blur' }
                ],
                appid: [
                    { required: true, message: '请输入小程序AppId', trigger: 'blur' }
                ],
                pagepath: [
                    { required: true, message: '请输入小程序跳转路径', trigger: 'blur' }
                ],
                thumb_media_id: [
                    { required: true, message: '请选择小程序封面图', trigger: 'blur' }
                ],
            },
            dialogEditor: false, //文本弹框
            dialogImages: false,//图片弹框
            dialogMiniprogrampage: false, //小程序弹框
            ReplyContent: '', //文本内容
            ReplyContentList: [],
            EditorTextCount: 300, //文字内容长度
            isSaveTextContentIndex: null, //回复文字修改索引值
            choiceType: '', //选择类型 1：图片 2：音频 3：视频
            currPage: 1, //当前页
            pageSize: 20, //每页显示条数
            total: 100, //总条数
            wechatMaterList: [], //素材列表
            SelectedImageCurrent: {}, //选中图片对象
            SelectedType: '',
            MiniprogrampageImage: '', //选择小程序卡片封面图图片 临时
        }
    },
    methods: {
        onTextClick() { //回复文字事件
            this.$emit('onFormDataValidate', (res) => {
                if(res) {
                    this.ReplyContent = "";
                    this.isSaveTextContentIndex = null;
                    this.dialogEditor = true;
                }
            });
        },
        onImagesClick() { //点击图片事件
            this.$emit('onFormDataValidate', (res) => { //增加父组件表单验证
                if(res) {
                    this.currPage = 1;
                    this.SelectedType = 'image';
                    this.getWechatMater(1); //获取图片素材列表
                    this.SelectedImageCurrent = {};
                    this.dialogImages = true;
                }
            })
        },
        onVoiceClick() { //点击音频事件
            this.$emit('onFormDataValidate', (res) => { //增加父组件表单验证
                if(res) {
                    this.currPage = 1;
                    this.SelectedType = 'voice';
                    this.getWechatMater(2); //获取音频素材列表
                    this.SelectedImageCurrent = {};
                    this.dialogImages = true;
                }
            })
        },
        onMiniprogrampageClick() { //点击小程序事件
            this.$emit('onFormDataValidate', (res) => { //增加父组件表单验证
                if(res) {
                    // this.$refs['MiniprogrampageForm'] ? this.$refs['MiniprogrampageForm'].resetFields() : '';
                    this.MiniprogrampageForm.title = "";
                    this.MiniprogrampageForm.appid = "";
                    this.MiniprogrampageForm.pagepath = "";
                    this.MiniprogrampageForm.thumb_media_id = "";
                    this.MiniprogrampageImage = "";
                    this.currPage = 1;
                    this.SelectedType = 'miniprogrampage';
                    this.dialogMiniprogrampage = true;
                }
            })
        },
        onMiniprogrampageImgClick() { //小程序卡片选择素材按钮事件
            this.getWechatMater(1); //获取图片素材列表
            this.SelectedType = 'miniprogrampage';
            this.dialogImages = true;
        },
        MiniprogrampageSubmit(formName) { //选择小程序确定事件
            this.$refs[formName].validate((valid) => {
                if (valid) {
                    if(this.isSaveTextContentIndex !== null) { //修改操作
                    this.ReplyContentList[this.isSaveTextContentIndex].content = this.copyDeep(this.MiniprogrampageForm);
                    this.ReplyContentList[this.isSaveTextContentIndex].url = this.SelectedImageCurrent.url;
                    this.isSaveTextContentIndex = null;
                    } else {
                    this.ReplyContentList.push({type:'miniprogrampage', url: this.SelectedImageCurrent.url, content: this.copyDeep(this.MiniprogrampageForm), seen: false});
                    }
                    this.dialogMiniprogrampage = false;
                }
            })
        },
        EditorTextSubmit() { //文字弹框确定事件
            if(this.ReplyContent == "" || this.ReplyContent.length <= 0) {
            this.$message.error("内容不能为空");
            return false;
            }
            if(this.isSaveTextContentIndex !== null) {
            this.ReplyContentList[this.isSaveTextContentIndex].content = this.ReplyContent;
            this.isSaveTextContentIndex = null;
            } else {
            this.ReplyContentList.push({type:'text', content: this.ReplyContent, seen: false});
            }
            this.dialogEditor = false;
        },
        addImageClass(item) { //选择图片/音频添加class事件
            this.SelectedImageCurrent = item;
        },
        clickImageSubmit() { //选择完图片/音频弹框确定事件
            if(JSON.stringify(this.SelectedImageCurrent)=='{}') {
            this.$message.error("请选择素材");
            return false;
            }
            if(this.SelectedType == 'miniprogrampage') { //小程序卡片选择图片
            this.MiniprogrampageImage = this.SelectedImageCurrent.url;
            this.MiniprogrampageForm.thumb_media_id = this.SelectedImageCurrent.media_id;
            } else {
            if(this.SelectedImageCurrent.type == 1) { //图片
                this.ReplyContentList.push({type:'image', url: this.SelectedImageCurrent.url, media_id: this.SelectedImageCurrent.media_id, seen: false});
            } else if(this.SelectedImageCurrent.type == 2) { //音频
                this.ReplyContentList.push({type:'voice', url: this.SelectedImageCurrent.url, media_id: this.SelectedImageCurrent.media_id, seen: false});
            }
            }
            this.dialogImages = false;
        },
        onTextSaveIndex(index, item) { //点击修改事件
            if(item.type == 'text') {
            this.dialogEditor = true;
            this.isSaveTextContentIndex = index;
            this.ReplyContent = item.content;
            } else if(item.type == 'miniprogrampage') {
            this.dialogMiniprogrampage = true;
            this.isSaveTextContentIndex = index;
            this.MiniprogrampageForm = item.content;
            this.MiniprogrampageImage = item.url;
            }
        },
        onTextDelIndex(index, item) { //回复文字点击删除事件
            for(let i=0;i<this.ReplyContentList.length;i++){
                if(i == index){
                    this.ReplyContentList.splice(i, 1);
                }
            }
        },
        getWechatMater(type) { //获取微信素材列表
            this.choiceType = type;
            get("/Api/Mater/MaterList", {page: this.currPage, limit:this.pageSize, type:type, wechat_source: this.wechatSource}, json => {
                if (json.data.code == 10000) {
                // console.log(json.data);
                this.wechatMaterList = json.data.data.data;
                this.total = json.data.data.count;
                } else {
                this.$message.error("加载数据失败");
                }
            });
        },
        limitPaging(limit) {
            //赋值当前条数
            this.pageSize = limit;
            this.getWechatMater(this.choiceType); //刷新列表
        },
        skipPaging(page) {
            //赋值当前页数
            this.currPage = page;
            this.getWechatMater(this.choiceType); //刷新列表
        },
        updateMater() { //跳转到上传素材页面
            this.$router.push("/index/exam/index/mater/materlist?type="+this.SelectedType);
        },
        getTitleName() {
            if(this.SelectedType == 'image') {
                return "图片";
            } else if(this.SelectedType == 'voice') {
                return "音频";
            } else if(this.SelectedType == 'miniprogrampage'){
                return "封面图";
            } else {
                return "文件";
            }
        },
        copyDeep(templateData) { //对象数据双向绑定深复制
            return JSON.parse(JSON.stringify(templateData));
        }
    },
    watch: {
        ReplyContentList(val, oldVal) {
            this.$emit('RefreshFarsParentReplyContentList', oldVal); //父组件数据实时更新
        }
    },
    components: {
        editor,
        "wbc-page": Page, //加载分页组件
    },
    created() {
        // console.log(formData);
    }
}
</script>
<style>
    .edit_container {
        font-family: 'Avenir', Helvetica, Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-align: center;
        color: #2c3e50;
        margin-top: 60px;
    }
    .ql-editor{
         height:400px;
     }

    /* 以下回复内容图片样式 */
    .media-text {
      display: inline-block;
      vertical-align: middle;
      position: relative;
      /* width: 90%; */
      line-height: normal;
    }
     .media_button {
      /* display: table-caption; */
      top: 25%;
      vertical-align: middle;
      position: relative;
      margin:6px 0 0 6px;
    }

    .desktop-div-picker{
      position: relative;
      overflow: auto; 
      height: 474px;
      /* margin-left:15px; */
    }
    .desktop-ul-picker {
        margin: 0 auto;
        float: left;
        overflow: auto;
    }
    .desktop-li-picker {
        cursor: pointer;
        position: relative;
        float: left;
        width: 110px;
        margin: 0 13px 20px 0;
    }
    .desktop-img-picker_img {
        position: relative;
        display: block;
        width: 110px;
        height: 110px;
        -webkit-background-size: contain;
        background-size: contain;
        background-position: 50% 50%;
        background-repeat: no-repeat;
        box-sizing: border-box;
        border-radius: 2px;
        overflow: hidden;
    }
    .desktop-li-picker.selected .desktop-img-picker_img {
      box-shadow: 0 0 0 2px #07C160 inset;
    }
    .desktop-img-picker_img:hover {
      box-shadow: 0 0 0 2px #07C160 inset;
    }
    .desktop-img-picker_img.selected::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background-color: #07C160;
        opacity: 0.1;
    }
    .desktop-img-picker_img-title {
      margin-top: 8px;
      display: block;
      width: auto;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      word-wrap: normal;
      font-weight: 400;
      line-height: 20px;
      text-align: center;
  }
  .el-card__header {
    padding: 5px;
  }
  .el-card__body {
    padding:20px 20px 5px 20px;
  } 
  .el-form-item .el-form-item {
      margin-bottom: 22px;
  }
  /* .el-row {
    margin-top: 0!important;
  } */
</style>