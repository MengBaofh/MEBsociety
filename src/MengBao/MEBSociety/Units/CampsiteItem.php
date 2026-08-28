<?php

namespace MengBao\MEBSociety\Units;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * 营地专属物品
 *
 * 营地升到某一级就解锁一种专属物品，这些物品是领地升级的必需材料。
 * 整张表写死在代码里，不放配置文件：
 * 领地升级要吃哪种专属物品是跨插件的约定，服主改一下这边，
 * MEBTerritory那边的配方就对不上了，出问题很难查。
 * 想调节难度请改营地升级费用和福利箱内容，那两个是可配置的。
 *
 * 物品本身是普通的原版物品，只是加了自定义名和一行lore做标记，
 * 所以它能正常丢地上、放箱子、和别人交易，
 * 这也是README里"该物品可交易"的实现方式。
 */
class CampsiteItem
{
    /** lore标记的前缀，用来认出这是哪种专属物品 */
    public const TAG_PREFIX = "§8§o[MEBS:";
    public const TAG_SUFFIX = "]";

    /**
     * 专属物品总表，键为解锁所需的营地等级
     *
     * key    => 物品标识，跨插件用的就是这个字符串
     * base   => 底材物品名，走StringToItemParser
     * name   => 展示名
     * price  => 商店基准价，玩家之间的买卖价都从它算
     */
    private const ITEMS = array(
        1 => array("key" => "camp_stone", "base" => "cobblestone", "name" => "§7营地基石", "price" => 800),
        2 => array("key" => "camp_timber", "base" => "oak_log", "name" => "§6营地梁木", "price" => 1600),
        3 => array("key" => "camp_iron", "base" => "iron_ingot", "name" => "§f营地铸铁", "price" => 3200),
        4 => array("key" => "camp_glass", "base" => "glass", "name" => "§b营地琉璃", "price" => 6000),
        5 => array("key" => "camp_gold", "base" => "gold_ingot", "name" => "§e营地鎏金", "price" => 11000),
        6 => array("key" => "camp_redstone", "base" => "redstone_dust", "name" => "§c营地红石核", "price" => 20000),
        7 => array("key" => "camp_lapis", "base" => "lapis_lazuli", "name" => "§9营地青金", "price" => 36000),
        8 => array("key" => "camp_diamond", "base" => "diamond", "name" => "§3营地钻石", "price" => 65000),
        9 => array("key" => "camp_emerald", "base" => "emerald", "name" => "§a营地翠玉", "price" => 120000),
        10 => array("key" => "camp_crown", "base" => "nether_star", "name" => "§d营地冠冕", "price" => 220000),
    );

    private static $instance;
    private $plugin;

    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 获取全表，键为解锁等级
     */
    public function getAll(): array
    {
        return self::ITEMS;
    }

    /**
     * 获取全部物品标识
     */
    public function getAllKeys(): array
    {
        $keys = array();
        foreach (self::ITEMS as $level => $define)
            $keys[] = $define["key"];
        return $keys;
    }

    /**
     * 某个等级解锁的专属物品定义，没有则返回null
     */
    public function getByLevel(int $level): ?array
    {
        return self::ITEMS[$level] ?? null;
    }

    /**
     * 按物品标识取定义，找不到则返回null
     */
    public function getByKey(string $key): ?array
    {
        foreach (self::ITEMS as $level => $define) {
            if ($define["key"] === $key)
                return $define + array("level" => $level);
        }
        return null;
    }

    /**
     * 该标识需要的营地等级，找不到则返回-1
     */
    public function getLevelByKey(string $key): int
    {
        $define = $this->getByKey($key);
        return $define === null ? -1 : $define["level"];
    }

    /**
     * 展示名
     */
    public function getName(string $key): string
    {
        $define = $this->getByKey($key);
        return $define === null ? $key : $define["name"];
    }

    /**
     * 商店基准价
     */
    public function getPrice(string $key): float
    {
        $define = $this->getByKey($key);
        return $define === null ? 0.0 : (float) $define["price"];
    }

    /**
     * 生成一个专属物品实例，标识非法或底材解析不出来时返回null
     */
    public function create(string $key, int $count = 1): ?Item
    {
        $define = $this->getByKey($key);
        if ($define === null)
            return null;
        $item = StringToItemParser::getInstance()->parse((string) $define["base"]);
        if ($item === null)
            return null;
        $item = clone $item;
        $item->setCount(max(1, $count));
        $item->setCustomName($define["name"]);
        //lore第一行是给玩家看的，第二行才是机器读的标记
        $item->setLore(array(
            "§7营地专属物品 §8| §7解锁等级 §f" . $define["level"],
            self::TAG_PREFIX . $key . self::TAG_SUFFIX,
        ));
        return $item;
    }

    /**
     * 认出一个物品是哪种专属物品，不是专属物品则返回null
     *
     * 只看lore里的标记，不看自定义名：
     * 玩家可以用铁砧改名，但改不了lore，标记留着就还认得。
     */
    public function identify(Item $item): ?string
    {
        if ($item->isNull())
            return null;
        foreach ($item->getLore() as $line) {
            if (!str_starts_with($line, self::TAG_PREFIX) || !str_ends_with($line, self::TAG_SUFFIX))
                continue;
            $key = substr($line, strlen(self::TAG_PREFIX), -strlen(self::TAG_SUFFIX));
            if ($this->getByKey($key) !== null)
                return $key;
        }
        return null;
    }

    /**
     * 判断物品是否为指定的专属物品
     */
    public function isItem(Item $item, string $key): bool
    {
        return $this->identify($item) === $key;
    }

    /**
     * 统计玩家背包里某种专属物品的总数
     */
    public function countInInventory(Player $player, string $key): int
    {
        $count = 0;
        $inventory = $player->getInventory();
        for ($i = 0, $size = $inventory->getSize(); $i < $size; $i++) {
            $slotItem = $inventory->getItem($i);
            if ($this->isItem($slotItem, $key))
                $count += $slotItem->getCount();
        }
        return $count;
    }

    /**
     * 从玩家背包里扣掉指定数量的专属物品，返回实际扣掉的数量
     * 前提：countInInventory已确认数量足够
     */
    public function removeFromInventory(Player $player, string $key, int $count): int
    {
        $removed = 0;
        $inventory = $player->getInventory();
        for ($i = 0, $size = $inventory->getSize(); $i < $size && $count > 0; $i++) {
            $slotItem = $inventory->getItem($i);
            if (!$this->isItem($slotItem, $key))
                continue;
            $amount = min($count, $slotItem->getCount());
            //getItem给的是克隆，改完必须写回去
            $slotItem->setCount($slotItem->getCount() - $amount);
            $inventory->setItem($i, $slotItem);  //数量减到0时核心会自动置为空气
            $count -= $amount;
            $removed += $amount;
        }
        return $removed;
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): CampsiteItem
    {
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin) {
            self::$instance = new self($plugin);
        }
        return self::$instance;
    }
}
