<?php

namespace MengBao\MEBSociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBSociety\Tools\ArrayPage;
use MengBao\MEBSociety\Units\Shop;
use MengBao\MEBSociety\Units\Players;
use MengBao\MEBSociety\MEBCommand\CommandHandler\CommandHandlerInterface;

class ShopCommandHandler implements CommandHandlerInterface
{
    public $logo = "[MEBS]";
    private $plugin;  //插件主类

    public function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    public function handle(CommandSender $sender, array $args): void
    {
        $c_name = $this->getCommandName();
        if (!isset($args[0])) {
            $sender->sendMessage($this->logo . "§c输入/" . $c_name . " help来获取帮助!");
            return;
        }
        switch ($args[0]) {
            case "help":
                $this->help($sender);
                break;
            case "list":
                $this->list($sender, $args);
                break;
            case "info":
                $this->info($sender, $args);
                break;
            case "buy":
                $this->buy($sender, $args);
                break;
            case "sell":
                $this->sell($sender, $args);
                break;
            case "additem":
                $this->additem($sender, $args);
                break;
            case "addpre":
                $this->addpre($sender, $args);
                break;
            case "del":
                $this->del($sender, $args);
                break;
            case "switch":
                $this->switch($sender, $args);
                break;
            case "icon":
                $this->icon($sender, $args);
                break;
            case "opshop":
                $this->opshop($sender);
                break;
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }

    public function help(CommandSender $sender): void
    {
        $c_name = $this->getCommandName();
        $senderName = strtolower($sender->getName());
        $sender->sendMessage("---------" . $this->logo . "商店指令帮助---------");
        $sender->sendMessage("§e> /" . $c_name . " list <page> --- 查看全部商品");
        $sender->sendMessage("§e> /" . $c_name . " info <shop_id> --- 查看商品详情");
        $sender->sendMessage("§e> /" . $c_name . " buy <shop_id> <times> --- 购买商品");
        $sender->sendMessage("§e> /" . $c_name . " sell <shop_id> <times> --- 出售商品");
        if (Players::getInstance($this->plugin)->isMaster($senderName) || $sender instanceof ConsoleCommandSender) {
            $sender->sendMessage("§e> /" . $c_name . " opshop --- 开关op管理商店的权限");
        }
        if ($this->hasManagePower($sender)) {
            $sender->sendMessage("§e> /" . $c_name . " additem <item_name> <num> <buy_price> <sell_price> --- 新增物品商品");
            $sender->sendMessage("§e> /" . $c_name . " addpre <prefix> <buy_price> --- 新增称号商品");
            $sender->sendMessage("§e> /" . $c_name . " del <shop_id> --- 删除商品");
            $sender->sendMessage("§e> /" . $c_name . " switch <shop_id> <buy/sell> --- 开关商品的购买/出售");
            $sender->sendMessage("§e> /" . $c_name . " icon <shop_id> <path/url/auto> <address> --- 设置商品图标");
        }
    }

    /**
     * 判断是否有管理商店的权限，master和控制台恒有
     */
    private function hasManagePower(CommandSender $sender): bool
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender || Players::getInstance($this->plugin)->isMaster($senderName))
            return true;
        return Players::getInstance($this->plugin)->isOp($senderName) && Shop::getInstance($this->plugin)->hasOpShop();
    }

    public function list(CommandSender $sender, array $args): void
    {
        $shops = Shop::getInstance($this->plugin)->getAllShops();
        if ($shops === array()) {
            $sender->sendMessage($this->logo . "§c商店里还没有任何商品！");
            return;
        }
        $page = isset($args[1]) && is_numeric($args[1]) ? (int) $args[1] : 1;
        $shopArray = new ArrayPage($shops, Shop::getInstance($this->plugin)->getShopEachNum());
        if (!$shopArray->isValidPage($page)) {
            $sender->sendMessage($this->logo . "§c页码不合理！(1~" . $shopArray->getTotalPages() . ")");
            return;
        }
        $sender->sendMessage($this->logo . "§a服务器的全部商品如下<" . $page . "/" . $shopArray->getTotalPages() . ">：");
        foreach ($shopArray->getContent($page) as $SID => $shop) {
            $type = $shop["类型"] === Shop::TYPE_PREFIX ? "称号" : "物品";
            $sender->sendMessage(
                "§e" . $SID . " §6=> §f" . Shop::getInstance($this->plugin)->getShopName($SID)
                    . "§r §7[" . $type . "] §a买:" . Shop::getInstance($this->plugin)->getTotalPrice((int) $SID)
                    . " §b卖:" . Shop::getInstance($this->plugin)->getTotalPrice((int) $SID, 1, false)
            );
        }
    }

    public function info(CommandSender $sender, array $args): void
    {
        $SID = $this->parseSID($sender, $args);
        if ($SID === null)
            return;
        $sender->sendMessage($this->logo . "§a商品详情如下：");
        foreach (explode("\n", Shop::getInstance($this->plugin)->getShopInfo($SID)) as $line)
            $sender->sendMessage($line);
    }

    public function buy(CommandSender $sender, array $args): void
    {
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        $SID = $this->parseSID($sender, $args);
        if ($SID === null)
            return;
        $times = $this->parseTimes($sender, $args);
        if ($times === null)
            return;
        $senderName = strtolower($sender->getName());
        $result = Shop::getInstance($this->plugin)->buy($senderName, $SID, $times);
        if ($result !== Shop::OK) {
            $sender->sendMessage($this->logo . Shop::getInstance($this->plugin)->getResultMsg($result));
            return;
        }
        $shop = Shop::getInstance($this->plugin)->getShop($SID);
        $price = Shop::getInstance($this->plugin)->getTotalPrice($SID, $times);
        if ($shop["类型"] === Shop::TYPE_PREFIX)
            $sender->sendMessage($this->logo . "§a成功购买称号：" . $shop["称号"] . "§a，花费游戏币：" . $price);
        else
            $sender->sendMessage(
                $this->logo . "§a成功购买" . Shop::getInstance($this->plugin)->getShopName($SID)
                    . "§a×" . (max(1, (int) $shop["数量"]) * $times) . "，花费游戏币：" . $price
            );
    }

    public function sell(CommandSender $sender, array $args): void
    {
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        $SID = $this->parseSID($sender, $args);
        if ($SID === null)
            return;
        $times = $this->parseTimes($sender, $args);
        if ($times === null)
            return;
        $senderName = strtolower($sender->getName());
        //卖价要在扣物品之前算，卖完再算也是一样的值，这里先取是为了提示语好写
        $price = Shop::getInstance($this->plugin)->getTotalPrice($SID, $times, false);
        $shop = Shop::getInstance($this->plugin)->getShop($SID);
        $result = Shop::getInstance($this->plugin)->sell($senderName, $SID, $times);
        if ($result !== Shop::OK) {
            $sender->sendMessage($this->logo . Shop::getInstance($this->plugin)->getResultMsg($result));
            return;
        }
        $sender->sendMessage(
            $this->logo . "§a成功出售" . Shop::getInstance($this->plugin)->getShopName($SID)
                . "§a×" . (max(1, (int) $shop["数量"]) * $times) . "，获得游戏币：" . $price
        );
    }

    public function additem(CommandSender $sender, array $args): void
    {
        if (!$this->hasManagePower($sender)) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        if (!isset($args[1]) || !isset($args[2]) || !isset($args[3])) {
            $sender->sendMessage($this->logo . "§c未输入<item_name>或<num>或<buy_price>，新增商品失败！");
            return;
        }
        $itemName = $args[1];
        if (!is_numeric($args[2]) || (int) $args[2] <= 0) {
            $sender->sendMessage($this->logo . "§c<num>必须为正整数！");
            return;
        }
        if (!is_numeric($args[3]) || $args[3] < 0) {
            $sender->sendMessage($this->logo . "§c<buy_price>必须为非负数！");
            return;
        }
        //卖价不填就按0处理，等于这件商品只卖不收
        $sellPrice = isset($args[4]) ? $args[4] : 0;
        if (!is_numeric($sellPrice) || $sellPrice < 0) {
            $sender->sendMessage($this->logo . "§c<sell_price>必须为非负数！");
            return;
        }
        //名称留空，让它显示物品自己的名字，管理员想改再去改Shops.yml
        $SID = Shop::getInstance($this->plugin)->addItemShop("", $itemName, (int) $args[2], (float) $args[3], (float) $sellPrice);
        //物品名解析不出来时只提醒，不拦着，方便管理员先建好商品再改配置
        if (Shop::getInstance($this->plugin)->getShopItem($SID) === null)
            $sender->sendMessage($this->logo . "§c警告：物品名" . $itemName . "无法识别，玩家将无法交易该商品，请修改Shops.yml！");
        $sender->sendMessage($this->logo . "§a成功新增商品，商品ID为：" . $SID);
    }

    public function addpre(CommandSender $sender, array $args): void
    {
        if (!$this->hasManagePower($sender)) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        if (!isset($args[1]) || !isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入<prefix>或<buy_price>，新增商品失败！");
            return;
        }
        $prefix = trim($args[1]);
        if ($prefix === "") {
            $sender->sendMessage($this->logo . "§c称号不能为空！");
            return;
        }
        if (!is_numeric($args[2]) || $args[2] < 0) {
            $sender->sendMessage($this->logo . "§c<buy_price>必须为非负数！");
            return;
        }
        $SID = Shop::getInstance($this->plugin)->addPrefixShop("", $prefix, (float) $args[2]);
        $sender->sendMessage($this->logo . "§a成功新增称号商品，商品ID为：" . $SID);
    }

    public function del(CommandSender $sender, array $args): void
    {
        if (!$this->hasManagePower($sender)) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        $SID = $this->parseSID($sender, $args);
        if ($SID === null)
            return;
        $name = Shop::getInstance($this->plugin)->getShopName($SID);
        Shop::getInstance($this->plugin)->delShop($SID);
        $sender->sendMessage($this->logo . "§a成功删除商品：" . $name);
    }

    public function switch(CommandSender $sender, array $args): void
    {
        if (!$this->hasManagePower($sender)) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        $SID = $this->parseSID($sender, $args);
        if ($SID === null)
            return;
        if (!isset($args[2]) || !in_array($args[2], array("buy", "sell"))) {
            $sender->sendMessage($this->logo . "§c请输入要开关的类型：buy或sell！");
            return;
        }
        $type = $args[2] === "buy";
        Shop::getInstance($this->plugin)->reverseShopSwitch($SID, $type);
        $shop = Shop::getInstance($this->plugin)->getShop($SID);
        $key = $type === true ? "可购买" : "可出售";
        $sender->sendMessage(
            $this->logo . "§a成功" . ($shop[$key] ? "开启" : "关闭") . "商品"
                . Shop::getInstance($this->plugin)->getShopName($SID) . "§a的" . ($type ? "购买" : "出售") . "！"
        );
    }

    public function icon(CommandSender $sender, array $args): void
    {
        if (!$this->hasManagePower($sender)) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        $SID = $this->parseSID($sender, $args);
        if ($SID === null)
            return;
        if (!isset($args[2]) || !in_array($args[2], array("path", "url", "auto"))) {
            $sender->sendMessage($this->logo . "§c图标类型必须为path(材质路径)、url(网络图片)或auto(自动推导)！");
            return;
        }
        //auto=清掉手动配置，交回给"自动推导商品图标"那套逻辑
        if ($args[2] === "auto") {
            Shop::getInstance($this->plugin)->setShopIcon($SID, -1, "");
            $guess = Shop::getInstance($this->plugin)->guessIconPath($SID);
            $sender->sendMessage($this->logo . "§a已恢复为自动推导，当前推导结果：" . ($guess === "" ? "§c无" : $guess));
            return;
        }
        if (!isset($args[3]) || trim($args[3]) === "") {
            $sender->sendMessage($this->logo . "§c未输入图标地址！");
            return;
        }
        $iconType = $args[2] === "path" ? 0 : 1;
        Shop::getInstance($this->plugin)->setShopIcon($SID, $iconType, trim($args[3]));
        $sender->sendMessage(
            $this->logo . "§a成功设置商品" . Shop::getInstance($this->plugin)->getShopName($SID)
                . "§a的图标为：" . trim($args[3])
        );
    }

    public function opshop(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if (!Players::getInstance($this->plugin)->isMaster($senderName) && !$sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个指令！");
            return;
        }
        $power = Shop::getInstance($this->plugin)->hasOpShop();
        $temp = $power === true ? "关闭" : "开启";
        Shop::getInstance($this->plugin)->reverseOpShop();
        $sender->sendMessage($this->logo . "§a成功" . $temp . "op管理商店的权限！");
    }

    /**
     * 取出并校验商品ID，不合法时提示并返回null
     */
    private function parseSID(CommandSender $sender, array $args): ?int
    {
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入商品ID！");
            return null;
        }
        if (!is_numeric($args[1])) {
            $sender->sendMessage($this->logo . "§c商品ID必须为整数！");
            return null;
        }
        $SID = (int) $args[1];
        if (!Shop::getInstance($this->plugin)->shopExist($SID)) {
            $sender->sendMessage($this->logo . "§c未知的商品ID：" . $SID);
            return null;
        }
        return $SID;
    }

    /**
     * 取出并校验份数，不填按1算，不合法时提示并返回null
     */
    private function parseTimes(CommandSender $sender, array $args): ?int
    {
        if (!isset($args[2]))
            return 1;
        if (!is_numeric($args[2]) || (int) $args[2] <= 0) {
            $sender->sendMessage($this->logo . "§c份数必须为正整数！");
            return null;
        }
        return (int) $args[2];
    }

    public function getCommandName(): string
    {
        return "mebshop";  //指令名
    }
}