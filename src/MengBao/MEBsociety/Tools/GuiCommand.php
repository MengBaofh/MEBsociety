<?

namespace MengBao\MEBsociety\Tools;

/**
 * gui指令
 */
class GuiCommand
{
    private array $guiCommand;

    public function __construct()
    {
        $this->guiCommand = array();
    }

    /**
     * 获取guiCommand数组
     */
    public function getAllGC(): array
    {
        return $this->guiCommand;
    }

    /**
     * 获取玩家的gui指令
     */
    public function getGC(string $playerName): string
    {
        return $this->guiCommand[$playerName];
    }

    /**
     * 一键设置guiCommand数组
     */
    public function setGC(array $guiCommand): void
    {
        $this->guiCommand = $guiCommand;
    }

    /**
     * 判断玩家是否已有未执行的gui指令
     */
    public function hasGC(string $playerName): bool
    {
        return isset($this->getAllGC()[$playerName]);
    }

    /**
     * 添加gui指令
     * 玩家已有则返回false
     */
    public function addGC(string $playerName, string $cmd): bool
    {
        if ($this->hasGC($playerName))
            return false;
        $this->guiCommand[$playerName] = $cmd;
        return true;
    }

    /**
     * 删除gui指令
     * 若无则返回false
     */
    public function delGC(string $playerName): bool
    {
        if (!$this->hasGC($playerName))
            return false;
        unset($this->guiCommand[$playerName]);
        return true;
    }

}
