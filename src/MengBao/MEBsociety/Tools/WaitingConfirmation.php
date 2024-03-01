<?

namespace MengBao\MEBsociety\Tools;

/**
 * 等待答复回调函数
 */
class WaitingConfirmation
{
    private array $waitingConfirmation;

    public function __construct()
    {
        $this->waitingConfirmation = array();
    }

    /**
     * 获取WaitingConfirmation数组
     */
    public function getAllWC(): array
    {
        return $this->waitingConfirmation;
    }

    /**
     * 获取玩家的回调函数
     */
    public function getWC(string $playerName): callable
    {
        return $this->waitingConfirmation[$playerName];
    }

    /**
     * 一键设置waitingConfirmation
     */
    public function setWC(array $waitingConfirmation): void
    {
        $this->waitingConfirmation = $waitingConfirmation;
    }

    /**
     * 判断玩家是否已有未答复
     */
    public function hasWC(string $playerName): bool
    {
        return isset($this->getAllWC()[$playerName]);
    }

    /**
     * 添加等待答复
     * 玩家已有未答复则返回false
     */
    public function addWC(string $playerName, callable $callable): bool
    {
        if ($this->hasWC($playerName))
            return false;
        $this->waitingConfirmation[$playerName] = $callable;
        return true;
    }

    /**
     * 删除等待答复
     * 无等待答复则返回false
     */
    public function delWC(string $playerName): bool
    {
        if (!$this->hasWC($playerName))
            return false;
        unset($this->waitingConfirmation[$playerName]);
        return true;
    }

}
