<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\Tools\ArrayPage;
use MengBao\MEBsociety\Units\Players;
use MengBao\MEBsociety\Units\MultiWorld;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class MultiWorldCommandHandler implements CommandHandlerInterface
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
            case "go":
                $this->go($sender, $args);
                break;
            case "transfer":
                $this->transfer($sender, $args);
                break;
            case "list":
                $this->list($sender, $args);
                break;
            case "setinfo":
                $this->setInfo($sender, $args);
                break;
            case "info":
                $this->info($sender, $args);
                break;
            case "load":
                $this->load($sender, $args);
                break;
            case "unload":
                $this->unload($sender, $args);
                break;
            /*case "limit":
                $this->limit($sender, $args);
                break;
            case "deli":
                $this->deli($sender, $args);
                break;*/
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }

    public function help(CommandSender $sender): void
    {
        $c_name = $this->getCommandName();
        $sender->sendMessage("---------" . $this->logo . "多世界指令帮助---------");
        $sender->sendMessage("§e> /" . $c_name . " go <world_name> --- 传送至世界出生点");
        $sender->sendMessage("§e> /" . $c_name . " transfer <world_name> [x] [y] [z] --- 定点传送(默认传送至世界出生点)");
        $sender->sendMessage("§e> /" . $c_name . " list [page] --- 查看所有世界名");
        $sender->sendMessage("§e> /" . $c_name . " setinfo <world_name> <information> --- 设置某世界的描述信息");
        $sender->sendMessage("§e> /" . $c_name . " info <world_name> --- 查看某世界的信息");
        $sender->sendMessage("§e> /" . $c_name . " load <world_name> --- 加载世界");
        $sender->sendMessage("§e> /" . $c_name . " unload <world_name> --- 卸载世界");
        //$sender->sendMessage("§e> /" . $c_name . " limit <world_name> <limitation_command> --- 添加世界传送条件");
        //$sender->sendMessage("§e> /" . $c_name . " deli <world_name> <limitation_command> --- 删除世界传送条件");
    }

    public function go(CommandSender $sender, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->logo . "§c只有玩家才可输入该指令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入世界名，传送失败!");
            return;
        }
        $result = MultiWorld::getInstance($this->plugin)->transportPlayer($sender, $args[1]);
        if ($result === 1)
            $sender->sendMessage($this->logo . "§e成功传送！");
        else
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "未加载！");
    }

    public function transfer(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->logo . "§c只有玩家才可输入该指令！");
            return;
        }
        if (!Players::getInstance($this->plugin)->isOp($senderName)) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令!");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入世界名，传送失败!");
            return;
        }
        $result = MultiWorld::getInstance($this->plugin)->transportPlayer($sender, $args[1], $args[2], $args[3], $args[4]);
        if ($result === 1)
            $sender->sendMessage($this->logo . "§e成功传送！");
        else
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "未加载！");
    }

    public function load(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isOp($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令!");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入待加载的世界名!");
            return;
        }
        $result = MultiWorld::getInstance($this->plugin)->loadWorldByName($args[1]);
        if ($result === -1)
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "不存在！");
        elseif ($result === -2)
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "已加载过了！");
        elseif ($result === -3)
            $sender->sendMessage($this->logo . "§c加载世界过程中出现未知错误！");
        else {
            MultiWorld::getInstance($this->plugin)->setLoadInfo($args[1], true);
            $sender->sendMessage($this->logo . "§a成功加载世界" . $args[1] . "！");
        }
    }

    public function unload(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isOp($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令!");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入待卸载的世界名!");
            return;
        }
        $result = MultiWorld::getInstance($this->plugin)->unloadWorldByName($args[1]);
        if ($result === -1)
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "不存在！");
        elseif ($result === -2)
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "未加载！");
        elseif ($result === -3)
            $sender->sendMessage($this->logo . "§c卸载世界过程中出现未知错误！");
        else {
            MultiWorld::getInstance($this->plugin)->setLoadInfo($args[1]);
            $sender->sendMessage($this->logo . "§a成功卸载世界" . $args[1] . "！");
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
        $worldArray = new ArrayPage(MultiWorld::getInstance($this->plugin)->getAllWolrdName(), MultiWorld::getInstance($this->plugin)->getWorldEachNum());
        if (!$worldArray->isValidPage($page)) {
            $sender->sendMessage($this->logo . "§c页码不合理！(1~" . $worldArray->getTotalPages() . ")");
            return;
        }
        $sender->sendMessage($this->logo . "§c服务器的全部世界名如下<" . $page . "/" . $worldArray->getTotalPages() . ">：");
        foreach ($worldArray->getContent($page) as $worldName)
            $sender->sendMessage($worldName);
    }

    public function setinfo(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isOp($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令!");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入世界名!");
            return;
        }
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入描述信息!");
            return;
        }
        if (!MultiWorld::getInstance($this->plugin)->isWorldExist($args[1])) {
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "不存在!");
            return;
        }
        MultiWorld::getInstance($this->plugin)->setInfo($args[1], $args[2]);
        $sender->sendMessage($this->logo . "成功设置世界" . $args[1] . "的描述信息为：" . $args[2]);
    }

    public function info(CommandSender $sender, array $args): void
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入世界名!");
            return;
        }
        if (!MultiWorld::getInstance($this->plugin)->isWorldExist($args[1])) {
            $sender->sendMessage($this->logo . "§c世界" . $args[1] . "不存在!");
            return;
        }
        $sender->sendMessage("------" . $this->logo . "世界信息查询------");
        $sender->sendMessage("世界名：" . $args[1]);
        $sender->sendMessage("世界描述：" . MultiWorld::getInstance($this->plugin)->getInfo($args[1]));
        $sender->sendMessage("是否已加载：" . MultiWorld::getInstance($this->plugin)->getLoadInfo($args[1]));
        $sender->sendMessage("在线玩家数量：" . (MultiWorld::getInstance($this->plugin)->isWorldLoaded($args[1]) === true ? MultiWorld::getInstance($this->plugin)->getOnlineNum($args[1]) : 0));
        //世界等级？
    }

    public function getCommandName(): string
    {
        return "mw";  //指令名
    }
}