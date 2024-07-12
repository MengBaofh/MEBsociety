<?php

namespace MengBao\MEBsociety\Tools;

use MengBao\MEBsociety\Tools\GuiStack;

/**
 * gui点击顺序
 */
class GuiStackSet
{
    private array $guiStackSet;

    public function __construct()
    {
        $this->guiStackSet = array();
    }

    /**
     * 一键设置数组
     * @param array<string, GuiStack> $name
     */
    public function setAllGSS(array $guiStackSet): void
    {
        $this->guiStackSet = $guiStackSet;
    }

    /**
     * 获取guiStackSet数组
     */
    public function getAllGSS(): array
    {
        return $this->guiStackSet;
    }

    /**
     * 获取玩家的gui堆栈对象
     */
    public function getGSS(string $playerName): GuiStack
    {
        return $this->guiStackSet[$playerName];
    }

    /**
     * 判断玩家堆栈是否异常
     */
    public function checkStack(string $playerName): bool
    {
        $stack = $this->getGSS($playerName);
        if ($stack->top >= 100)  //堆栈中元素过多
            return true;
        return false;
    }

    /**
     * 删除玩家的gui堆栈对象
     */
    public function delGSS(string $playerName): void
    {
        if (isset($this->guiStackSet[$playerName]))
            unset($this->guiStackSet[$playerName]);
    }

    /**
     * 一键重建玩家的gui堆栈对象
     */
    public function newGSS(string $playerName): void
    {
        $this->delGSS($playerName);
        $this->guiStackSet[$playerName] = new GuiStack;
    }
}
