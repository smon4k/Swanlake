
import Web3 from "web3"
const Web3Modal = window.Web3Modal.default;
const WalletConnectProvider = window.WalletConnectProvider.default;
// import BinanceChainProvider from '@binance-chain/bsc-connector';
// import { OKXWalletProvider } from '@okx/okx-web3-provider';

// import WalletConnectProvider from "@walletconnect/web3-provider";

// console.log(WalletConnectProvider);
// console.log(window);
let networkId = 56;
//获取链id
export const getChainNameId = async (name) => {
  let chainName = name || localStorage.getItem('chainName');
  let networkId = 128; 
  if(chainName && chainName !== undefined) {
    if(chainName === 'HECO') {
      networkId = 128;
    }
    if(chainName === 'ARB') {
      networkId = 42161;
    }
    if(chainName === 'BSC') {
      networkId = 56;
    }
  }
  return networkId;
}
// setTimeout(async () => {
//   networkId = await getChainNameId();  //获取链网络id
// }, 100)

// 获取系统网络id
export const getChainId = async () => {
  const web3 = new Web3(window.ethereum);
  const chainId = await web3.eth.getChainId();
  return chainId;
}

export const switchToBSC = async (provider) => {
  try {
    const chainId = "0x38"; // BSC Mainnet 的 Chain ID（十六进制）
    const networkData = {
      chainId: chainId,
      chainName: "Binance Smart Chain",
      nativeCurrency: {
        name: "Binance Coin",
        symbol: "BNB",
        decimals: 18,
      },
      rpcUrls: ["https://bsc-dataseed.binance.org/"],
      blockExplorerUrls: ["https://bscscan.com"],
    };

    await provider.request({
      method: "wallet_addEthereumChain",
      params: [networkData],
    });

    console.log("Switched to Binance Smart Chain!");
  } catch (error) {
    console.error("Failed to switch network", error);
  }
};


export const walletConnect = async () => {  
  // console.log('connect');
  // __ownInstance__.$store.commit("setWeb3Model", web3Modal);
  // console.log(web3Modal);
  // alert(window);
  try {
    let accounts = null;
    // 优先检查 Binance Wallet
    if (window.BinanceChain) {
      // alert("Connecting using Binance Wallet...");
      await window.BinanceChain.enable(); // 请求权限
      const web3 = new Web3(window.BinanceChain);
      accounts = await web3.eth.getAccounts();

      const currentChainId = await web3.eth.getChainId();
      if (currentChainId !== 56) {
        await switchToBSC(window.BinanceChain); // 切换到 BSC
      }
    }
    // 如果没有 Binance Wallet，检查 MetaMask 或其他钱包
    else if (window.ethereum) {
      // alert("Connecting using MetaMask or another Ethereum wallet...");
      accounts = await window.ethereum.request({ method: "eth_requestAccounts" });

      const currentChainId = await window.ethereum.request({ method: "eth_chainId" });
      if (currentChainId !== "0x38") {
        await switchToBSC(window.ethereum); // 切换到 BSC
      }
    } else {
      const providerOptions = {
        walletconnect: {
          package: WalletConnectProvider,
          options: {
            infuraId: '',//以太坊连接必填
            rpc: {
              56: 'https://bsc-dataseed1.binance.org',
            },
            // network: 56,
          },
        },
        'custom-binance': {
            display: {
              name: 'Binance',
              description: 'Binance Chain Wallet',
              logo: require(`@/assets/wallets/binance-wallet.png`),
            },
            package: 'Binance',
            connector: async () => {
              // alert("Binance", window.BinanceChain);
              const provider = window.BinanceChain;
              if (!provider) {
                throw new Error('Binance Wallet not found');
              }
              await provider.enable();
              return provider;
            },
          },
          'custom-okx': {
            display: {
              name: 'OKX',
              description: 'OKX Chain Wallet',
              logo: require(`@/assets/wallets/okx-wallet.png`),
            },
            package: 'OKX',
            connector: async () => {
              // alert("OKX", window.okexchain);
              const provider = window.okexchain;
              if (!provider) {
                throw new Error('OKX Wallet not found');
              }
              await provider.enable();
              return provider;
            },
          },
      };
      
      const web3Modal = new Web3Modal({
        network: 'mainnet',
        cacheProvider: true,
        providerOptions,
      });
  
      const instance = await web3Modal.connect(); // 连接钱包
      const web3 = new Web3(instance); // 初始化Web3
      // 获取钱包地址
      accounts = await web3.eth.getAccounts();
    }

    let address = accounts[0]; // 获取连接的第一个账户
    // 连接成功，获取连接的账户
    localStorage.setItem('connectorId', 'injected');

    // localStorage.setItem('walletName', 'WalletConnect');
    
    // __ownInstance__.$store.commit("setWalletName", 'WalletConnect');

    // console.log(address);
    if (window.location.host === "localhost:8008") {
      // address = "http://www.runenft.com/";
      // address = "0xd0977b90ae8592a3755d341d0fc94279c78f2655";
      // address = "0x669515B8B042174deF89229c8135435a8207bFc4";
    }
    await getBaseData(networkId, accounts, address);
  } catch (e) {
    console.log("Could not get a wallet connection", e);
    return;
  }
};

export const disconnectWallet = async () => {
  __ownInstance__.$store.dispatch("disconnectMetaMask");
  localStorage.removeItem('connectorId');
  localStorage.removeItem('WEB3_CONNECT_CACHED_PROVIDER');
};

/**
 * 切换网络
 * @returns tronWeb
 */
export const networkSetup02 = async () => { 
  return new Promise(async (resolve, reject) => {
    let tronWeb;
    if (window.tronLink.ready) {
      tronWeb = tronLink.tronWeb;
      resolve(tronWeb);
      // console.log(tronLink, tronWeb.defaultAddress.base58);
    } else {
      const res = await tronLink.request({ method: 'tron_requestAccounts' });
      console.log(tronLink, res);
      if(res) {
        if (res.code === 200) {
          tronWeb = tronLink.tronWeb;
          resolve(tronWeb);
        } else {
          alert(res.message)
        }
      } else {
        alert('Please confirm whether the TronLink wallet is successfully connected')
      }
    }
    resolve(false);
  })
};

export const connectTronWebWallet = async () => {
  // console.log(window.tronLink);
  if (window.tronLink) {
    await connect();
  } else {
    alert("您未安装tronLink")
  }
}


async function getBaseData(chainId, accounts, address) {
    if (chainId && accounts && address) {
      // console.log(web3Modal);
      __ownInstance__.$store.commit("getChainId", chainId);
      __ownInstance__.$store.commit("getAccounts", accounts);
      __ownInstance__.$store.commit("getAddress", address);
      __ownInstance__.$store.dispatch('getHashPowerPoolsList');
      localStorage.setItem('connectorId', 'injected');
    }
    __ownInstance__.$store.commit("isConnected", true);
  }
