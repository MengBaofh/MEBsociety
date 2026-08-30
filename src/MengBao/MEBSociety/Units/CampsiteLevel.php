<?php

namespace MengBao\MEBSociety\Units;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * 营地等级、捐献池与福利箱
 *
 * 这三件事放在一个类里，因为它们是同一条链路：
 * 成员往捐献池里捐钱 -> 市长用捐献池升级营地 -> 升级解锁新的专属物品
 * -> 市长把专属物品放进福利箱 -> 成员每周领一次 -> 成员用它升级自己的领地。
 *
 * 为什么升级费用要走捐献池而不是市长自己掏：
 * 营地领地远大于个人领地，费用也高一个量级，一个人掏不现实；
 * 而且捐献记录留着，谁出了多少大家都能看到，省得营地内部扯皮。
 */
class CampsiteLevel
{
    //操作结果，成功为正，失败为负
    public const OK = 1;
    public const ERR_NOT_FOUND = -1;  //营地不存在
    public const ERR_MAX_LEVEL = -2;  //已达最高等级
    public const ERR_POOL_NOT_ENOUGH = -3;  //捐献池不够
    public const ERR_NO_POWER = -4;  //没有权限
    public const ERR_NO_MONEY = -5;  //玩家游戏币不够
    public const ERR_AMOUNT = -6;  //数量不合法
    public const ERR_CLAIMED = -7;  //本周已领取
    public const ERR_WELFARE_EMPTY = -8;  //福利箱是空的
    public const ERR_INVENTORY_FULL = -9;  //背包放不下
    public const ERR_NOT_MEMBER = -10;  //不是营地成员
    public const ERR_ITEM_UNKNOWN = -11;  //专属物品标识不存在
    public const ERR_ITEM_LOCKED = -12;  //该专属物品还没解锁
    public const ERR_ITEM_NOT_ENOUGH = -13;  //背包里的专属物品不够
    public const OK_NO_POOL_MONEY = 2;  //物品发了，但捐献池不够，没发游戏币

    private static $instance;
    private $plugin;

    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    // ------------------------------------------------------------------
    // 等级
    // ------------------------------------------------------------------

    /**
     * 营地最大等级
     */
    public function getMaxLevel(): int
    {
        return max(1, (int) $this->plugin->campsiteConfig->get("营地最大等级"));
    }

    /**
     * 营地当前等级，营地不存在时返回0
     */
    public function getLevel(int $CID): int
    {
        $campsite = $this->plugin->campsites->get($CID);
        if (!is_array($campsite))
            return 0;
        //2.0.7及更早的营地没有这个键，按1级算
        return max(1, (int) ($campsite["level"] ?? 1));
    }

    /**
     * 设置营地等级
     * 前提：营地是否存在
     */
    public function setLevel(int $CID, int $level): void
    {
        $campsites = $this->plugin->campsites->getAll();
        $campsites[$CID]["level"] = max(1, min($this->getMaxLevel(), $level));
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
    }

    /**
     * 从$level升到下一级要花多少游戏币
     *
     * 等差递增：基数 * 当前等级。1->2花一份，2->3花两份，
     * 这样后期升级明显更贵，但不会像指数那样到8级就没人升得动。
     */
    public function getUpgradeCost(int $level): float
    {
        $base = (float) $this->plugin->campsiteConfig->get("营地升级费用基数");
        return $base * max(1, $level);
    }

    /**
     * 营地升到下一级还差多少钱，够了则返回0
     */
    public function getUpgradeLack(int $CID): float
    {
        $need = $this->getUpgradeCost($this->getLevel($CID));
        return max(0.0, $need - $this->getPoolMoney($CID));
    }

    /**
     * 用捐献池升级营地
     * 前提：调用方已确认操作者有权限
     */
    public function upgrade(int $CID): int
    {
        if (!is_array($this->plugin->campsites->get($CID)))
            return self::ERR_NOT_FOUND;
        $level = $this->getLevel($CID);
        if ($level >= $this->getMaxLevel())
            return self::ERR_MAX_LEVEL;
        $cost = $this->getUpgradeCost($level);
        if ($this->getPoolMoney($CID) < $cost)
            return self::ERR_POOL_NOT_ENOUGH;
        $this->addPoolMoney($CID, -$cost);
        $this->setLevel($CID, $level + 1);
        return self::OK;
    }

