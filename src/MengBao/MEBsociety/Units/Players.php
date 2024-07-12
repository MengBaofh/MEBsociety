<?php

namespace MengBao\MEBsociety\Units;

use pocketmine\plugin\PluginBase;

class Players
{
    private static $instance;
    private $plugin;
    private string $logo = "[MEB]";

    // 私有构造函数，防止外部直接实例化
    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }


    /**
     * 通过自定义名检查玩家背包是否包含某物
     * 前提：玩家是否存在且在线
     */
    public function isInIventory(string $playerName, string $customName): bool
    {
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        foreach ($player->getInventory()->getContents() as $items) {
            $name = $items->getCustomName();
            if ($name === $customName)
                return true;
        }
        return false;
    }

    /**
     * 向玩家发送一条(离线)消息
     * 前提：玩家是否存在
     */
    public function sendSuperMsg(string $playerName, string $msg): void
    {
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null)
            $this->plugin->offlineMessage->addOM($playerName, $msg);
        else
            $player->sendMessage($msg);
    }

    /**
     * 检查指令是否已被禁用，不带/
     */
    public function isCmdLimited(string $cmdName): bool
    {
        return in_array("/" . $cmdName, $this->getAllLimitedCmd());
    }

    /**
     * 获取全部已禁用的指令
     */
    public function getAllLimitedCmd(): array
    {
        return $this->plugin->basicConfig->get("禁止使用的指令");
    }

    /**
     * 增删禁用指令，默认为禁用
     * 前提：指令是否已被禁用
     */
    public function changeLiCmd(string $cmdName, bool $type = true): void
    {
        $basicConfig = $this->plugin->basicConfig->getAll();
        if ($type)
            array_push($basicConfig["禁止使用的指令"], "/" . $cmdName);
        else {
            unset($basicConfig["禁止使用的指令"][array_search("/" . $cmdName, $basicConfig["禁止使用的指令"])]);
            $basicConfig["禁止使用的指令"] = array_values($basicConfig["禁止使用的指令"]);
        }
        $this->plugin->basicConfig->setAll($basicConfig);
        $this->plugin->basicConfig->save();
    }

    /**
     * 通过游戏名判断玩家是否存在
     */
    public function playerExist(string $playerName): bool
    {
        return isset($this->plugin->playerConfig->getAll()[$playerName]);
    }

    /**
     * 判断op是否可以管理vip/svip
     */
    public function hasOpVip(bool $type = true): bool
    {
        $temp = $type === true ? "vip" : "svip";
        return $this->plugin->vipConfig->get("op是否可以管理" . $temp);
    }

    /**
     * 反转op管理vip/svip的权限
     */
    public function reverseOpVip(bool $type = true): void
    {
        $temp = $type === true ? "vip" : "svip";
        $vipConfig = $this->plugin->vipConfig->get("op是否可以管理" . $temp);
        $this->plugin->vipConfig->set("op是否可以管理" . $temp, !$vipConfig);
        $this->plugin->vipConfig->save();
    }

    /**
     * 获取全部op
     */
    public function getOps(): array
    {
        return $this->plugin->basicConfig->get("OP");
    }

    /**
     * 获取全部vip/svip
     */
    public function getVips(bool $type = true): array
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        return array_keys($config->getAll());
    }

    /**
     * 判断玩家是否是op
     */
    public function isOp(string $playerName): bool
    {
        return in_array($playerName, $this->getOps());
    }

    /**
     * 添加op
     * 前提：玩家是否存在/是否已经是op
     */
    public function addOp(string $playerName): void
    {
        $basicConfig = $this->plugin->basicConfig->getAll();
        $basicConfig["OP"][] = $playerName;  //隐式添加元素
        $this->plugin->basicConfig->setAll($basicConfig);
        $this->plugin->basicConfig->save();
    }

    /**
     * 删除op
     */
    public function removeOp(string $playerName): void
    {
        $basicConfig = $this->plugin->basicConfig->getAll();
        unset($basicConfig["OP"][array_search($playerName, $basicConfig["OP"])]);
        $basicConfig["OP"] = array_values($basicConfig["OP"]);  //重置索引并转换为隐式数组
        $this->plugin->basicConfig->setAll($basicConfig);
        $this->plugin->basicConfig->save();
    }

    /**
     * 判断玩家是否是最高权限
     */
    public function isMaster(string $playerName): bool
    {
        return $playerName === $this->plugin->basicConfig->get("最高权限");
    }

    /**
     * 设置最高权限
     * 前提：玩家是否存在/是否已经是最高权限
     */
    public function setMaster(string $playerName): void
    {
        $basicConfig = $this->plugin->basicConfig->getAll();
        $basicConfig["最高权限"] = $playerName;
        $this->plugin->basicConfig->setAll($basicConfig);
        $this->plugin->basicConfig->save();
    }

    /**
     * 判断玩家是否是vip/svip
     */
    public function isVip(string $playerName, bool $type = true): bool
    {
        return in_array($playerName, $this->getVips($type));
    }

    /**
     * 设置vip/svip天数,-1永久,0取消
     * 前提：玩家是否存在/是否为永久
     */
    public function setVip(string $playerName, int $day, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        /*
        playerName:
        -"day"=>剩余天数（-1表示永久）
        -"sign"=>当天是否签到
        -"color"=>聊天消息颜色
        */
        if (!isset($vips[$playerName]))  //此前非vip
            $vips[$playerName] = array(
                "day" => $day,
                "sign" => false,
                "color" => "§e",
                "transfer" => $this->getTransferNum($type)
            );
        else
            $vips[$playerName]["day"] = $day;
        if ($vips[$playerName]["day"] === 0) {
            unset($vips[$playerName]);
            $vips[$playerName] = array_values($vips[$playerName]);
        }
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 一键增减所有vip/svip的天数(永久的除外)
     */
    public function setAllVipDay(int $day, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        foreach ($this->getVips() as $playerName) {
            if ($this->getPresiceVipDay($playerName) === -1)
                continue;
            $vips[$playerName]["day"] += $day;
        }
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 获取玩家的精确vip/svip天数
     * 前提：玩家是否存在/是否是vipsvip
     */
    public function getPresiceVipDay(string $playerName, bool $type = true): int
    {

        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        return $config->get($playerName)["day"];
    }

    /**
     * 获取玩家的vip/svip天数
     * 前提：玩家是否存在/是否是vip
     */
    public function getVipDay(string $playerName, bool $type = true): int|string
    {
        $day = $this->getPresiceVipDay($playerName, $type);
        return $day === -1 ? "永久" : $day;
    }

    /**
     * 判断玩家是否已签到，默认为判断vip签到
     * 前提：玩家是否存在/是否是vipsvip
     */
    public function isSigned(string $playerName, bool $type = true): bool
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        return $config->get($playerName)["sign"];
    }

    /**
     * 设置玩家vip/svip签到情况为$temp，默认为vip
     * 前提：玩家是否存在/是否是vipsvip
     */
    public function setSign(string $playerName, bool $temp, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        $vips[$playerName]["sign"] = $temp;
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 一键设置所有vip/svip的签到情况为$temp
     */
    public function setAllSign(bool $temp, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        foreach ($this->getVips($type) as $playerName)
            $vips[$playerName]["sign"] = $temp;
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 获取vip/svip签到奖励
     */
    public function getSignMoney(bool $type = true): float
    {
        $temp = $type === true ? "vip签到奖励游戏币" : "svip签到奖励游戏币";
        return $this->plugin->vipConfig->get($temp);
    }

    /**
     * 获取玩家vip/svip传送次数
     * 前提：玩家是否存在/是否vipsvip
     */
    public function getPlayerTransferNum(string $playerName, bool $type = true): int
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        return $config->get($playerName)["transfer"];
    }

    /**
     * 设置玩家vip/svip传送次数
     */
    public function setPlayerTransferNum(string $playerName, int $num, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        $vips[$playerName]["transfer"] = $num;
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 一键设置所有玩家的vip/svip传送次数为$num
     */
    public function setAllPlayerTransferNum(int $num, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        foreach ($this->getVips($type) as $playerName)
            $vips[$playerName]["transfer"] = $num;
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 获取默认vip/svip传送次数上限
     */
    public function getTransferNum(bool $type = true): int
    {
        $temp = $type === true ? "vip" : "svip";
        return $this->plugin->vipConfig->get($temp . "每日传送次数上限");
    }

    /**
     * 获取玩家权限
     */
    public function getRand(string $playerName): string
    {
        if ($this->isMaster($playerName))
            return "最高权限";
        elseif ($this->isOp($playerName))
            return "OP";
        elseif ($this->isVip($playerName, false))
            return "SVIP";
        elseif ($this->isVip($playerName))
            return "VIP";
        else
            return "玩家";
    }

    /**
     * 获取聊天颜色
     */
    public function getColor(string $playerName): string
    {
        if ($this->isVip($playerName, false))
            return $this->plugin->svips->get($playerName)["color"];
        elseif ($this->isVip($playerName))
            return $this->plugin->vips->get($playerName)["color"];
        else
            return "§b";
    }

    /**
     * 获取全部vip聊天颜色
     */
    public function getAllColor(bool $temp = true): array
    {
        if ($temp)
            return ["1", "2", "3", "4", "5", "6", "7", "8", "9"];
        else
            return ["1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f", "o", "m", "r"];
    }

    /**
     * 设置玩家的vip/svip聊天颜色
     */
    public function setColor(string $playerName, string $color, bool $type = true): void
    {
        $config = $type === true ? $this->plugin->vips : $this->plugin->svips;
        $vips = $config->getAll();
        $vips[$playerName]["color"] = "§" . $color;
        $config->setAll($vips);
        $config->save();
    }

    /**
     * 判断op是否有管理称号的权限
     */
    public function hasOpPrefix(): bool
    {
        return $this->plugin->prefixConfig->get("op是否可以管理称号");
    }

    /**
     * 反转op管理称号的权限
     */
    public function reverseOpPrefix(): void
    {
        $prefixConfig = $this->plugin->prefixConfig->get("op是否可以管理称号");
        $this->plugin->prefixConfig->set("op是否可以管理称号", !$prefixConfig);
        $this->plugin->prefixConfig->save();
    }

    /**
     * 添加玩家称号
     * 前提：玩家是否存在/称号是否重复/称号是否有空格
     */
    public function addPrefix(string $playerName, string $prefix): void
    {
        $prefixes = $this->plugin->prefixes->getAll();
        array_push($prefixes[$playerName], $prefix);
        $this->plugin->prefixes->setAll($prefixes);
        $this->plugin->prefixes->save();
    }

    /**
     * 删除玩家称号
     * 前提：玩家是否存在/称号是否存在
     */
    public function delPrefix(string $playerName, string $prefix): void
    {
        $prefixes = $this->plugin->prefixes->getAll();
        unset($prefixes[$playerName][$this->getIdByPrefix($playerName, $prefix)]);
        $this->plugin->prefixes->setAll($prefixes);
        $this->plugin->prefixes->save();
    }

    /**
     * 检测称号是否重复
     * 前提：玩家是否存在
     */
    public function isPrefixExist(string $playerName, string $prefix): bool
    {
        return in_array($prefix, $this->getPlayerAllPrefixes($playerName));
    }

    /**
     * 检测称号id是否存在
     */
    public function isPrefixIdExist(string $playerName, int $id): bool
    {
        return in_array($id, array_keys($this->getPlayerAllPrefixes($playerName)));
    }

    /**
     * 获取玩家的全部称号
     * 前提：玩家是否存在
     */
    public function getPlayerAllPrefixes(string $playerName): array
    {
        return $this->plugin->prefixes->get($playerName);
    }

    /**
     * 根据称号id获取称号
     * 前提：玩家是否存在
     */
    public function getPrefixById(string $playerName, int $id): string
    {
        return $this->plugin->prefixes->get($playerName)[$id];
    }

    /**
     * 通过称号获取id
     * 前提：玩家是否存在/称号是否存在
     */
    public function getIdByPrefix(string $playerName, string $prefix): string
    {
        return array_search($prefix, $this->plugin->prefixes->get($playerName));
    }

    /**
     * 获取玩家正在使用的称号
     * 前提：玩家是否存在
     */
    public function getCurPrefix(string $playerName): ?string
    {
        return $this->plugin->playerConfig->get($playerName)["正在使用的称号"];
    }

    /**
     * 通过id设置玩家正在使用的称号
     * 前提：玩家是否存在/id是否存在
     */
    public function setCurPrefix(string $playerName, int $id): void
    {
        $prefix = $this->getPrefixById($playerName, $id);
        $playerConfig = $this->plugin->playerConfig->getAll();
        $playerConfig[$playerName]["正在使用的称号"] = $prefix;
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 获取每页显示的op数量
     */
    public function getOpEachNum(): int
    {
        return $this->plugin->basicConfig->get("每页显示的op数量");
    }

    /**
     * 获取每页显示的vip数量
     */
    public function getVipEachNum(): int
    {
        return $this->plugin->vipConfig->get("每页显示的vip数量");
    }

    /**
     * 获取聊天格式
     */
    public function getChatFormat(): string
    {
        return $this->plugin->msgConfig->get("聊天格式");
    }

    /**
     * 通过游戏名判断玩家是否在线
     */
    public function isOnline(string $playerName): bool
    {
        return $this->plugin->getServer()->getPlayerExact($playerName) !== null;
    }

    /**
     * 获取全部在线玩家名
     */
    public function getAllOnlinePlayerName(): array
    {
        $onlinePlayers = array();
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer)
            $onlinePlayers[] = strtolower($onlinePlayer->getName());
        return $onlinePlayers;
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): Players
    {
        // 如果实例不存在，或者参数不同，则创建新实例
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin) {
            self::$instance = new self($plugin);
        }
        // 返回实例
        return self::$instance;
    }
}