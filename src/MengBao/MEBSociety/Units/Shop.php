<?php

namespace MengBao\MEBSociety\Units;

use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * GUI商店
 *
 * 商品统一存在Shops.yml里，用商品ID(SID)做键，靠"类型"区分是物品还是称号，
 * 不像旧版那样拆成sell/purchase/prefixshop三个配置文件，
 * 这样同一件商品的买价和卖价放在一起，不会出现两个文件对不上的情况。
 */
class Shop
{
    //操作结果，成功为正，失败为负，方便指令那边统一判断
    public const OK = 1;
    public const ERR_NOT_FOUND = -1;  //商品不存在
    public const ERR_TYPE = -2;  //商品类型不对
    public const ERR_DISABLED = -3;  //商品不可购买/不可出售
    public const ERR_ITEM_INVALID = -4;  //物品名解析不出来
    public const ERR_NO_MONEY = -5;  //游戏币不够
    public const ERR_INVENTORY_FULL = -6;  //背包放不下
    public const ERR_NOT_ENOUGH_ITEM = -7;  //背包里的物品不够
    public const ERR_PREFIX_OWNED = -8;  //已经拥有该称号
    public const ERR_OFFLINE = -9;  //玩家不在线
    public const ERR_PLAYER_NOT_FOUND = -10;  //玩家数据不存在
    public const ERR_ITEM_MODIFIED = -11;  //背包里只有被改过(带NBT)的同类物品

    public const TYPE_ITEM = "item";
    public const TYPE_PREFIX = "prefix";

    private static $instance;
    private $plugin;

    // 私有构造函数，防止外部直接实例化
    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 获取全部商品
     */
    public function getAllShops(): array
    {
        return $this->plugin->shops->getAll();
    }

    /**
     * 获取全部商品ID
     */
    public function getAllSID(): array
    {
        return array_keys($this->getAllShops());
    }

    /**
     * 判断商品是否存在
     */
    public function shopExist(int $SID): bool
    {
        return isset($this->getAllShops()[$SID]);
    }

    /**
     * 获取单个商品，商品不存在则返回null
     */
    public function getShop(int $SID): ?array
    {
        return $this->getAllShops()[$SID] ?? null;
    }

    /**
     * 按类型筛选商品，$onlyBuyable/$onlySellable用来过掉不可买/不可卖的
     */
    public function getShopsByType(string $type, bool $onlyBuyable = false, bool $onlySellable = false): array
    {
        $shops = array();
        foreach ($this->getAllShops() as $SID => $shop) {
            if ($shop["类型"] !== $type)
                continue;
            if ($onlyBuyable && !$shop["可购买"])
                continue;
            if ($onlySellable && !$shop["可出售"])
                continue;
            $shops[$SID] = $shop;
        }
        return $shops;
    }

    /**
     * 取一个没被占用的商品ID，从1开始
     */
    public function getNewSID(): int
    {
        $SIDs = $this->getAllSID();
        $SID = 1;
        while (in_array($SID, $SIDs))
            $SID++;
        return $SID;
    }

    /**
     * 新增物品商品，返回新商品的SID
     * 前提：物品名是否能解析
     */
    public function addItemShop(string $name, string $itemName, int $num, float $buyPrice, float $sellPrice): int
    {
        $SID = $this->getNewSID();
        $shops = $this->getAllShops();
        $shops[$SID] = array(
            "类型" => self::TYPE_ITEM,
            "名称" => $name,
            "物品" => $itemName,
            "数量" => $num,
            "称号" => null,
            "购买单价" => $buyPrice,
            "出售单价" => $sellPrice,
            "可购买" => true,
            "可出售" => $sellPrice > 0,
            "图标类型" => -1,
            "图标" => "",
        );
        $this->plugin->shops->setAll($shops);
        $this->plugin->shops->save();
        return $SID;
    }

    /**
     * 新增称号商品，返回新商品的SID
     */
    public function addPrefixShop(string $name, string $prefix, float $buyPrice): int
    {
        $SID = $this->getNewSID();
        $shops = $this->getAllShops();
        $shops[$SID] = array(
            "类型" => self::TYPE_PREFIX,
            "名称" => $name,
            "物品" => null,
            "数量" => 1,
            "称号" => $prefix,
            "购买单价" => $buyPrice,
            "出售单价" => 0,
            "可购买" => true,
            "可出售" => false,  //称号只卖不收
            "图标类型" => -1,
            "图标" => "",
        );
        $this->plugin->shops->setAll($shops);
        $this->plugin->shops->save();
        return $SID;
    }

