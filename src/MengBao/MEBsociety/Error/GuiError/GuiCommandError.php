<?

namespace MengBao\MEBsociety\Error\GuiError;

use pocketmine\player\Player;

/**
 * GUI指令错误
 */
class GuiCommandError
{
    private string $logo = "[MEBS]";

    public function guiCommandNotHandled(Player $player): void
    {
        $player->sendMessage($this->logo . "§c你有一个GUI指令未处理，无法执行当前请求！");
    }
}