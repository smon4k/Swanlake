<template>
    <div class="home-container">
        <!-- 英雄区 -->
        <section id="hero" class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">{{ $t('home:HeroTitle') }}</h1>
                <p class="hero-subtitle">{{ $t('home:HeroSubtitle') }}</p>
                <button class="hero-btn" @click="handleRegister">{{ $t('home:StartMining') }}</button>
            </div>
        </section>

        <!-- 统计数据部分 -->
        <section id="stats" class="stats-section">
            <div class="stats-content">
                <div class="stats-number">{{ userCount | formatNumber }}</div>
                <div class="stats-label">{{ $t('home:BuyHashpowerCount') }}</div>
            </div>
        </section>

        <!-- 为什么选择我们 -->
        <section class="features">
            <div class="section-container">
                <div class="section-title">
                    <h2>{{ $t('home:WhyChooseUs') }}</h2>
                    <p>{{ $t('home:WhyChooseUsDesc') }}</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <h3>{{ $t('home:TopTechnology') }}</h3>
                        <p>{{ $t('home:TopTechnologyDesc') }}</p>
                    </div>
                    <div class="feature-card">
                        <h3>{{ $t('home:DailySettlement') }}</h3>
                        <p>{{ $t('home:DailySettlementDesc') }}</p>
                    </div>
                    <div class="feature-card">
                        <h3>{{ $t('home:FullyTransparent') }}</h3>
                        <p>{{ $t('home:FullyTransparentDesc') }}</p>
                    </div>
                    <div class="feature-card">
                        <h3>{{ $t('home:SafeReliable') }}</h3>
                        <p>{{ $t('home:SafeReliableDesc') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 工作原理 -->
        <section class="how-it-works">
            <div class="section-container">
                <div class="section-title">
                    <h2>{{ $t('home:ThreeSimpleSteps') }}</h2>
                    <p>{{ $t('home:ThreeSimpleStepsDesc') }}</p>
                </div>
                <div class="steps-container">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h3>{{ $t('home:OneSecondRegister') }}</h3>
                        <p>{{ $t('home:OneSecondRegisterDesc') }}</p>
                    </div>
                    <!-- <div class="step-connector"></div> -->
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h3>{{ $t('home:ChoosePlan') }}</h3>
                        <p>{{ $t('home:ChoosePlanDesc') }}</p>
                    </div>
                    <!-- <div class="step-connector"></div> -->
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h3>{{ $t('home:DailyRewards') }}</h3>
                        <p>{{ $t('home:DailyRewardsDesc') }}</p>
                    </div>
                </div>
            </div>
        </section>


        <!-- 常见问题 -->
        <section class="faq-section">
            <div class="section-container">
                <div class="section-title">
                    <h2>{{ $t('home:FAQ') }}</h2>
                </div>
                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question" @click="toggleFaq(0)">
                            <span>{{ $t('home:Q1') }}</span>
                            <i :class="faqOpen[0] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                        </div>
                        <div class="faq-answer" v-show="faqOpen[0]">
                            <p>{{ $t('home:A1') }}</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" @click="toggleFaq(1)">
                            <span>{{ $t('home:Q2') }}</span>
                            <i :class="faqOpen[1] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                        </div>
                        <div class="faq-answer" v-show="faqOpen[1]">
                            <p>{{ $t('home:A2') }}</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" @click="toggleFaq(2)">
                            <span>{{ $t('home:Q3') }}</span>
                            <i :class="faqOpen[2] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                        </div>
                        <div class="faq-answer" v-show="faqOpen[2]">
                            <p>{{ $t('home:A3') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 行动号召 -->
        <section class="cta-section">
            <div class="cta-content">
                <h2>{{ $t('home:ReadyToStart') }}</h2>
                <p>{{ $t('home:ReadyToStartDesc') }}</p>
                <button class="cta-btn" @click="handleRegister">{{ $t('home:StartMining') }}</button>
            </div>
        </section>

        <!-- 页脚 -->
        <footer class="main-footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-section">
                        <h4>{{ $t('home:AboutBlockEarning') }}</h4>
                        <p>{{ $t('home:AboutBlockEarningDesc') }}</p>
                    </div>
                    <div class="footer-section">
                        <h4>{{ $t('home:Legal') }}</h4>
                        <ul>
                            <li><a href="#">{{ $t('home:TermsOfService') }}</a></li>
                            <li><a href="#">{{ $t('home:PrivacyPolicy') }}</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h4>{{ $t('home:ContactUs') }}</h4>
                        <p>{{ $t('home:ContactEmail') }}</p>
                        <p>{{ $t('home:ContactAddress') }}</p>
                    </div>
                </div>
                <!-- <div class="footer-bottom">
                    <p>{{ $t('home:RiskWarning') }}</p>
                    <p>&copy; {{ $t('home:Copyright') }}</p>
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
        this.getUserPurchaseCount();
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
                name: 'hashpowerList',
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
        },
        getUserPurchaseCount() {
            get(this.nftUrl + "/hashpower/hashpower/getUserPurchaseCount", {}, json => {
                if (json.code == 10000) {
                    this.userCount = json.data;
                }
            });
        }
    },
}
</script>

<style lang="scss" scoped>
.home-container {
    width: 100%;
    margin-top: -20px;
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
