<?php

namespace MengBao\MEBSociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBSociety\Tools\ArrayPage;
use MengBao\MEBSociety\Units\Shop;
use MengBao\MEBSociety\Units\Players;
use MengBao\MEBSociety\Units\CampsiteItem;
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
            case "camp":
                $this->camp($sender, $args);
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
        $sender->sendMessage("§e> /" . $c_name . " camp [list] --- 查看营地专属物品市场");
        $sender->sendMessage("§e> /" . $c_name . " camp sell <item_key> <num> --- 把营地专属物品挂卖到市场");
        $sender->sendMessage("§e> /" . $c_name . " camp buy <item_key> <num> --- 从市场购买营地专属物品");
        if (Players::getInstance($this->plugin)->isMaster($senderName) || $sender instanceof ConsoleCommandSender) {
            $sender->sendMessage("§e> /" . $c_name . " opshop --- 开关op管理商店的权限");
        }
        if ($this->hasManagePower($sender)) {
            $sender->sendMessage("§e> /" . $c_name . " additem <item_name> <num> <buy_price> <sell_price> --- 新增物品商品");
            $sender->sendMessage("§e> /" . $c_name . " addpre <prefix> <buy_price> --- 新增称号商品");
            $sender->sendMessage("§7   物品名/称号带空格时可以直接写，也可以用引号包起来，如\"white wool\"");
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
        //物品名可能带空格(如white wool)，按下标取参数会把空格后面那截当成<num>
        list($itemName, $numbers) = $this->splitNameAndNumbers(array_slice($args, 1), 3);
        $itemName = trim($itemName);
        if ($itemName === "" || count($numbers) < 2) {
            $sender->sendMessage($this->logo . "§c用法：/" . $this->getCommandName() . " additem <item_name> <num> <buy_price> <sell_price>");
            $sender->sendMessage($this->logo . "§c物品名带空格时可以用引号包起来，如\"white wool\"");
            return;
        }
        $num = $numbers[0];
        $buyPrice = $numbers[1];
        if (!is_numeric($num) || (int) $num <= 0) {
            $sender->sendMessage($this->logo . "§c<num>必须为正整数！");
            return;
        }
        if (!is_numeric($buyPrice) || $buyPrice < 0) {
            $sender->sendMessage($this->logo . "§c<buy_price>必须为非负数！");
            return;
        }
        //卖价不填就按0处理，等于这件商品只卖不收
        $sellPrice = isset($numbers[2]) ? $numbers[2] : 0;
        if (!is_numeric($sellPrice) || $sellPrice < 0) {
            $sender->sendMessage($this->logo . "§c<sell_price>必须为非负数！");
            return;
        }
        //名称留空，让它显示物品自己的名字，管理员想改再去改Shops.yml
        $SID = Shop::getInstance($this->plugin)->addItemShop("", $itemName, (int) $num, (float) $buyPrice, (float) $sellPrice);
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
        //称号同样可能带空格，不能按固定下标取价格
        list($prefix, $numbers) = $this->splitNameAndNumbers(array_slice($args, 1), 1);
        $prefix = trim($prefix);
        if ($prefix === "" || $numbers === array()) {
            $sender->sendMessage($this->logo . "§c用法：/" . $this->getCommandName() . " addpre <prefix> <buy_price>");
            $sender->sendMessage($this->logo . "§c称号带空格时可以用引号包起来，如\"§b萌新 玩家\"");
            return;
        }
        $buyPrice = $numbers[0];
        if (!is_numeric($buyPrice) || $buyPrice < 0) {
            $sender->sendMessage($this->logo . "§c<buy_price>必须为非负数！");
            return;
        }
        $SID = Shop::getInstance($this->plugin)->addPrefixShop("", $prefix, (float) $buyPrice);
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

    /**
     * 营地专属物品市场
     *
     * 和常规商品分开成一个子指令，因为两者的交易模型不一样：
     * 常规商品由服务器无限供货，专属物品的货全部来自其他玩家挂卖。
     */
    public function camp(CommandSender $sender, array $args): void
    {
        $shop = Shop::getInstance($this->plugin);
        $item = CampsiteItem::getInstance($this->plugin);
        $sub = $args[1] ?? "list";

        if ($sub === "list") {
            $sender->sendMessage("--------" . $this->logo . "营地专属物品市场--------");
            $sender->sendMessage("§7货源全部来自玩家挂卖，服务器不产出。存货为0时买不到。");
            foreach ($item->getAll() as $level => $define) {
                $key = $define["key"];
                $stock = $shop->getCampStock($key);
                $sender->sendMessage(
                    "§f" . $define["name"] . "§r §7(" . $key . ") §7解锁等级:" . $level
                        . " §a买:" . $shop->getCampBuyPrice($key)
                        . " §b卖:" . $shop->getCampSellPrice($key)
                        . " §e存货:" . ($stock > 0 ? "§f" . $stock : "§c0")
                );
            }
            return;
        }

        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!in_array($sub, array("buy", "sell"))) {
            $sender->sendMessage($this->logo . "§c用法：/" . $this->getCommandName() . " camp [list|buy|sell] <item_key> <num>");
            return;
        }
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入专属物品标识！可用 /" . $this->getCommandName() . " camp list 查看。");
            return;
        }
        $key = (string) $args[2];
        if ($item->getByKey($key) === null) {
            $sender->sendMessage($this->logo . "§c未知的专属物品标识：" . $key);
            return;
        }
        //数量不填按1算
        $num = 1;
        if (isset($args[3])) {
            if (!is_numeric($args[3]) || (int) $args[3] <= 0) {
                $sender->sendMessage($this->logo . "§c数量必须为正整数！");
                return;
            }
            $num = (int) $args[3];
        }

        $senderName = strtolower($sender->getName());
        if ($sub === "sell") {
            $price = $shop->getCampSellPrice($key) * $num;
            $result = $shop->campSell($senderName, $key, $num);
            if ($result !== Shop::OK) {
                $sender->sendMessage($this->logo . $shop->getResultMsg($result));
                return;
            }
            $sender->sendMessage($this->logo . "§a成功挂卖" . $item->getName($key) . "§a×" . $num . "，获得游戏币：" . $price);
            return;
        }

        $price = $shop->getCampBuyPrice($key) * $num;
        $result = $shop->campBuy($senderName, $key, $num);
        if ($result !== Shop::OK) {
            $sender->sendMessage($this->logo . $shop->getResultMsg($result));
            return;
        }
        $sender->sendMessage($this->logo . "§a成功购买" . $item->getName($key) . "§a×" . $num . "，花费游戏币：" . $price);
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
     * 把"名称 + 若干个数字"的参数拆开，名称允许带空格
     *
     * 核心的parseQuoteAware只会按空格拆参数(引号包起来的算一个)，
     * 所以像white wool这种带空格的物品名，按固定下标取参数一定会取错，
     * 会把wool当成<num>，触发"必须为正整数"。
     * 这里从末尾往前剥数字，最多剥$maxNumbers个，且至少给名称留一个参数，
     * 剩下的用空格拼回去当名称。引号写法也照样能用，那种情况名称本来就只有一个参数。
     *
     * @param list<string> $args 已经去掉子指令的参数
     * @return array{0: string, 1: list<string>} [名称, 顺序排列的数字参数]
     */
    private function splitNameAndNumbers(array $args, int $maxNumbers): array
    {
        $numbers = array();
        //留最后一个参数给名称，避免整串都被当成数字后名称为空
        for ($i = count($args) - 1; $i >= 1 && count($numbers) < $maxNumbers; $i--) {
            if (!is_numeric($args[$i]))
                break;
            $numbers[] = $args[$i];
        }
        $numbers = array_reverse($numbers);
        $name = implode(" ", array_slice($args, 0, count($args) - count($numbers)));
        return array($name, $numbers);
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