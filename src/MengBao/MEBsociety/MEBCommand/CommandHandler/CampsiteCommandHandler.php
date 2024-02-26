<?php

namespace MengBao\MEBsociety\MEBCommand\CommandHandler;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;

use MengBao\MEBsociety\Tools\ArrayPage;
use MengBao\MEBsociety\CallbackTask;
use MengBao\MEBsociety\Units\Economy;
use MengBao\MEBsociety\Units\Campsite;
use MengBao\MEBsociety\Units\MultiWorld;
use MengBao\MEBsociety\MEBCommand\CommandHandler\CommandHandlerInterface;

//营地命令处理器
class CampsiteCommandHandler implements CommandHandlerInterface
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
            $sender->sendMessage($this->logo . "输入/" . $c_name . " help来获取帮助!");
            return;
        }
        switch ($args[0]) {
            case "help":
                $this->help($sender);
                break;
            case "create":  //除了控制台都可以输入
                $this->create($sender, $args);
                break;
            case "sethome":
                $this->setHome($sender);
                break;
            case "gohome":
                $this->goHome($sender);
                break;
            case "transfer":
                $this->transfer($sender, $args);
                break;
            case "call":
                $this->call($sender);
                break;
            case "disband":
                $this->disband($sender);
                break;
            case "join":
                $this->join($sender, $args);
                break;
            case "quit":
                $this->quit($sender);
                break;
            case "accept":
                $this->accept($sender, $args);
                break;
            case "disagree":
                $this->disagree($sender, $args);
                break;
            case "list":
                $this->list($sender, $args);
                break;
            case "post":
                $this->post($sender, $args);
                break;
            case "power":
                $this->power($sender, $args);
                break;
            case "search":
                $this->search($sender, $args);
                break;
            case "out":
                $this->out($sender, $args);
                break;
            default:
                $sender->sendMessage($this->logo . "§c未知指令，输入/" . $c_name . " help来获取帮助!");
        }
    }

    public function help(CommandSender $sender): void
    {
        $c_name = $this->getCommandName();
        $sender->sendMessage("---------" . $this->logo . "营地指令帮助---------");
        $sender->sendMessage("§e> /" . $c_name . " create <campsite_name> --- 创建营地");
        $sender->sendMessage("§e> /" . $c_name . " sethome --- 设置营地入口传送点");
        $sender->sendMessage("§e> /" . $c_name . " gohome --- 传送到营地入口");
        $sender->sendMessage("§e> /" . $c_name . " transfer <player_name> --- 将营地转让给某人");
        $sender->sendMessage("§e> /" . $c_name . " call <all/yes/no> --- 召集营地所有成员/响应召集/拒绝召集");
        $sender->sendMessage("§e> /" . $c_name . " disband --- 解散营地");
        $sender->sendMessage("§e> /" . $c_name . " join <campsite_id> --- 申请加入营地");
        $sender->sendMessage("§e> /" . $c_name . " quit --- 退出营地");
        $sender->sendMessage("§e> /" . $c_name . " accept <player_name/all> --- 同意<某人/所有人>的入营申请");
        $sender->sendMessage("§e> /" . $c_name . " disagree <player_name/all> --- 拒绝<某人/所有人>的入营申请");
        $sender->sendMessage("§e> /" . $c_name . " list [page] --- 查看入营申请列表");
        $sender->sendMessage("§e> /" . $c_name . " post <player_name> <post_name>  --- 修改玩家的营地职称");
        $sender->sendMessage("§e> /" . $c_name . " power <add/remove> <player_name> <power_id> --- 给予/移除玩家的营地权力");
        $sender->sendMessage("§e> /" . $c_name . " search <campsite_id> --- 查询某营地的信息，不填则查询自己所在营地信息");
        $sender->sendMessage("§e> /" . $c_name . " out <player_name> --- 将某人请出营地");
    }

    public function create(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        $moneyCreateCampsite = $this->plugin->campsiteConfig->get("创建营地的费用");
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (Campsite::getInstance($this->plugin)->totalNum() >= $this->plugin->campsiteConfig->get("服务器最大营地个数")) {
            $sender->sendMessage($this->logo . "§c服务器的营地数量已达上限，无法创建营地！");
            return;
        }
        if (Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你已经加入了一个营地！");
            return;
        }
        if (Economy::getInstance($this->plugin)->getMoney($senderName) < $moneyCreateCampsite) {
            $sender->sendMessage($this->logo . "§c你没有足够的费用来创建营地，总共需要" . $moneyCreateCampsite);
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入营地名，创建失败！");
            return;
        }
        $args[1] = trim($args[1]);  //去除营地名前后的空格
        if (strpos($args[1], '§') !== false) {
            $sender->sendMessage($this->logo . "§c营地名禁止包含符号：§");
            return;
        }
        if (Campsite::getInstance($this->plugin)->campsiteExistByName($args[1])) {
            $sender->sendMessage($this->logo . "§c该营地已存在，请更换营地名。");
            return;
        }
        Campsite::getInstance($this->plugin)->createCampsite($args[1], $senderName);
        Economy::getInstance($this->plugin)->addMoney($senderName, -$moneyCreateCampsite);  //扣钱
        $sender->sendMessage($this->logo . "§a成功创建" . $args[1] . "营地，花费" . $moneyCreateCampsite . "！");
        $this->plugin->getServer()->broadcastMessage($this->logo . "§b玩家" . $senderName . "§a创建了" . $args[1] . "§a营地，§e输入 /campsite join " . Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName) . " §e即可申请加入！");
    }

    public function setHome(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"] && !$ownedPower["设置营地传送点"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        $worldName = $sender->getWorld()->getFolderName();
        $x = (int) $sender->getPosition()->getX();
        $y = (int) $sender->getPosition()->getY();
        $z = (int) $sender->getPosition()->getZ();
        Campsite::getInstance($this->plugin)->setHome($senderName, $worldName, $x, $y, $z);
        $sender->sendMessage($this->logo . "§a成功设置当前坐标(x:" . $x . " y:" . $y . " z:" . $z . " world:" . $worldName . ")为营地传送点！");
    }

    public function goHome(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        $home = Campsite::getInstance($this->plugin)->getHome($CID);
        $worldName = $home["world"];
        $x = $home["x"];
        $y = $home["y"];
        $z = $home["z"];
        if ($worldName === null || $x === null || $y === null || $z === null) {
            $sender->sendMessage("§c营地未设置传送点！");
            return;
        }
        $result = MultiWorld::getInstance($this->plugin)->transportPlayer($sender, $worldName, $x, $y, $z);
        if ($result === 1)
            $sender->sendMessage($this->logo . "§e成功传送！");
        else
            $sender->sendMessage($this->logo . "§c世界未加载，传送失败！");
    }

    public function transfer(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入转让对象，转让失败！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if ($args[1] === $senderName) {
            $sender->sendMessage($this->logo . "§c你不能选择你自己！");
            return;
        }
        if (!array_key_exists($args[1], $this->plugin->playerConfig->getAll())) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isSameCampsite($senderName, $args[1])) {
            $sender->sendMessage($this->logo . "§c对方与你不在同一个营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (isset($this->plugin->waitingConfirmation[$senderName])) {
            $sender->sendMessage($this->logo . "§c你有一个请求未处理，无法执行当前请求！");
            return;
        }
        $sender->sendMessage($this->logo . "§c营地将会转让给" . $args[1] . "，请再次确认！(yes/no)");
        $this->plugin->waitingConfirmation[$senderName] = function ($confirmed) use ($sender, $senderName, $args) {
            if ($confirmed) {
                // 确认后执行的操作
                $sender->sendMessage($this->logo . "§a操作已确认，成功将营地转让给" . $args[1] . "。");
                //修改营长\两个玩家的营地职位及权限
                $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
                Campsite::getInstance($this->plugin)->changeOwner($CID, $senderName, $args[1]);
            } else
                $sender->sendMessage($this->logo . "§a操作已取消。");
            unset($this->plugin->waitingConfirmation[$senderName]);
        };
    }

    public function call(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"] && !$ownedPower["召集营地成员"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        $callNum = Campsite::getInstance($this->plugin)->getCallNum($CID);
        if ($callNum <= 0) {
            $sender->sendMessage($this->logo . "§c营地每日召集已达上限！");
            return;
        }
        Campsite::getInstance($this->plugin)->setCallNum($CID, $callNum - 1);  //使用一次召集次数
        $sender->sendMessage($this->logo . "§a成功召集所有成员，请等待成员响应！");
        $member = Campsite::getInstance($this->plugin)->getAllMember($CID);
        $limitTime = $this->plugin->campsiteConfig->getAll()["营地召集有效时间(s)"];
        foreach ($member as $k => $name) {
            if ($name === $senderName)
                continue;
            $player = $this->plugin->getServer()->getPlayerExact($name);  //获取在线玩家实例
            $playerName = strtolower($player->getName());
            if ($player === null || isset($this->plugin->waitingConfirmation[$playerName]))  //玩家不在线或玩家有未处理的请求
                continue;
            $player->sendMessage($this->logo . "§a营地的'" . Campsite::getInstance($this->plugin)->getCPost($senderName) . "'正在召集所有成员，请在" . $limitTime . "s内作出回应！(yes/no)");
            $this->plugin->waitingConfirmation[$name] = function ($confirmed) use ($player, $sender, $senderName) {
                if ($confirmed) {
                    // 确认后执行的操作
                    $player->sendMessage($this->logo . "§a操作已确认，正在传送。");
                    //传送至指定地点
                    $worldName = $sender->getWorld()->getFolderName();
                    $x = (int) $sender->getPosition()->getX();
                    $y = (int) $sender->getPosition()->getY();
                    $z = (int) $sender->getPosition()->getZ();
                    $result = MultiWorld::getInstance($this->plugin)->transportPlayer($player, $worldName, $x, $y, $z);
                    if ($result === 1)
                        $player->sendMessage($this->logo . "§e成功传送！");
                    else
                        $player->sendMessage($this->logo . "§c世界未加载，传送失败！");
                } else
                    $player->sendMessage($this->logo . "§a操作已取消。");
                unset($this->plugin->waitingConfirmation[$senderName]);
            };
            // 创建一个定时器，在limitTime秒后自动执行回调函数
            $this->plugin->getScheduler()->scheduleDelayedTask(new CallbackTask(function () use ($player, $playerName): void {
                if (isset($this->plugin->waitingConfirmation[$playerName])) {
                    $player->sendMessage($this->logo . "§c响应超时。");
                    $callback = $this->plugin->waitingConfirmation[$playerName];
                    $callback(false);
                    unset($this->plugin->waitingConfirmation[$playerName]);
                }
            }), 20 * $limitTime);
        }
    }

    public function disband(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (isset($this->plugin->waitingConfirmation[$senderName])) {
            $sender->sendMessage($this->logo . "§c你有一个请求未处理，无法执行当前请求！");
            return;
        }
        $sender->sendMessage($this->logo . "§c营地将会被解散，请再次确认！(yes/no)");
        $this->plugin->waitingConfirmation[$senderName] = function ($confirmed) use ($sender, $senderName) {
            if ($confirmed) {
                // 确认后执行的操作
                $sender->sendMessage($this->logo . "§a操作已确认，成功解散营地。");
                Campsite::getInstance($this->plugin)->deleteCampsite(Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName));
            } else
                $sender->sendMessage($this->logo . "§a操作已取消。");
            unset($this->plugin->waitingConfirmation[$senderName]);
        };
    }

    public function join(CommandSender $sender, array $args): void
    {
        $c_name = $this->getCommandName();
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你已经加入了营地！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入营地ID！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->campsiteExistByCID($args[1])) {
            $sender->sendMessage($this->logo . "§c营地ID无效！");
            return;
        }
        Campsite::getInstance($this->plugin)->changeApplication($args[1], $senderName);
        foreach (Campsite::getInstance($this->plugin)->getHierarchByPowerName($args[1], "审核入营申请") as $k => $name) {
            $hierarch = $this->plugin->getServer()->getPlayerExact($name);
            if ($hierarch !== null)
                $hierarch->sendMessage($this->logo . "§a收到玩家" . $senderName . "的入营申请，输入/" . $c_name . " <accept/disagree> <player_name/all> 来<接受/拒绝>申请。");
        }
        $sender->sendMessage($this->logo . "§a已向营地管理员发送入营申请，请耐心等待。");
    }

    public function quit(CommandSender $sender): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你未加入营地！");
            return;
        }
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        if (Campsite::getInstance($this->plugin)->getOwner($CID) === $senderName)
            $sender->sendMessage($this->logo . "§c需要先转让营长身份再退出营地！");
        else {
            Campsite::getInstance($this->plugin)->changePost($senderName, null);
            Campsite::getInstance($this->plugin)->changePower($senderName, [false, false, false, false, false]);
            Campsite::getInstance($this->plugin)->changePlayerCID($senderName, null);
            Campsite::getInstance($this->plugin)->changeMember($CID, $senderName, false);
            $sender->sendMessage($this->logo . "§c成功退出营地！");
            //退营冷却期？？？？
        }
    }
    public function accept(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你未加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"] && !$ownedPower["审核入营申请"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        $application = Campsite::getInstance($this->plugin)->getApplication($CID);
        if (empty($application)) {
            $sender->sendMessage($this->logo . "§c暂时没有收到入营申请。");
            return;
        }
        if ($args[1] !== "all") {
            if (!in_array($args[1], $application)) {  //玩家未提出申请
                $sender->sendMessage($this->logo . "§c未找到该玩家的请求。");
                return;
            }
            $application = array($args[1]);
        }
        $playerConfig = $this->plugin->playerConfig->getAll();
        foreach ($application as $key => $name) {
            if (!array_key_exists($name, $playerConfig))
                $sender->sendMessage($this->logo . "§c玩家" . $name . "不存在！");
            elseif (Campsite::getInstance($this->plugin)->isJoinCampsite($name))
                $sender->sendMessage($this->logo . "§c玩家" . $name . "已有营地！");
            else {
                Campsite::getInstance($this->plugin)->changeMember($CID, $name, true);
                Campsite::getInstance($this->plugin)->changePlayerCID($name, $CID);
                $sender->sendMessage($this->logo . "§a成功同意玩家" . $name . "加入营地！");
                $player = $this->plugin->getServer()->getPlayerExact($name);
                if ($player !== null)
                    $player->sendMessage($this->logo . "§a营地管理员批准了你的入营申请！");
            }
            Campsite::getInstance($this->plugin)->changeApplication($CID, $name, false);
        }
    }
    public function disagree(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你未加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"] && !$ownedPower["审核入营申请"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        $application = Campsite::getInstance($this->plugin)->getApplication($CID);
        if (empty($application)) {
            $sender->sendMessage($this->logo . "§c暂时没有收到入营申请。");
            return;
        }
        if ($args[1] !== "all") {
            if (!in_array($args[1], $application)) {  //玩家未提出申请
                $sender->sendMessage($this->logo . "§c未找到该玩家的请求。");
                return;
            }
            $application = array($args[1]);
        }
        foreach ($application as $key => $name) {
            $sender->sendMessage($this->logo . "§a成功拒绝玩家" . $name . "加入营地。");
            $player = $this->plugin->getServer()->getPlayerExact($name);
            if ($player !== null)
                $player->sendMessage($this->logo . "§a营地管理员拒绝了你的入营申请。");
        }
        Campsite::getInstance($this->plugin)->changeApplication($CID, $name, false);
    }
    public function list(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"] && !$ownedPower["审核入营申请"]) {
            $sender->sendMessage($this->logo . "§c你没有权限输入该指令！");
            return;
        }
        if (!isset($args[1]))
            $page = 1;
        else
            $page = $args[1];
        if (!is_numeric($page)) {
            $sender->sendMessage($this->logo . "§c页码必须为整数！");
            return;
        }
        $page = (int) $page;
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        $applicationArray = new ArrayPage(Campsite::getInstance($this->plugin)->getApplication($CID), Campsite::getInstance($this->plugin)->getAppEachNum());
        if (!$applicationArray->isValidPage($page)) {
            $sender->sendMessage($this->logo . "§c页码不合理！(1~" . $applicationArray->getTotalPages() . ")");
            return;
        }
        $sender->sendMessage($this->logo . "申请入营的玩家名如下<" . $page . "/" . $applicationArray->getTotalPages() . ">：");
        foreach ($applicationArray->getContent($page) as $playerName)
            $sender->sendMessage($playerName);
    }
    public function post(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入职位名！");
            return;
        }
        if (!array_key_exists($args[1], $this->plugin->playerConfig->getAll())) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isSameCampsite($senderName, $args[1])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "与你不在一个营地！");
            return;
        }
        Campsite::getInstance($this->plugin)->changePost($args[1], $args[2]);
        $sender->sendMessage($this->logo . "§a成功设置玩家" . $args[1] . "的职称为" . $args[2] . "。");
    }
    public function power(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c请选择add或remove来给予或移除玩家权力！");
            return;
        }
        if (!isset($args[2])) {
            $sender->sendMessage($this->logo . "§c未输入玩家名！");
            return;
        }
        $args[2] = strtolower($args[2]);
        if ($args[2] === $senderName) {
            $sender->sendMessage($this->logo . "§c你不能选择你自己！");
            return;
        }
        if (!isset($args[3])) {
            $sender->sendMessage($this->logo . "§c未输入权力ID！");
            return;
        }
        if (!is_numeric($args[3]) || $args[3] < 0 || $args[3] > 4) {
            $sender->sendMessage($this->logo . "§c权力ID输入有误！");
            return;
        }
        if (!array_key_exists($args[2], $this->plugin->playerConfig->getAll())) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[2] . "不存在！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isSameCampsite($senderName, $args[2])) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[2] . "与你不在一个营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($args[2]);
        $arrays = [false, false, false, false, false];
        foreach ($ownedPower as $powerName => $powerTemp) {
            $index = 0;
            switch ($powerName) {
                case "所有权力":
                    $index = 0;
                    break;
                case "设置营地传送点":
                    $index = 1;
                    break;
                case "召集营地成员":
                    $index = 2;
                    break;
                case "审核入营申请":
                    $index = 3;
                    break;
                case "踢人":
                    $index = 4;
                    break;
            }
            $arrays[$index] = $powerTemp;
        }
        $arrays[$args[3]] = $args[1] === "add" ? true : false;
        Campsite::getInstance($this->plugin)->changePower($args[2], $arrays);
        $type = $args[1] === "add" ? "给予" : "移除";
        $sender->sendMessage($this->logo . "§a成功" . $type . "玩家" . $args[2] . Campsite::getInstance($this->plugin)->getPowerNameByID($args[3]) . "的权力。");
    }
    public function search(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if (!isset($args[1])) {
            if ($sender instanceof ConsoleCommandSender) {
                $sender->sendMessage($this->logo . "§c控制台禁止输入！");
                return;
            }
            if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
                $sender->sendMessage($this->logo . "§c你还没有加入营地！");
                return;
            }
            $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName);
        } else {
            if (!is_numeric($args[1])) {
                $sender->sendMessage($this->logo . "§c营地ID有误，查询失败！");
                return;
            }
            if (!Campsite::getInstance($this->plugin)->campsiteExistByCID($args[1])) {
                $sender->sendMessage($this->logo . "§c查询的营地不存在！");
                return;
            }
            $CID = $args[1];
        }
        $sender->sendMessage("--------" . $this->logo . "营地信息查询" . "--------");
        $sender->sendMessage("营地名：" . Campsite::getInstance($this->plugin)->getCName($CID));
        $sender->sendMessage("营地ID：" . $CID);
        $sender->sendMessage("营地人数：" . count(Campsite::getInstance($this->plugin)->getAllMember($CID)));
        $sender->sendMessage("营长：" . Campsite::getInstance($this->plugin)->getOwner($CID));
    }
    public function out(CommandSender $sender, array $args): void
    {
        $senderName = strtolower($sender->getName());
        if ($sender instanceof ConsoleCommandSender) {
            $sender->sendMessage($this->logo . "§c控制台禁止输入！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($senderName)) {
            $sender->sendMessage($this->logo . "§c你还没有加入营地！");
            return;
        }
        $ownedPower = Campsite::getInstance($this->plugin)->getCPower($senderName);
        if (!$ownedPower["所有权力"] && !$ownedPower["踢人"]) {
            $sender->sendMessage($this->logo . "§c你没有权限使用这个命令！");
            return;
        }
        if (!isset($args[1])) {
            $sender->sendMessage($this->logo . "§c未输入踢出玩家名！");
            return;
        }
        $args[1] = strtolower($args[1]);
        if ($args[1] === $senderName) {
            $sender->sendMessage($this->logo . "§c你不能选择你自己！");
            return;
        }
        if (!array_key_exists($args[1], $this->plugin->playerConfig->getAll())) {
            $sender->sendMessage($this->logo . "§c玩家" . $args[1] . "不存在！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isOwner($senderName) && Campsite::getInstance($this->plugin)->isOwner($args[1])) {
            $sender->sendMessage($this->logo . "§c你没有权限踢出营长！");
            return;
        }
        if (!Campsite::getInstance($this->plugin)->isSameCampsite($senderName, $args[1])) {
            $sender->sendMessage($this->logo . "§c对方与你不在同一个营地！");
            return;
        }
        Campsite::getInstance($this->plugin)->changePost($args[1], null);
        Campsite::getInstance($this->plugin)->changePower($args[1], [false, false, false, false, false]);
        Campsite::getInstance($this->plugin)->changePlayerCID($args[1], null);
        Campsite::getInstance($this->plugin)->changeMember(Campsite::getInstance($this->plugin)->getCIDbyPlayerName($senderName), $args[1], false);
        $sender->sendMessage($this->logo . "§a成功踢出" . $args[1]);
        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
        if ($player !== null) {
            $player->sendMessage($this->logo . "§c你被" . $senderName . "踢出了营地。");
        }
    }


    public function getCommandName(): string
    {
        return "campsite";  //指令名
    }
}