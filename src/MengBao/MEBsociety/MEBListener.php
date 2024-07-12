<?php

namespace MengBao\MEBsociety;

use pocketmine\item\StringToItemParser;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\Economy;
use MengBao\MEBsociety\Units\MultiWorld;
use MengBao\MEBsociety\Units\Cohabitant;
use MengBao\MEBsociety\Units\Campsite;
use MengBao\MEBsociety\Error\GuiError\GuiCommandError;

class MEBListener implements Listener
{
    public $logo = "[MEBS]";
    const MAIN = 00000;
    const CONFIRM = 00001;
    const ONE_V_INPUT_FORM = 00002;  //单参数输入窗口
    const TEXT_FORM = 00003;  //文本提示窗口
    const TWO_V_INPUT_FORM = 00004;  //双参数输入窗口
    const TWO_D_ONE_I_FORM = 00005;  //2个下拉框+1个输入框窗口
    const ONE_DROPDOWN_FORM = 00006;  //单下拉框窗口
    const TOGGLE_FORM = 00007;  //开关窗口
    const MONEY = 10000;
    const CAMPSITE = 20000;
    const CAMPSITE_MANAGE = 20001;
    const CAMPSITE_APPLICATION = 20002;
    const COHABITANT = 30000;
    const MEBPRE = 40000;
    const MW = 50000;
    const MEBOP = 60000;
    const MEBVIP = 70000;
    const VIPPRIVILEGE = 70001;
    const SVIPPRIVILEGE = 70002;
    const SHOP = 80000;
    public array $mainIndex = [self::MONEY, self::CAMPSITE, self::COHABITANT, self::MEBPRE, self::MW, self::MEBOP, self::MEBVIP, self::SHOP];
    public array $campsiteIndex = [];
    private GuiCommandError $guiCommandError;
    private $plugin;  //插件主类

