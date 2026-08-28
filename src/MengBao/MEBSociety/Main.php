<?php

namespace MengBao\MEBSociety;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use MengBao\MEBSociety\GuiHandler;
use MengBao\MEBSociety\Units\Shop;
use MengBao\MEBSociety\Units\Players;
use MengBao\MEBSociety\Units\Economy;
use MengBao\MEBSociety\Units\Campsite;
use MengBao\MEBSociety\Units\Cohabitant;
use MengBao\MEBSociety\Units\MultiWorld;
use MengBao\MEBSociety\Tools\OfflineMessage;
use MengBao\MEBSociety\Tools\WaitingConfirmation;
use MengBao\MEBSociety\MEBCommand\CommandRegistry;
use MengBao\MEBSociety\MEBCommand\CommandHandler\OpCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\ShopCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\VipCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\SvipCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\PrefixCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\EconomyCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\CampsiteCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\MultiWorldCommandHandler;
use MengBao\MEBSociety\MEBCommand\CommandHandler\CohabitantCommandHandler;


class Main extends PluginBase
{
    private string $logo = "[MEBS]";
    private CommandRegistry $commandRegistry;
    public Config $basicConfig;  //基础配置文件
    public Config $campsiteConfig;  //营地配置文件
    public Config $campsites;  //营地列表文件
    public Config $cohabitantConfig;  //同居配置文件
    public Config $cohabitants;  //同居列表文件
    public Config $playerConfig;  //玩家配置文件
    public Config $economyConfig;  //游戏币配置文件
    public Config $economyRanking;  //游戏币排行榜文件
    public Config $multiWorldConfig;  //多世界配置文件
    public Config $worlds;  //世界列表文件
    public Config $vipConfig;  //vip配置文件
    public Config $vips;  //vip列表文件
    public Config $svips;  //svip列表文件
    public Config $msgConfig;  //消息配置文件
    public Config $prefixConfig;  //称号配置文件
    public Config $prefixes;  //称号列表文件
    public Config $shopConfig;  //GUI商店配置文件
    public Config $shops;  //GUI商店列表文件
    public Config $campMarket;  //营地专属物品市场存货文件
    public Config $offlineMessages;
    public WaitingConfirmation $waitingConfirmation;  //等候答复对象
    public OfflineMessage $offlineMessage;  //离线消息对象
    public GuiHandler $gui;

    public function onLoad(): void
    {
        $this->getLogger()->info("§c--------------------");
        $this->getLogger()->info("§aMEBSociety插件加载中...");
        $this->getLogger()->info("§a作者:梦宝(fanghao)");
        $this->getLogger()->info("§a联系方式:825585398@qq.com");
        $this->getLogger()->info("§aQQ群:495262926");
        $this->getLogger()->info("§c--------------------");
    }

