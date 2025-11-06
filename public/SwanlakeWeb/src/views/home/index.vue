<template>
    <div class="home-container">
        <!-- 英雄区 -->
        <section id="hero" class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">自动化您的加密收益</h1>
                <p class="hero-subtitle">加入我们的全球领先云挖矿服务平台。无需昂贵硬件，每日稳定被动收入。</p>
                <button class="hero-btn" @click="handleRegister">立即开始挖矿</button>
            </div>
        </section>

        <!-- 统计数据部分 -->
        <section id="stats" class="stats-section">
            <div class="stats-content">
                <div class="stats-number">{{ userCount | formatNumber }}</div>
                <div class="stats-label">购买算力币的人数</div>
            </div>
        </section>

        <!-- 为什么选择我们 -->
        <section class="features">
            <div class="section-container">
                <div class="section-title">
                    <h2>为什么选择我们？</h2>
                    <p>我们提供安全、高效、透明的云挖矿服务，帮助您轻松增加财富。</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <h3>顶级技术</h3>
                        <p>采用最新一代矿机，确保最大哈希率效率和稳定性</p>
                    </div>
                    <div class="feature-card">
                        <h3>每日结算</h3>
                        <p>挖矿收益每天自动结算，及时发放到您的钱包</p>
                    </div>
                    <div class="feature-card">
                        <h3>完全透明</h3>
                        <p>实时算力监控和详细收益报告，让您完全掌控</p>
                    </div>
                    <div class="feature-card">
                        <h3>安全可靠</h3>
                        <p>多层安全保护和24/7专家维护，保护您的资产</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 工作原理 -->
        <section class="how-it-works">
            <div class="section-container">
                <div class="section-title">
                    <h2>仅需三个简单步骤</h2>
                    <p>我们设计了一个非常简单的流程，让您的挖矿体验更加轻松</p>
                </div>
                <div class="steps-container">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h3>一秒钟注册</h3>
                        <p>仅需web3钱包</p>
                    </div>
                    <!-- <div class="step-connector"></div> -->
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h3>选择方案</h3>
                        <p>我们提供多种灵活的算力合约，满足不同投资需求</p>
                    </div>
                    <!-- <div class="step-connector"></div> -->
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h3>每日收益</h3>
                        <p>系统自动为您挖矿和结算，收益每日存入钱包</p>
                    </div>
                </div>
            </div>
        </section>


        <!-- 常见问题 -->
        <section class="faq-section">
            <div class="section-container">
                <div class="section-title">
                    <h2>常见问题</h2>
                </div>
                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question" @click="toggleFaq(0)">
                            <span>云挖矿是什么？</span>
                            <i :class="faqOpen[0] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                        </div>
                        <div class="faq-answer" v-show="faqOpen[0]">
                            <p>云挖矿是一种远程挖矿模式。用户通过平台租用算力来赚取加密货币挖矿奖励，无需购买和维护物理矿机。</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" @click="toggleFaq(1)">
                            <span>我的收益是如何计算和分配的？</span>
                            <i :class="faqOpen[1] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                        </div>
                        <div class="faq-answer" v-show="faqOpen[1]">
                            <p>每日收益 = (您的算力 / 网络总算力) × 每日区块奖励 - 平台服务费(包括电费、维护等)。计算完全透明，收益每日自动分配到账户。</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" @click="toggleFaq(2)">
                            <span>云挖矿有风险吗？</span>
                            <i :class="faqOpen[2] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                        </div>
                        <div class="faq-answer" v-show="faqOpen[2]">
                            <p>是的，所有投资都存在风险。云挖矿收益受加密货币价格、挖矿难度和网络状况等多种因素影响，可能会波动。我们建议您在充分了解风险并确保符合您的风险承受能力后再投资。</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 行动号召 -->
        <section class="cta-section">
            <div class="cta-content">
                <h2>准备好开始了吗？</h2>
                <p>加入全球数十万聪明投资者。一分钟内注册开始。</p>
                <button class="cta-btn" @click="handleRegister">立即免费注册</button>
            </div>
        </section>

        <!-- 页脚 -->
        <footer class="main-footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-section">
                        <h4>关于 SWAN LAKE</h4>
                        <p>我们是领先的全球云挖矿服务提供商，致力于让加密货币挖矿对所有人都可获得。</p>
                    </div>
                    <div class="footer-section">
                        <h4>法律</h4>
                        <ul>
                            <li><a href="#">服务条款</a></li>
                            <li><a href="#">隐私政策</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h4>联系我们</h4>
                        <p>Email: <a href="mailto:support@BLOCK EARNING.com">support@BLOCK EARNING.com</a></p>
                        <p>地址: 英国伦敦科文特花园谢尔顿街71-75号，邮编WC2H 9JQ</p>
                    </div>
                </div>
                <!-- <div class="footer-bottom">
                    <p>风险警告: 加密货币投资存在高风险。价格可能会波动，可能导致您的投资资本损失。请确保您充分了解相关风险并考虑您的经验水平和投资目标。</p>
                    <p>&copy; 2025 SWAN LAKE. 版权所有。</p>
                </div> -->
            </div>
        </footer>
    </div>