    public function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
        $this->guiCommandError = new GuiCommandError();
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        //玩家名不区分大小写(case insensitive)
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $playerConfig = $this->plugin->playerConfig->getAll();
        $this->plugin->guiStackSet->newGSS($playerName);  //玩家加入服务器新建堆栈
        if (!isset($playerConfig[$playerName])) {  //初次加入服务器，初始化玩家信息
            $this->plugin->offlineMessage->clearOM($playerName);  //初始化离线消息
            $this->plugin->offlineMessages->setAll($this->plugin->offlineMessage->getAllOM());
            $this->plugin->offlineMessages->save();
            $playerConfig[$playerName] = array(
                "同居" => null,
                "正在使用的称号" => null,
                "强制解除同居权力" => false,  //op\svip特权
                "同居传送倍数" => 1,  //op10倍\svip5倍\vip2倍（每人
                "营地ID" => null,
                "营地职位名" => null,
                "营地召集倍数" => 1,  //op5倍\svip2倍（每人
                "营地权力" => array(
                    "所有权力" => false,
                    "设置营地传送点" => false,
                    "召集营地成员" => false,
                    "审核入营申请" => false,
                    "踢人" => false,
                ),
                "游戏币" => 0,
            );
            $this->plugin->playerConfig->setAll($playerConfig);
            $this->plugin->playerConfig->save();
            $prefixes = $this->plugin->prefixes->getAll();
            $prefixes[$playerName] = array();
            $this->plugin->prefixes->setAll($prefixes);
            $this->plugin->prefixes->save();
        }
        //gui物品
        $item = StringToItemParser::getInstance()->parse("340:99") ?? LegacyStringToItemParser::getInstance()->parse("340:99");
        $item->setCustomName($this->logo . "§l§5导航");
        if (!Players::getInstance($this->plugin)->isInIventory($playerName, $this->logo . "§l§5导航")) {
            $player->getInventory()->addItem($item);
            $player->sendMessage($this->logo . "§l§5导航工具§7已发送至你的背包！");
        }
        //非法op权限检测
        if ($this->plugin->getServer()->isOp($playerName) && (!(Players::getInstance($this->plugin)->isOp($playerName) || Players::getInstance($this->plugin)->isMaster($playerName)))) {
            $this->plugin->getServer()->removeOP($playerName);
            Cohabitant::getInstance($this->plugin)->setPlayerTransferNumScale($playerName, 1);
            Cohabitant::getInstance($this->plugin)->setOpdivPower($playerName, false);
            Campsite::getInstance($this->plugin)->setPlayerCallScale($playerName, 1);
        }
        if (Players::getInstance($this->plugin)->isOp($playerName) || Players::getInstance($this->plugin)->isMaster($playerName)) {
            //op/master权限重置检测
            if (!$this->plugin->getServer()->isOp($playerName))
                $this->plugin->getServer()->addOP($playerName);
            if (!Players::getInstance($this->plugin)->isOp($playerName))
                Players::getInstance($this->plugin)->addOp($playerName);  //若为master，可能仅使用配置文件设置了master但未通过指令给予
            if (Cohabitant::getInstance($this->plugin)->getPlayerTransferNumScale($playerName) !== 10)  //同居传送倍数
                Cohabitant::getInstance($this->plugin)->setPlayerTransferNumScale($playerName, 10);
            if (!Cohabitant::getInstance($this->plugin)->hasOpdivPower($playerName))  //强制解除同居权力
                Cohabitant::getInstance($this->plugin)->setOpdivPower($playerName);
            if (Campsite::getInstance($this->plugin)->getPlayerCallScale($playerName) !== 5)  //营地召集倍数
                Campsite::getInstance($this->plugin)->setPlayerCallScale($playerName, 5);
            $this->plugin->getServer()->broadcastMessage($this->logo . "§e尊贵的§6OP管理员:§b" . $playerName . "§6加入游戏!");
        } elseif (Players::getInstance($this->plugin)->isVip($playerName, false)) {
            //svip权限重置检测
            if (Cohabitant::getInstance($this->plugin)->getPlayerTransferNumScale($playerName) !== 5)  //同居传送倍数
                Cohabitant::getInstance($this->plugin)->setPlayerTransferNumScale($playerName, 5);
            if (!Cohabitant::getInstance($this->plugin)->hasOpdivPower($playerName))  //强制解除同居权力
                Cohabitant::getInstance($this->plugin)->setOpdivPower($playerName);
            if (Campsite::getInstance($this->plugin)->getPlayerCallScale($playerName) !== 2)  //营地召集倍数
                Campsite::getInstance($this->plugin)->setPlayerCallScale($playerName, 2);
            $this->plugin->getServer()->broadcastMessage($this->logo . "§e尊贵的§6SVIP:§b" . $playerName . "§6加入游戏!");
        } elseif (Players::getInstance($this->plugin)->isVip($playerName)) {
            //vip权限重置检测
            if (Cohabitant::getInstance($this->plugin)->getPlayerTransferNumScale($playerName) !== 2)  //同居传送倍数
                Cohabitant::getInstance($this->plugin)->setPlayerTransferNumScale($playerName, 2);
            if (Cohabitant::getInstance($this->plugin)->hasOpdivPower($playerName))  //强制解除同居权力
                Cohabitant::getInstance($this->plugin)->setOpdivPower($playerName, false);
            if (Campsite::getInstance($this->plugin)->getPlayerCallScale($playerName) !== 1)  //营地召集倍数
                Campsite::getInstance($this->plugin)->setPlayerCallScale($playerName, 1);
            $this->plugin->getServer()->broadcastMessage($this->logo . "§6VIP:§b" . $playerName . "§6加入游戏!");
        }
        //接收离线消息
        if (!$this->plugin->offlineMessage->isEmptyOM($playerName)) {
            $offlineMessage = $this->plugin->offlineMessage->getOM($playerName);
            $omString = $this->plugin->offlineMessage->getOMString($playerName);
            $player->sendMessage($this->logo . "§c您接收到了如下离线消息: ");
            foreach ($offlineMessage as $key => $message) {
                $player->sendMessage("§c" . $key + 1 . " §6=> §c" . $message);
            }
            $this->plugin->offlineMessage->clearOM($playerName);
            $this->plugin->gui->handle(self::TEXT_FORM, $player, "接收的离线消息", $omString);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $this->plugin->guiStackSet->delGSS($playerName);  //玩家退出，删除数据
        $this->plugin->waitingConfirmation->delWC($playerName);
        $this->plugin->guiCommand->delGC($playerName);
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        //玩家点击导航
        $player = $event->getPlayer();
        $item = $event->getItem();
        $name = $item->getCustomName();
        if ($name === $this->logo . "§l§5导航")
            $this->plugin->gui->handle(00000, $player);
    }