    /**
     * 营地已解锁的专属物品标识
     *
     * @return string[]
     */
    public function getUnlockedItemKeys(int $CID): array
    {
        $level = $this->getLevel($CID);
        $keys = array();
        foreach (CampsiteItem::getInstance($this->plugin)->getAll() as $needLevel => $define) {
            if ($needLevel <= $level)
                $keys[] = $define["key"];
        }
        return $keys;
    }

    /**
     * 营地是否已解锁某个专属物品
     */
    public function isItemUnlocked(int $CID, string $key): bool
    {
        $need = CampsiteItem::getInstance($this->plugin)->getLevelByKey($key);
        return $need !== -1 && $this->getLevel($CID) >= $need;
    }

    // ------------------------------------------------------------------
    // 捐献池
    // ------------------------------------------------------------------

    /**
     * 捐献池里的游戏币
     */
    public function getPoolMoney(int $CID): float
    {
        $campsite = $this->plugin->campsites->get($CID);
        if (!is_array($campsite))
            return 0.0;
        return (float) ($campsite["donation"]["money"] ?? 0);
    }

    /**
     * 增减捐献池游戏币，不会减到负数
     * 前提：营地是否存在
     */
    public function addPoolMoney(int $CID, float $money): void
    {
        $campsites = $this->plugin->campsites->getAll();
        $current = (float) ($campsites[$CID]["donation"]["money"] ?? 0);
        $campsites[$CID]["donation"]["money"] = max(0.0, $current + $money);
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
    }

    /**
     * 每个人的累计捐献额，键为玩家名
     *
     * @return array<string, float>
     */
    public function getPoolRecord(int $CID): array
    {
        $campsite = $this->plugin->campsites->get($CID);
        if (!is_array($campsite))
            return array();
        $record = $campsite["donation"]["record"] ?? array();
        return is_array($record) ? $record : array();
    }

    /**
     * 玩家捐钱进捐献池
     */
    public function donate(string $playerName, int $CID, float $money): int
    {
        if (!is_array($this->plugin->campsites->get($CID)))
            return self::ERR_NOT_FOUND;
        if ($money <= 0)
            return self::ERR_AMOUNT;
        if (Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName) !== $CID)
            return self::ERR_NOT_MEMBER;
        if (Economy::getInstance($this->plugin)->getMoney($playerName) < $money)
            return self::ERR_NO_MONEY;
        //先扣钱再入池，扣钱失败就不动池子
        if (Economy::getInstance($this->plugin)->addMoney($playerName, -$money) !== 1)
            return self::ERR_NO_MONEY;

