<?php

declare(strict_types=1);

namespace MengBao\MEBSociety;

use pocketmine\player\Player;

use MengBao\MEBForms\SimpleForm;
use MengBao\MEBForms\CustomForm;
use MengBao\MEBForms\ModalForm;

use MengBao\MEBSociety\Tools\ArrayPage;
use MengBao\MEBSociety\Units\Shop;
use MengBao\MEBSociety\Units\Players;
use MengBao\MEBSociety\Units\Economy;
use MengBao\MEBSociety\Units\Campsite;
use MengBao\MEBSociety\Units\CampsiteItem;
use MengBao\MEBSociety\Units\CampsiteLevel;
use MengBao\MEBSociety\Units\MultiWorld;
use MengBao\MEBSociety\Units\Cohabitant;

/**
 * GUI导航
 *
 * 全部界面都走MEBForms，由Player::sendForm发送，表单id由核心分配，
 * 回调直接写在打开界面的地方，所以不再需要:
 *  - 手动发ModalFormRequestPacket并自己编formId
 *  - 用GuiCommand把"待执行的指令"暂存起来等下一个窗口的应答
 *  - 用GuiStack记录点击顺序来实现"返回上一级"
 * "返回上一级"直接在闭包里调用上一层的方法即可。
 */
class GuiHandler
{
    private string $logo = "[MEBS]";
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /* ---------------------------------------------------------------- 通用工具 */

    /**
     * 以玩家身份执行指令
     */
    private function dispatch(Player $player, string $command): void
    {
        $this->plugin->getServer()->dispatchCommand($player, $command);
    }

    /**
     * 把参数用引号包起来，供拼接指令时使用
     *
     * 核心的CommandStringHelper::parseQuoteAware按空格拆参数，
     * 引号包住的算一个参数，并且会把\"和\\还原。
     * 所以表单里填的带空格内容(如white wool)必须这样包一层，
     * 否则会被拆成多个参数，后面的数字参数全部错位。
     */
    private function quoteArg(string $value): string
    {
        return '"' . str_replace(array("\\", '"'), array("\\\\", '\\"'), $value) . '"';
    }

    /**
     * 按钮界面
     *
     * $entries的每一项为 [按钮文字, 点击后执行的闭包, 图标类型, 图标地址]，
     * 后两项可以不给，不给就是没图标。
     * 按钮顺序就是数组顺序，回调里按下标取，不会出现对不上的情况。
     *
     * @param array<int, array{0: string, 1: \Closure, 2?: int, 3?: string}> $entries
     */
    private function menu(Player $player, string $title, string $content, array $entries): void
    {
        $entries = array_values($entries);
        $form = new SimpleForm(function (Player $player, $data) use ($entries): void {
            if ($data === null) {  //玩家直接关掉了窗口
                return;
            }
            $entry = $entries[$data] ?? null;
            if ($entry !== null) {
                ($entry[1])($player);
            }
        });
        $form->setTitle($title);
        $form->setContent($content);
        foreach ($entries as $entry) {
            $form->addButton($entry[0], $entry[2] ?? -1, (string) ($entry[3] ?? ""));
        }
        $player->sendForm($form);
    }

    /**
     * 多组件界面
     *
     * $fields用字符串做下标，回调里按同样的下标取值，不必去数组元素顺序。
     * 每一项的写法:
     *  - 输入框: ["label" => "提示", "placeholder" => "灰字"]
     *  - 下拉框: ["label" => "提示", "kind" => "dropdown", "options" => [...], "values" => [...]]
     * 下拉框客户端回的是选项下标，这里直接换成values里对应的值交给回调，
     * 免得把下标丢给指令、再让指令自己去猜要查哪张表。
     *
     * @param array<string, array<string, mixed>> $fields
     * @param \Closure(Player, array<string, mixed>): void $onSubmit
     */
    private function form(Player $player, string $title, array $fields, \Closure $onSubmit): void
    {
        $form = new CustomForm(function (Player $player, $data) use ($fields, $onSubmit): void {
            if ($data === null) {
                return;
            }
            $values = [];
            $index = 0;
            foreach ($fields as $key => $field) {
                $raw = $data[$index] ?? null;
                if (($field["kind"] ?? "input") === "dropdown") {
                    $options = $field["values"] ?? $field["options"];
                    $options = array_values($options);
                    $values[$key] = $options[(int) $raw] ?? null;
                } else {
                    $values[$key] = is_string($raw) ? trim($raw) : $raw;
                }
                $index++;
            }
            $onSubmit($player, $values);
        });
        $form->setTitle($title);
        foreach ($fields as $field) {
            if (($field["kind"] ?? "input") === "dropdown") {
                $form->addDropdown($field["label"], array_values($field["options"]));
            } else {
                $form->addInput($field["label"], (string) ($field["placeholder"] ?? ""));
            }
        }
        $player->sendForm($form);
    }

    /**
     * 纯文本界面
     *
     * 按钮窗口至少给一个按钮，不然部分客户端会显示成空白页。
     */
    public function openText(Player $player, string $title, string $content, ?\Closure $back = null): void
    {
        $entries = [];
        if ($back !== null) {
            $entries[] = ["§c返回上一级", $back];
        }
        $entries[] = ["关闭", static function (Player $player): void {
            //什么都不做，窗口关掉就行
        }];
        $this->menu($player, $title, $content, $entries);
    }

    /**
     * 确认窗口
     *
     * 用来配合WaitingConfirmation: 指令那边先登记好回调，再调用这里把窗口发出去，
     * 玩家点哪个按钮就把true/false交给那个回调。
     * 玩家把窗口关掉时按"拒绝"处理，这样不会留下一个永远处理不掉的请求。
     */
    public function openConfirm(Player $player, string $title, string $content, string $yes = "确认", string $no = "取消"): void
    {
        $playerName = strtolower($player->getName());
        $form = new ModalForm(function (Player $player, $data) use ($playerName): void {
            if (!$this->plugin->waitingConfirmation->hasWC($playerName)) {
                return;  //已经被聊天栏的yes/no或者超时任务处理掉了
            }
            $callback = $this->plugin->waitingConfirmation->getWC($playerName);
            $callback($data === null ? false : (bool) $data);
            //回调里一般自己会删，这里兜底一次，避免玩家一直卡在"有请求未处理"
            $this->plugin->waitingConfirmation->delWC($playerName);
        });
        $form->setTitle($title === "" ? $this->logo . "操作确认窗口" : $title);
        $form->setContent($content);
        $form->setButton1($yes);
        $form->setButton2($no);
        $player->sendForm($form);
    }

    /**
     * 提示一句话，然后回到上一级
     */
    private function tip(Player $player, string $title, string $message, \Closure $back): void
    {
        $this->openText($player, $title, $message, $back);
    }

    /* ---------------------------------------------------------------- 主界面 */

    /**
     * 主界面，/mebui或点击导航物品唤出
     */
    public function openMain(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $players = Players::getInstance($this->plugin);
        $money = Economy::getInstance($this->plugin)->getMoney($playerName);
        $rand = $players->getRand($playerName);
        $content = "§7欢迎使用MEBS系统\n§e你的身份: §b" . $rand
            . "\n§e你的游戏币: §6" . $money . "\n§7请选择要使用的功能:";

        $entries = [
            ["§6经济系统", fn(Player $p) => $this->openMoney($p), 0, "textures/items/gold_ingot"],
            ["§2商店系统", fn(Player $p) => $this->openShop($p), 0, "textures/items/emerald"],
            ["§a营地系统", fn(Player $p) => $this->openCampsite($p), 0, "textures/items/bed_red"],
            ["§d同居系统", fn(Player $p) => $this->openCohabitant($p), 0, "textures/items/heart"],
            ["§b称号系统", fn(Player $p) => $this->openPrefix($p), 0, "textures/items/name_tag"],
            ["§3多世界系统", fn(Player $p) => $this->openMultiWorld($p), 0, "textures/items/compass_item"],
        ];
        //vip/svip特权入口只给对应身份的玩家，普通玩家点进去只会收到"没有权限"
        $isVip = $players->isVip($playerName);
        $isSvip = $players->isVip($playerName, false);
        if ($isVip || $isSvip)
            $entries[] = [
                "§5我的" . ($isSvip ? "svip" : "vip") . "特权",
                fn(Player $p) => $this->openVipPrivilege($p, !$isSvip, fn(Player $p) => $this->openMain($p)),
                0,
                "textures/items/diamond",
            ];
        //op系统与vip管理合并成一个管理入口，只对op和最高权限显示
        if ($players->isMaster($playerName) || $players->isOp($playerName))
            $entries[] = ["§4管理面板", fn(Player $p) => $this->openAdmin($p), 0, "textures/items/command_block"];
        //按钮底色是浅灰的，§7/§8写上去几乎看不见，帮助中心统一用亮黄
        $entries[] = ["§e帮助中心", fn(Player $p) => $this->openHelp($p), 0, "textures/items/book_normal"];
        $entries[] = ["§c关闭", static function (Player $p): void {
        }, 0, "textures/ui/cancel"];

        $this->menu($player, "§6§l" . $this->logo . " §r§e系统导航", $content, $entries);
    }

    /* ---------------------------------------------------------------- 管理面板 */

    /**
     * 管理面板，合并op管理与vip/svip管理
     */
    public function openAdmin(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $players = Players::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openMain($p);
        if (!$players->isMaster($playerName) && !$players->isOp($playerName)) {
            $this->tip($player, $this->logo . "管理面板", "§c你没有权限使用管理面板！", $back);
            return;
        }
        $content = "§eOP数量: §c" . count($players->getOps())
            . "\n§eVIP数量: §6" . count($players->getVips(true))
            . "\n§eSVIP数量: §5" . count($players->getVips(false))
            . "\n§7请选择要管理的内容:";

        $entries = [
            ["§b一键切换生存/创造", fn(Player $p) => $this->dispatch($p, "mebop gm"), 0, "textures/items/diamond_pickaxe"],
            ["§5VIP/SVIP管理", fn(Player $p) => $this->openVipManage($p), 0, "textures/items/diamond"],
        ];
        //op增删和指令禁用只有最高权限能做，普通op看不到这些按钮
        if ($players->isMaster($playerName))
            $entries[] = ["§4OP管理", fn(Player $p) => $this->openOp($p), 0, "textures/items/command_block"];
        $entries[] = ["§c返回上一级", $back, 0, "textures/ui/arrow_left"];
        $this->menu($player, "§4§l" . $this->logo . " §r§e管理面板", $content, $entries);
    }