    public function onEnable(): void
    {
        $this->waitingConfirmation = new WaitingConfirmation();
        //创建配置文件
        @mkdir($this->getDataFolder(), 0777, true);
        $this->offlineMessages = new Config($this->getDataFolder() . "OfflineMessage.yml", Config::YAML, []);
        $this->offlineMessage = new OfflineMessage();
        $this->offlineMessage->setAllOM($this->offlineMessages->getAll());  //恢复离线消息
        $this->basicConfig = new Config(
            $this->getDataFolder() . "BasicConfig.yml",
            Config::YAML,
            array(
                "version" => "2.0.8",
                "update" => 0,
                "禁止使用的指令" => ["/op", "/deop"],
                "最高权限" => null,
                "每页显示的op数量" => 5,
                "OP" => [],
            )
        );
        $this->campsiteConfig = new Config(
            $this->getDataFolder() . "CampsiteConfig.yml",
            Config::YAML,
            array(
                "每页显示的入营申请数量" => 5,
                "创建营地的费用" => 10000,
                "服务器最大营地个数" => 10000,
                "营地每日召集次数上限" => 1,
                "营地召集有效时间(s)" => 10,
                //营地等级上限，每级解锁一种营地专属物品(专属物品表写死在代码里)
                "营地最大等级" => 10,
                //从n级升到n+1级的费用 = 基数 * n，钱从营地捐献池出
                "营地升级费用基数" => 50000,
                //福利箱里每种专属物品每人每周最多领多少个
                "福利箱每种物品数量上限" => 8,
            )
        );
        $this->campsites = new Config($this->getDataFolder() . "Campsites.yml", Config::YAML, []);
        $this->cohabitantConfig = new Config(
            $this->getDataFolder() . "CohabitantConfig.yml",
            Config::YAML,
            array(
                "同居需要的费用" => 2000,
                "同居每日传送次数上限" => 1,
            )
        );
        $this->cohabitants = new Config($this->getDataFolder() . 'Cohabitants.yml', Config::YAML, []);
        /*
        playerA-playerB:
        -"传送次数"=>"同居每日传送次数上限",
        -"同居等级"？？？
        */
        $this->playerConfig = new Config($this->getDataFolder() . "PlayerConfig.yml", Config::YAML, []);
        $this->economyConfig = new Config(
            $this->getDataFolder() . "EconomyConfig.yml",
            Config::YAML,
            array(
                "排行榜每页展示的玩家数量" => 10,
            )
        );
        $this->economyRanking = new Config($this->getDataFolder() . 'EconomyRanking.yml', Config::YAML, []);
        $this->multiWorldConfig = new Config(
            $this->getDataFolder() . "MultiWorldConfig.yml",
            Config::YAML,
            array(
                "是否开启传送限制" => true,
                "每页显示的世界数量" => 5,
                //开服时自动把worlds里已生成的世界全部加载好，不用再手动load一遍
                "自动加载全部世界" => true,
            )
        );
        $this->worlds = new Config($this->getDataFolder() . "Worlds.yml", Config::YAML, []);
        $this->vipConfig = new Config(
            $this->getDataFolder() . "VipConfig.yml",
            Config::YAML,
            array(
                "op是否可以管理vip" => true,
                "op是否可以管理svip" => true,
                "vip每日传送次数上限" => 1,
                "svip每日传送次数上限" => 2,
                "每页显示的vip数量" => 5,
                "vip签到奖励游戏币" => 100,
                "svip签到奖励游戏币" => 200,
            )
        );
        $this->vips = new Config($this->getDataFolder() . "Vips.yml", Config::YAML, []);
        $this->svips = new Config($this->getDataFolder() . "Svips.yml", Config::YAML, []);
        $this->msgConfig = new Config(
            $this->getDataFolder() . "MsgConfig.yml",
            Config::YAML,
            array(
                "聊天格式" => "§6[{rand}§6]§r[{campsite}:{CID}§r]§c[{prefix}§c]§a[§f{cohabitant}§a]§7◆{name}§5>>> {color}",
                "底部格式" => "§f|§e在线人数:{online} §d游戏币:{money} §e手持物品:{item} §2数量:{num} \n§f|§c权限:{rand} §b当前时间:{time}  §6当前地图:{world}\n§f|§e营地名:{campsite} §d营地id:{CID} §d同居:{cohabitant}",
                "底部刷新时间间隔(s)" => 5,
            )
        );
        $this->prefixConfig = new Config(
            $this->getDataFolder() . "PrefixConfig.yml",
            Config::YAML,
            array(
                "op是否可以管理称号" => true,
                "每页显示的称号数量" => 5,
            )
        );
        $this->prefixes = new Config($this->getDataFolder() . "Prefixes.yml", Config::YAML, []);
        $this->shopConfig = new Config(
            $this->getDataFolder() . "ShopConfig.yml",
            Config::YAML,
            array(
                "op是否可以管理商店" => true,
                "每页显示的商品数量" => 5,
                //没手动配图标的物品商品，按物品名去猜材质路径。猜错只是没图标，不影响买卖
                "自动推导商品图标" => true,
                //营地专属物品市场的价格系数，乘在物品基准价上。
                //购买系数必须大于出售系数，否则玩家可以反复买卖刷钱
                "营地专属物品出售价格系数" => 0.8,
                "营地专属物品购买价格系数" => 1.25,
            )
        );
        $this->shops = new Config($this->getDataFolder() . "Shops.yml", Config::YAML, []);
        //营地专属物品市场的存货。服务器不产出这些物品，货全部来自玩家挂卖
        $this->campMarket = new Config($this->getDataFolder() . "CampMarket.yml", Config::YAML, []);
        /*
        商品ID:
        -"类型"=>item或prefix
        -"名称"=>商品展示名，留空则用物品名/称号顶上
        -"物品"=>物品名，走StringToItemParser，如diamond
        -"数量"=>每份的物品个数
        -"称号"=>称号内容，仅prefix用
        -"购买单价"/"出售单价"=>单个物品的价格，总价=单价*数量*份数
        -"可购买"/"可出售"=>开关
        -"图标类型"=>-1不要图标，0材质路径，1网络url
        -"图标"=>图标地址
        */
        //旧配置没有商品的话给一份样例，方便管理员照着改
        if ($this->shops->getAll() === array()) {
            Shop::getInstance($this)->addItemShop("§b钻石", "diamond", 1, 1000, 500);
            Shop::getInstance($this)->addPrefixShop("§d萌新", "§d萌新", 5000);
        }

        $this->migrate();

        $this->gui = new GuiHandler($this);
        //注册事件监听器
        $this->getServer()->getPluginManager()->registerEvents(new MEBListener($this), $this);
        //初始化命令注册器
        $this->commandRegistry = new CommandRegistry($this);
        //注册命令处理器
        $this->commandRegistry->register(new CampsiteCommandHandler($this));  //营地命令处理器
        $this->commandRegistry->register(new EconomyCommandHandler($this));  //经济命令处理器
        $this->commandRegistry->register(new MultiWorldCommandHandler($this));  //多世界命令处理器
        $this->commandRegistry->register(new CohabitantCommandHandler($this));
        $this->commandRegistry->register(new OpCommandHandler($this));
        $this->commandRegistry->register(new VipCommandHandler($this));
        $this->commandRegistry->register(new SvipCommandHandler($this));
        $this->commandRegistry->register(new PrefixCommandHandler($this));
        $this->commandRegistry->register(new ShopCommandHandler($this));  //商店命令处理器
        //初始化worlds
        $multiWorld = MultiWorld::getInstance($this);
        //先按配置把世界都加载好，"是否已加载"才能写成真实状态
        if ($multiWorld->hasAutoLoad()) {
            $autoLoad = $multiWorld->loadAllWorlds();
            if ($autoLoad["loaded"] > 0)
                $this->getLogger()->info("§a已自动加载 " . $autoLoad["loaded"] . " 个世界");
            if ($autoLoad["failed"] !== array())
                $this->getLogger()->warning("§c以下世界加载失败，请检查存档是否完整: " . implode(", ", $autoLoad["failed"]));
        }
        $worlds = $this->worlds->getAll();
        $defaultWorldName = $multiWorld->getDefaultWorld()->getFolderName();
        foreach ($multiWorld->getAllWolrdName() as $worldName) {
            //只补新世界，已有条目保留原样:
            //以前每次开服都整条覆盖，管理员填的"描述"一重启就没了
            if (!isset($worlds[$worldName]) || !is_array($worlds[$worldName]))
                $worlds[$worldName] = array(
                    //"传送条件"=>array("/command"),  //后台执行该指令来检测该玩家是否达到传送要求
                    //"等级"??
                    "描述" => $worldName === $defaultWorldName ? "服务器默认世界" : null,
                    "是否已加载" => false
                );
            //按真实加载状态回写，避免配置说已加载、实际没加载，玩家传送时才发现
            $worlds[$worldName]["是否已加载"] = $multiWorld->isWorldLoaded($worldName);
        }
        $this->worlds->setAll($worlds);
        $this->worlds->save();
        //创建计时器
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask(function (): void {  //底部信息
            $this->sendTip();
        }), 20 * $this->msgConfig->get("底部刷新时间间隔(s)"));
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask(function (): void {  // 小时刷新计时器，以服务器开启的天数计算
            //0点刷新检测
            $currentTime = time();  //当前时间
            $midnight = strtotime("today", $currentTime);  //当天的起始0点时间
            $lastUpdateTime = $this->basicConfig->get("update");  //上一次更新时间
            if ($currentTime - $lastUpdateTime > 60 * 60 * 24) {  //距离上次更新时间>24小时则更新数据
                $this->basicConfig->set("update", $midnight);  //设置更新时间为当天起始时间
                $this->basicConfig->save();
                //刷新营地召集次数
                Campsite::getInstance($this)->updateAllCallNum();
                //刷新同居传送次数
                Cohabitant::getInstance($this)->updateAllTransferNum();
                //刷新vip/svip剩余时间
                Players::getInstance($this)->setAllVipDay(-1);
                Players::getInstance($this)->setAllVipDay(-1, false);
                //刷新vip/svip每日签到
                Players::getInstance($this)->setAllSign(false);
                Players::getInstance($this)->setAllSign(false, false);
                //刷新vip/svip传送次数
                Players::getInstance($this)->setAllPlayerTransferNum(Players::getInstance($this)->getTransferNum());
                Players::getInstance($this)->setAllPlayerTransferNum(Players::getInstance($this)->getTransferNum(false), false);
            }
        }), 20 * 60 * 60);
    }

    /**
     * 老版本数据的迁移
     *
     * Config只会补齐缺失的顶层键，营地条目里新增的字段(等级/捐献池/福利箱)
     * 和已经写进数据的"营长"它都管不着，所以要自己走一遍。
     * 每次启动都跑，已经迁移过的服务器不会有副作用。
     */
    private function migrate(): void
    {
        //营长 -> 市长
        $renamed = Campsite::getInstance($this)->migrateOwnerPost();
        if ($renamed > 0)
            $this->getLogger()->info("§a已把" . $renamed . "位营长的职位名迁移为市长。");

        //给老营地补上等级/捐献池/福利箱三个字段
        $campsites = $this->campsites->getAll();
        $patched = 0;
        foreach ($campsites as $CID => $campsite) {
            if (!is_array($campsite))
                continue;
            $changed = false;
            if (!isset($campsite["level"])) {
                $campsite["level"] = 1;
                $changed = true;
            }
            if (!is_array($campsite["donation"] ?? null)) {
                $campsite["donation"] = array("money" => 0, "record" => array());
                $changed = true;
            }
            if (!is_array($campsite["welfare"] ?? null)) {
                $campsite["welfare"] = array("money" => 0, "items" => array(), "claimed" => array());
                $changed = true;
            }
            if ($changed) {
                $campsites[$CID] = $campsite;
                $patched++;
            }
        }
        if ($patched > 0) {
            $this->campsites->setAll($campsites);
            $this->campsites->save();
            $this->getLogger()->info("§a已为" . $patched . "个营地补齐等级与捐献池数据。");
        }

        //版本号在配置里也更新一下，方便服主确认自己跑的是哪版
        if ($this->basicConfig->get("version") !== "2.0.8") {
            $this->basicConfig->set("version", "2.0.8");
            $this->basicConfig->save();
        }
    }

    public function onDisable(): void
    {   //服务器异常关闭时不会保存！！
        $this->offlineMessages->setAll($this->offlineMessage->getAllOM());
        $this->offlineMessages->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "mebhelp") {
            $sender->sendMessage("§c---§b" . $this->logo . "指令帮助§c---");
            $sender->sendMessage("§e/money--游戏币指令");
            $sender->sendMessage("§e/campsite--营地指令");
            $sender->sendMessage("§e/cohabitant--同居指令");
            $sender->sendMessage("§e/mebpre--称号指令");
            $sender->sendMessage("§e/mebshop--商店指令");
            $sender->sendMessage("§e/mw--多世界指令");
            $sender->sendMessage("§e/mebop--管理op指令");
            $sender->sendMessage("§e/mebvip--vip指令");
            $sender->sendMessage("§e/mebsvip--svip指令");
            $sender->sendMessage("§c---------------------------");
            return true;
        }
        if ($command->getName() === "mebui") {
            if (!$sender instanceof Player) {
                $sender->sendMessage($this->logo . "§c只有玩家才可以打开GUI！");
                return true;
            }
            $this->gui->openMain($sender);
            return true;
        }
        return $this->commandRegistry->onCommand($sender, $command, $label, $args);
    }

    public function sendTip(): void
    {
        $online = count($this->getServer()->getOnlinePlayers());
        date_default_timezone_set('Asia/Shanghai');
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $playerName = strtolower($player->getName());
            if (Players::getInstance($this)->playerExist($playerName)) {
                $money = Economy::getInstance($this)->getMoney($playerName);
                $CID = Campsite::getInstance($this)->getCIDbyPlayerName($playerName);
                $campsite = $CID === -1 ? "无营地" : Campsite::getInstance($this)->getCName($CID);
                $CID = $CID === -1 ? "无营地" : $CID;
                $world = $player->getWorld()->getFolderName();
                $item = $player->getInventory()->getItemInHand();
                $itemName = $item->getName();
                $num = $item->getcount();
                $rand = Players::getInstance($this)->getRand($playerName);
                $cohabitant = Cohabitant::getInstance($this)->getCohabitant($playerName);
                $cohabitant = $cohabitant === null ? "无同居" : $cohabitant;
                $time = date('H:i:s');
                $popupTemp = array(
                    "{online}",
                    "{money}",
                    "{item}",
                    "{num}",
                    "{rand}",
                    "{time}",
                    "{world}",
                    "{campsite}",
                    "{CID}",
                    "{cohabitant}"
                );
                $popupPara = array(
                    $online,
                    $money,
                    $itemName,
                    $num,
                    $rand,
                    $time,
                    $world,
                    $campsite,
                    $CID,
                    $cohabitant
                );
                $popup = str_replace($popupTemp, $popupPara, $this->msgConfig->get("底部格式"));
                $player->sendPopup("$popup");
            }
        }
    }

}