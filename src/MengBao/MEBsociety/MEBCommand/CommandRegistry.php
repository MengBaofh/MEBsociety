<?php
namespace MengBao\MEBsociety\MEBCommand;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

//命令注册器
class CommandRegistry
{
    private $plugin;  //插件主类
    public $logo = "[MEBS]";
    public $commandHandlers = [];

    public function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(CommandHandlerInterface $handler): void
    {
        $this->commandHandlers[$handler->getCommandName()] = $handler;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $commandName = $command->getName();
        if (isset($this->commandHandlers[$commandName])) {  //根据指令分配命令处理器并调用
            $handler = $this->commandHandlers[$commandName];
            $handler->handle($sender, $args);
            return true;  //成功处理命令
        }
        return false;  //非本插件的命令
    }
}