    /* ---------------------------------------------------------------- 经济系统 */

    public function openMoney(Player $player): void
    {
        $back = fn(Player $p) => $this->openMain($p);
        $playerName = strtolower($player->getName());
        $money = Economy::getInstance($this->plugin)->getMoney($playerName);
        $content = "§e你的游戏币: §6" . $money . "\n§7选择经济相关操作:";

        $entries = [
            ["§e支付游戏币", fn(Player $p) => $this->moneyChange($p, "pay", "支付游戏币"), 0, "textures/items/gold_nugget"],
            ["§b查询某人的游戏币", fn(Player $p) => $this->form(
                $p,
                "§e游戏币查询",
                ["name" => ["label" => "§6请输入查询的玩家名：", "placeholder" => "玩家名"]],
                function (Player $p, array $v): void {
                    if ($v["name"] === "") {
                        $this->tip($p, "游戏币查询", "§c未输入玩家名！", fn(Player $p) => $this->openMoney($p));
                        return;
                    }
                    $this->dispatch($p, "money get " . $v["name"]);
                }
            ), 0, "textures/items/book_writable"],
            ["§6游戏币排行榜", function (Player $p): void {
                $ranking = Economy::getInstance($this->plugin)->getRanking();
                $text = "§e玩家 §7| §6游戏币\n§7--------------------------------\n";
                $rank = 1;
                foreach ($ranking as $name => $money) {
                    $color = $rank === 1 ? "§6" : ($rank === 2 ? "§e" : ($rank === 3 ? "§c" : "§f"));
                    $text .= $color . $rank . ". §f" . $name . " §7=> §6" . $money . "\n";
                    $rank++;
                }
                $this->openText($p, "§6游戏币排行榜", $text, fn(Player $p) => $this->openMoney($p));
            }, 0, "textures/items/trophy"],
        ];
        //增减游戏币要op权限，普通玩家不显示
        $players = Players::getInstance($this->plugin);
        if ($players->isMaster($playerName) || $players->isOp($playerName)) {
            $entries[] = ["§2增加某人的游戏币", fn(Player $p) => $this->moneyChange($p, "add", "增加游戏币"), 0, "textures/ui/plus"];
            $entries[] = ["§4减少某人的游戏币", fn(Player $p) => $this->moneyChange($p, "remove", "减少游戏币"), 0, "textures/ui/minus"];
        }
        $entries[] = ["§c返回上一级", $back, 0, "textures/ui/arrow_left"];
        $this->menu($player, "§6§l" . $this->logo . " §r§e经济系统", $content, $entries);
    }

    /**
     * 增加/减少/支付游戏币，三个界面只差一个子指令
     */
    private function moneyChange(Player $player, string $sub, string $title): void
    {
        $this->form($player, $title, [
            "name" => ["label" => "请输入玩家名：", "placeholder" => "<player_name>"],
            "money" => ["label" => "请输入游戏币数量：", "placeholder" => "<money>"],
        ], function (Player $p, array $v) use ($sub, $title): void {
            if ($v["name"] === "" || $v["money"] === "") {
                $this->tip($p, $title, "§c玩家名和游戏币数量都要填！", fn(Player $p) => $this->openMoney($p));
                return;
            }
            if (!is_numeric($v["money"])) {
                $this->tip($p, $title, "§c游戏币数量必须是数字！", fn(Player $p) => $this->openMoney($p));
                return;
            }
            $this->dispatch($p, "money " . $sub . " " . $v["name"] . " " . $v["money"]);
        });
    }

    /* ---------------------------------------------------------------- 营地系统 */

    public function openCampsite(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $campsite = Campsite::getInstance($this->plugin);

        $content = "§7营地系统管理中心\n";
        if ($campsite->isJoinCampsite($playerName)) {
            $CID = $campsite->getCIDbyPlayerName($playerName);
            $campsiteName = $campsite->getCName($CID);
            $content .= "§e你的营地: §a" . $campsiteName . " §7(ID: " . $CID . ")";
        } else {
            $content .= "§c你还未加入营地";
        }

        //按是否已加入营地给按钮，没入营的人不需要看到福利箱和营地管理
        $joined = $campsite->isJoinCampsite($playerName);
        $entries = [];
        if (!$joined) {
            $entries[] = ["§2创建营地", fn(Player $p) => $this->campsiteInput($p, "campsite create", "§2创建营地", "§6请输入营地名称：", "我的营地"), 0, "textures/items/bed_red"];
            $entries[] = ["§a加入营地", fn(Player $p) => $this->campsiteInput($p, "campsite join", "§a加入营地", "§6请输入营地ID：", "1"), 0, "textures/ui/invite_base"];
        } else {
            $entries[] = ["§6营地等级与捐献池", fn(Player $p) => $this->openCampsitePool($p), 0, "textures/items/gold_block"];
            $entries[] = ["§d营地福利箱", fn(Player $p) => $this->openCampsiteWelfare($p), 0, "textures/items/chest_front"];
            $entries[] = ["§3营地管理", fn(Player $p) => $this->openCampsiteManage($p), 0, "textures/items/writable_book_pages"];
        }
        $entries[] = ["§e营地查询", fn(Player $p) => $this->campsiteInput($p, "campsite search", "§e营地查询", "§6请输入营地ID(留空查询自己的营地)：", ""), 0, "textures/items/book_normal"];
        if ($joined)
            $entries[] = ["§4退出营地", fn(Player $p) => $this->dispatch($p, "campsite quit"), 0, "textures/ui/cancel"];
        $entries[] = ["§c返回上一级", fn(Player $p) => $this->openMain($p), 0, "textures/ui/arrow_left"];

        $this->menu($player, "§a§l" . $this->logo . " §r§e营地系统", $content, $entries);
    }

    /**
     * 营地系统里的单参数输入窗口
     */
    private function campsiteInput(Player $player, string $command, string $title, string $label, string $placeholder): void
    {
        $this->form($player, $title, [
            "value" => ["label" => $label, "placeholder" => $placeholder],
        ], function (Player $p, array $v) use ($command, $title): void {
            if ($v["value"] === "") {
                // 营地查询允许留空，查询自己的营地
                if (strpos($command, "campsite search") === 0) {
                    $this->dispatch($p, $command);
                    return;
                }
                $this->tip($p, $title, "§c内容不能为空！", fn(Player $p) => $this->openCampsite($p));
                return;
            }
            $this->dispatch($p, $command . " " . $v["value"]);
        });
    }

    public function openCampsiteManage(Player $player): void
    {
        $this->menu($player, "§3§l" . $this->logo . " §r§e营地管理", "§7营地管理功能面板", [
            ["§e营地召集", fn(Player $p) => $this->dispatch($p, "campsite call"), 0, "textures/items/bell"],
            ["§a管理入营申请", fn(Player $p) => $this->openCampsiteApplication($p), 0, "textures/items/paper"],
            ["§d管理营地职称", fn(Player $p) => $this->form($p, "§d设置营地职称", [
                "name" => ["label" => "§6请输入玩家名：", "placeholder" => "玩家名"],
                "post" => ["label" => "§6请输入职称：", "placeholder" => "长老"],
            ], function (Player $p, array $v): void {
                if ($v["name"] === "" || $v["post"] === "") {
                    $this->tip($p, "设置营地职称", "§c玩家名和职称都要填！", fn(Player $p) => $this->openCampsiteManage($p));
                    return;
                }
                $this->dispatch($p, "campsite post " . $v["name"] . " " . $v["post"]);
            }), 0, "textures/items/name_tag"],
            ["§6管理营地权力", fn(Player $p) => $this->openCampsitePower($p), 0, "textures/items/diamond_sword"],
            ["§c踢出成员", fn(Player $p) => $this->openCampsiteKick($p), 0, "textures/ui/user_icon"],
            ["§5转让营地", fn(Player $p) => $this->campsiteManageInput($p, "campsite transfer", "§5营地转让", "§6请输入玩家名：", "玩家名"), 0, "textures/items/paper"],
            ["§4解散营地", fn(Player $p) => $this->dispatch($p, "campsite disband"), 0, "textures/items/barrier"],
            ["§c返回上一级", fn(Player $p) => $this->openCampsite($p), 0, "textures/ui/arrow_left"],
        ]);
    }

    private function campsiteManageInput(Player $player, string $command, string $title, string $label, string $placeholder): void
    {
        $this->form($player, $title, [
            "value" => ["label" => $label, "placeholder" => $placeholder],
        ], function (Player $p, array $v) use ($command, $title): void {
            if ($v["value"] === "") {
                $this->tip($p, $title, "§c内容不能为空！", fn(Player $p) => $this->openCampsiteManage($p));
                return;
            }
            $this->dispatch($p, $command . " " . $v["value"]);
        });
    }

    /**
     * 踢出营地成员，成员从下拉框里选
     *
     * 名单里排除自己，非市长时也排除市长。
     */
    private function openCampsiteKick(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $campsite = Campsite::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openCampsiteManage($p);
        if (!$campsite->isJoinCampsite($playerName)) {
            $this->tip($player, "§c踢出成员", "§c你还没有加入营地！", $back);
            return;
        }
        $CID = $campsite->getCIDbyPlayerName($playerName);
        $isOwner = $campsite->isOwner($playerName);
        $players = Players::getInstance($this->plugin);
        $members = array();
        $labels = array();
        foreach ($campsite->getAllMember($CID) as $member) {
            $member = strtolower((string) $member);
            if ($member === $playerName)
                continue;
            //没有玩家数据的成员取不到职称，跳过，选了指令那边也会拦
            if (!$players->playerExist($member))
                continue;
            if (!$isOwner && $campsite->isOwner($member))
                continue;
            $post = $campsite->getCPost($member);
            $members[] = $member;
            //下拉框里带上职称，方便分辨成员身份
            $labels[] = $member . ($post === "" ? "" : " §7(" . $post . ")");
        }
        if ($members === array()) {
            $this->tip($player, "§c踢出成员", "§c没有可以踢出的成员。", $back);
            return;
        }
        $this->form($player, "§c踢出成员", [
            "name" => [
                "label" => "§6请选择要踢出的成员：",
                "kind" => "dropdown",
                "options" => $labels,
                "values" => $members,
            ],
        ], function (Player $p, array $v) use ($back): void {
            if ($v["name"] === null) {
                $this->tip($p, "§c踢出成员", "§c请选择一个成员！", $back);
                return;
            }
            $this->dispatch($p, "campsite out " . $v["name"]);
        });
    }

