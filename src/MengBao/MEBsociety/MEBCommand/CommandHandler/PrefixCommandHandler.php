<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class PrefixCommandHandler implements CommandHandlerInterface
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
            case "oppre":
                $this->oppre($sender);
                break;
            case "add":
                $this->add($sender, $args);
                break;
            case "del":
                $this->del($sender, $args);
                break;
            case "list":
                $this->list($sender);
                break;
            case "change":
                $this->change($sender, $args);
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
            $sender->sendMessage("§e> /" . $c_name . " oppre --- 开关op管理称号的权限");
        }
        if ((Players::getInstance($this->plugin)->isOp($senderName) && Players::getInstance($this->plugin)->hasOpVip()) || Players::getInstance($this->plugin)->isMaster($senderName) || $sender instanceof ConsoleCommandSender) {
            $sender->sendMessage("§e> /" . $c_name . " add <player_name> <prefix> --- 给予玩家称号");
            $sender->sendMessage("§e> /" . $c_name . " del <player_name> <prefix> --- 回收玩家称号");
        }
        $sender->sendMessage("§e> /" . $c_name . " list --- 查看拥有的称号");
        $sender->sendMessage("§e> /" . $c_name . " change <prefix_id> --- 更换称号");
    }

    public function oppre(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        $power = Players::getInstance($this->plugin)->hasOpPrefix();
        $temp = $power === true ? "关闭" : "开启";
        Players::getInstance($this->plugin)->reverseOpPrefix();
        $sender->sendMessage($this->logo . "§a成功" . $temp . "op管理称号的权限！");
    }

    public function add(CommandSender $sender, array $args): void
    {
        //称号支持§，区分大小写
        $senderName = strtolower($sender->getName());
        if ((!Players::getInstance($this->plugin)->isOp($senderName) || !Players::getInstance($this->plugin)->hasOpPrefix()) && !Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
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
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入称号！");
            return;
        }
        $args[2] = trim($args[2]);
        if (Players::getInstance($this->plugin)->isPrefixExist($args[1], $args[2])) {
            $sender->sendMessage($this->logo . "§c玩家已拥有该称号！");
            return;
        }
        Players::getInstance($this->plugin)->addPrefix($args[1], $args[2]);
        $sender->sendMessage($this->logo . "§a成功给予玩家" . $args[1] . "称号：" . $args[2]);
        Players::getInstance($this->plugin)->sendSuperMsg($args[1], $senderName . "授予你称号：" . $args[2]);
    }

    public function del(CommandSender $sender, array $args): void
    {
        //称号支持§，区分大小写
        $senderName = strtolower($sender->getName());
        if ((!Players::getInstance($this->plugin)->isOp($senderName) || !Players::getInstance($this->plugin)->hasOpPrefix()) && !Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
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
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入称号！");
            return;
        }
        $args[2] = trim($args[2]);
        if (!Players::getInstance($this->plugin)->isPrefixExist($args[1], $args[2])) {
            $sender->sendMessage($this->logo . "§c玩家未拥有该称号！");
            return;
        }
        Players::getInstance($this->plugin)->delPrefix($args[1], $args[2]);
        $sender->sendMessage($this->logo . "§a成功回收玩家" . $args[1] . "的称号：" . $args[2]);
        Players::getInstance($this->plugin)->sendSuperMsg($args[1], $senderName . "回收了你的称号：" . $args[2]);
    }

    public function list(CommandSender $sender): void
    {
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        $senderName = strtolower($sender->getName());
        $sender->sendMessage($this->logo . "§e你拥有的称号如下：");
        foreach (Players::getInstance($this->plugin)->getPlayerAllPrefixes($senderName) as $key => $prefix)
            $sender->sendMessage($key . "=>" . $prefix);
    }

    public function change(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入称号id！");
            return;
        }
        if (!is_numeric($args[1])) {
            $sender->sendMessage($this->logo . "§c称号id必须为整数！");
            return;
        }
        $args[1] = (int) $args[1];
        if (!Players::getInstance($this->plugin)->isPrefixIdExist($senderName, $args[1])) {
            $sender->sendMessage($this->logo . "§c未知的称号id：" . $args[1]);
            return;
        }
        Players::getInstance($this->plugin)->setCurPrefix($senderName, $args[1]);
        $sender->sendMessage($this->logo . "§a成功使用称号：" . Players::getInstance($this->plugin)->getCurPrefix($senderName));
    }

    public function getCommandName(): string
    {
        return "mebpre";  //指令名
    }
}