    public function onPlayerChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $message = $event->getMessage();
        if ($this->plugin->waitingConfirmation->hasWC($playerName)) {
            $lowerMsg = strtolower($message);
            if ($lowerMsg === "yes") {
                $callback = $this->plugin->waitingConfirmation->getWC($playerName);
                $callback(true);
            } elseif ($lowerMsg === "no") {
                $callback = $this->plugin->waitingConfirmation->getWC($playerName);
                $callback(false);
            } else
                $player->sendMessage($this->logo . "§c你有一个请求未处理，暂时无法输入其他消息。请输入 'yes' 或 'no' 来确认或取消请求。");
            $event->cancel();
            return;
        }
        //格式化聊天消息
        $rand = Players::getInstance($this->plugin)->getRand($playerName);
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName);
        $campsite = $CID === -1 ? "无营地" : Campsite::getInstance($this->plugin)->getCName($CID);
        $CID = $CID === -1 ? "" : $CID;
        $prefix = Players::getInstance($this->plugin)->getCurPrefix($playerName);
        $cohabitant = Cohabitant::getInstance($this->plugin)->getCohabitant($playerName);
        $cohabitant = $cohabitant === null ? "无同居" : $cohabitant;
        $color = Players::getInstance($this->plugin)->getColor($playerName);
        $msg = str_replace("§", "", $event->getMessage());
        $chatTemp = array(
            "{rand}",
            "{campsite}",
            "{CID}",
            "{prefix}",
            "{cohabitant}",
            "{name}",
            "{color}"
        );
        $chatPara = array(
            $rand,
            $campsite,
            $CID,
            $prefix,
            $cohabitant,
            $playerName,
            $color
        );
        $chatFormat = str_replace($chatTemp, $chatPara, Players::getInstance($this->plugin)->getChatFormat());
        $event->cancel();
        $this->plugin->getServer()->broadcastMessage($chatFormat . $msg);
    }

    public function onCommandPreSend(CommandEvent $event)
    {
        $sender = $event->getSender();
        $senderName = strtolower($sender->getName());
        $cmd = $event->getCommand();
        $first = explode(' ', $cmd)[0];
        if (Players::getInstance($this->plugin)->isCmdLimited($first) && !Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c该指令已被禁用!");
            $event->cancel();
        }
    }

    public function onReceive(DataPacketReceiveEvent $event)
    {
        $pk = $event->getPacket();
        if (!($pk instanceof ModalFormResponsePacket))
            return;
        if ($pk->formData === null)
            return;
        $player = $event->getOrigin()->getPlayer();
        $id = $pk->formId;
        $playerName = strtolower($player->getName());
        $data = json_decode($pk->formData);
        switch ($id) {
            case self::MAIN:
                if ($this->plugin->guiCommand->hasGC($playerName))  //检测是否有未执行的gui指令
                    $this->plugin->guiCommand->delGC($playerName);
                $this->plugin->gui->handle($this->mainIndex[(int) $data], $player);
                break;
            case self::CONFIRM:
                if (!$this->plugin->waitingConfirmation->hasWC($playerName))
                    return;
                $callback = $this->plugin->waitingConfirmation->getWC($playerName);
                $callback($data);
                break;
            case self::MONEY:  //moneyGUI指令处理
                switch ((int) $data) {
                    case 0:
                        $this->plugin->getServer()->dispatchCommand($player, "money my");
                        break;
                    case 1:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "money get value");  //添加GUI指令
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "游戏币查询", "", "请输入查询的玩家名：", "<player_name>");
                        break;
                    case 2:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "money add value1 value2");
                        $this->plugin->gui->handle(self::TWO_V_INPUT_FORM, $player, "增加游戏币", "", ["请输入玩家名：", "请输入游戏币数量"], ["<player_name>", "<money>"]);
                        break;
                    case 3:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "money remove value1 value2");
                        $this->plugin->gui->handle(self::TWO_V_INPUT_FORM, $player, "减少游戏币", "", ["请输入玩家名：", "请输入游戏币数量"], ["<player_name>", "<money>"]);
                        break;
                    case 4:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "money pay value1 value2");
                        $this->plugin->gui->handle(self::TWO_V_INPUT_FORM, $player, "支付游戏币", "", ["请输入玩家名：", "请输入游戏币数量"], ["<player_name>", "<money>"]);
                        break;
                    case 5:
                        $ranking = Economy::getInstance($this->plugin)->getRanking();
                        $rankingString = "";
                        foreach ($ranking as $name => $money)
                            $rankingString .= $name . " => " . $money . "\n";
                        $this->plugin->gui->handle(self::TEXT_FORM, $player, "游戏币排行榜", $rankingString);
                        break;
                    default:
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::ONE_V_INPUT_FORM:
                $value = $data[0];  //参数内容
                if (!$this->plugin->guiCommand->hasGC($playerName))
                    return;
                $cmd = $this->plugin->guiCommand->getGC($playerName);
                $cmd = str_replace("value", $value, $cmd);
                $this->plugin->getServer()->dispatchCommand($player, $cmd);
                $this->plugin->guiCommand->delGC($playerName);
                break;
            case self::TWO_V_INPUT_FORM:  //发送
                $value1 = $data[0];
                $value2 = $data[1];
                if (!$this->plugin->guiCommand->hasGC($playerName))
                    return;
                $cmd = $this->plugin->guiCommand->getGC($playerName);
                $cmd = str_replace(["value1", "value2"], [$value1, $value2], $cmd);
                $this->plugin->getServer()->dispatchCommand($player, $cmd);
                $this->plugin->guiCommand->delGC($playerName);
                break;
            case self::TWO_D_ONE_I_FORM:
                if (!$this->plugin->guiCommand->hasGC($playerName))
                    return;
                $cmd = $this->plugin->guiCommand->getGC($playerName);
                $num = count($data);
                for ($i = 0; $i < $num; $i++) {
                    $optionPosition = strpos($cmd, "option");
                    $valuePosition = strpos($cmd, "value");
                    if ($optionPosition === false)
                        $optionPosition = 999;
                    if ($valuePosition === false)
                        $valuePosition = 999;
                    if ($optionPosition < $valuePosition)
                        $cmd = substr_replace($cmd, $data[$i], $optionPosition, strlen("option"));
                    else
                        $cmd = substr_replace($cmd, $data[$i], $valuePosition, strlen("value"));
                }
                $this->plugin->getServer()->dispatchCommand($player, $cmd);
                $this->plugin->guiCommand->delGC($playerName);
                break;
            case self::ONE_DROPDOWN_FORM:
                $option = $data[0];
                if (!$this->plugin->guiCommand->hasGC($playerName))
                    return;
                $cmd = $this->plugin->guiCommand->getGC($playerName);
                $cmd = str_replace("option", $option, $cmd);
                $this->plugin->getServer()->dispatchCommand($player, $cmd);
                $this->plugin->guiCommand->delGC($playerName);
                break;
            case self::TOGGLE_FORM:
                if (!$this->plugin->guiCommand->hasGC($playerName))
                    return;
                $cmd = $this->plugin->guiCommand->getGC($playerName);
                foreach ($data as $key => $value) {
                    $option = $data[$key];
                    $cmd = str_replace("option", $option, $cmd);
                }
                $this->plugin->getServer()->dispatchCommand($player, $cmd);
                $this->plugin->guiCommand->delGC($playerName);
            case self::CAMPSITE:  //营地主gui指令处理
                switch ((int) $data) {
                    case 0:  //创建
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite create value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "创建营地", "", "请输入营地名：", "<campsite_name>");
                        break;
                    case 1:  //join
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite join value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "加入营地", "", "请输入营地ID：", "<campsite_id>");
                        break;
                    case 2:  //gohome
                        $this->plugin->getServer()->dispatchCommand($player, "campsite gohome");
                        break;
                    case 3:  //search
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite search value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "营地查询", "", "请输入营地ID：", "<campsite_id>");
                        break;
                    case 4:  //manage
                        $this->plugin->gui->handle(self::CAMPSITE_MANAGE, $player);
                        break;
                    case 5:  //quit
                        $this->plugin->getServer()->dispatchCommand($player, "campsite quit");
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::CAMPSITE_MANAGE:  //营地管理gui指令处理
                switch ((int) $data) {
                    case 0:  //sethome
                        $this->plugin->getServer()->dispatchCommand($player, "campsite sethome");
                        break;
                    case 1:  //call召集
                        $this->plugin->getServer()->dispatchCommand($player, "campsite call");
                        break;
                    case 2:  //application-list-accept/disagreeAll-back
                        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName);
                        $application = Campsite::getInstance($this->plugin)->getApplication($CID);
                        if (empty($application))
                            $application = array();
                        $this->plugin->gui->handle(self::CAMPSITE_APPLICATION, $player, "", "", $application);
                        break;
                    case 3:  //post
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite post value1 value2");
                        $this->plugin->gui->handle(self::TWO_V_INPUT_FORM, $player, "设置营地职称", "", ["请输入玩家名：", "请输入职称："], ["<player_name>", "<post_name>"]);
                        break;
                    case 4:  //power
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite power option value option");
                        $this->plugin->gui->handle(self::TWO_D_ONE_I_FORM, $player, "管理营地权力", "", ["请选择操作：", "玩家名：", "请选择权力："], [["add", "remove"], "<player_name>", Campsite::getInstance($this->plugin)->getPowerId()]);
                        break;
                    case 5:  //out踢人
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite out value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "踢人", "", "请输入玩家名：", "<player_name>");
                        break;
                    case 6:  //transfer转让
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "campsite transfer value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "营地转让", "", "请输入玩家名：", "<player_name>");
                        break;
                    case 7:  //disband解散
                        $this->plugin->getServer()->dispatchCommand($player, "campsite disband");
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::CAMPSITE_APPLICATION:
                switch ((int) $data) {
                    case 0:
                        $this->plugin->getServer()->dispatchCommand($player, "campsite accept all");
                        break;
                    case 1:
                        $this->plugin->getServer()->dispatchCommand($player, "campsite disagree all");
                        break;
                    case 2:
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                        break;
                    default:
                        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName);
                        $application = Campsite::getInstance($this->plugin)->getApplication($CID);
                        $applicantName = $application[((int) $data) - 3];
                        $this->plugin->waitingConfirmation->addWC($playerName, function ($confirmed) use ($player, $playerName, $applicantName, $CID) {
                            if ($confirmed)
                                $this->plugin->getServer()->dispatchCommand($player, "campsite accept " . $applicantName);
                            else
                                $this->plugin->getServer()->dispatchCommand($player, "campsite disagree " . $applicantName);
                        });
                        //发送gui窗口
                        $this->plugin->gui->handle(self::CONFIRM, $player, $this->logo . "管理" . $applicantName . "的入营申请", "§a请选择是否同意玩家" . $applicantName . "加入营地。", ["同意", "拒绝"]);
                }
                break;
            case self::COHABITANT:
                switch ((int) $data) {
                    case 0:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "cohabitant propose value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "申请同居", "", "请输入同居名：", "<cohabitant_name>");
                        break;
                    case 1:
                        $this->plugin->getServer()->dispatchCommand($player, "cohabitant transfer");
                        break;
                    case 2:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "cohabitant opdiv value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "强制解除同居", "", "请输入同居名：", "<cohabitant_name>");
                        break;
                    case 3:
                        $this->plugin->getServer()->dispatchCommand($player, "cohabitant divorce");
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::MEBPRE:
                switch ((int) $data) {
                    case 0:
                        $this->plugin->getServer()->dispatchCommand($player, "mebpre list");
                        break;
                    case 1:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebpre change option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "更换使用称号", "", "请选择要使用的称号：", Players::getInstance($this->plugin)->getPlayerAllPrefixes($playerName));
                        break;
                    case 2:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebpre add value1 value2");
                        $this->plugin->gui->handle(self::TWO_V_INPUT_FORM, $player, "给予玩家称号", "", ["请输入玩家名：", "请输入称号："], ["<player_name>", "<prefix_name>"]);
                        break;
                    case 3:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebpre del value1 value2");
                        $this->plugin->gui->handle(self::TWO_V_INPUT_FORM, $player, "回收玩家称号", "", ["请输入玩家名：", "请输入称号："], ["<player_name>", "<prefix_name>"]);
                        break;
                    case 4:
                        $this->plugin->getServer()->dispatchCommand($player, "mebpre oppre");
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::MW:
                switch ((int) $data) {
                    case 0:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mw go option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "世界传送", "", "请选择要传送的世界：", MultiWorld::getInstance($this->plugin)->getAllWolrdName());
                        break;
                    case 1:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mw transfer option value value value");
                        $this->plugin->gui->handle(self::TWO_D_ONE_I_FORM, $player, "定点传送", "", ["请选择世界：", "请输入x坐标：", "请输入y坐标：", "请输入z坐标："], [MultiWorld::getInstance($this->plugin)->getAllWolrdName(), "若不填则默认传送至世界出生点", "若不填则默认传送至世界出生点", "若不填则默认传送至世界出生点"]);
                        break;
                    case 2:
                        $worldString = "世界名 | 是否已加载 | 在线玩家\n-----------------------------------------------\n";
                        foreach (MultiWorld::getInstance($this->plugin)->getAllWolrdName() as $key => $name)
                            $worldString .= $name . " | " . MultiWorld::getInstance($this->plugin)->getLoadInfo($name) . "§r | " . (MultiWorld::getInstance($this->plugin)->isWorldLoaded($name) === true ? MultiWorld::getInstance($this->plugin)->getOnlineNum($name) : 0) . "\n";
                        $this->plugin->gui->handle(self::TEXT_FORM, $player, "世界名单", $worldString);
                        break;
                    case 3:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mw info option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "查询世界信息", "", "请选择要查询的世界：", MultiWorld::getInstance($this->plugin)->getAllWolrdName());
                        break;
                    case 4:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mw setinfo option value");
                        $this->plugin->gui->handle(self::TWO_D_ONE_I_FORM, $player, "设置世界描述", "", ["请选择世界：", "请输入描述："], [MultiWorld::getInstance($this->plugin)->getAllWolrdName(), "<info>"]);
                        break;
                    case 5:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mw load option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "加载世界", "", "请选择要加载的世界：", MultiWorld::getInstance($this->plugin)->getAllWolrdName());
                        break;
                    case 6:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mw unload option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "卸载世界", "", "请选择要卸载的世界：", MultiWorld::getInstance($this->plugin)->getAllWolrdName());
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::MEBOP:
                switch ((int) $data) {
                    case 0:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebop add value");
                        $this->plugin->gui->handle(self::ONE_V_INPUT_FORM, $player, "新增op", "", "请输入玩家名：", "<player_name>");
                        break;
                    case 1:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebop del option");
                        $a = Players::getInstance($this->plugin)->getOps();
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "删除op", "", "请选择要删除的op：", Players::getInstance($this->plugin)->getOps());
                        break;
                    case 2:
                        $opString = "OP | 状态\n----------------\n";
                        foreach (Players::getInstance($this->plugin)->getOps() as $key => $name)
                            $opString .= $name . " | " . (Players::getInstance($this->plugin)->isOnline($name) === true ? "§a在线" : "§c离线") . "\n";
                        $this->plugin->gui->handle(self::TEXT_FORM, $player, "op名单", $opString);
                        break;
                    case 3:
                        $commands = $this->plugin->getServer()->getCommandMap()->getCommands();
                        $commands = array_keys($commands);
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebop licmd option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "禁用指令", "", "请选择要禁用的指令：", $commands);
                        break;
                    case 4:
                        $commands = Players::getInstance($this->plugin)->getAllLimitedCmd();
                        if (empty($commands))
                            $commands = array();
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebop unlicmd option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "取消禁用指令", "", "请选择要取消禁用的指令：", $commands);
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::MEBVIP:
                switch ((int) $data) {
                    case 0:
                        $vipString = "VIP | 剩余天数 | 状态\n----------------\n";
                        foreach (Players::getInstance($this->plugin)->getVips() as $key => $name)
                            $vipString .= $name . " | " . Players::getInstance($this->plugin)->getVipDay($name) . " | " . (Players::getInstance($this->plugin)->isOnline($name) === true ? "§a在线" : "§c离线") . "\n";
                        $this->plugin->gui->handle(self::TEXT_FORM, $player, "vip名单", $vipString);
                        break;
                    case 1:
                        $vipString = "SVIP | 剩余天数 | 状态\n----------------\n";
                        foreach (Players::getInstance($this->plugin)->getVips(false) as $key => $name)
                            $vipString .= $name . " | " . Players::getInstance($this->plugin)->getVipDay($name, false) . " | " . (Players::getInstance($this->plugin)->isOnline($name) === true ? "§a在线" : "§c离线") . "\n";
                        $this->plugin->gui->handle(self::TEXT_FORM, $player, "svip名单", $vipString);
                        break;
                    case 2:
                        $this->plugin->gui->handle(self::VIPPRIVILEGE, $player);
                        break;
                    case 3:
                        $this->plugin->gui->handle(self::SVIPPRIVILEGE, $player);
                        break;
                    case 4:
                        $this->plugin->getServer()->dispatchCommand($player, "mebvip opvip");
                        break;
                    case 5:
                        $this->plugin->getServer()->dispatchCommand($player, "mebsvip opsvip");
                        break;
                    case 6:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebvip set option value");
                        $this->plugin->gui->handle(self::TWO_D_ONE_I_FORM, $player, "设置vip天数", "", ["请选择玩家：", "请输入天数："], [Players::getInstance($this->plugin)->getAllOnlinePlayerName(), "<day>"]);
                        break;
                    case 7:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebsvip set option value");
                        $this->plugin->gui->handle(self::TWO_D_ONE_I_FORM, $player, "设置svip天数", "", ["请选择玩家：", "请输入天数："], [Players::getInstance($this->plugin)->getAllOnlinePlayerName(), "<day>"]);
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::VIPPRIVILEGE:
                switch ((int) $data) {
                    case 0:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebvip day option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "查看vip剩余天数", "", "请选择玩家：", Players::getInstance($this->plugin)->getVips());
                        break;
                    case 1:
                        $this->plugin->getServer()->dispatchCommand($player, "mebvip sign");
                        break;
                    case 2:
                        $this->plugin->getServer()->dispatchCommand($player, "mebvip fly");
                        break;
                    case 3:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebvip guicolor option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "更换聊天颜色", "", "请选择颜色：", Players::getInstance($this->plugin)->getAllColor());
                        break;
                    case 4:
                        $onlinePlayers = Players::getInstance($this->plugin)->getAllOnlinePlayerName();
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebvip transfer option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "vip传送", "", "请选择玩家：", $onlinePlayers);
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::SVIPPRIVILEGE:
                switch ((int) $data) {
                    case 0:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebsvip day option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "查看svip剩余天数", "", "请选择玩家：", Players::getInstance($this->plugin)->getVips(false));
                        break;
                    case 1:
                        $this->plugin->getServer()->dispatchCommand($player, "mebsvip sign");
                        break;
                    case 2:
                        $this->plugin->getServer()->dispatchCommand($player, "mebsvip fly");
                        break;
                    case 3:
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebsvip guicolor option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "更换聊天颜色", "", "请选择颜色：", Players::getInstance($this->plugin)->getAllColor(false));
                        break;
                    case 4:
                        $onlinePlayerName = Players::getInstance($this->plugin)->getAllOnlinePlayerName();
                        if ($this->plugin->guiCommand->hasGC($playerName))
                            return $this->guiCommandError->guiCommandNotHandled($player);
                        $this->plugin->guiCommand->addGC($playerName, "mebsvip transfer option");
                        $this->plugin->gui->handle(self::ONE_DROPDOWN_FORM, $player, "svip强制传送", "", "请选择玩家：", $onlinePlayerName);
                        break;
                    default:  //return
                        $lastFormId = $this->plugin->guiStackSet->getGSS($playerName)->out();
                        $this->plugin->gui->handle($lastFormId, $player);
                }
                break;
            case self::SHOP:
                break;
        }
        $this->plugin->guiStackSet->getGSS($playerName)->in($id);  //窗口id进栈
    }
}