    /**
     * 删除商品
     * 前提：商品是否存在
     */
    public function delShop(int $SID): void
    {
        $shops = $this->getAllShops();
        unset($shops[$SID]);
        $this->plugin->shops->setAll($shops);
        $this->plugin->shops->save();
    }

    /**
     * 反转商品的可购买/可出售状态
     * 前提：商品是否存在
     */
    public function reverseShopSwitch(int $SID, bool $type = true): void
    {
        $key = $type === true ? "可购买" : "可出售";
        $shops = $this->getAllShops();
        $shops[$SID][$key] = !$shops[$SID][$key];
        $this->plugin->shops->setAll($shops);
        $this->plugin->shops->save();
    }

    /**
     * 把商品的物品名解析成物品，解析不出来则返回null
     * 前提：商品是否存在且为物品商品
     */
    public function getShopItem(int $SID, int $times = 1): ?Item
    {
        $shop = $this->getShop($SID);
        if ($shop === null || $shop["类型"] !== self::TYPE_ITEM || $shop["物品"] === null)
            return null;
        //物品名走StringToItemParser，不用旧版"id:damage"那种写法，PM5下解析不了会抛异常
        $item = StringToItemParser::getInstance()->parse((string) $shop["物品"]);
        if ($item === null)
            return null;
        $item = clone $item;
        $item->setCount(max(1, (int) $shop["数量"]) * max(1, $times));
        return $item;
    }

