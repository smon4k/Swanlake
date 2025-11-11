<template>
    <div class="container">
        <!-- <el-page-header @back="$router.go(-1)" :content="$t('subscribe:WhitePaper')"></el-page-header> -->
        <el-container class="main">
            <!-- <el-header class="title">{{ $t("subscribe:WhitePaper") }}</el-header> -->
            <el-main>
                <el-row>
                    <!-- <el-col :span="24" class="images">
                        <el-image :src="require(`@/assets/hashpower_abstract.png`)">
                            <div slot="placeholder" class="image-slot">
                                loading<span class="dot">...</span>
                            </div>
                        </el-image>
                    </el-col> -->
                    <el-col :span="24">
                        <p class="content">
                            &nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-desc") }}
                        </p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-01") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-01-01") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-01-02") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-02") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-02-01") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-03") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-03-01") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-03-02") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-03-03") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-03-04") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-03-05") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-04") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-04-01") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-05") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-05-01") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-06") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-06-01") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-06-02") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-07") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-07-01") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-07-02") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-08") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-08-01") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-08-02") }}</p>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-08-03") }}</p>

                        <h3 class="item">{{ $t("subscribe:WhitePaper-09") }}</h3>
                        <p class="content">&nbsp;&nbsp;&nbsp;&nbsp;{{ $t("subscribe:WhitePaper-09-01") }}</p>
                    </el-col>
                </el-row>
            </el-main>
        </el-container>
    </div>
</template>
<script>
export default {
    data() {
        return {

        }
    }
}
</script>
<style lang="scss" scoped>
.container {
    border-radius: 30px;
    background-color: rgb(14,22,33) !important;

    // padding: 30px;
    .main {
        padding: 20px;
        color: #fff !important;

        .el-main {
            padding: 0;
        }

        .images {
            text-align: center;

            .el-image {
                width: 70%;
            }
        }

        .title {
            text-align: center;
            height: 30px !important;
            // line-height: 30px;
        }

        .content {
            line-height: 30px;
        }

        .item {
            margin-bottom: -10px;
        }
    }

}
</style>