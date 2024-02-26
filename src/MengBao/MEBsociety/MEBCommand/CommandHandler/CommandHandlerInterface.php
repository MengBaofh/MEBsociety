<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\command\CommandSender;

//命令处理器接口
interface CommandHandlerInterface
{
    public function handle(CommandSender $sender, array $args): void;
    public function getCommandName(): string;
}
