<?

namespace MengBao\MEBsociety;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\server\CommandEvent;

use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\Cohabitant;
use MengBao\MEBsociety\Units\Campsite;

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
            $this->plugin->offlineMessage[$playerName] = array();
            $this->plugin->offlineMessages->setAll($this->plugin->offlineMessage);
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
        //权限检测
        if ($this->plugin->getServer()->isOp($playerName) && !Players::getInstance($this->plugin)->isOp($playerName)) {
            $this->plugin->getServer()->removeOP($playerName);
            Cohabitant::getInstance($this->plugin)->setPlayerTransferNumScale($playerName, 1);
            Cohabitant::getInstance($this->plugin)->setOpdivPower($playerName, false);
            Campsite::getInstance($this->plugin)->setPlayerCallScale($playerName, 1);
        }
        if (Players::getInstance($this->plugin)->isOp($playerName)) {
            //op权限重置检测
            if (!$this->plugin->getServer()->isOp($playerName))
                $this->plugin->getServer()->addOP($playerName);
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
        if (!empty($this->plugin->offlineMessage[$playerName])) {
            $offlineMessage = $this->plugin->offlineMessage[$playerName];
            $player->sendMessage($this->logo . "§c您接收到了如下离线消息：");
            foreach ($offlineMessage as $key => $message)
                $player->sendMessage("§c" . $key + 1 . "§a=>§c" . $message);
            $this->plugin->offlineMessage[$playerName] = array();
        }
    }

    public function onPlayerChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());
        $message = $event->getMessage();
        if (isset($this->plugin->waitingConfirmation[$playerName])) {  //存在等待确认的消息
            $lowerMsg = strtolower($message);
            if ($lowerMsg === "yes") {
                // 调用匿名函数，并传递确认状态为 true
                $callback = $this->plugin->waitingConfirmation[$playerName];
                $callback(true);
            } elseif ($lowerMsg === "no") {
                // 调用匿名函数，并传递确认状态为 false
                $callback = $this->plugin->waitingConfirmation[$playerName];
                $callback(false);
            } else
                $player->sendMessage($this->logo . "§c你有一个请求未处理，暂时无法输入其他消息。请输入 'yes' 或 'no' 来确认或取消请求。");
            // 防止其他玩家看到该玩家的回答消息
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
}