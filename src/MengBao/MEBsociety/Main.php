<?php

namespace MengBao\MEBsociety;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use MengBao\MEBsociety\GuiHandler;
use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\Economy;
use MengBao\MEBsociety\Units\Campsite;
use MengBao\MEBsociety\Units\Cohabitant;
use MengBao\MEBsociety\Units\MultiWorld;
use MengBao\MEBsociety\Tools\GuiCommand;
use MengBao\MEBsociety\Tools\GuiStackSet;
use MengBao\MEBsociety\Tools\OfflineMessage;
use MengBao\MEBsociety\Tools\WaitingConfirmation;
use MengBao\MEBsociety\MEBCommand\CommandRegistry;
use MengBao\MEBsociety\MEBCommand\CommandHandler\OpCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\VipCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\SvipCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\PrefixCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\EconomyCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CampsiteCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\MultiWorldCommandHandler;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CohabitantCommandHandler;


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
    public Config $offlineMessages;
    public WaitingConfirmation $waitingConfirmation;  //等候答复对象
    public OfflineMessage $offlineMessage;  //离线消息对象
    public GuiCommand $guiCommand;  //玩家通过GUI发送的指令
    public GuiHandler $gui;
    public GuiStackSet $guiStackSet;  //玩家点击gui的顺序

    public function onLoad(): void
    {
        $this->getLogger()->info("§c--------------------");
        $this->getLogger()->info("§aMEBsociety插件加载中...");
        $this->getLogger()->info("§a作者:梦宝(fanghao)");
        $this->getLogger()->info("§a联系方式:825585398@qq.com");
        $this->getLogger()->info("§aQQ群:495262926");
        $this->getLogger()->info("§c--------------------");
    }

    public function onEnable(): void
    {
        $this->guiCommand = new GuiCommand();
        $this->guiStackSet = new GuiStackSet();
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
                "version" => "2.0.0",
                "update" => 0,
                "禁止使用的指令" => ["/op", "/deop"],
                "最高权限" => "mengbaofh0",
                "每页显示的op数量" => 5,
                "OP" => ["mengbaofh0"],
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
        
        $this->gui = new GuiHandler();
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
        //初始化worlds
        $worlds = $this->worlds->getAll();
        foreach (MultiWorld::getInstance($this)->getAllWolrdName() as $worldName) {
            $worlds[$worldName] = array(
                //"传送条件"=>array("/command"),  //后台执行该指令来检测该玩家是否达到传送要求
                //"等级"??
                "描述" => null,
                "是否已加载" => false
            );
            if ($worldName === MultiWorld::getInstance($this)->getDefaultWorld()->getFolderName()) {  //默认世界
                $worlds[$worldName]["描述"] = "服务器默认世界";
                $worlds[$worldName]["是否已加载"] = true;
            }
        }
        $this->worlds->setAll($worlds);
        $this->worlds->save();
        //创建计时器
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask(function (): void {  //底部信息
            $this->sendTip();
        }), 20 * $this->msgConfig->get("底部刷新时间间隔(s)"));
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask(function (): void {  // 小时刷新计时器，以服务器开启的天数计算
            //堆栈刷新检测
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                $onlienPlayerName = strtolower($onlinePlayer->getName());
                if ($this->guiStackSet->checkStack(strtolower($onlienPlayerName)))
                    $this->guiStackSet->newGSS($onlienPlayerName);
            }
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
            $sender->sendMessage("§e/mw--多世界指令");
            $sender->sendMessage("§e/mebop--管理op指令");
            $sender->sendMessage("§e/mebvip--vip指令");
            $sender->sendMessage("§e/mebsvip--svip指令");
            $sender->sendMessage("§c---------------------------");
            return true;
        }
        if ($command->getName() === "mebui" && $sender instanceof Player) {
            $this->gui->handle(00000, $sender);
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