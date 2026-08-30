<?php

namespace MengBao\MEBSociety;

use pocketmine\item\StringToItemParser;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBSociety\Units\Players;
use MengBao\MEBSociety\Units\Cohabitant;
use MengBao\MEBSociety\Units\Campsite;

/**
 * 事件监听
 *
 * GUI的应答由MEBForms在Player内部处理，所以这里不再监听DataPacketReceiveEvent。
 */
class MEBListener implements Listener
{
    public $logo = "[MEBS]";
    private $plugin;  //插件主类

    public function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        //玩家名不区分大小写(case insensitive)
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $playerConfig = $this->plugin->playerConfig->getAll();
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
        $item = StringToItemParser::getInstance()->parse("book");
        if ($item !== null) {
            $item->setCustomName($this->logo . "§l§5导航");
            if (!Players::getInstance($this->plugin)->isInIventory($playerName, $this->logo . "§l§5导航")) {
                $player->getInventory()->addItem($item);
                $player->sendMessage($this->logo . "§l§5导航工具§7已发送至你的背包！");
            }
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
                $player->sendMessage("§c" . ($key + 1) . " §6=> §c" . $message);
            }
            $this->plugin->offlineMessage->clearOM($playerName);
            $this->plugin->offlineMessages->setAll($this->plugin->offlineMessage->getAllOM());
            $this->plugin->offlineMessages->save();
            $this->plugin->gui->openText($player, $this->logo . "接收的离线消息", $omString);
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $this->plugin->waitingConfirmation->delWC($playerName);  //玩家退出，删除数据
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        //玩家点击导航
        $player = $event->getPlayer();
        $item = $event->getItem();
        $name = $item->getCustomName();
        if ($name === $this->logo . "§l§5导航")
            $this->plugin->gui->openMain($player);
    }

    public function onPlayerChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $message = $event->getMessage();
        if ($this->plugin->waitingConfirmation->hasWC($playerName)) {
            $lowerMsg = strtolower(trim($message));
            if ($lowerMsg === "yes" || $lowerMsg === "no") {
                $callback = $this->plugin->waitingConfirmation->getWC($playerName);
                $callback($lowerMsg === "yes");
                //回调里通常自己会删，这里兜底一次，避免留下处理不掉的请求
                $this->plugin->waitingConfirmation->delWC($playerName);
            } else
                $player->sendMessage($this->logo . "§c你有一个请求未处理，暂时无法输入其他消息。请输入 'yes' 或 'no' 来确认或取消请求。");
            $event->cancel();
            return;
        }
        //格式化聊天消息
        if (!Players::getInstance($this->plugin)->playerExist($playerName))
            return;  //玩家数据还没初始化好，交给默认格式
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
}
