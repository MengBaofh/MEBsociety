<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\Tools\ArrayPage;
use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\Economy;
use MengBao\MEBsociety\Units\MultiWorld;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class SvipCommandHandler implements CommandHandlerInterface
{
    public $logo = "[MEBS]";
    private $plugin;  //插件主类

    public function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    public function handle(CommandSender $sender, array $args): void
    {
        $c_name = $this->getCommandName();
        if (!isset($args[0])) {
            $sender->sendMessage($this->logo . "§c输入/" . $c_name . " help来获取帮助!");
            return;
        }
        switch ($args[0]) {
            case "help":
                $this->help($sender);
                break;
            case "opsvip":
                $this->opsvip($sender);
                break;
            case "set":
                $this->set($sender, $args);
                break;
            case "list":
                $this->list($sender, $args);
                break;
            case "day":
                $this->day($sender, $args);
                break;
            case "sign":
                $this->sign($sender);
                break;
            case "fly":
                $this->fly($sender);
                break;
            case "color":
                $this->color($sender, $args);
                break;
            case "transfer":
                $this->transfer($sender, $args);
                break;
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }

    public function help(CommandSender $sender): void
    {
        $c_name = $this->getCommandName();
        $senderName = strtolower($sender->getName());
        $sender->sendMessage("---------" . $this->logo . "SVIP指令帮助---------");
        if (Players::getInstance($this->plugin)->isMaster($senderName) || $sender instanceof ConsoleCommandSender) {
            $sender->sendMessage("§e> /" . $c_name . " opsvip --- 开关op管理svip的权限");
        }
        if ((Players::getInstance($this->plugin)->isOp($senderName) && Players::getInstance($this->plugin)->hasOpVip(false)) || Players::getInstance($this->plugin)->isMaster($senderName) || $sender instanceof ConsoleCommandSender) {
            $sender->sendMessage("§e> /" . $c_name . " set <player_name> <day> --- 设置玩家的svip天数(-1表示永久/0表示取消权限)");
        }
        $sender->sendMessage("§e> /" . $c_name . " list --- 查看所有svip");
        $sender->sendMessage("§e> /" . $c_name . " day [player_name] --- 查看玩家/自己的svip天数");
        $sender->sendMessage("§e> /" . $c_name . " sign --- svip每日签到");
        $sender->sendMessage("§e> /" . $c_name . " fly --- 切换飞行模式");
        $sender->sendMessage("§e> /" . $c_name . " color <1/2/3/4/5/6/7/8/9/a/b/c/d/e/f/o/m/r> --- 设置聊天颜色");
        $sender->sendMessage("§e> /" . $c_name . " transfer <player_name> --- svip强制传送");
    }

    public function opsvip(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
        }
        $power = Players::getInstance($this->plugin)->hasOpVip(false);
        $temp = $power === true ? "关闭" : "开启";
        Players::getInstance($this->plugin)->reverseOpVip(false);
        $sender->sendMessage($this->logo . "§a成功" . $temp . "op管理svip的权限！");
    }


    public function set(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ((!Players::getInstance($this->plugin)->isOp($senderName) || !Players::getInstance($this->plugin)->hasOpVip()) && !Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入天数！");
            return;
        }
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (!is_numeric($args[2])) {
            $sender->sendMessage($this->logo . "§c天数必须为整数！");
            return;
        }
        $args[2] = (int) $args[2];
        Players::getInstance($this->plugin)->setVip($args[1], $args[2], false);
        if ($args[2] !== -1) {
            $sender->sendMessage($this->logo . "§a成功设置玩家" . $args[1] . "的svip天数为：" . $args[2] . "天！");
            Players::getInstance($this->plugin)->sendSuperMsg($args[1], $senderName . "将你的svip天数设置为：" . $args[2] . "天！");
        } else {
            $sender->sendMessage($this->logo . "§a成功设置玩家" . $args[1] . "的svip天数为永久！");
            Players::getInstance($this->plugin)->sendSuperMsg($args[1], $senderName . "将你的svip天数设置为：永久！");
        }
    }

    public function list(CommandSender $sender, array $args): void
    {
        if (!isset($args[1]))
            $page = 1;
        else
            $page = $args[1];
        if (!is_numeric($page)) {
            $sender->sendMessage($this->logo . "§c页码必须为整数！");
            return;
        }
        $page = (int) $page;
        $vipsArray = new ArrayPage(Players::getInstance($this->plugin)->getVips(false), Players::getInstance($this->plugin)->getVipEachNum());
        if (!$vipsArray->isValidPage($page)) {
            $sender->sendMessage($this->logo . "§c页码不合理！(1~" . $vipsArray->getTotalPages() . ")");
            return;
        }
        $sender->sendMessage($this->logo . "§a服务器的全部svip如下<" . $page . "/" . $vipsArray->getTotalPages() . ">：");
        foreach ($vipsArray->getContent($page) as $playerName)
            $sender->sendMessage($playerName . "|剩余天数：" . Players::getInstance($this->plugin)->getVipDay($playerName, false) . "|状态：" . (Players::getInstance($this->plugin)->isOnline($playerName) === true ? "§a在线" : "§c离线"));
    }

    public function day(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!isset($args[1]))
            $args[1] = $senderName;
        else
            $args[1] = strtolower($args[1]);
        if ($args[1] === "console") {
            $sender->sendMessage($this->logo . "§c控制台哪来的svip？");
            return;
        }
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (!Players::getInstance($this->plugin)->isVip($args[1], false)) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不是svip！");
            return;
        }
        $sender->sendMessage($this->logo . "§e玩家" . $args[1] . "的svip剩余天数为：" . Players::getInstance($this->plugin)->getVipDay($args[1], false) . "天。");
    }

    public function sign(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isVip($senderName, false)) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
            return;
        }
        if (Players::getInstance($this->plugin)->isSigned($senderName, false)) {
            $sender->sendMessage($this->logo . "§c你今天已经签过到了！");
            return;
        }
        Players::getInstance($this->plugin)->setSign($senderName, true, false);
        $money = Players::getInstance($this->plugin)->getSignMoney(false);
        Economy::getInstance($this->plugin)->addMoney($senderName, $money);
        $sender->sendMessage($this->logo . "§a成功签到，获得" . $money . "个游戏币！");
    }

    public function fly(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isVip($senderName, false)) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
            return;
        }
        $sender->setAllowFlight(!$sender->getAllowFlight());
        $sender->sendMessage($this->logo . "§a切换模式成功！");
    }

    public function color(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isVip($senderName, false)) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入颜色代号！");
            return;
        }
        $colorArray = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f", "o", "m", "r");
        if (!in_array($args[1], $colorArray)) {
            $sender->sendMessage($this->logo . "§c未知的颜色代号：" . $args[1]);
            return;
        }
        Players::getInstance($this->plugin)->setColor($senderName, $args[1], false);
        $sender->sendMessage($this->logo . "§a成功更换颜色为：§" . $args[1] . $args[1]);
    }

    public function transfer(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isVip($senderName, false)) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
            return;
        }
        if (Players::getInstance($this->plugin)->getPlayerTransferNum($senderName, false) <= 0) {
            $sender->sendMessage($this->logo . "§c你今天的传送次数已用完！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        $player = $this->plugin->getServer()->getPlayerExact($args[1]);  //获取在线玩家实例
        if ($player === null) {
            $sender->sendMessage($this->logo . "§c对方不在线！");
            return;
        }
        $worldName = $player->getWorld()->getFolderName();
        $x = (int) $player->getPosition()->getX();
        $y = (int) $player->getPosition()->getY();
        $z = (int) $player->getPosition()->getZ();
        $result = MultiWorld::getInstance($this->plugin)->transportPlayer($sender, $worldName, $x, $y, $z);
        if ($result === 1) {
            $sender->sendMessage($this->logo . "§e成功传送，消耗一次传送次数。");
            Players::getInstance($this->plugin)->setPlayerTransferNum($senderName, Players::getInstance($this->plugin)->getPlayerTransferNum($senderName, false) - 1, false);
        } else
            $sender->sendMessage($this->logo . "§c世界未加载，传送失败！");
    }

    public function getCommandName(): string
    {
        return "mebsvip";  //指令名
    }
}