<?

namespace MengBao\MEBsociety\Tools;

/**
 * GuiID堆栈
 */
class GuiStack
{
    private int $top;  //栈顶
    private array $guiStack;

    public function __construct()
    {
        $this->top = -1;
        $this->guiStack = array();
    }

    /**
     * 获取guiStack数组
     */
    public function getAllStack(): array
    {
        return $this->guiStack;
    }

    /**
     * 出栈，失败返回false
     */
    public function out(): int|bool
    {
        if ($this->top <= -1)
            return false;
        $topValue = $this->guiStack[$this->top];
        unset($this->guiStack[$this->top]);
        $this->top--;
        return $topValue;
    }

    /**
     * 进栈
     */
    public function in(int $value): void
    {
        $this->top++;
        $this->guiStack[$this->top] = $value;
    }

    /**
     * 获取栈顶元素，空则返回false
     */
    public function getTop(): int|bool
    {
        if ($this->top <= -1)
            return false;
        return $this->guiStack[$this->top];
    }

}
