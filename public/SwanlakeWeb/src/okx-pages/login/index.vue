<template>
  <div class="login-container">
    <div class="login-box">
      <!-- 标题 -->
      <h2 class="login-title">欢迎 {{ isRegister ? "注册" : "登录" }}</h2>
      <p class="login-subtitle">
        {{ isRegister ? "开启精彩之旅" : "探索更多精彩内容" }}
      </p>

      <!-- 登录/注册表单 -->
      <form class="login-form" @submit.prevent="handleSubmit">
        <!-- 手机号输入 -->
        <div class="form-item">
          <input
            v-model="phone"
            type="tel"
            placeholder="请输入手机号"
            maxlength="11"
            class="input-field"
            @blur="validatePhone"
          />
          <div v-if="phoneError" class="error-message">{{ phoneError }}</div>
        </div>

        <!-- 验证码输入 -->
        <div v-if="isRegister" class="form-item code-item">
          <input
            v-model="code"
            type="number"
            placeholder="请输入验证码"
            maxlength="6"
            class="input-field"
          />
          <button
            type="button"
            class="code-btn"
            :disabled="countdown > 0"
            @click="sendCode"
          >
            {{ countdown > 0 ? `${countdown}s` : "获取验证码" }}
          </button>
        </div>

        <!-- 密码输入 -->
        <div class="form-item">
          <input
            v-model="password"
            type="password"
            :placeholder="'请输入' + isRegister ? '密码' : '已有密码'"
            class="input-field"
            @blur="validatePassword"
          />
          <div v-if="passwordError" class="error-message">{{ passwordError }}</div>
        </div>

        <!-- 重复密码输入 -->
        <div class="form-item" v-if="isRegister">
          <input
            v-model="confirmPassword"
            type="password"
            :placeholder="'请再次输入' + (isRegister ? '密码' : '已有密码')"
            class="input-field"
            @blur="validateConfirmPassword"
          />
          <div v-if="onfirmPasswordError" class="error-message">{{ onfirmPasswordError }}</div>
        </div>


        <!-- 登录/注册按钮 -->
        <button type="submit" class="submit-btn">
          {{ isRegister ? "注册" : "登录" }}
        </button>
      </form>

      <!-- 切换登录/注册方式 -->
      <p class="toggle-text" @click="toggleMode">
        {{ isRegister ? "已有账号？去登录" : "还没有账号？去注册" }}
      </p>

      <!-- 其他登录方式 -->
      <!-- <div class="other-login">
          <p class="divider"><span>或</span></p>
          <button class="wechat-login">
            微信登录
          </button>
        </div> -->
    </div>
  </div>
</template>