    /**
     * 管理营地权力
     */
    private function openCampsitePower(Player $player): void
    {
        $powerId = Campsite::getInstance($this->plugin)->getPowerId();
        $this->form($player, "管理营地权力", [
            "action" => [
                "label" => "请选择操作：",
                "kind" => "dropdown",
                "options" => ["给予", "移除"],
                "values" => ["add", "remove"],
            ],
            "name" => ["label" => "玩家名：", "placeholder" => "<player_name>"],
            "power" => [
                "label" => "请选择权力：",
                "kind" => "dropdown",
                "options" => array_values($powerId),
                //下拉框回的是选项下标，这里换回权力ID，指令要的是ID
                "values" => array_keys($powerId),
            ],
        ], function (Player $p, array $v): void {
            if ($v["name"] === "") {
                $this->tip($p, "管理营地权力", "§c未输入玩家名！", fn(Player $p) => $this->openCampsiteManage($p));
                return;
            }
            $this->dispatch($p, "campsite power " . $v["action"] . " " . $v["name"] . " " . $v["power"]);
        });
    }

    /**
     * 入营申请管理
     */
    public function openCampsiteApplication(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $back = fn(Player $p) => $this->openCampsiteManage($p);
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($playerName)) {
            $this->tip($player, $this->logo . "入营申请管理系统", "§c你还没有加入营地！", $back);
            return;
        }
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName);
        $application = Campsite::getInstance($this->plugin)->getApplication($CID);
        $entries = [
            ["全部同意", fn(Player $p) => $this->dispatch($p, "campsite accept all")],
            ["全部拒绝", fn(Player $p) => $this->dispatch($p, "campsite disagree all")],
            ["§c返回上一级", $back],
        ];
        foreach ($application as $applicantName) {
            $applicantName = (string) $applicantName;
            $entries[] = ["Player: " . $applicantName, function (Player $p) use ($applicantName): void {
                $this->confirmApplication($p, $applicantName);
            }];
        }
        $content = $application === []
            ? "§e暂时没有收到入营申请。"
            : "点击玩家可以管理其入营申请！";
        $this->menu($player, $this->logo . "入营申请管理系统", $content, $entries);
    }

    /**
     * 单个入营申请的同意/拒绝
     */
    private function confirmApplication(Player $player, string $applicantName): void
    {
        $playerName = strtolower($player->getName());
        if ($this->plugin->waitingConfirmation->hasWC($playerName)) {
            $this->tip(
                $player,
                $this->logo . "入营申请管理系统",
                "§c你有一个请求未处理，无法执行当前请求！",
                fn(Player $p) => $this->openCampsiteApplication($p)
            );
            return;
        }
        $this->plugin->waitingConfirmation->addWC($playerName, function ($confirmed) use ($player, $playerName, $applicantName): void {
            $this->dispatch($player, "campsite " . ($confirmed ? "accept" : "disagree") . " " . $applicantName);
            $this->plugin->waitingConfirmation->delWC($playerName);
        });
        $this->openConfirm(
            $player,
            $this->logo . "管理" . $applicantName . "的入营申请",
            "§a请选择是否同意玩家" . $applicantName . "加入营地。",
            "同意",
            "拒绝"
        );
    }

    /**
     * 营地等级与捐献池
     */
    private function openCampsitePool(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $back = fn(Player $p) => $this->openCampsite($p);
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($playerName)) {
            $this->tip($player, $this->logo . "营地捐献池", "§c你还没有加入营地！", $back);
            return;
        }
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName);
        $level = CampsiteLevel::getInstance($this->plugin);
        $curLevel = $level->getLevel($CID);
        $isMax = $curLevel >= $level->getMaxLevel();

        $content = "§e营地等级：§f" . $curLevel . "§7/" . $level->getMaxLevel() . "\n"
            . "§e捐献池：§f" . $level->getPoolMoney($CID) . "\n";
        if ($isMax)
            $content .= "§e升级所需：§a已满级\n";
        else {
            $content .= "§e升到" . ($curLevel + 1) . "级需要：§f" . $level->getUpgradeCost($curLevel) . "\n";
            $lack = $level->getUpgradeLack($CID);
            $content .= "§e还差：§f" . ($lack <= 0 ? "§a已凑齐" : $lack) . "\n";
        }
        $unlocked = $level->getUnlockedItemKeys($CID);
        $content .= "§e已解锁专属物品：§f" . ($unlocked === [] ? "无" : implode(", ", $unlocked));

        $record = $level->getPoolRecord($CID);
        if ($record !== []) {
            arsort($record);
            $content .= "\n\n§e捐献记录：";
            foreach ($record as $name => $money)
                $content .= "\n§f" . $name . " §6=> §f" . $money;
        }

        $entries = [
            ["§a捐献游戏币", fn(Player $p) => $this->form(
                $p,
                "向捐献池捐献",
                ["money" => ["label" => "请输入要捐献的游戏币数量：", "placeholder" => "1000"]],
                function (Player $p, array $v): void {
                    if ($v["money"] === "" || !is_numeric($v["money"]) || (float) $v["money"] <= 0) {
                        $this->tip($p, "向捐献池捐献", "§c游戏币数量必须是正数！", fn(Player $p) => $this->openCampsitePool($p));
                        return;
                    }
                    $this->dispatch($p, "campsite donate " . (float) $v["money"]);
                }
            )],
        ];
        //升级会花掉全营地凑的钱，按钮只给市长
        if (!$isMax && Campsite::getInstance($this->plugin)->isOwner($playerName))
            $entries[] = ["§6升级营地(市长)", fn(Player $p) => $this->dispatch($p, "campsite upgrade")];
        $entries[] = ["§c返回上一级", $back];
        $this->menu($player, $this->logo . "营地等级与捐献池", $content, $entries);
    }

    /**
     * 营地福利箱
     */
    private function openCampsiteWelfare(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $back = fn(Player $p) => $this->openCampsite($p);
        if (!Campsite::getInstance($this->plugin)->isJoinCampsite($playerName)) {
            $this->tip($player, $this->logo . "营地福利箱", "§c你还没有加入营地！", $back);
            return;
        }
        $CID = Campsite::getInstance($this->plugin)->getCIDbyPlayerName($playerName);
        $level = CampsiteLevel::getInstance($this->plugin);
        $item = CampsiteItem::getInstance($this->plugin);
        $claimed = $level->hasClaimed($CID, $playerName);

        $content = "§e附带游戏币：§f" . $level->getWelfareMoney($CID)
            . " §7(上限" . $level->getWelfareMoneyLimit() . "，从捐献池扣)\n"
            . "§e营地捐献池：§f" . $level->getPoolMoney($CID) . "\n§e箱内物品：";
        $welfare = $level->getWelfare($CID);
        if ($welfare === [])
            $content .= "§7空";
        else {
            foreach ($welfare as $key => $num)
                $content .= "\n§f" . $item->getName((string) $key) . "§r §6×" . $num;
        }
        $content .= "\n\n§e本周状态：" . ($claimed ? "§c已领取" : "§a可领取")
            . "\n§7福利箱每周一刷新，每人每周可领一次。";

        $entries = [];
        if (!$claimed)
            $entries[] = ["§a领取本周福利箱", fn(Player $p) => $this->dispatch($p, "campsite claim")];
        //设置福利箱内容只有市长能做
        if (Campsite::getInstance($this->plugin)->isOwner($playerName)) {
            $entries[] = ["§6设置箱内物品(市长)", fn(Player $p) => $this->campsiteWelfareSet($p, $CID)];
            $limit = $level->getWelfareMoneyLimit();
            $entries[] = ["§6设置附带游戏币(市长)", fn(Player $p) => $this->form(
                $p,
                "设置福利箱游戏币",
                ["money" => ["label" => "请输入每人每周可领的游戏币(上限" . $limit . ")：", "placeholder" => "0"]],
                function (Player $p, array $v) use ($limit): void {
                    if ($v["money"] === "" || !is_numeric($v["money"]) || (float) $v["money"] < 0) {
                        $this->tip($p, "设置福利箱游戏币", "§c游戏币数量必须是非负数！", fn(Player $p) => $this->openCampsiteWelfare($p));
                        return;
                    }
                    if ((float) $v["money"] > $limit) {
                        $this->tip($p, "设置福利箱游戏币", "§c不能超过上限" . $limit . "！", fn(Player $p) => $this->openCampsiteWelfare($p));
                        return;
                    }
                    $this->dispatch($p, "campsite welfare money " . (float) $v["money"]);
                }
            )];
        }
        $entries[] = ["§c返回上一级", $back];
        $this->menu($player, $this->logo . "营地福利箱", $content, $entries);
    }

    /**
     * 市长设置福利箱里的专属物品
     *
     * 下拉框里只列已解锁的物品，没解锁的选了也会被指令拦下来，
     * 不如干脆不给选。
     */
    private function campsiteWelfareSet(Player $player, int $CID): void
    {
        $level = CampsiteLevel::getInstance($this->plugin);
        $item = CampsiteItem::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openCampsiteWelfare($p);
        $keys = $level->getUnlockedItemKeys($CID);
        if ($keys === []) {
            $this->tip($player, $this->logo . "设置福利箱", "§c营地还没有解锁任何专属物品，先升级营地吧！", $back);
            return;
        }
        $labels = [];
        foreach ($keys as $key)
            $labels[] = $item->getName($key);

        $this->form($player, "设置福利箱物品", [
            "key" => [
                "label" => "请选择专属物品：",
                "kind" => "dropdown",
                "options" => $labels,
                "values" => $keys,
            ],
            "num" => ["label" => "每人每周可领数量(0表示移出福利箱)：", "placeholder" => "1"],
        ], function (Player $p, array $v) use ($back): void {
            if ($v["key"] === null) {
                $this->tip($p, "设置福利箱", "§c请选择一种专属物品！", $back);
                return;
            }
            if ($v["num"] === "" || !is_numeric($v["num"]) || (int) $v["num"] < 0) {
                $this->tip($p, "设置福利箱", "§c数量必须是非负整数！", $back);
                return;
            }
            $this->dispatch($p, "campsite welfare set " . $v["key"] . " " . (int) $v["num"]);
        });
    }

    /* ---------------------------------------------------------------- 同居系统 */

    public function openCohabitant(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $cohabitant = Cohabitant::getInstance($this->plugin);

        $content = "§7同居系统管理\n";
        if ($cohabitant->hasCohabitant($playerName)) {
            $partner = $cohabitant->getCohabitant($playerName);
            $content .= "§e当前同居对象: §d" . $partner;
        } else {
            $content .= "§c你还未与任何人同居";
        }

        //按有没有同居对象给按钮，已同居的人不需要"申请同居"
        $has = $cohabitant->hasCohabitant($playerName);
        $entries = [];
        if ($has) {
            $entries[] = ["§b同居传送", fn(Player $p) => $this->dispatch($p, "cohabitant transfer"), 0, "textures/items/ender_pearl"];
            $entries[] = ["§c解除同居", fn(Player $p) => $this->dispatch($p, "cohabitant divorce"), 0, "textures/items/iron_sword"];
        } else {
            $entries[] = ["§a申请同居", fn(Player $p) => $this->cohabitantInput($p, "cohabitant propose", "§a申请同居"), 0, "textures/items/heart"];
        }
        //强制解除是管理操作
        $players = Players::getInstance($this->plugin);
        if ($players->isMaster($playerName) || $players->isOp($playerName))
            $entries[] = ["§4强制解除同居", fn(Player $p) => $this->cohabitantInput($p, "cohabitant opdiv", "§4强制解除同居"), 0, "textures/items/barrier"];
        $entries[] = ["§c返回上一级", fn(Player $p) => $this->openMain($p), 0, "textures/ui/arrow_left"];

        $this->menu($player, "§d§l" . $this->logo . " §r§e同居系统", $content, $entries);
    }

    private function cohabitantInput(Player $player, string $command, string $title): void
    {
        $this->form($player, $title, [
            "name" => ["label" => "请输入玩家名：", "placeholder" => "<player_name>"],
        ], function (Player $p, array $v) use ($command, $title): void {
            if ($v["name"] === "") {
                $this->tip($p, $title, "§c未输入玩家名！", fn(Player $p) => $this->openCohabitant($p));
                return;
            }
            $this->dispatch($p, $command . " " . $v["name"]);
        });
    }

    /* ---------------------------------------------------------------- 称号系统 */

    public function openPrefix(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $players = Players::getInstance($this->plugin);

        $content = "§7称号系统管理\n";
        if ($players->playerExist($playerName)) {
            $currentPrefix = $players->getCurPrefix($playerName);
            $content .= "§e当前使用称号: " . ($currentPrefix !== null && $currentPrefix !== "" ? $currentPrefix : "§7未设置");
        }

        $entries = [
            ["§a查看我的称号", fn(Player $p) => $this->dispatch($p, "mebpre list"), 0, "textures/items/book_normal"],
            ["§e更换使用称号", fn(Player $p) => $this->openPrefixChange($p), 0, "textures/items/name_tag"],
        ];
        //给予/回收称号和权限开关都是管理操作
        $canManage = $players->isMaster($playerName)
            || ($players->isOp($playerName) && $players->hasOpPrefix());
        if ($canManage) {
            $entries[] = ["§2给予称号", fn(Player $p) => $this->prefixEdit($p, "add", "§2给予玩家称号"), 0, "textures/ui/plus"];
            $entries[] = ["§4回收称号", fn(Player $p) => $this->prefixEdit($p, "del", "§4回收玩家称号"), 0, "textures/ui/minus"];
        }
        if ($players->isMaster($playerName))
            $entries[] = ["§6切换op管理称号的权限", fn(Player $p) => $this->dispatch($p, "mebpre oppre"), 0, "textures/items/command_block"];
        $entries[] = ["§c返回上一级", fn(Player $p) => $this->openMain($p), 0, "textures/ui/arrow_left"];

        $this->menu($player, "§b§l" . $this->logo . " §r§e称号系统", $content, $entries);
    }

    private function openPrefixChange(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $back = fn(Player $p) => $this->openPrefix($p);
        if (!Players::getInstance($this->plugin)->playerExist($playerName)) {
            $this->tip($player, "更换使用称号", "§c找不到你的玩家数据！", $back);
            return;
        }
        $prefixes = Players::getInstance($this->plugin)->getPlayerAllPrefixes($playerName);
        if ($prefixes === []) {
            $this->tip($player, "更换使用称号", "§c你还没有任何称号。", $back);
            return;
        }
        $this->form($player, "更换使用称号", [
            "prefix" => [
                "label" => "请选择要使用的称号：",
                "kind" => "dropdown",
                "options" => array_values($prefixes),
                //称号被回收后数组下标会留空档，所以要用真正的称号ID，不能用下拉框下标
                "values" => array_keys($prefixes),
            ],
        ], function (Player $p, array $v): void {
            $this->dispatch($p, "mebpre change " . $v["prefix"]);
        });
    }

    private function prefixEdit(Player $player, string $sub, string $title): void
    {
        $this->form($player, $title, [
            "name" => ["label" => "请输入玩家名：", "placeholder" => "<player_name>"],
            "prefix" => ["label" => "请输入称号：", "placeholder" => "<prefix_name>"],
        ], function (Player $p, array $v) use ($sub, $title): void {
            if ($v["name"] === "" || $v["prefix"] === "") {
                $this->tip($p, $title, "§c玩家名和称号都要填！", fn(Player $p) => $this->openPrefix($p));
                return;
            }
            $this->dispatch($p, "mebpre " . $sub . " " . $v["name"] . " " . $v["prefix"]);
        });
    }

    /* ---------------------------------------------------------------- 多世界系统 */

    public function openMultiWorld(Player $player): void
    {
        $back = fn(Player $p) => $this->openMultiWorld($p);
        $currentWorld = $player->getWorld()->getFolderName();
        $content = "§7多世界传送与管理\n§e当前世界: §a" . $currentWorld;

        $entries = [
            ["§a世界传送", fn(Player $p) => $this->worldPick($p, "mw go", "§a世界传送", "§6请选择要传送的世界："), 0, "textures/items/ender_pearl"],
            ["§b定点传送", fn(Player $p) => $this->openWorldTransfer($p), 0, "textures/items/compass_item"],
            ["§e查看所有世界", function (Player $p) use ($back): void {
                $mw = MultiWorld::getInstance($this->plugin);
                $text = "§e世界名 §7| §a状态 §7| §b在线玩家\n§7-----------------------------------------------\n";
                foreach ($mw->getAllWolrdName() as $name) {
                    $online = $mw->isWorldLoaded($name) ? $mw->getOnlineNum($name) : 0;
                    $text .= "§f" . $name . " §7| " . $mw->getLoadInfo($name) . "§r §7| §b" . $online . "\n";
                }
                $this->openText($p, "§e世界名单", $text, $back);
            }, 0, "textures/items/book_normal"],
            ["§d查询世界信息", fn(Player $p) => $this->worldPick($p, "mw info", "§d查询世界信息", "§6请选择要查询的世界："), 0, "textures/items/book_writable"],
        ];
        //加载/卸载/改描述要op权限，合并到一个管理界面
        $players = Players::getInstance($this->plugin);
        $playerName = strtolower($player->getName());
        if ($players->isMaster($playerName) || $players->isOp($playerName))
            $entries[] = ["§6世界管理", fn(Player $p) => $this->openWorldManage($p), 0, "textures/items/writable_book_pages"];
        $entries[] = ["§c返回上一级", fn(Player $p) => $this->openMain($p), 0, "textures/ui/arrow_left"];

        $this->menu($player, "§3§l" . $this->logo . " §r§e多世界系统", $content, $entries);
    }

    /**
     * 世界管理，加载/卸载/设置描述
     */
    private function openWorldManage(Player $player): void
    {
        $back = fn(Player $p) => $this->openMultiWorld($p);
        $this->menu($player, "§3§l" . $this->logo . " §r§e世界管理", "§7世界的加载与描述管理", [
            ["§2加载世界", fn(Player $p) => $this->worldPick($p, "mw load", "§2加载世界", "§6请选择要加载的世界："), 0, "textures/ui/check"],
            ["§4卸载世界", fn(Player $p) => $this->worldPick($p, "mw unload", "§4卸载世界", "§6请选择要卸载的世界："), 0, "textures/ui/cancel"],
            ["§6设置世界描述", fn(Player $p) => $this->openWorldSetInfo($p), 0, "textures/items/writable_book_pages"],
            ["§c返回上一级", $back, 0, "textures/ui/arrow_left"],
        ]);
    }

    /**
     * 选一个世界然后执行指令
     */
    private function worldPick(Player $player, string $command, string $title, string $label): void
    {
        $worlds = MultiWorld::getInstance($this->plugin)->getAllWolrdName();
        if ($worlds === []) {
            $this->tip($player, $title, "§c没有找到任何世界。", fn(Player $p) => $this->openMultiWorld($p));
            return;
        }
        $this->form($player, $title, [
            "world" => ["label" => $label, "kind" => "dropdown", "options" => $worlds],
        ], function (Player $p, array $v) use ($command): void {
            $this->dispatch($p, $command . " " . $v["world"]);
        });
    }

    private function openWorldTransfer(Player $player): void
    {
        $worlds = MultiWorld::getInstance($this->plugin)->getAllWolrdName();
        if ($worlds === []) {
            $this->tip($player, "定点传送", "§c没有找到任何世界。", fn(Player $p) => $this->openMultiWorld($p));
            return;
        }
        $this->form($player, "定点传送", [
            "world" => ["label" => "请选择世界：", "kind" => "dropdown", "options" => $worlds],
            "x" => ["label" => "请输入x坐标：", "placeholder" => "留空则传送至世界出生点"],
            "y" => ["label" => "请输入y坐标：", "placeholder" => "留空则传送至世界出生点"],
            "z" => ["label" => "请输入z坐标：", "placeholder" => "留空则传送至世界出生点"],
        ], function (Player $p, array $v): void {
            $x = $v["x"];
            $y = $v["y"];
            $z = $v["z"];
            //三个坐标要么都填要么都不填，只填一半的话指令那边收到的参数会错位
            if ($x === "" && $y === "" && $z === "") {
                $this->dispatch($p, "mw transfer " . $v["world"]);
                return;
            }
            if ($x === "" || $y === "" || $z === "") {
                $this->tip($p, "定点传送", "§cxyz三个坐标要么都填，要么都留空！", fn(Player $p) => $this->openMultiWorld($p));
                return;
            }
            if (!is_numeric($x) || !is_numeric($y) || !is_numeric($z)) {
                $this->tip($p, "定点传送", "§c坐标必须是数字！", fn(Player $p) => $this->openMultiWorld($p));
                return;
            }
            $this->dispatch($p, "mw transfer " . $v["world"] . " " . (int) $x . " " . (int) $y . " " . (int) $z);
        });
    }

    private function openWorldSetInfo(Player $player): void
    {
        $worlds = MultiWorld::getInstance($this->plugin)->getAllWolrdName();
        if ($worlds === []) {
            $this->tip($player, "设置世界描述", "§c没有找到任何世界。", fn(Player $p) => $this->openMultiWorld($p));
            return;
        }
        $this->form($player, "设置世界描述", [
            "world" => ["label" => "请选择世界：", "kind" => "dropdown", "options" => $worlds],
            "info" => ["label" => "请输入描述：", "placeholder" => "<info>"],
        ], function (Player $p, array $v): void {
            if ($v["info"] === "") {
                $this->tip($p, "设置世界描述", "§c未输入描述！", fn(Player $p) => $this->openMultiWorld($p));
                return;
            }
            $this->dispatch($p, "mw setinfo " . $v["world"] . " " . $v["info"]);
        });
    }

    /* ---------------------------------------------------------------- op系统 */

    public function openOp(Player $player): void
    {
        $back = fn(Player $p) => $this->openOp($p);
        $players = Players::getInstance($this->plugin);
        $limited = $players->getAllLimitedCmd();

        $content = "§7OP管理系统\n§e当前OP数量: §c" . count($players->getOps())
            . "\n§e已禁用指令: §c" . count($limited);

        $this->menu($player, "§4§l" . $this->logo . " §r§eOP管理系统", $content, [
            ["§a新增OP", fn(Player $p) => $this->form($p, "§a新增OP", [
                "name" => ["label" => "§6请输入玩家名：", "placeholder" => "玩家名"],
            ], function (Player $p, array $v): void {
                if ($v["name"] === "") {
                    $this->tip($p, "新增op", "§c未输入玩家名！", fn(Player $p) => $this->openOp($p));
                    return;
                }
                $this->dispatch($p, "mebop add " . $v["name"]);
            }), 0, "textures/ui/plus"],
            ["§c删除OP", function (Player $p): void {
                $ops = Players::getInstance($this->plugin)->getOps();
                if ($ops === []) {
                    $this->tip($p, "删除op", "§c服务器还没有op。", fn(Player $p) => $this->openOp($p));
                    return;
                }
                $this->form($p, "§c删除OP", [
                    "name" => ["label" => "§6请选择要删除的OP：", "kind" => "dropdown", "options" => array_values($ops)],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebop del " . $v["name"]);
                });
            }, 0, "textures/ui/minus"],
            ["§e查看所有OP", function (Player $p) use ($back): void {
                $text = "§eOP §7| §a状态\n§7----------------\n";
                foreach (Players::getInstance($this->plugin)->getOps() as $name) {
                    $text .= "§f" . $name . " §7| " . (Players::getInstance($this->plugin)->isOnline($name) ? "§a在线" : "§c离线") . "§r\n";
                }
                $this->openText($p, "§eOP名单", $text, $back);
            }, 0, "textures/items/book_normal"],
            ["§6指令禁用管理", fn(Player $p) => $this->openOpLimitCmd($p), 0, "textures/items/barrier"],
            ["§c返回上一级", fn(Player $p) => $this->openAdmin($p), 0, "textures/ui/arrow_left"],
        ]);
    }

    /**
     * 指令禁用管理，禁用与取消禁用合并到一个界面
     */
    private function openOpLimitCmd(Player $player): void
    {
        $back = fn(Player $p) => $this->openOp($p);
        $limited = Players::getInstance($this->plugin)->getAllLimitedCmd();
        $content = $limited === []
            ? "§7当前没有被禁用的指令。"
            : "§e已禁用: §c" . implode("§7, §c", array_values($limited));

        $this->menu($player, "§4§l" . $this->logo . " §r§e指令禁用管理", $content, [
            ["§4禁用一条指令", function (Player $p): void {
                $commands = array_keys($this->plugin->getServer()->getCommandMap()->getCommands());
                sort($commands);
                $this->form($p, "§4禁用指令", [
                    "cmd" => ["label" => "§6请选择要禁用的指令：", "kind" => "dropdown", "options" => $commands],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebop licmd " . $v["cmd"]);
                });
            }, 0, "textures/items/barrier"],
            ["§2取消禁用指令", function (Player $p): void {
                $limited = Players::getInstance($this->plugin)->getAllLimitedCmd();
                if ($limited === []) {
                    $this->tip($p, "取消禁用指令", "§c当前没有被禁用的指令。", fn(Player $p) => $this->openOpLimitCmd($p));
                    return;
                }
                //配置里存的是带"/"的，指令要的是不带"/"的
                $names = array_map(static fn(string $cmd): string => ltrim($cmd, "/"), array_values($limited));
                $this->form($p, "§2取消禁用指令", [
                    "cmd" => ["label" => "§6请选择要取消禁用的指令：", "kind" => "dropdown", "options" => $names],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebop unlicmd " . $v["cmd"]);
                });
            }, 0, "textures/ui/check"],
            ["§c返回上一级", $back, 0, "textures/ui/arrow_left"],
        ]);
    }

    /* ---------------------------------------------------------------- vip系统 */

    /**
     * VIP/SVIP管理
     *
     * vip和svip的按钮成对出现，合并成先选等级再选操作，按钮数量减半。
     */
    public function openVipManage(Player $player): void
    {
        $back = fn(Player $p) => $this->openAdmin($p);
        $players = Players::getInstance($this->plugin);
        $content = "§7VIP系统管理中心\n§e当前VIP数量: §6" . count($players->getVips(true))
            . "\n§d当前SVIP数量: §5" . count($players->getVips(false));

        $this->menu($player, "§5§l" . $this->logo . " §r§eVIP管理系统", $content, [
            ["§6查看名单", fn(Player $p) => $this->vipTierPick($p, "查看名单", function (Player $p, bool $isVip): void {
                $this->openVipList($p, $isVip, fn(Player $p) => $this->openVipManage($p));
            }), 0, "textures/items/book_normal"],
            ["§a特权面板", fn(Player $p) => $this->vipTierPick($p, "特权面板", function (Player $p, bool $isVip): void {
                $this->openVipPrivilege($p, $isVip);
            }), 0, "textures/items/gold_block"],
            ["§2设置天数", fn(Player $p) => $this->vipTierPick($p, "设置天数", function (Player $p, bool $isVip): void {
                $this->openVipSetDay($p, $isVip);
            }), 0, "textures/items/clock_item"],
            ["§e切换op管理权限", fn(Player $p) => $this->vipTierPick($p, "切换op管理权限", function (Player $p, bool $isVip): void {
                $this->dispatch($p, $isVip ? "mebvip opvip" : "mebsvip opsvip");
            }), 0, "textures/items/command_block"],
            ["§c返回上一级", $back, 0, "textures/ui/arrow_left"],
        ]);
    }

    /**
     * 先选VIP还是SVIP，再执行操作
     */
    private function vipTierPick(Player $player, string $title, \Closure $onPick): void
    {
        $this->menu($player, $this->logo . $title, "§7请选择要操作的等级:", [
            ["§6VIP", fn(Player $p) => $onPick($p, true), 0, "textures/items/gold_ingot"],
            ["§5SVIP", fn(Player $p) => $onPick($p, false), 0, "textures/items/diamond"],
            ["§c返回上一级", fn(Player $p) => $this->openVipManage($p), 0, "textures/ui/arrow_left"],
        ]);
    }

    private function openVipList(Player $player, bool $isVip, \Closure $back): void
    {
        $players = Players::getInstance($this->plugin);
        $label = $isVip ? "VIP" : "SVIP";
        $text = $label . " | 剩余天数 | 状态\n--------------------------------\n";
        foreach ($players->getVips($isVip) as $name) {
            $text .= $name . " | " . $players->getVipDay($name, $isVip) . " | " . ($players->isOnline($name) ? "§a在线" : "§c离线") . "§r\n";
        }
        $this->openText($player, $label . "名单", $text, $back);
    }

    private function openVipSetDay(Player $player, bool $isVip): void
    {
        $command = $isVip ? "mebvip" : "mebsvip";
        $title = ($isVip ? "设置vip天数" : "设置svip天数");
        $online = Players::getInstance($this->plugin)->getAllOnlinePlayerName();
        if ($online === []) {
            $this->tip($player, $title, "§c当前没有在线玩家。", fn(Player $p) => $this->openVipManage($p));
            return;
        }
        $this->form($player, $title, [
            "name" => ["label" => "请选择玩家：", "kind" => "dropdown", "options" => $online],
            "day" => ["label" => "请输入天数(-1永久/0取消)：", "placeholder" => "<day>"],
        ], function (Player $p, array $v) use ($command, $title): void {
            if ($v["day"] === "" || !is_numeric($v["day"])) {
                $this->tip($p, $title, "§c天数必须是整数！", fn(Player $p) => $this->openVipManage($p));
                return;
            }
            $this->dispatch($p, $command . " set " . $v["name"] . " " . (int) $v["day"]);
        });
    }

    /**
     * vip/svip特权界面
     *
     * 玩家从主界面进来时$back回主界面，管理员从VIP管理进来时回VIP管理。
     */
    private function openVipPrivilege(Player $player, bool $isVip, ?\Closure $back = null): void
    {
        $command = $isVip ? "mebvip" : "mebsvip";
        $label = $isVip ? "vip" : "svip";
        $back ??= fn(Player $p) => $this->openVipManage($p);
        $self = fn(Player $p) => $this->openVipPrivilege($p, $isVip, $back);
        $this->menu($player, $this->logo . $label . "特权导航", "§7请选择要使用的特权:", [
            ["§a每日签到", fn(Player $p) => $this->dispatch($p, $command . " sign"), 0, "textures/items/gold_nugget"],
            ["§b切换飞行模式", fn(Player $p) => $this->dispatch($p, $command . " fly"), 0, "textures/items/feather"],
            ["§d设置聊天颜色", function (Player $p) use ($isVip, $command): void {
                //颜色代号直接交给color子指令，不用guicolor那套"下标换代号"的绕法
                $colors = Players::getInstance($this->plugin)->getAllColor($isVip);
                $options = array_map(static fn(string $code): string => "§" . $code . $code, $colors);
                $this->form($p, "更换聊天颜色", [
                    "color" => ["label" => "请选择颜色：", "kind" => "dropdown", "options" => $options, "values" => $colors],
                ], function (Player $p, array $v) use ($command): void {
                    $this->dispatch($p, $command . " color " . $v["color"]);
                });
            }, 0, "textures/items/dye_powder_blue"],
            [$isVip ? "§e传送到玩家" : "§e强制传送玩家", function (Player $p) use ($command, $isVip, $self): void {
                $title = $isVip ? "vip传送" : "svip强制传送";
                $online = Players::getInstance($this->plugin)->getAllOnlinePlayerName();
                $me = strtolower($p->getName());
                $online = array_values(array_filter($online, static fn(string $name): bool => $name !== $me));
                if ($online === []) {
                    $this->tip($p, $title, "§c没有其他在线玩家。", $self);
                    return;
                }
                $this->form($p, $title, [
                    "name" => ["label" => "请选择玩家：", "kind" => "dropdown", "options" => $online],
                ], function (Player $p, array $v) use ($command): void {
                    $this->dispatch($p, $command . " transfer " . $v["name"]);
                });
            }, 0, "textures/items/ender_pearl"],
            ["§6查看剩余天数", function (Player $p) use ($isVip, $command, $label, $self): void {
                $vips = Players::getInstance($this->plugin)->getVips($isVip);
                if ($vips === []) {
                    $this->tip($p, "查看" . $label . "剩余天数", "§c服务器还没有" . $label . "。", $self);
                    return;
                }
                $this->form($p, "查看" . $label . "剩余天数", [
                    "name" => ["label" => "请选择玩家：", "kind" => "dropdown", "options" => array_values($vips)],
                ], function (Player $p, array $v) use ($command): void {
                    $this->dispatch($p, $command . " day " . $v["name"]);
                });
            }, 0, "textures/items/clock_item"],
            ["§c返回上一级", $back, 0, "textures/ui/arrow_left"],
        ]);
    }

    /* ---------------------------------------------------------------- 商店系统 */

    public function openShop(Player $player): void
    {
        $playerName = strtolower($player->getName());
        $shop = Shop::getInstance($this->plugin);
        $money = Economy::getInstance($this->plugin)->getMoney($playerName);

        $content = "§7欢迎来到MEBS商店\n§e你的游戏币: §6" . $money . "\n§7选择购买、出售或管理商品";

        $entries = [
            ["§a购买物品", fn(Player $p) => $this->openShopList($p, "buy"), 0, "textures/items/emerald"],
            ["§e出售物品", fn(Player $p) => $this->openShopList($p, "sell"), 0, "textures/items/gold_ingot"],
            ["§d购买称号", fn(Player $p) => $this->openShopList($p, "prefix"), 0, "textures/items/name_tag"],
            ["§b查看全部商品", fn(Player $p) => $this->openShopAll($p), 0, "textures/items/book_normal"],
            ["§6营地专属物品市场", fn(Player $p) => $this->openCampMarket($p), 0, "textures/items/diamond"],
        ];
        //没管理权限的玩家就不给这个入口，免得点进去只收到一句"没有权限"
        $canManage = Players::getInstance($this->plugin)->isMaster($playerName)
            || (Players::getInstance($this->plugin)->isOp($playerName) && $shop->hasOpShop());
        if ($canManage) {
            $entries[] = ["§3商店管理", fn(Player $p) => $this->openShopManage($p), 0, "textures/items/writable_book_pages"];
        }
        $entries[] = ["§c返回上一级", fn(Player $p) => $this->openMain($p), 0, "textures/ui/arrow_left"];
        $this->menu($player, "§2§l" . $this->logo . " §r§e商店系统", $content, $entries);
    }

    /**
     * 商品列表，$mode决定列出哪一类商品以及点进去做什么
     *
     * buy=可购买的物品，sell=可出售的物品，prefix=可购买的称号。
     * 按钮数量按配置分页，翻页按钮只在有上下页的时候才加。
     */
    private function openShopList(Player $player, string $mode, int $page = 1): void
    {
        $shop = Shop::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openShop($p);
        switch ($mode) {
            case "sell":
                $title = "出售物品";
                $shops = $shop->getShopsByType(Shop::TYPE_ITEM, false, true);
                break;
            case "prefix":
                $title = "购买称号";
                $shops = $shop->getShopsByType(Shop::TYPE_PREFIX, true);
                break;
            default:
                $mode = "buy";
                $title = "购买物品";
                $shops = $shop->getShopsByType(Shop::TYPE_ITEM, true);
        }
        if ($shops === []) {
            $this->tip($player, $this->logo . $title, "§e暂时没有可交易的商品。", $back);
            return;
        }
        $shopPage = new ArrayPage($shops, $shop->getShopEachNum());
        $totalPages = $shopPage->getTotalPages();
        $page = $shopPage->isValidPage($page) ? $page : 1;
        $entries = [];
        foreach ($shopPage->getContent($page) as $SID => $config) {
            $SID = (int) $SID;
            $price = $mode === "sell" ? $shop->getTotalPrice($SID, 1, false) : $shop->getTotalPrice($SID);
            $num = $config["类型"] === Shop::TYPE_PREFIX ? "" : "§7×" . max(1, (int) $config["数量"]) . " ";
            $entries[] = [
                $shop->getShopName($SID) . "§r\n" . $num . "§e" . $price . "游戏币",
                fn(Player $p) => $this->openShopDetail($p, $SID, $mode, $page),
                //图标没配就按物品名推导，推不出来时getIconType会返回-1，按钮照常显示只是没图
                $shop->getIconType($SID),
                $shop->getIconPath($SID),
            ];
        }
        if ($page > 1) {
            $entries[] = ["§b上一页", fn(Player $p) => $this->openShopList($p, $mode, $page - 1)];
        }
        if ($page < $totalPages) {
            $entries[] = ["§b下一页", fn(Player $p) => $this->openShopList($p, $mode, $page + 1)];
        }
        $entries[] = ["§c返回上一级", $back];
        $this->menu(
            $player,
            $this->logo . $title . "<" . $page . "/" . $totalPages . ">",
            "§a点击商品即可查看详情并交易。",
            $entries
        );
    }

    /**
     * 商品详情，确认份数后交给指令处理
     */
    private function openShopDetail(Player $player, int $SID, string $mode, int $page): void
    {
        $shop = Shop::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openShopList($p, $mode, $page);
        if (!$shop->shopExist($SID)) {
            $this->tip($player, $this->logo . "商品详情", "§c该商品已经下架了。", $back);
            return;
        }
        $config = $shop->getShop($SID);
        $isSell = $mode === "sell";
        $action = $isSell ? "出售" : "购买";
        $money = Economy::getInstance($this->plugin)->getMoney(strtolower($player->getName()));
        $content = $shop->getShopInfo($SID) . "\n§e你的游戏币：§f" . $money;
        //称号只有一份，没必要问份数，直接确认
        if ($config["类型"] === Shop::TYPE_PREFIX) {
            $this->menu($player, $this->logo . "商品详情", $content, [
                ["§a确认购买", fn(Player $p) => $this->dispatch($p, "mebshop buy " . $SID)],
                ["§c返回上一级", $back],
            ]);
            return;
        }
        $this->menu($player, $this->logo . "商品详情", $content, [
            ["§a" . $action . "1份", fn(Player $p) => $this->dispatch($p, "mebshop " . $mode . " " . $SID)],
            ["§a" . $action . "指定份数", fn(Player $p) => $this->form(
                $p,
                $action . $shop->getShopName($SID),
                ["times" => ["label" => "请输入" . $action . "份数(每份" . max(1, (int) $config["数量"]) . "个)：", "placeholder" => "1"]],
                function (Player $p, array $v) use ($SID, $mode, $action, $back): void {
                    if ($v["times"] === "" || !is_numeric($v["times"]) || (int) $v["times"] <= 0) {
                        $this->tip($p, $action . "商品", "§c份数必须是正整数！", $back);
                        return;
                    }
                    $this->dispatch($p, "mebshop " . $mode . " " . $SID . " " . (int) $v["times"]);
                }
            )],
            ["§c返回上一级", $back],
        ]);
    }

    /**
     * 全部商品一览，纯文本，不区分类型
     */
    private function openShopAll(Player $player): void
    {
        $shop = Shop::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openShop($p);
        $shops = $shop->getAllShops();
        if ($shops === []) {
            $this->tip($player, $this->logo . "全部商品", "§e商店里还没有任何商品。", $back);
            return;
        }
        $text = "ID | 商品 | 类型 | 买价 | 卖价\n-----------------------------------------------\n";
        foreach ($shops as $SID => $config) {
            $SID = (int) $SID;
            $text .= $SID . " | " . $shop->getShopName($SID) . "§r | "
                . ($config["类型"] === Shop::TYPE_PREFIX ? "称号" : "物品") . " | "
                . ($config["可购买"] ? $shop->getTotalPrice($SID) : "§c不可买§r") . " | "
                . ($config["可出售"] ? $shop->getTotalPrice($SID, 1, false) : "§c不可卖§r") . "\n";
        }
        $this->openText($player, $this->logo . "全部商品", $text, $back);
    }

    /* ------------------------------------------------ 营地专属物品市场 */

    /**
     * 营地专属物品市场
     *
     * 和常规商品分开一个界面：专属物品的货源是玩家挂卖，
     * 存货为0时就买不到，混在常规商品列表里玩家会以为是bug。
     */
    private function openCampMarket(Player $player): void
    {
        $shop = Shop::getInstance($this->plugin);
        $item = CampsiteItem::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openShop($p);
        $entries = [];
        foreach ($item->getAll() as $level => $define) {
            $key = (string) $define["key"];
            $stock = $shop->getCampStock($key);
            $entries[] = [
                $define["name"] . "§r\n§7解锁Lv" . $level . " §a买" . $shop->getCampBuyPrice($key)
                    . " §b卖" . $shop->getCampSellPrice($key) . " §e存货" . $stock,
                fn(Player $p) => $this->openCampMarketDetail($p, $key),
            ];
        }
        $entries[] = ["§c返回上一级", $back];
        $this->menu(
            $player,
            $this->logo . "营地专属物品市场",
            "§7货源全部来自玩家挂卖，服务器不产出。\n§7营地升级后可从福利箱获得这些物品。",
            $entries
        );
    }

    /**
     * 单个专属物品的买卖
     */
    private function openCampMarketDetail(Player $player, string $key): void
    {
        $shop = Shop::getInstance($this->plugin);
        $item = CampsiteItem::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openCampMarket($p);
        $define = $item->getByKey($key);
        if ($define === null) {
            $this->tip($player, $this->logo . "营地专属物品", "§c未知的专属物品！", $back);
            return;
        }
        $playerName = strtolower($player->getName());
        $held = $item->countInInventory($player, $key);
        $content = "§e名称：§f" . $define["name"] . "§r\n"
            . "§e标识：§f" . $key . "\n"
            . "§e解锁所需营地等级：§f" . $define["level"] . "\n"
            . "§e购买单价：§f" . $shop->getCampBuyPrice($key) . "\n"
            . "§e出售单价：§f" . $shop->getCampSellPrice($key) . "\n"
            . "§e市场存货：§f" . $shop->getCampStock($key) . "\n"
            . "§e你持有：§f" . $held . "\n"
            . "§e你的游戏币：§f" . Economy::getInstance($this->plugin)->getMoney($playerName);

        $this->menu($player, $this->logo . "营地专属物品", $content, [
            ["§a购买指定数量", fn(Player $p) => $this->campMarketAmount($p, $key, "buy")],
            ["§b挂卖指定数量", fn(Player $p) => $this->campMarketAmount($p, $key, "sell")],
            ["§c返回上一级", $back],
        ]);
    }

    /**
     * 专属物品买卖的数量输入
     */
    private function campMarketAmount(Player $player, string $key, string $mode): void
    {
        $action = $mode === "sell" ? "挂卖" : "购买";
        $back = fn(Player $p) => $this->openCampMarketDetail($p, $key);
        $this->form(
            $player,
            $action . CampsiteItem::getInstance($this->plugin)->getName($key),
            ["num" => ["label" => "请输入" . $action . "数量：", "placeholder" => "1"]],
            function (Player $p, array $v) use ($key, $mode, $action, $back): void {
                if ($v["num"] === "" || !is_numeric($v["num"]) || (int) $v["num"] <= 0) {
                    $this->tip($p, $action . "营地专属物品", "§c数量必须是正整数！", $back);
                    return;
                }
                $this->dispatch($p, "mebshop camp " . $mode . " " . $key . " " . (int) $v["num"]);
            }
        );
    }

    /**
     * 商店管理
     */
    private function openShopManage(Player $player): void
    {
        $shop = Shop::getInstance($this->plugin);
        $back = fn(Player $p) => $this->openShopManage($p);
        $shops = $shop->getAllShops();
        $itemNum = count($shop->getShopsByType(Shop::TYPE_ITEM));
        $prefixNum = count($shop->getShopsByType(Shop::TYPE_PREFIX));
        $content = "§7商店商品的增删与开关\n"
            . "§e商品总数: §f" . count($shops)
            . " §7(§f物品§6" . $itemNum . " §7/ §f称号§d" . $prefixNum . "§7)\n"
            . "§eOP管理权限: " . ($shop->hasOpShop() ? "§a已开启" : "§c已关闭") . "\n"
            . "§7请选择要执行的操作:";

        $this->menu($player, "§2§l" . $this->logo . " §r§e商店管理", $content, [
            ["§a新增物品商品", fn(Player $p) => $this->form($p, "§a新增物品商品", [
                "item" => ["label" => "请输入物品名：", "placeholder" => "diamond"],
                "num" => ["label" => "请输入每份数量：", "placeholder" => "1"],
                "buy" => ["label" => "请输入每个物品的买价：", "placeholder" => "1000"],
                "sell" => ["label" => "请输入每个物品的卖价(填0则不可出售)：", "placeholder" => "500"],
            ], function (Player $p, array $v) use ($back): void {
                if ($v["item"] === "" || $v["num"] === "" || $v["buy"] === "") {
                    $this->tip($p, "新增物品商品", "§c物品名、数量和买价都要填！", $back);
                    return;
                }
                if (!is_numeric($v["num"]) || (int) $v["num"] <= 0) {
                    $this->tip($p, "新增物品商品", "§c数量必须是正整数！", $back);
                    return;
                }
                if (!is_numeric($v["buy"]) || (float) $v["buy"] < 0) {
                    $this->tip($p, "新增物品商品", "§c买价必须是非负数！", $back);
                    return;
                }
                $sell = $v["sell"] === "" ? "0" : $v["sell"];
                if (!is_numeric($sell) || (float) $sell < 0) {
                    $this->tip($p, "新增物品商品", "§c卖价必须是非负数！", $back);
                    return;
                }
                //物品名可能带空格(如white wool)，包一层引号交给指令，避免被拆成多个参数
                $this->dispatch($p, "mebshop additem " . $this->quoteArg(trim($v["item"])) . " " . (int) $v["num"] . " " . $v["buy"] . " " . $sell);
            }), 0, "textures/items/diamond"],
            ["§d新增称号商品", fn(Player $p) => $this->form($p, "§d新增称号商品", [
                "prefix" => ["label" => "请输入称号(支持§颜色)：", "placeholder" => "<prefix>"],
                "buy" => ["label" => "请输入价格：", "placeholder" => "5000"],
            ], function (Player $p, array $v) use ($back): void {
                if ($v["prefix"] === "" || $v["buy"] === "") {
                    $this->tip($p, "新增称号商品", "§c称号和价格都要填！", $back);
                    return;
                }
                if (!is_numeric($v["buy"]) || (float) $v["buy"] < 0) {
                    $this->tip($p, "新增称号商品", "§c价格必须是非负数！", $back);
                    return;
                }
                //称号也可能带空格，同样包一层引号
                $this->dispatch($p, "mebshop addpre " . $this->quoteArg(trim($v["prefix"])) . " " . $v["buy"]);
            }), 0, "textures/items/name_tag"],
            ["§4删除商品", function (Player $p) use ($shop, $back): void {
                $options = $this->getShopOptions($shop->getAllShops());
                if ($options === []) {
                    $this->tip($p, "删除商品", "§c商店里还没有任何商品。", $back);
                    return;
                }
                $this->form($p, "§4删除商品", [
                    "SID" => [
                        "label" => "请选择要删除的商品：",
                        "kind" => "dropdown",
                        "options" => array_values($options),
                        //商品被删掉后ID会留空档，所以要取真正的商品ID，不能用下拉框下标
                        "values" => array_keys($options),
                    ],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebshop del " . $v["SID"]);
                });
            }, 0, "textures/ui/minus"],
            ["§6开关商品的购买/出售", function (Player $p) use ($shop, $back): void {
                $options = $this->getShopOptions($shop->getAllShops());
                if ($options === []) {
                    $this->tip($p, "开关商品", "§c商店里还没有任何商品。", $back);
                    return;
                }
                $this->form($p, "§6开关商品的购买/出售", [
                    "SID" => [
                        "label" => "请选择商品：",
                        "kind" => "dropdown",
                        "options" => array_values($options),
                        "values" => array_keys($options),
                    ],
                    "type" => [
                        "label" => "请选择要开关的类型：",
                        "kind" => "dropdown",
                        "options" => ["购买", "出售"],
                        "values" => ["buy", "sell"],
                    ],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebshop switch " . $v["SID"] . " " . $v["type"]);
                });
            }, 0, "textures/ui/check"],
            ["§b设置商品图标", function (Player $p) use ($shop, $back): void {
                $options = $this->getShopOptions($shop->getAllShops());
                if ($options === []) {
                    $this->tip($p, "设置商品图标", "§c商店里还没有任何商品。", $back);
                    return;
                }
                $this->form($p, "§b设置商品图标", [
                    "SID" => [
                        "label" => "请选择商品：",
                        "kind" => "dropdown",
                        "options" => array_values($options),
                        "values" => array_keys($options),
                    ],
                    "type" => [
                        "label" => "请选择图标类型：",
                        "kind" => "dropdown",
                        "options" => ["自动推导", "材质路径", "网络图片"],
                        "values" => ["auto", "path", "url"],
                    ],
                    "address" => ["label" => "图标地址(选自动推导时可留空)：", "placeholder" => "textures/items/diamond"],
                ], function (Player $p, array $v) use ($back): void {
                    if ($v["type"] !== "auto" && $v["address"] === "") {
                        $this->tip($p, "设置商品图标", "§c选择材质路径或网络图片时必须填地址！", $back);
                        return;
                    }
                    //地址带空格会被指令拆成多个参数，直接挡住
                    if (strpos($v["address"], " ") !== false) {
                        $this->tip($p, "设置商品图标", "§c图标地址里不能有空格！", $back);
                        return;
                    }
                    $this->dispatch($p, "mebshop icon " . $v["SID"] . " " . $v["type"] . " " . $v["address"]);
                });
            }, 0, "textures/items/painting"],
            ["§e切换op管理商店的权限", fn(Player $p) => $this->dispatch($p, "mebshop opshop"), 0, "textures/items/command_block"],
            ["§c返回上一级", fn(Player $p) => $this->openShop($p), 0, "textures/ui/arrow_left"],
        ]);
    }

    /**
     * 把商品表转成"商品ID => 下拉框显示文字"，键保留真正的商品ID
     */
    private function getShopOptions(array $shops): array
    {
        $shop = Shop::getInstance($this->plugin);
        $options = [];
        foreach ($shops as $SID => $config) {
            $SID = (int) $SID;
            $options[$SID] = $SID . " - " . $shop->getShopName($SID);
        }
        return $options;
    }

    /* ---------------------------------------------------------------- 帮助界面 */

    /**
     * 帮助主界面，展示所有系统的帮助入口
     */
    public function openHelp(Player $player): void
    {
        $this->menu($player, "§e§l" . $this->logo . " §r§6帮助中心", "§7选择要查看帮助的系统:", [
            ["§6经济系统帮助", fn(Player $p) => $this->openHelpMoney($p), 0, "textures/items/gold_ingot"],
            ["§a营地系统帮助", fn(Player $p) => $this->openHelpCampsite($p), 0, "textures/items/bed_red"],
            ["§d同居系统帮助", fn(Player $p) => $this->openHelpCohabitant($p), 0, "textures/items/heart"],
            ["§b称号系统帮助", fn(Player $p) => $this->openHelpPrefix($p), 0, "textures/items/name_tag"],
            ["§3多世界系统帮助", fn(Player $p) => $this->openHelpMultiWorld($p), 0, "textures/items/compass_item"],
            ["§4OP系统帮助", fn(Player $p) => $this->openHelpOp($p), 0, "textures/items/command_block"],
            ["§5VIP系统帮助", fn(Player $p) => $this->openHelpVip($p), 0, "textures/items/diamond"],
            ["§2商店系统帮助", fn(Player $p) => $this->openHelpShop($p), 0, "textures/items/emerald"],
            ["§c关闭", function(Player $p) {}, 0, "textures/ui/cancel"],
        ]);
    }

    private function openHelpMoney(Player $player): void
    {
        $text = "§e§l经济系统指令帮助\n\n";
        $text .= "§6> §f/money my §7- 查看自己的游戏币\n";
        $text .= "§6> §f/money get <玩家名> §7- 查看某人的游戏币\n";
        $text .= "§6> §f/money add <玩家名> <数量> §7- 增加某人的游戏币\n";
        $text .= "§6> §f/money remove <玩家名> <数量> §7- 减少某人的游戏币\n";
        $text .= "§6> §f/money pay <玩家名> <数量> §7- 支付游戏币给某人\n";
        $text .= "§6> §f/money ranking §7- 查看游戏币排行榜\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§6经济系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpCampsite(Player $player): void
    {
        $text = "§a§l营地系统指令帮助\n\n";
        $text .= "§a基础操作:\n";
        $text .= "§6> §f/campsite create <营地名> §7- 创建营地\n";
        $text .= "§6> §f/campsite join <营地ID> §7- 申请加入营地\n";
        $text .= "§6> §f/campsite quit §7- 退出营地\n";
        $text .= "§6> §f/campsite search [营地ID] §7- 查询营地信息\n";
        $text .= "§7营地传送已改用营地领地的传送功能，见 §f/ter gui\n\n";
        $text .= "§a管理操作(市长权限):\n";
        $text .= "§6> §f/campsite call §7- 召集营地成员\n";
        $text .= "§6> §f/campsite accept <玩家名|all> §7- 同意入营申请\n";
        $text .= "§6> §f/campsite disagree <玩家名|all> §7- 拒绝入营申请\n";
        $text .= "§6> §f/campsite post <玩家名> <职称> §7- 设置成员职称\n";
        $text .= "§6> §f/campsite power <add|remove> <玩家名> <权力ID> §7- 管理权限\n";
        $text .= "§6> §f/campsite out <玩家名> §7- 踢出成员\n";
        $text .= "§6> §f/campsite transfer <玩家名> §7- 转让营地\n";
        $text .= "§6> §f/campsite disband §7- 解散营地\n\n";
        $text .= "§a营地升级:\n";
        $text .= "§6> §f/campsite donate <金额> §7- 向捐献池捐钱\n";
        $text .= "§6> §f/campsite pool §7- 查看捐献池\n";
        $text .= "§6> §f/campsite upgrade §7- 升级营地(市长)\n";
        $text .= "§6> §f/campsite welfare §7- 查看福利箱\n";
        $text .= "§6> §f/campsite claim §7- 领取本周福利\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§a营地系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpCohabitant(Player $player): void
    {
        $text = "§d§l同居系统指令帮助\n\n";
        $text .= "§6> §f/cohabitant propose <玩家名> §7- 向某人申请同居\n";
        $text .= "§6> §f/cohabitant transfer §7- 传送到同居对象身边\n";
        $text .= "§6> §f/cohabitant divorce §7- 解除同居关系\n";
        $text .= "§6> §f/cohabitant opdiv <玩家名> §7- 强制解除某人的同居(OP)\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§d同居系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpPrefix(Player $player): void
    {
        $text = "§b§l称号系统指令帮助\n\n";
        $text .= "§6> §f/mebpre list §7- 查看我的称号列表\n";
        $text .= "§6> §f/mebpre change <称号ID> §7- 更换使用的称号\n";
        $text .= "§6> §f/mebpre add <玩家名> <称号> §7- 给予玩家称号(OP)\n";
        $text .= "§6> §f/mebpre del <玩家名> <称号ID> §7- 回收玩家称号(OP)\n";
        $text .= "§6> §f/mebpre oppre §7- 切换OP管理称号的权限\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§b称号系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpMultiWorld(Player $player): void
    {
        $text = "§3§l多世界系统指令帮助\n\n";
        $text .= "§6> §f/mw go <世界名> §7- 传送到指定世界\n";
        $text .= "§6> §f/mw transfer <世界名> [x] [y] [z] §7- 定点传送\n";
        $text .= "§6> §f/mw info <世界名> §7- 查询世界信息\n";
        $text .= "§6> §f/mw list §7- 查看所有世界\n";
        $text .= "§6> §f/mw load <世界名> §7- 加载世界(OP)\n";
        $text .= "§6> §f/mw unload <世界名> §7- 卸载世界(OP)\n";
        $text .= "§6> §f/mw setinfo <世界名> <描述> §7- 设置世界描述(OP)\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§3多世界系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpOp(Player $player): void
    {
        $text = "§4§lOP系统指令帮助\n\n";
        $text .= "§6> §f/mebop add <玩家名> §7- 添加OP\n";
        $text .= "§6> §f/mebop del <玩家名> §7- 删除OP\n";
        $text .= "§6> §f/mebop list §7- 查看所有OP\n";
        $text .= "§6> §f/mebop licmd <指令名> §7- 禁用指令\n";
        $text .= "§6> §f/mebop unlicmd <指令名> §7- 取消禁用指令\n";
        $text .= "§6> §f/mebop gm §7- 一键切换生存/创造模式\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§4OP系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpVip(Player $player): void
    {
        $text = "§5§lVIP系统指令帮助\n\n";
        $text .= "§6VIP特权:\n";
        $text .= "§6> §f/mebvip day [玩家名] §7- 查看VIP剩余天数\n";
        $text .= "§6> §f/mebvip sign §7- 每日签到\n";
        $text .= "§6> §f/mebvip fly §7- 切换飞行模式\n";
        $text .= "§6> §f/mebvip color <颜色代码> §7- 设置聊天颜色\n";
        $text .= "§6> §f/mebvip transfer <玩家名> §7- 传送到玩家身边\n\n";
        $text .= "§dSVIP特权:\n";
        $text .= "§6> §f/mebsvip day [玩家名] §7- 查看SVIP剩余天数\n";
        $text .= "§6> §f/mebsvip sign §7- 每日签到\n";
        $text .= "§6> §f/mebsvip fly §7- 切换飞行模式\n";
        $text .= "§6> §f/mebsvip color <颜色代码> §7- 设置聊天颜色\n";
        $text .= "§6> §f/mebsvip transfer <玩家名> §7- 强制传送玩家\n\n";
        $text .= "§4管理指令(OP):\n";
        $text .= "§6> §f/mebvip set <玩家名> <天数> §7- 设置VIP天数(-1永久/0取消)\n";
        $text .= "§6> §f/mebsvip set <玩家名> <天数> §7- 设置SVIP天数\n";
        $text .= "§6> §f/mebvip opvip §7- 切换OP管理VIP权限\n";
        $text .= "§6> §f/mebsvip opsvip §7- 切换OP管理SVIP权限\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§5VIP系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }

    private function openHelpShop(Player $player): void
    {
        $text = "§2§l商店系统指令帮助\n\n";
        $text .= "§a购买与出售:\n";
        $text .= "§6> §f/mebshop buy <商品ID> [份数] §7- 购买物品\n";
        $text .= "§6> §f/mebshop sell <商品ID> [份数] §7- 出售物品\n";
        $text .= "§6> §f/mebshop list §7- 查看商品列表\n\n";
        $text .= "§a营地专属物品市场:\n";
        $text .= "§6> §f/mebshop camp buy <物品key> <数量> §7- 购买专属物品\n";
        $text .= "§6> §f/mebshop camp sell <物品key> <数量> §7- 挂卖专属物品\n\n";
        $text .= "§4管理指令(OP/最高权限):\n";
        $text .= "§6> §f/mebshop additem <物品名> <数量> <买价> <卖价> §7- 添加物品\n";
        $text .= "§6> §f/mebshop addpre <称号> <价格> §7- 添加称号商品\n";
        $text .= "§6> §f/mebshop del <商品ID> §7- 删除商品\n";
        $text .= "§6> §f/mebshop switch <商品ID> <buy|sell> §7- 开关购买/出售\n";
        $text .= "§6> §f/mebshop icon <商品ID> <auto|path|url> [地址] §7- 设置图标\n";
        $text .= "§6> §f/mebshop opshop §7- 切换OP管理商店权限\n\n";
        $text .= "§e提示: §7也可以使用 §a/mebui §7打开图形界面操作";
        $this->openText($player, "§2商店系统帮助", $text, fn(Player $p) => $this->openHelp($p));
    }
}
