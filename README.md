### 合约方法
已经领取 airDropDrawed
待领取 airDropValue


conf deployed to: 0xdA039c3d3F3e114b7dC3b0A7F469b9EB02341f34
interestModel deployed to: 0x3F27681ac5454ac8DaFBa20d0c3150Eb3C1d844B
bank deployed to: 0x2147fc21926B73A5ea01D521aFd30ddD9b2736E8
H2O deployed to: 0x6C1bC576B227E4b04C53a78cdEe8a7987F47f8B9

存
function deposit(address token, uint256 amount) external payable nonReentrant {
取
function withdraw(address token, uint256 pAmount) external nonReentrant {


合约参考
https://testnet.hecoinfo.com/address/0xD57430aCb085898E3385dCb71DA175418aA40194#code



account0: 0xb5A95b4bD63b50A1426d1f170D12d5c32004fbba
conf deployed to: 0xe2B91bEA69aD1b60A22cDaB29F088B966E6DeCf7
oracle deployed to: 0x51Ab82dE7c64709e03C53cC3E24575Dab355d4D7
interestModel deployed to: 0x52c7Bb6F43ad46d7E977452340f02AE690597423
bank_proxy deployed to: 0x39cd7BE091b329FAC5b55Eb082Ec0f3677557b49
H2O deployed to: 0x5619Fb599a99374DfA1Ac024A7b56d159900ab18
FairLaunch deployed to: 0x1908E87e412F89fa759C6415a1e628493E168D1F
AirDrop deployed to: 0x703e830474E7Dcd94897FB0e845050AEd9746ad4
TimeLock deployed to: 0x6b5570d906d0fa5d631f7fc21661ad444233695b
rH2o deployed to: 0x971Dd90172C85ea2aE2dE22023B9e9842cc87900
DebtToken deployed to: 0xB8c83af0F0f746ed704a3c816C7EE8C1624F5131
lp token:0xba7cb412264b7698e13cbd107ceb43da221782a7
cake  deployed to: 0x89Dc8Afd6A35762F5de4E40ebe77Ec43CD7601F1

syrupBar  deployed to: 0xc2554b69EfC1ea36c793B62302DC3799BbF2B728
masterChef deployed to: 0xf983a1bC9f53d37148142c25de1a29aBd3A2A90c
sousChef deployed to: 0x00da5696a8A890f85a034F7FA152fB4944B88ad1
strategyAllTokenOnly deployed to: 0x9F1787c0B4589E1Ac97703E03D32D25366B6b198
liquidate deployed to: 0x6678B9750fEA1f3FC77Dbd44341aC08aD6C8A7Dd
pancake goblin proxy deployed to: 0x29Fea4A4DA0103DC8DF02CCA2dD169cc092f4749
trategyAdd deployed to: 0x3E97f6834b70C3e7eb29baf78e84777C03deaf1D
WithdrawMinimize deployed to: 0x1f85f3409c6497B99Ba6187240e5486FeD13Fab8

DebtToken deployed to: 0x58783F7C3408f49Ad9C4fD42fddAac26C12e7337
launch_pid: 2
mdex router deployed to: 0x7DAe51BD3E3376B8c7c4900E9107f12Be3AF1bA8
LP token: 0x0A4D88a877f65Fb0b9488e8e41491d847D7B3b89
MdxToken deployed to: 0x0f3f1Cf866CB6Bdc2B385A1A6002401966b3E519
bscPool deployed to: 0x94BC1EEa83e94E76451A117A38a117d0DF4aA017
hmdx deployed to: 0x97D1b64720C22daF09C99FDa79c907A5c684594A
boardRoomHMDX deployed to: 0x1FbCD08bA7C7090edbF1f9A3289062e7801A5604
strategyLiquidateMdx deployed to: 0xe442A0c559Ad3547dF567eBbCD00C3a3AAAa0A8A
mdex goblin proxy deployed to: 0x22c732d62aA07d5889c1f5173B3a33425CB6fC67
strategyAddTwoSidesOptimal deployed to: 0xFc55100c256A8e9A28A3F55aF08BC79F01C8c57D
strategyWithdrawMinimizeTrading deployed to: 0x73eC71376f39d24c5E2Ab283553579Ec90799972




赎回数据
amount0 = liquidity.mul(balance0) / _totalSupply; // using balances ensures pro-rata distribution
amount1 = liquidity.mul(balance1) / _totalSupply; /
债务
totalValue

liquidity = goblin.shareToBalance(posId)
lpToken = goblin.lpToken()
balance0 = token0.balanceof(lptoken)
balance1 = token1.balanceof(lptoken)
lpToken.totalSupply


harvest
goblin
fairLanch

fairLanch.pendingRabbit(uint256 _pid, address _user)

zeroAddress = "0x0000000000000000000000000000000000000000";
//token0 传借款的token合约地址，token1 传0地址，token0 amount 传0，token1 amount  传转账金额值，最后一个传0
a = web3.eth.abi.encodeParameters(['address', 'address', 'uint256', 'uint256', 'uint256'], [borrowToken, zeroAddress, "0", ethers.utils.parseEther("0.00001"), "0"]);
b = _addStrat  //strage 合约地址
c = web3.eth.abi.encodeParameters(['address', 'bytes'], [b, a])
//参数说明，从左至右，posId(add的时候为0),pid(product id),borrow amount,data
// posId 传0，pid 这次 传0（其他交易对在告诉），borrow amount 借款金额，
// d = web3.eth.abi.encodeParameters(['uint256', 'uint256', 'uint256', 'bytes'], [0, 1, ethers.utils.parseEther("0.001"), c],{value:ethers.utils.parseEther("0.00001")})

await rabbit.approve(_addStrat, ethers.utils.parseEther("1"));

await bank.work(0, 1, ethers.utils.parseEther("0.001"), c, {value: ethers.utils.parseEther("0.00001")})


//2 加仓

let posId = await bank.userPosition(0);
let position  = await bank.positions(posId);
if (position.productionId == 1){
     await bank.work(posId, 0, ethers.utils.parseEther("0.001"), c, {value: ethers.utils.parseEther("0.00001")})
 }


 //3 赎回
 zeroAddress = "0x0000000000000000000000000000000000000000";

 //token0 ,token1 , which user want back :0 token0 back,1 token1 back,2 token what surplus.
 a = web3.eth.abi.encodeParameters([('address', 'address', 'uint')], [borrowToken, zeroAddress, type]);
 b = _withDrawStrage  //strage 合约地址
 c = web3.eth.abi.encodeParameters(['address', 'bytes'], [b, a])
 //参数说明，从左至右，posId(add的时候为0),pid(product id),borrow amount,data
 // posId 传0，pid 这次 传0（其他交易对在告诉），borrow amount 借款金额，
 // d = web3.eth.abi.encodeParameters(['uint256', 'uint256', 'uint256', 'bytes'], [0, 1, ethers.utils.parseEther("0.001"), c],{value:ethers.utils.parseEther("0.00001")})

 await rabbit.approve(_addStrat, ethers.utils.parseEther("1"));
 //
 await bank.work(posId, 0,0, c)


需改：
顶部地址栏 交易中

价格影响（滑点）（uniswap源码）

https://cpz4fq.axshare.com（原型）


页面数据：
 全部挖矿

        当前风险系数：
        借入资产/资产总价值 （以借款币种计价）
        综合年化收益率：
        (（1+ daily apr）的365次方  ) -1  // daily apr:日收益率

        

当前借款利息率:
TokenBank storage bank = banks[token];
uint256 totalDebt = bank.totalDebt;
uint256 totalBalance = totalToken(token);
uint256 ratePerSec = config.getInterestRate(totalDebt, totalBalance);


<!-- banks[token] . totalValue  -->

// 存款总存
totalToken[token]




function    _optimalDepositA(
  uint256 amtA,
        uint256 amtB,
        uint256 resA,
        uint256 resB
  ){
  uint256 a = 9975;
        uint256 b = uint256(19975).mul(resA);
        uint256 _c = (amtA.mul(resB)).sub(amtB.mul(resA));
        uint256 c = _c.mul(10000).div(amtB.add(resB)).mul(resA);

        uint256 d = a.mul(c).mul(4);
        uint256 e = Math.sqrt(b.mul(b).add(d));

        uint256 numerator = e.sub(b);
        uint256 denominator = a.mul(2);

        return numerator.div(denominator);
  }
  
  
  
   function optimalDeposit(
        uint256 amtA,
        uint256 amtB,
        uint256 resA,
        uint256 resB
    ) internal pure returns (uint256 swapAmt, bool isReversed) {
        if (amtA.mul(resB) >= amtB.mul(resA)) {
            swapAmt = _optimalDepositA(amtA, amtB, resA, resB);
            isReversed = false;
        } else {
            swapAmt = _optimalDepositA(amtB, amtA, resB, resA);
            isReversed = true;
        }
    }




https://api.h2o.live//api/apy/getReptileBscData

BNB借款
BNB总价值  


0xcd4541ceDB1C39C4742486B4f8D0009Ca0F212ed 