<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\Tools\ArrayPage;
use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class OpCommandHandler implements CommandHandlerInterface
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
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令!");
            return;
        }
        if (!isset($args[0])) {
            $sender->sendMessage($this->logo . "§c输入/" . $c_name . " help来获取帮助!");
            return;
        }
        switch ($args[0]) {
            case "help":
                $this->help($sender);
                break;
            case "master":
                $this->master($sender, $args);
                break;
            case "add":
                $this->add($sender, $args);
                break;
            case "del":
                $this->del($sender, $args);
                break;
            case "list":
                $this->list($sender, $args);
                break;
            case "licmd":
                $this->licmd($sender, $args);
                break;
            case "unlicmd":
                $this->unlicmd($sender, $args);
                break;
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }

    public function help(CommandSender $sender): void
    {
        $c_name = $this->getCommandName();
        $sender->sendMessage("---------" . $this->logo . "OP管理指令帮助---------");
        $sender->sendMessage("§e> /" . $c_name . " master <player_name> --- 设置最高权限");
        $sender->sendMessage("§e> /" . $c_name . " add <player_name> --- 新增op");
        $sender->sendMessage("§e> /" . $c_name . " del <player_name> --- 删除op");
        $sender->sendMessage("§e> /" . $c_name . " list [page] --- 查看所有op");
        $sender->sendMessage("§e> /" . $c_name . " licmd <command_name> --- 禁用一条指令(command_name不加'/')");
        $sender->sendMessage("§e> /" . $c_name . " unlicmd <command_name> --- 取消禁用一条指令");
    }

    public function master(CommandSender $sender, array $args): void
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (Players::getInstance($this->plugin)->isMaster($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "已经是最高权限了！");
            return;
        }
        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
        if ($player !== null) {
            $this->plugin->getServer()->addOP($args[1]);
            $player->sendMessage($this->logo . "§a你被授予最高权限！");
        }
        Players::getInstance($this->plugin)->addOp($args[1]);
        Players::getInstance($this->plugin)->setMaster($args[1]);
        $sender->sendMessage($this->logo . "§a成功授予" . $args[1] . "最高权限。");
    }

    public function add(CommandSender $sender, array $args): void
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (Players::getInstance($this->plugin)->isOp($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "已经是OP了！");
            return;
        }
        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
        if ($player !== null) {
            $this->plugin->getServer()->addOP($args[1]);
            $player->sendMessage($this->logo . "§a你被给予OP权限！");
        }
        Players::getInstance($this->plugin)->addOp($args[1]);
        $sender->sendMessage($this->logo . "§a成功添加" . $args[1] . "为OP。");
    }

    public function del(CommandSender $sender, array $args): void
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!Players::getInstance($this->plugin)->playerExist($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (!Players::getInstance($this->plugin)->isOp($args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "还不是OP！");
            return;
        }
        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
        if ($player !== null) {
            $this->plugin->getServer()->removeOp($args[1]);
            $player->sendMessage($this->logo . "§c你被剥夺OP权限！");
        }
        Players::getInstance($this->plugin)->removeOp($args[1]);
        $sender->sendMessage($this->logo . "§a成功剥夺" . $args[1] . "的OP权限。");
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
        $opArray = new ArrayPage(Players::getInstance($this->plugin)->getOps(), Players::getInstance($this->plugin)->getOpEachNum());
        if (!$opArray->isValidPage($page)) {
            $sender->sendMessage($this->logo . "§c页码不合理！(1~" . $opArray->getTotalPages() . ")");
            return;
        }
        $sender->sendMessage($this->logo . "§a服务器拥有OP权限的玩家如下<" . $page . "/" . $opArray->getTotalPages() . ">：");
        foreach ($opArray->getContent($page) as $playerName)
            $sender->sendMessage($playerName . "|状态：" . (Players::getInstance($this->plugin)->isOnline($playerName) === true ? "§a在线" : "§c离线"));
    }

    public function licmd(CommandSender $sender, array $args): void
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入要禁用的指令(指令前面不加'/')！");
            return;
        }
        if (Players::getInstance($this->plugin)->isCmdLimited($args[1])) {
            $sender->sendMessage($this->logo . "§c该指令已被禁用！");
            return;
        }
        Players::getInstance($this->plugin)->changeLiCmd($args[1]);
        $sender->sendMessage($this->logo . "§a成功禁用指令：" . $args[1]);
    }

    public function unlicmd(CommandSender $sender, array $args): void
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入要禁用的指令(指令前面不加'/')！");
            return;
        }
        if (!Players::getInstance($this->plugin)->isCmdLimited($args[1])) {
            $sender->sendMessage($this->logo . "§c该指令未被禁用！");
            return;
        }
        Players::getInstance($this->plugin)->changeLiCmd($args[1], false);
        $sender->sendMessage($this->logo . "§a成功取消禁用指令：" . $args[1]);
    }

    public function getCommandName(): string
    {
        return "mebop";  //指令名
    }
}