<?php

namespace MengBao\MEBsociety\Units;

use pocketmine\plugin\PluginBase;

class Cohabitant
{
    private static $instance;
    private $plugin;

    // 私有构造函数，防止外部直接实例化
    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 获取所有的同居键对
     */
    public function getAllCohabitant(): array
    {
        return array_keys($this->plugin->cohabitants->getAll());
    }

    /**
     * 判断玩家是否有强制解除同居权力
     * 前提：玩家是否存在
     */
    public function hasOpdivPower(string $playerName): bool
    {
        return $this->plugin->playerConfig->get($playerName)["强制解除同居权力"];
    }

    /**
     * 设置玩家强制解除同居权力
     */
    public function setOpdivPower(string $playerName, bool $temp = true): void
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        $playerConfig[$playerName]["强制解除同居权力"] = $temp;
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 通过游戏名获取同居名，若没有则返回null
     * 前提：玩家是否存在
     */
    public function getCohabitant(string $playerName): ?string
    {
        return $this->plugin->playerConfig->get($playerName)["同居"];
    }

    /**
     * 通过游戏名判断是否有同居
     * 前提：玩家是否存在
     */
    public function hasCohabitant(string $playerName): bool
    {
        return $this->getCohabitant($playerName) !== null;
    }

    /**
     * 增删同居关系，默认为增加
     * 前提：玩家是否存在/是否有同居
     */
    public function setCohabitant(string $playerNameA, string $playerNameB, bool $mode = true): void
    {
        $cohabitants = $this->plugin->cohabitants->getAll();
        $playerConfig = $this->plugin->playerConfig->getAll();
        if ($mode) {
            $transferNum = $this->plugin->cohabitantConfig->get("同居每日传送次数上限") * $this->getTransferNumScale($playerNameA);
            //修改cohabitants
            $cohabitants[$playerNameA . "-" . $playerNameB] = array(
                "transferNum" => $transferNum,
            );
            //修改playerconfig
            $playerConfig[$playerNameA]["同居"] = $playerNameB;
            $playerConfig[$playerNameB]["同居"] = $playerNameA;
        } else {
            if (isset($cohabitants[$playerNameA . "-" . $playerNameB]))
                unset($cohabitants[$playerNameA . "-" . $playerNameB]);
            else
                unset($cohabitants[$playerNameB . "-" . $playerNameA]);
            $playerConfig[$playerNameA]["同居"] = null;
            $playerConfig[$playerNameB]["同居"] = null;
        }
        //save
        $this->plugin->cohabitants->setAll($cohabitants);
        $this->plugin->cohabitants->save();
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 获取同居传送次数
     * 前提：是否有同居
     */
    public function getTransferNum(string $playerName): int
    {
        $cohabitantName = $this->getCohabitant($playerName);
        $cohabitants = $this->plugin->cohabitants->getAll();
        if (isset($cohabitants[$playerName . "-" . $cohabitantName]))
            return $cohabitants[$playerName . "-" . $cohabitantName]["transferNum"];
        else
            return $cohabitants[$cohabitantName . "-" . $playerName]["transferNum"];
    }

    /**
     * 获取同居传送倍数
     * 前提：是否有同居
     */
    public function getTransferNumScale(string $playerName): int
    {
        $cohabitantName = $this->getCohabitant($playerName);
        return $this->getPlayerTransferNumScale($playerName) * $this->getPlayerTransferNumScale($cohabitantName);
    }

    /**
     * 设置传送次数
     */
    public function setTransferNum(string $cohabitantKey, int $num): void
    {
        $cohabitants = $this->plugin->cohabitants->getAll();
        $cohabitants[$cohabitantKey]["transferNum"] = $num;
        $this->plugin->cohabitants->setAll($cohabitants);
        $this->plugin->cohabitants->save();
    }

    /**
     * 一键刷新全部同居传送次数
     */
    public function updateAllTransferNum(): void
    {
        $defaultTransferNum = $this->plugin->cohabitantConfig->get("同居每日传送次数上限");
        $cohabitants = $this->plugin->cohabitants->getAll();
        foreach($this->getAllCohabitant() as $cohabitantKey)
            $cohabitants[$cohabitantKey]["transferNum"] = $defaultTransferNum * $this->getTransferNumScale(explode("-", $cohabitantKey)[0]);
        $this->plugin->cohabitants->setAll($cohabitants);
        $this->plugin->cohabitants->save();
    }

    /**
     * 获取玩家的同居传送倍数
     * 前提：玩家是否存在
     */
    public function getPlayerTransferNumScale(string $playerName): int
    {
        return $this->plugin->playerConfig->get($playerName)["同居传送倍数"];
    }

    /**
     * 设置玩家的同居传送倍数
     */
    public function setPlayerTransferNumScale(string $playerName, int $num): void
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        $playerConfig[$playerName]["同居传送倍数"] = $num;
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): Cohabitant
    {
        // 如果实例不存在，或者参数不同，则创建新实例
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin) {
            self::$instance = new self($plugin);
        }
        // 返回实例
        return self::$instance;
    }
}