    /**
     * 获取商品展示名，物品商品优先显示配置里的名称
     * 前提：商品是否存在
     */
    public function getShopName(int $SID): string
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return "";
        if ((string) $shop["名称"] !== "")
            return (string) $shop["名称"];
        if ($shop["类型"] === self::TYPE_PREFIX)
            return (string) $shop["称号"];
        $item = $this->getShopItem($SID);
        return $item === null ? (string) $shop["物品"] : $item->getName();
    }

    /**
     * 计算总价，$type为true算买价，false算卖价
     * 前提：商品是否存在
     */
    public function getTotalPrice(int $SID, int $times = 1, bool $type = true): float
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return 0;
        $price = $type === true ? (float) $shop["购买单价"] : (float) $shop["出售单价"];
        //称号只有一份，倍数对它没意义
        $num = $shop["类型"] === self::TYPE_PREFIX ? 1 : max(1, (int) $shop["数量"]);
        $times = $shop["类型"] === self::TYPE_PREFIX ? 1 : max(1, $times);
        return $price * $num * $times;
    }

    /**
     * 商品详情文本，给指令和GUI共用
     * 前提：商品是否存在
     */
    public function getShopInfo(int $SID): string
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return "";
        $info = "§e商品ID：§f" . $SID . "\n";
        $info .= "§e名称：§f" . $this->getShopName($SID) . "\n";
        if ($shop["类型"] === self::TYPE_PREFIX) {
            $info .= "§e类型：§f称号\n";
            $info .= "§e称号：§f" . $shop["称号"] . "§r\n";
            $info .= "§e价格：§f" . $this->getTotalPrice($SID) . "\n";
            $info .= "§e可购买：§f" . ($shop["可购买"] ? "§a是" : "§c否");
            return $info;
        }
        $item = $this->getShopItem($SID);
        $info .= "§e类型：§f物品\n";
        $info .= "§e物品：§f" . ($item === null ? "§c" . $shop["物品"] . "(无法识别)" : $item->getName()) . "\n";
        $info .= "§e每份数量：§f" . max(1, (int) $shop["数量"]) . "\n";
        $info .= "§e每份买价：§f" . $this->getTotalPrice($SID) . "\n";
        $info .= "§e每份卖价：§f" . $this->getTotalPrice($SID, 1, false) . "\n";
        $info .= "§e可购买：§f" . ($shop["可购买"] ? "§a是" : "§c否") . "§r\n";
        $info .= "§e可出售：§f" . ($shop["可出售"] ? "§a是" : "§c否");
        return $info;
    }

    /**
     * 购买商品，$times为购买份数
     */
    public function buy(string $playerName, int $SID, int $times = 1): int
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return self::ERR_NOT_FOUND;
        if (!$shop["可购买"])
            return self::ERR_DISABLED;
        if (!Players::getInstance($this->plugin)->playerExist($playerName))
            return self::ERR_PLAYER_NOT_FOUND;
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null)
            return self::ERR_OFFLINE;
        $times = max(1, $times);
        if ($shop["类型"] === self::TYPE_PREFIX) {
            if ($shop["称号"] === null)
                return self::ERR_TYPE;
            if (Players::getInstance($this->plugin)->isPrefixExist($playerName, (string) $shop["称号"]))
                return self::ERR_PREFIX_OWNED;
            $price = $this->getTotalPrice($SID);
            if (Economy::getInstance($this->plugin)->getMoney($playerName) < $price)
                return self::ERR_NO_MONEY;
            Economy::getInstance($this->plugin)->addMoney($playerName, -$price);
            Players::getInstance($this->plugin)->addPrefix($playerName, (string) $shop["称号"]);
            return self::OK;
        }
        $item = $this->getShopItem($SID, $times);
        if ($item === null)
            return self::ERR_ITEM_INVALID;
        $price = $this->getTotalPrice($SID, $times);
        if (Economy::getInstance($this->plugin)->getMoney($playerName) < $price)
            return self::ERR_NO_MONEY;
        //先确认背包放得下再扣钱，不然会出现钱没了东西也没拿到
        if (!$player->getInventory()->canAddItem($item))
            return self::ERR_INVENTORY_FULL;
        Economy::getInstance($this->plugin)->addMoney($playerName, -$price);
        $player->getInventory()->addItem($item);
        return self::OK;
    }

    /**
     * 统计背包里"没被改过"的同类物品个数
     *
     * Inventory::contains是按 $item->hasNamedTag() 决定要不要比对NBT的，
     * 商店物品是刚解析出来的、没有NBT，于是它那边不比NBT，
     * 附魔过/改过名的物品会被当成素物品收走(比如插件自己发的导航书)。
     * 所以这里自己扫一遍，要求槽位里的物品NBT也是空的。
     */
    public function countSellableItem(Player $player, Item $item): int
    {
        $count = 0;
        $inventory = $player->getInventory();
        for ($i = 0, $size = $inventory->getSize(); $i < $size; $i++) {
            $slotItem = $inventory->getItem($i);
            if ($slotItem->isNull())
                continue;
            //第三个参数为true，强制连NBT一起比，带NBT的物品不会被算进来
            if ($slotItem->equals($item, true, true))
                $count += $slotItem->getCount();
        }
        return $count;
    }

    /**
     * 统计背包里的同类物品个数，不管有没有被改过
     */
    public function countMatchedItem(Player $player, Item $item): int
    {
        $count = 0;
        $inventory = $player->getInventory();
        for ($i = 0, $size = $inventory->getSize(); $i < $size; $i++) {
            $slotItem = $inventory->getItem($i);
            if ($slotItem->isNull())
                continue;
            if ($slotItem->equals($item, true, false))  //不比NBT
                $count += $slotItem->getCount();
        }
        return $count;
    }

    /**
     * 从背包里扣掉指定个数的"没被改过"的同类物品
     * 前提：countSellableItem已经确认数量足够
     */
    private function removeSellableItem(Player $player, Item $item, int $count): void
    {
        $inventory = $player->getInventory();
        for ($i = 0, $size = $inventory->getSize(); $i < $size && $count > 0; $i++) {
            $slotItem = $inventory->getItem($i);
            if ($slotItem->isNull())
                continue;
            if (!$slotItem->equals($item, true, true))
                continue;
            $amount = min($count, $slotItem->getCount());
            //getItem给的是克隆，改完必须写回去
            $slotItem->setCount($slotItem->getCount() - $amount);
            $inventory->setItem($i, $slotItem);  //count减到0时核心会自动置为空气
            $count -= $amount;
        }
    }

    /**
     * 出售商品，$times为出售份数
     */
    public function sell(string $playerName, int $SID, int $times = 1): int
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return self::ERR_NOT_FOUND;
        if ($shop["类型"] !== self::TYPE_ITEM)
            return self::ERR_TYPE;  //称号收不回来
        if (!$shop["可出售"])
            return self::ERR_DISABLED;
        if (!Players::getInstance($this->plugin)->playerExist($playerName))
            return self::ERR_PLAYER_NOT_FOUND;
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player === null)
            return self::ERR_OFFLINE;
        $times = max(1, $times);
        $item = $this->getShopItem($SID, $times);
        if ($item === null)
            return self::ERR_ITEM_INVALID;
        $need = $item->getCount();
        if ($this->countSellableItem($player, $item) < $need) {
            //同类物品数量够、但干净的不够，说明玩家手里的是改过的，单独提示一下，
            //不然玩家看着满背包钻石却被告知"数量不足"，会以为是插件坏了
            if ($this->countMatchedItem($player, $item) >= $need)
                return self::ERR_ITEM_MODIFIED;
            return self::ERR_NOT_ENOUGH_ITEM;
        }
        $this->removeSellableItem($player, $item, $need);
        Economy::getInstance($this->plugin)->addMoney($playerName, $this->getTotalPrice($SID, $times, false));
        return self::OK;
    }

    /**
     * 把操作结果翻译成提示语
     */
    public function getResultMsg(int $result): string
    {
        switch ($result) {
            case self::ERR_NOT_FOUND:
                return "§c商品不存在！";
            case self::ERR_TYPE:
                return "§c该商品不支持此操作！";
            case self::ERR_DISABLED:
                return "§c该商品暂未开放此操作！";
            case self::ERR_ITEM_INVALID:
                return "§c商品配置里的物品名无法识别，请联系管理员！";
            case self::ERR_NO_MONEY:
                return "§c你的游戏币不足！";
            case self::ERR_INVENTORY_FULL:
                return "§c你的背包空间不足！";
            case self::ERR_NOT_ENOUGH_ITEM:
                return "§c你的物品数量不足！";
            case self::ERR_ITEM_MODIFIED:
                return "§c该物品被修改过，无法出售！";
            case self::ERR_PREFIX_OWNED:
                return "§c你已经拥有该称号了！";
            case self::ERR_OFFLINE:
                return "§c玩家不在线！";
            case self::ERR_PLAYER_NOT_FOUND:
                return "§c找不到你的玩家数据！";
            default:
                return "§a操作成功！";
        }
    }

    /**
     * 判断op是否有管理商店的权限
     */
    public function hasOpShop(): bool
    {
        return $this->plugin->shopConfig->get("op是否可以管理商店");
    }

    /**
     * 反转op管理商店的权限
     */
    public function reverseOpShop(): void
    {
        $power = $this->plugin->shopConfig->get("op是否可以管理商店");
        $this->plugin->shopConfig->set("op是否可以管理商店", !$power);
        $this->plugin->shopConfig->save();
    }

    /**
     * 获取每页显示的商品数量
     */
    public function getShopEachNum(): int
    {
        return $this->plugin->shopConfig->get("每页显示的商品数量");
    }

    /**
     * 是否开启图标自动推导
     */
    public function hasAutoIcon(): bool
    {
        return $this->plugin->shopConfig->get("自动推导商品图标");
    }

    /**
     * 设置商品图标
     * 前提：商品是否存在
     */
    public function setShopIcon(int $SID, int $iconType, string $iconPath): void
    {
        $shops = $this->getAllShops();
        $shops[$SID]["图标类型"] = $iconType;
        $shops[$SID]["图标"] = $iconPath;
        $this->plugin->shops->setAll($shops);
        $this->plugin->shops->save();
    }

    /**
     * 推导物品的材质路径
     *
     * 核心里没有"物品->材质路径"的对照表，只能按物品名拼。
     * 原版大部分方块和物品的材质名和物品名一致，但确实有对不上的
     * (材质名带后缀、或者一个物品对应多张图)，拼错的话客户端那个按钮就没有图标，
     * 不影响点击。要精确控制就用 /mebshop icon 手动指定。
     */
    public function guessIconPath(int $SID): string
    {
        $item = $this->getShopItem($SID);
        if ($item === null)
            return "";
        $shop = $this->getShop($SID);
        //按物品名拼，规则和StringToItemParser的归一化保持一致
        $name = strtolower(str_replace([" ", "minecraft:"], ["_", ""], trim((string) $shop["物品"])));
        if ($name === "")
            return "";
        //方块和物品的材质放在两个目录下
        return ($item instanceof ItemBlock ? "textures/blocks/" : "textures/items/") . $name;
    }

    /**
     * 获取商品最终使用的图标类型，-1表示不显示图标
     * 前提：商品是否存在
     */
    public function getIconType(int $SID): int
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return -1;
        //管理员显式配过就用配置里的
        if ((int) $shop["图标类型"] !== -1 && (string) $shop["图标"] !== "")
            return (int) $shop["图标类型"];
        if (!$this->hasAutoIcon() || $shop["类型"] !== self::TYPE_ITEM)
            return -1;  //称号没有对应的材质，不推导
        return $this->guessIconPath($SID) === "" ? -1 : 0;  //0=材质路径
    }

    /**
     * 获取商品最终使用的图标地址
     * 前提：商品是否存在
     */
    public function getIconPath(int $SID): string
    {
        $shop = $this->getShop($SID);
        if ($shop === null)
            return "";
        if ((int) $shop["图标类型"] !== -1 && (string) $shop["图标"] !== "")
            return (string) $shop["图标"];
        if (!$this->hasAutoIcon() || $shop["类型"] !== self::TYPE_ITEM)
            return "";
        return $this->guessIconPath($SID);
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): Shop
    {
        // 如果实例不存在，或者参数不同，则创建新实例
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin) {
            self::$instance = new self($plugin);
        }
        // 返回实例
        return self::$instance;
    }
}