        $campsites = $this->plugin->campsites->getAll();
        $current = (float) ($campsites[$CID]["donation"]["money"] ?? 0);
        $campsites[$CID]["donation"]["money"] = $current + $money;
        $record = $campsites[$CID]["donation"]["record"] ?? array();
        $record[$playerName] = (float) ($record[$playerName] ?? 0) + $money;
        $campsites[$CID]["donation"]["record"] = $record;
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        return self::OK;
    }

    // ------------------------------------------------------------------
    // 福利箱
    // ------------------------------------------------------------------

    /**
     * 福利箱内容，键为专属物品标识，值为每人每周可领的数量
     *
     * @return array<string, int>
     */
    public function getWelfare(int $CID): array
    {
        $campsite = $this->plugin->campsites->get($CID);
        if (!is_array($campsite))
            return array();
        $welfare = $campsite["welfare"]["items"] ?? array();
        return is_array($welfare) ? $welfare : array();
    }

    /**
     * 福利箱里附带的游戏币
     */
    public function getWelfareMoney(int $CID): float
    {
        $campsite = $this->plugin->campsites->get($CID);
        if (!is_array($campsite))
            return 0.0;
        return (float) ($campsite["welfare"]["money"] ?? 0);
    }

    /**
     * 设置福利箱里某种专属物品的数量，$num为0表示移出福利箱
     *
     * 只能放已解锁的专属物品：福利箱的意义就是把营地等级的收益分给成员，
     * 放没解锁的东西等于绕过了升级。
     * 前提：调用方已确认操作者是市长
     */
    public function setWelfareItem(int $CID, string $key, int $num): int
    {
        if (!is_array($this->plugin->campsites->get($CID)))
            return self::ERR_NOT_FOUND;
        if (CampsiteItem::getInstance($this->plugin)->getByKey($key) === null)
            return self::ERR_ITEM_UNKNOWN;
        if ($num < 0)
            return self::ERR_AMOUNT;
        if ($num > 0 && !$this->isItemUnlocked($CID, $key))
            return self::ERR_ITEM_LOCKED;
        $limit = max(1, (int) $this->plugin->campsiteConfig->get("福利箱每种物品数量上限"));
        if ($num > $limit)
            return self::ERR_AMOUNT;

        $campsites = $this->plugin->campsites->getAll();
        $items = $campsites[$CID]["welfare"]["items"] ?? array();
        if ($num === 0)
            unset($items[$key]);
        else
            $items[$key] = $num;
        $campsites[$CID]["welfare"]["items"] = $items;
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        return self::OK;
    }

    /**
     * 福利箱附带游戏币的上限，0表示不允许附带游戏币
     */
    public function getWelfareMoneyLimit(): float
    {
        return max(0.0, (float) $this->plugin->campsiteConfig->get("福利箱游戏币上限"));
    }

    /**
     * 设置福利箱附带的游戏币，超过上限则返回ERR_AMOUNT
     * 前提：调用方已确认操作者是市长
     */
    public function setWelfareMoney(int $CID, float $money): int
    {
        if (!is_array($this->plugin->campsites->get($CID)))
            return self::ERR_NOT_FOUND;
        if ($money < 0)
            return self::ERR_AMOUNT;
        if ($money > $this->getWelfareMoneyLimit())
            return self::ERR_AMOUNT;
        $campsites = $this->plugin->campsites->getAll();
        $campsites[$CID]["welfare"]["money"] = $money;
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        return self::OK;
    }

    /**
     * 本周的周标识，用来判断"这周领过没"
     *
     * 用ISO年+周号，周一切换。按自然周发放而不是"距上次领取满7天"，
     * 是为了让全营地的成员在同一天刷新，市长安排福利箱内容时好算。
     */
    public function getCurrentWeek(): string
    {
        return date("o-W");
    }

    /**
     * 玩家上次领取福利箱的周标识，没领过则返回null
     */
    public function getClaimedWeek(int $CID, string $playerName): ?string
    {
        $campsite = $this->plugin->campsites->get($CID);
        if (!is_array($campsite))
            return null;
        $claimed = $campsite["welfare"]["claimed"] ?? array();
        if (!is_array($claimed))
            return null;
        $week = $claimed[$playerName] ?? null;
        return is_string($week) ? $week : null;
    }

    /**
     * 玩家本周是否已领过福利箱
     */
    public function hasClaimed(int $CID, string $playerName): bool
    {
        return $this->getClaimedWeek($CID, $playerName) === $this->getCurrentWeek();
    }

    /**
     * 领取福利箱
     *
     * 领取记录按周标识存，不需要定时任务去清：
     * 到了下一周，存着的旧周标识自然就和当前周不相等了。
     *
     * 附带的游戏币从营地捐献池扣，不是凭空发放。池子不够时
     * 只发物品并返回OK_NO_POOL_MONEY，让调用方提示一声。
     */
    public function claimWelfare(string $playerName, int $CID): int
    {
        if (!is_array($this->plugin->campsites->get($CID)))
            return self::ERR_NOT_FOUND;
        if (Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName) !== $CID)
            return self::ERR_NOT_MEMBER;
        if ($this->hasClaimed($CID, $playerName))
            return self::ERR_CLAIMED;
        $items = $this->getWelfare($CID);
        //上限可能被服主调小过，存量数据里的旧值要按新上限截断
        $money = min($this->getWelfareMoney($CID), $this->getWelfareMoneyLimit());
        if ($items === array() && $money <= 0)
            return self::ERR_WELFARE_EMPTY;
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null)
            return self::ERR_NOT_MEMBER;

        //捐献池不够就只发物品，不足的部分不补
        $paidMoney = $money > 0 && $this->getPoolMoney($CID) >= $money ? $money : 0.0;

        //先把要发的物品都造出来，确认背包全放得下再真的发，
        //否则会出现领取记录写了、东西只发了一半
        $give = array();
        foreach ($items as $key => $num) {
            //期间营地可能降级(理论上不会)或物品表改过，解锁不了的就跳过
            if (!$this->isItemUnlocked($CID, (string) $key))
                continue;
            $item = CampsiteItem::getInstance($this->plugin)->create((string) $key, (int) $num);
            if ($item !== null)
                $give[] = $item;
        }
        if ($give === array() && $paidMoney <= 0)
            return $money > 0 ? self::ERR_POOL_NOT_ENOUGH : self::ERR_WELFARE_EMPTY;
        if (!$this->canAddAll($player, $give))
            return self::ERR_INVENTORY_FULL;

        foreach ($give as $item)
            $player->getInventory()->addItem($item);
        if ($paidMoney > 0) {
            $this->addPoolMoney($CID, -$paidMoney);
            Economy::getInstance($this->plugin)->addMoney($playerName, $paidMoney);
        }
        $this->markClaimed($CID, $playerName);
        return $money > 0 && $paidMoney <= 0 ? self::OK_NO_POOL_MONEY : self::OK;
    }

    /**
     * 判断背包能否一次性放下全部物品
     *
     * Inventory::canAddItem是一件一件判断的，多件物品分开问会各自
     * 以为"还有空位"，真发的时候后面几件就掉地上了。
     */
    private function canAddAll(Player $player, array $items): bool
    {
        if ($items === array())
            return true;
        //在克隆的背包上试放，不影响玩家真实背包
        $slots = array();
        for ($i = 0, $size = $player->getInventory()->getSize(); $i < $size; $i++)
            $slots[$i] = $player->getInventory()->getItem($i);

        foreach ($items as $item) {
            $left = $item->getCount();
            foreach ($slots as $index => $slotItem) {
                if ($left <= 0)
                    break;
                if ($slotItem->isNull()) {
                    $put = min($left, $item->getMaxStackSize());
                    $placed = clone $item;
                    $placed->setCount($put);
                    $slots[$index] = $placed;
                    $left -= $put;
                    continue;
                }
                if (!$slotItem->canStackWith($item))
                    continue;
                $room = $slotItem->getMaxStackSize() - $slotItem->getCount();
                if ($room <= 0)
                    continue;
                $put = min($left, $room);
                $slotItem->setCount($slotItem->getCount() + $put);
                $slots[$index] = $slotItem;
                $left -= $put;
            }
            if ($left > 0)
                return false;
        }
        return true;
    }

    /**
     * 记下玩家本周已领取
     */
    private function markClaimed(int $CID, string $playerName): void
    {
        $campsites = $this->plugin->campsites->getAll();
        $claimed = $campsites[$CID]["welfare"]["claimed"] ?? array();
        $claimed[$playerName] = $this->getCurrentWeek();
        $campsites[$CID]["welfare"]["claimed"] = $claimed;
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
    }

    /**
     * 把操作结果翻译成提示语
     */
    public function getResultMsg(int $result): string
    {
        switch ($result) {
            case self::ERR_NOT_FOUND:
                return "§c营地不存在！";
            case self::ERR_MAX_LEVEL:
                return "§c营地已达到最高等级！";
            case self::ERR_POOL_NOT_ENOUGH:
                return "§c营地捐献池的游戏币不足！";
            case self::ERR_NO_POWER:
                return "§c只有市长可以执行这个操作！";
            case self::ERR_NO_MONEY:
                return "§c你的游戏币不足！";
            case self::ERR_AMOUNT:
                return "§c数量不合法！";
            case self::ERR_CLAIMED:
                return "§c本周的福利箱你已经领过了！";
            case self::ERR_WELFARE_EMPTY:
                return "§c福利箱还是空的，等市长放点东西进去吧！";
            case self::ERR_INVENTORY_FULL:
                return "§c你的背包空间不足！";
            case self::ERR_NOT_MEMBER:
                return "§c你不是这个营地的成员，或者你不在线！";
            case self::ERR_ITEM_UNKNOWN:
                return "§c未知的专属物品标识！";
            case self::ERR_ITEM_LOCKED:
                return "§c营地等级不够，该专属物品还没解锁！";
            case self::ERR_ITEM_NOT_ENOUGH:
                return "§c你的专属物品数量不足！";
            default:
                return "§a操作成功！";
        }
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): CampsiteLevel
    {
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin) {
            self::$instance = new self($plugin);
        }
        return self::$instance;
    }
}
