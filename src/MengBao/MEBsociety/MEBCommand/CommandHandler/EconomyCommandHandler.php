<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\Economy;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class EconomyCommandHandler implements CommandHandlerInterface
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
            $sender->sendMessage($this->logo . "输入/" . $c_name . " help来获取帮助!");
            return;
        }
        switch ($args[0]) {
            case "help":
                $sender->sendMessage("-------" . $this->logo . "游戏币指令帮助-------");
                $sender->sendMessage("§e> /" . $c_name . " my --- 查看自己的游戏币");
                $sender->sendMessage("§e> /" . $c_name . " get <player_name> --- 查看某人的游戏币");
                $sender->sendMessage("§e> /" . $c_name . " add <player_name> <money> --- 增加某人的游戏币");
                $sender->sendMessage("§e> /" . $c_name . " remove <player_name> <money> --- 减少某人的游戏币");
                $sender->sendMessage("§e> /" . $c_name . " pay <player_name> <money> --- 支付给某人游戏币");
                //$sender->sendMessage("§e> /" . $c_name . " bankin <money> --- 向银行存钱");
                //$sender->sendMessage("§e> /" . $c_name . " bankout <money> --- 取出存入银行的钱");
                //$sender->sendMessage("§e> /" . $c_name . " banksee --- 查看存入银行的钱");
                //$sender->sendMessage("§e> /" . $c_name . " bankrate --- 查看银行月利率");
                $sender->sendMessage("§e> /" . $c_name . " top <page> --- 查看游戏币排行榜");
                break;
            case "my":  //除了控制台都可以输入
                if ($sender instanceof ConsoleCommandSender) {
                    $sender->sendMessage($this->logo . "控制台哪来的游戏币?");
                    return;
                }
                $senderName = strtolower($sender->getName());
                $myMoney = Economy::getInstance($this->plugin)->getMoney($senderName);
                $sender->sendMessage($this->logo . "§a您的游戏币数量为：" . $myMoney . "。");
                break;
            case "get":
                if (!isset($args[1])) {
                    $sender->sendMessage($this->logo . "§c未输入待查询的玩家名，查询失败！");
                    return;
                }
                $args[1] = strtolower($args[1]);
                $myMoney = Economy::getInstance($this->plugin)->getMoney($args[1]);
                if ($myMoney === (float) -1)
                    $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
                else
                    $sender->sendMessage($this->logo . "§a玩家" . $args[1] . "的游戏币数量为：" . $myMoney . "。");
                break;
            case "add":
                if (!isset($args[1]) || !isset($args[2])) {
                    $sender->sendMessage($this->logo . "§c未输入<player_name>或<money>，增加游戏币失败！");
                    return;
                }
                $args[1] = strtolower($args[1]);
                $senderName = strtolower($sender->getName());
                if ($args[2] < 0 || !is_numeric($args[2])) {
                    $sender->sendMessage($this->logo . "§c<money>必须为正数！");
                    return;
                }
                if (!Players::getInstance($this->plugin)->isOp($senderName) && !$sender instanceof ConsoleCommandSender) {
                    $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
                    return;
                }
                $result = Economy::getInstance($this->plugin)->addMoney($args[1], $args[2]);
                if ($result === -1)
                    $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
                else
                    $sender->sendMessage($this->logo . "§a成功为玩家" . $args[1] . "增加游戏币：" . $args[2]);
                break;
            case "remove":
                if (!isset($args[1]) || !isset($args[2])) {
                    $sender->sendMessage($this->logo . "§c未输入<player_name>或<money>，增加游戏币失败！");
                    return;
                }
                $args[1] = strtolower($args[1]);
                $senderName = strtolower($sender->getName());
                if ($args[2] < 0 || !is_numeric($args[2])) {
                    $sender->sendMessage($this->logo . "§c<money>必须为正数！");
                    return;
                }
                if (!Players::getInstance($this->plugin)->isOp($senderName) && !$sender instanceof ConsoleCommandSender) {
                    $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
                    return;
                }
                $result = Economy::getInstance($this->plugin)->addMoney($args[1], -$args[2]);
                if ($result === -1)
                    $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
                elseif ($result === -2)
                    $sender->sendMessage($this->logo . "§c游戏币不能减少为负值！");
                else
                    $sender->sendMessage($this->logo . "§a成功为玩家" . $args[1] . "减少游戏币：" . $args[2]);
                break;
            case "pay":
                if ($sender instanceof ConsoleCommandSender) {
                    $sender->sendMessage($this->logo . "§c控制台禁止输入该指令！");
                    return;
                }
                if (!isset($args[1]) || !isset($args[2])) {
                    $sender->sendMessage($this->logo . "§c未输入<player_name>或<money>，支付失败！");
                    return;
                }
                $args[1] = strtolower($args[1]);
                $senderName = strtolower($sender->getName());
                if ($args[2] < 0 || !is_numeric($args[2])) {
                    $sender->sendMessage($this->logo . "§c<money>必须为正数！");
                    return;
                }
                if ($args[1] === $senderName) {
                    $sender->sendMessage($this->logo . "§c不能给你自己转账！");
                    return;
                }
                $result = Economy::getInstance($this->plugin)->payMoney($senderName, $args[1], $args[2]);
                if ($result === -1)
                    $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
                elseif ($result === -2)
                    $sender->sendMessage($this->logo . "§c你没有足够的游戏币来支付费用：" . $args[2] . "！");
                else
                    $sender->sendMessage($this->logo . "§a成功支付给玩家" . $args[1] . "游戏币：" . $args[2]);
                break;
            case "top":
                Economy::getInstance($this->plugin)->getRanking();  //获取并更新排行榜
                $maxPage = count($this->plugin->economyRanking->getAll());
                if (!isset($args[1]) || $args[1] < 1)
                    $page = 1;
                elseif ($args[1] > $maxPage)
                    $page = $maxPage;
                else
                    $page = $args[1];
                $sender->sendMessage("-------" . $this->logo . "游戏币排行榜<" . $page . "/" . $maxPage . ">-------");
                foreach ($this->plugin->economyRanking->get($page) as $playerName => $money)
                    $sender->sendMessage($playerName . ":" . $money);
                break;
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }
    public function getCommandName(): string
    {
        return "money";  //指令名
    }
}