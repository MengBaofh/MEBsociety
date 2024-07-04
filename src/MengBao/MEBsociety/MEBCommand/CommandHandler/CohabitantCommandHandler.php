<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\CallbackTask;
use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\Economy;
use MengBao\MEBsociety\Units\MultiWorld;
use MengBao\MEBsociety\Units\Cohabitant;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class CohabitantCommandHandler implements CommandHandlerInterface
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
            case "propose":
                $this->propose($sender, $args);
                break;
            case "divorce":
                $this->divorce($sender);
                break;
            case "opdiv":
                $this->opdiv($sender, $args);
                break;
            case "transfer":
                $this->transfer($sender);
                break;
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }

    public function help(CommandSender $sender): void
    {
        $c_name = $this->getCommandName();
        $sender->sendMessage("---------" . $this->logo . "同居指令帮助---------");
        $sender->sendMessage("§e> /" . $c_name . " propose <player_name> --- 申请同居");
        $sender->sendMessage("§e> /" . $c_name . " divorce --- 解除同居");
        $sender->sendMessage("§e> /" . $c_name . " opdiv <player_name> --- 强制解除某人的同居关系");
        $sender->sendMessage("§e> /" . $c_name . " transfer --- 传送至同居位置");
    }

    public function propose(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (Cohabitant::getInstance($this->plugin)->hasCohabitant($senderName)) {
            $sender->sendMessage($this->logo . "§c你已经有同居了！");
            return;
        }
        $moneyCohabitant = $this->plugin->cohabitantConfig->get("同居需要的费用");
        if (Economy::getInstance($this->plugin)->getMoney($senderName) < $moneyCohabitant) {
            $sender->sendMessage($this->logo . "§c你没有足够的费用来确认同居关系，总共需要" . $moneyCohabitant);
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入同居对象！");
            return;
        }
        $player = $this->plugin->getServer()->getPlayerExact($args[1]);  //获取在线玩家实例
        if ($player === null) {  //若对方不在线或未注册
            $sender->sendMessage($this->logo . "§c对方不在线！");
            return;
        }
        if ($this->plugin->waitingConfirmation->hasWC($args[1])) {
            $sender->sendMessage($this->logo . "§c对方有一个请求未处理，无法接收当前请求！");
            return;
        }
        if (Cohabitant::getInstance($this->plugin)->hasCohabitant($args[1])) {
            $sender->sendMessage($this->logo . "§c对方已经有同居了！");
            return;
        }
        $player->sendMessage($this->logo . "§a玩家" . $senderName . "向你提出了同居申请，请在20s内作出回应。(yes/no)");
        $playerName = strtolower($player->getName());  //获取玩家名并转换为小写
        $this->plugin->waitingConfirmation->addWC($args[1], function ($confirmed) use ($player, $playerName, $sender, $senderName, $moneyCohabitant) {
            if ($confirmed) {
                $player->sendMessage($this->logo . "§a已接受同居申请。");
                $sender->sendMessage($this->logo . "§a对方接受了你的同居申请！");
                Economy::getInstance($this->plugin)->addMoney($senderName, -$moneyCohabitant);  //扣钱
                Cohabitant::getInstance($this->plugin)->setCohabitant($senderName, $playerName);  //增加同居关系
                $this->plugin->getServer()->broadcastMessage($this->logo . "§b玩家" . $senderName . "和玩家" . $playerName . "达成同居关系，让我们祝福他(她)们吧！");
            } else {
                $player->sendMessage($this->logo . "§a已拒绝同居申请。");
                $sender->sendMessage($this->logo . "§c对方拒绝了你的同居申请。");
            }
            $this->plugin->waitingConfirmation->delWC($senderName);
        });
        //创建一个定时器，在20秒后自动执行回调函数
        $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask(function () use ($player, $playerName, $sender): void {
            if ($this->plugin->waitingConfirmation->hasWC($playerName)) {
                $player->sendMessage($this->logo . "§c响应超时，已自动拒绝。");
                $sender->sendMessage($this->logo . "§c对方未作出回应，已自动拒绝你的同居申请。");
                $callback = $this->plugin->waitingConfirmation->getWC($playerName);
                $callback(false);
                $this->plugin->waitingConfirmation->delWC($playerName);
            }
        }), 20 * 20);
    }

    public function divorce(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Cohabitant::getInstance($this->plugin)->hasCohabitant($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有同居！");
            return;
        }
        $cohabitantName = Cohabitant::getInstance($this->plugin)->getCohabitant($senderName);
        $cohabitant = $this->plugin->getServer()->getPlayerExact($cohabitantName);  //获取在线玩家实例
        if ($cohabitant === null) {
            $sender->sendMessage($this->logo . "§c对方不在线！");
            return;
        }
        if ($this->plugin->waitingConfirmation->hasWC($cohabitantName)) {
            $sender->sendMessage($this->logo . "§c对方有一个请求未处理，无法接收当前请求！");
            return;
        }
        $cohabitant->sendMessage($this->logo . "§a玩家" . $senderName . "向你提出了解除同居申请，请在20s内作出回应。(yes/no)");
        $this->plugin->waitingConfirmation->addWC($cohabitantName, function ($confirmed) use ($cohabitant, $cohabitantName, $sender, $senderName) {
            if ($confirmed) {
                $cohabitant->sendMessage($this->logo . "§a已同意解除同居申请。");
                $sender->sendMessage($this->logo . "§a对方同意了你的解除同居申请。");
                Cohabitant::getInstance($this->plugin)->setCohabitant($senderName, $cohabitant->getName(), false);  //删除同居关系
            } else {
                $cohabitant->sendMessage($this->logo . "§a已拒绝解除同居申请。");
                $sender->sendMessage($this->logo . "§c对方拒绝了你的解除同居申请。");
            }
            $this->plugin->waitingConfirmation->delWC($cohabitantName);
        });
        // 创建一个定时器，在20秒后自动执行回调函数
        $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask(function () use ($cohabitant, $cohabitantName, $sender): void {
            if ($this->plugin->waitingConfirmation->hasWC($cohabitantName)) {
                $cohabitant->sendMessage($this->logo . "§c响应超时，已自动拒绝。");
                $sender->sendMessage($this->logo . "§c对方未作出回应，已自动拒绝你的解除同居申请。");
                $callback = $this->plugin->waitingConfirmation->getWC($cohabitantName);
                $callback(false);
                $this->plugin->waitingConfirmation->delWC($cohabitantName);
            }
        }), 20 * 20);
    }

    public function opdiv(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!$sender instanceof ConsoleCommandSender && !Cohabitant::getInstance($this->plugin)->hasOpdivPower($senderName)) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入强制解除同居关系的玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家不存在！");
            return;
        }
        if (!Cohabitant::getInstance($this->plugin)->hasCohabitant($args[1])) {
            $sender->sendMessage($this->logo . "§c该玩家还没有同居！");
            return;
        }
        $cohabitantName = Cohabitant::getInstance($this->plugin)->getCohabitant($args[1]);
        Cohabitant::getInstance($this->plugin)->setCohabitant($args[1], $cohabitantName, false);  //删除同居关系
        $sender->sendMessage($this->logo . "§a成功强制解除" . $args[1] . "和" . $cohabitantName . "的同居关系！");
        Players::getInstance($this->plugin)->sendSuperMsg($args[1], $senderName . "强制解除了你和" . $cohabitantName . "的同居关系！");
        Players::getInstance($this->plugin)->sendSuperMsg($cohabitantName, $senderName . "强制解除了你和" . $args[1] . "的同居关系！");
    }

    public function transfer(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Cohabitant::getInstance($this->plugin)->hasCohabitant($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有同居！");
            return;
        }
        if (Cohabitant::getInstance($this->plugin)->getTransferNum($senderName) <= 0) {
            $sender->sendMessage($this->logo . "§c今日的传送次数已用完！");
            return;
        }
        $cohabitantName = Cohabitant::getInstance($this->plugin)->getCohabitant($senderName);
        $cohabitant = $this->plugin->getServer()->getPlayerExact($cohabitantName);  //获取在线玩家实例
        if ($cohabitant === null) {
            $sender->sendMessage($this->logo . "§c同居不在线！");
            return;
        }
        $worldName = $cohabitant->getWorld()->getFolderName();
        $x = (int) $cohabitant->getPosition()->getX();
        $y = (int) $cohabitant->getPosition()->getY();
        $z = (int) $cohabitant->getPosition()->getZ();
        $result = MultiWorld::getInstance($this->plugin)->transportPlayer($sender, $worldName, $x, $y, $z);
        if ($result === 1){
            $sender->sendMessage($this->logo . "§e成功传送！");
            $cohabitant->sendMessage($this->logo . "§e你的同居已传送到你身边！");
        }
        else
            $sender->sendMessage($this->logo . "§c世界未加载，传送失败！");
    }

    public function getCommandName(): string
    {
        return "cohabitant";  //指令名
    }
}