<script>
import { get, post } from "@/common/axios.js";
import { mapGetters, mapState } from "vuex";
export default {
  name: "login-index",
  data() {
    return {
      phone: "",
      code: "",
      password: "",
      countdown: 0,
      isRegister: false,
      phoneError: '',
      onfirmPasswordError: '',
      passwordError: '',
    };
  },
  computed: {
      ...mapState({
          apiUrl:state=>state.base.apiUrl,
      }),
  },
  methods: {
    async sendCode() {
      if (!/^1[3-9]\d{9}$/.test(this.phone)) {
        this.phoneError = '手机号格式不正确，请输入11位数字';
        return;
      }

      this.countdown = 60;
      const timer = setInterval(() => {
        this.countdown--;
        if (this.countdown <= 0) clearInterval(timer);
      }, 1000);

      // 这里调用发送验证码接口
      // await axios.post('/api/sendCode', { phone: this.phone })
      get(this.apiUrl + "/api/userokx/sendVerificationCode", {
          mobile: this.phone
      }, json => {
          console.log(json.data);
          if (json.code == 10000) {
            this.$message.success('验证码发送成功');
          }
      })
    },
    validatePassword() {
      if (!this.password) {
        this.passwordError = '请输入密码';
      } else if (!/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d.]{6,}$/.test(this.password)) {
        this.passwordError = '密码必须包含字母数字组合，且长度至少为6位';
      } else {
        this.passwordError = '';
      }
    },
    validateConfirmPassword() {
      if (!this.confirmPassword) {
        this.onfirmPasswordError = '请再次输入密码';
      } else if (this.confirmPassword !== this.password) {
        this.onfirmPasswordError = '两次输入密码不一致';
      } else {
        this.onfirmPasswordError = '';
      }
    },
    validatePhone() {
      if (!/^1[3-9]\d{9}$/.test(this.phone)) {
        this.phoneError = '手机号格式不正确，请输入11位数字';
      } else {
        this.phoneError = '';
      }
    },
    handleSubmit() {
      if (!this.phone || !this.password || (this.isRegister && !this.confirmPassword)) {
        alert("请填写完整信息");
        return;
      }

      if (this.isRegister) {
        if (!this.code) {
          alert("请输入验证码");
          return;
        }
        // 这里调用注册接口
        post(this.apiUrl + "/api/userokx/createAccount", {
            mobile: this.phone,
            password: this.password,
            code: this.code
        }, json => {
            console.log(json.data);
            if (json.code == 10000) {
              this.isRegister = false;
              this.$message.success('注册成功，请登录');
              this.clearForm();
            }
        })
      } else {
        // 这里调用登录接口
        // await axios.post('/api/login', { phone: this.phone, password: this.password })
        post(this.apiUrl + "/api/userokx/login", {
            mobile: this.phone,
            password: this.password
        }, json => {
            console.log(json.data);
            if (json.code == 10000) {
              localStorage.setItem('token', json.data.token);
              this.$message.success('登录成功');
              this.$router.push("/okx/home");
            } else {
              this.$message.error(json.msg);
            }
        })
      }
    },
    clearForm() {
      this.phone = "";
      this.code = "";
      this.password = "";
      this.confirmPassword = "";
      this.countdown = 0;
      this.phoneError = "";
      this.onfirmPasswordError = "";
      this.passwordError = "";
    },
    toggleMode() {
      this.isRegister = !this.isRegister;
      this.code = "";
      this.countdown = 0;
    },
  },
};
</script>

<style scoped>
/* 全局样式 */
.login-container {
  display: flex;
  height: 100vh;
  justify-content: center;
  align-items: center;
  background-image: linear-gradient(to right, #fbc2eb, #a6c1ee);
}

.login-box {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding: 40px;
  max-width: 358px;
  width: 100%;
  transform: scale(1);
}

.login-title {
  font-size: 24px;
  font-weight: bold;
  text-align: center;
  margin-bottom: 8px;
}

.login-subtitle {
  font-size: 14px;
  color: #666;
  text-align: center;
  margin-bottom: 24px;
}

/* 表单样式 */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-item {
  width: 100%;
  display: block;
}

.error-message {
  color: red;
  font-size: 12px;
  margin-top: 5px;
}

.input-field {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.3s;
}

.input-field:focus {
  border-color: #ff2442;
}

.code-item {
  display: flex;
  gap: 10px;
}

.code-btn {
  flex-shrink: 0;
  padding: 0 15px;
  background: #ff2442;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.3s;
}

.code-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.submit-btn {
  width: 100%;
  padding: 12px;
  background: #ff2442;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: background 0.3s;
}

.submit-btn:hover {
  background: #e60023;
}

/* 切换登录/注册方式文本样式 */
.toggle-text {
  text-align: center;
  font-size: 14px;
  color: #666;
  margin-top: 16px;
  cursor: pointer;
}

.toggle-text:hover {
  text-decoration: underline;
}

/* 其他登录方式 */
.other-login {
  margin-top: 24px;
}

.divider {
  text-align: center;
  position: relative;
  color: #999;
  font-size: 14px;
}

.divider span {
  background: white;
  padding: 0 10px;
  position: relative;
  z-index: 1;
}

.divider::before {
  content: "";
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  height: 1px;
  background: #ddd;
  z-index: 0;
}

.wechat-login {
  width: 100%;
  padding: 12px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #333;
  cursor: pointer;
  /* transition: background 0.3s; */
}

.wechat-login img {
  width: 20px;
  height: 20px;
}

.wechat-login:hover {
  background: #f5f5f5;
}

/* 响应式设计 */
@media (max-width: 768px) {
  .login-box {
    /* padding: 24px; */
    box-shadow: none;
    border-radius: 0;
    transform: scale(0.9);
  }

  .login-title {
    font-size: 20px;
  }

  .login-subtitle {
    font-size: 12px;
  }

  .input-field,
  .code-btn,
  .submit-btn,
  .wechat-login {
    font-size: 14px;
  }
}
</style>