</template>

<script>
import { get } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
import { getBalance, getGameFillingBalance } from "@/wallet/serve";
import Address from '@/wallet/address.json'

export default {
    name: 'home',
    data() {
        return {
            CNY_USD: 6.70,
            tableData: [],
            h2oBalance: 0,
            walletBalance: 0,
            userCount: 200140,
            totalHashpower: 0,
            totalEarnings: 0,
            faqOpen: [false, false, false],
        }
    },
    computed: {
        ...mapState({
            address: state => state.base.address,
            isConnected: state => state.base.isConnected,
            isMobel: state => state.comps.isMobel,
            mainTheme: state => state.comps.mainTheme,
            apiUrl: state => state.base.apiUrl,
            nftUrl: state => state.base.nftUrl,
            userInfo: state => state.base.userInfo,
            hashPowerPoolsList: state => state.base.hashPowerPoolsList,
        }),
    },
    filters: {
        formatNumber(value) {
            if (!value) return '0';
            return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    },
    created() {
        this.getListData();
    },
    watch: {
        isConnected: {
            immediate: true,
            handler(val) {
                if (val) {
                    if (!this.hashPowerPoolsList.length) {
                        this.$store.dispatch("getHashPowerPoolsList");
                    }
                }
            },
        },
        address: {
            immediate: true,
            async handler(val) {
                if (val) {
                    this.h2oBalance = await getBalance(Address.BUSDT, 18);
                    this.walletBalance = await getGameFillingBalance();
                }
            }
        },
    },
    methods: {
        toFixed(val, length) {
            return (val || 0).toFixed(length);
        },
        toggleFaq(index) {
            this.faqOpen[index] = !this.faqOpen[index];
            this.$forceUpdate();
        },
        handleRegister() {
            this.$router.push({
                name: 'powerBuy',
                params: {
                    type: 1,
                    hash_id: 2,
                }
            })
        },
        buyClick(row) {
            this.$router.push({
                path: '/financial/currentDetail',
                query: {
                    type: 1,
                    product_id: row.id,
                }
            })
        },
        hashpowerBuyClick(row, type) {
            this.$router.push({
                name: 'hashpowerBuy',
                params: {
                    type: type,
                    hash_id: row.id,
                    hashpowerAddress: row.hashpowerAddress
                }
            })
        },
        getListData() {
            let ServerWhere = {
                limit: 100,
                page: 1,
            };
            get(this.apiUrl + "/Api/Product/getProductList", ServerWhere, json => {
                if (json.code == 10000) {
                    this.tableData = json.data.lists;
                }
            });
        },
        routeMyOrder() {
            this.$router.push('/order/record');
        }
    },
}
</script>

<style lang="scss" scoped>
.home-container {
    width: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
    color: #333;
    line-height: 1.6;
    // background-color: #fff;

    .section-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    // 英雄区
    .hero-section {
        // background: linear-gradient(135deg, #3378ff 0%, #0052cc 100%);
        color: #fff;
        padding: 120px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;

        &::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 0%, rgba(0, 232, 137, 0.15), transparent 40%);
            pointer-events: none;
        }

        .hero-content {
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 52px;
            font-weight: 800;
            margin-bottom: 25px;
            line-height: 1.25;
            letter-spacing: -0.5px;
        }

        .hero-subtitle {
            font-size: 18px;
            margin-bottom: 45px;
            opacity: 0.95;
            line-height: 1.6;
            font-weight: 400;
        }

        .hero-btn {
            background: #00e889;
            color: #000;
            border: none;
            padding: 15px 45px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);

            &:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
            }

            &:active {
                transform: translateY(-1px);
            }
        }
    }

    // 统计数据部分
    .stats-section {
        // background: #fff;
        padding: 60px 20px;
        text-align: center;
        // border-bottom: 1px solid #f0f0f0;

        .stats-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .stats-number {
            font-size: 56px;
            font-weight: 800;
            color: #00e889;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }

        .stats-label {
            font-size: 16px;
            color: #fff;
            font-weight: 500;
        }
    }

    // 通用段落标题
    .section-title {
        text-align: center;
        margin-bottom: 50px;

        h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
        }

        p {
            font-size: 16px;
            color: #fff;
        }
    }

    // 特性区
    .features {
        // background: #fff;
        padding: 100px 20px;

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            margin-bottom: 60px;
            color: #fff;
            h2 {
                font-size: 42px;
                font-weight: 700;
                margin-bottom: 15px;
            }

            p {
                font-size: 16px;
            }
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
        }

        .feature-card {
            background: #0a0f0d;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid rgba(0, 232, 137, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;

            &:hover {
                border: 1px solid #00c677;
                .feature-icon {
                    transform: scale(1.1);
                }
            }

            .feature-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #3378ff 0%, #0052cc 100%);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
                color: #fff;
                margin-bottom: 20px;
                transition: all 0.3s ease;
            }

            h3 {
                font-size: 20px;
                font-weight: 600;
                margin-bottom: 12px;
                color: #00e889;
            }

            p {
                font-size: 14px;
                color: #fff;
                line-height: 1.7;
            }
        }
    }

    // 工作原理
    .how-it-works {
        // background: #f0f2f5;
        padding: 80px 20px;

        .steps-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            max-width: 1000px;
            margin: 0 auto;

            .step-card {
                flex: 1;
                // background: #fff;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);

                .step-number {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    border: 2px solid #00e889;
                    background-color: #0a0f0d;
                    // background: linear-gradient(135deg, #3378ff, #0052cc);
                    color: #fff;
                    font-size: 24px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                }

                h3 {
                    font-size: 18px;
                    margin-bottom: 10px;
                    color: #fff;
                }

                p {
                    color: #fff;
                    font-size: 14px;
                }
            }

            .step-connector {
                width: 30px;
                height: 2px;
                background: #3378ff;
            }
        }
    }



    // FAQ 区
    .faq-section {
        // background: #fff;
        padding: 80px 20px;

        .faq-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .faq-item {
            border: 1px solid rgba(0, 232, 137, 0.15);
            border-radius: 8px;
            background-color: #0a0f0d;
            padding: 20px;
            margin-bottom: 15px;

            .faq-question {
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: pointer;
                font-weight: 600;
                color: #fff;
                font-size: 16px;
                transition: color 0.3s;

                &:hover {
                    color: #fff;
                }

                i {
                    color: #fff;
                    transition: transform 0.3s;
                }
            }

            .faq-answer {
                margin-top: 15px;
                color: #fff;
                font-size: 14px;
                animation: slideDown 0.3s ease;

                p {
                    margin: 0;
                    line-height: 1.8;
                }
            }
        }
        .faq-item:hover {
            color: #fff;
            background-color: #1c222b;
        }
                
    }

    // 行动号召
    .cta-section {
        // background: linear-gradient(135deg, #3378ff 0%, #0052cc 100%);
        color: #fff;
        padding: 60px 20px;

        .cta-content {
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        h2 {
            font-size: 36px;
            margin-bottom: 15px;
        }

        p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .cta-btn {
            background: #00e889;
            color: #000;
            border: none;
            padding: 14px 40px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;

            &:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            }
        }
    }

    // 页脚
    .main-footer {
        // background-color: #1a1a1a;
        color: #fff;
        padding: 60px 20px 20px;
        text-align: center;

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 40px;
        }

        .footer-content {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 40px;
        }

        .footer-section {
            flex: 1;
            min-width: 200px;
            text-align: left;

            h4 {
                font-size: 18px;
                margin-bottom: 20px;
                color: #00e889;
            }

            p {
                font-size: 14px;
                color: #fff;
                line-height: 1.8;
            }

            a {
                color: #fff;
             }

            ul {
                list-style: none;
                padding: 0;
                margin: 0;

                li {
                    margin-bottom: 10px;

                    a {
                        color: #fff;
                        text-decoration: none;
                        transition: color 0.3s;

                        &:hover {
                            color: #00e889;
                        }
                    }
                }
            }
        }

        .footer-bottom {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 12px;
            color: #888;

            p {
                margin-bottom: 10px;
            }
        }
    }

    // 响应式设计
    @media (max-width: 768px) {
        .hero-section {
            padding: 50px 20px;

            .hero-title {
                font-size: 28px;
            }

            .hero-subtitle {
                font-size: 16px;
            }

            .hero-btn {
                padding: 10px 30px;
                font-size: 14px;
            }
        }

        .section-title {
            h2 {
                font-size: 24px;
            }
        }

        .how-it-works {
            .steps-container {
                flex-direction: column;

                .step-connector {
                    width: 2px;
                    height: 30px;
                }
            }
        }

        .products-grid {
            grid-template-columns: 1fr !important;
        }

        .features, .account-section, .products-section, .faq-section, .cta-section {
            padding: 40px 20px;
        }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
}
</style>
