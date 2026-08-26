<?php

declare(strict_types=1);

namespace MengBao\MEBSociety;

use pocketmine\player\Player;

use MengBao\MEBForms\SimpleForm;
use MengBao\MEBForms\CustomForm;
use MengBao\MEBForms\ModalForm;

use MengBao\MEBSociety\Units\Players;
use MengBao\MEBSociety\Units\Economy;
use MengBao\MEBSociety\Units\Campsite;
use MengBao\MEBSociety\Units\MultiWorld;

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
     * 按钮界面
     *
     * $entries的每一项为 [按钮文字, 点击后执行的闭包]，
     * 按钮顺序就是数组顺序，回调里按下标取，不会出现对不上的情况。
     *
     * @param array<int, array{0: string, 1: \Closure}> $entries
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
            $form->addButton($entry[0]);
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
        $this->menu($player, $this->logo . "系统导航", "请选择: ", [
            ["经济系统", fn(Player $p) => $this->openMoney($p)],
            ["营地系统", fn(Player $p) => $this->openCampsite($p)],
            ["同居系统", fn(Player $p) => $this->openCohabitant($p)],
            ["称号系统", fn(Player $p) => $this->openPrefix($p)],
            ["多世界系统", fn(Player $p) => $this->openMultiWorld($p)],
            ["op系统", fn(Player $p) => $this->openOp($p)],
            ["vip系统", fn(Player $p) => $this->openVipManage($p)],
            ["商店系统", fn(Player $p) => $this->openText(
                $p,
                $this->logo . "商店系统",
                "§e商店系统还没做好，敬请期待。",
                fn(Player $p) => $this->openMain($p)
            )],
        ]);
    }

    /* ---------------------------------------------------------------- 经济系统 */

    public function openMoney(Player $player): void
    {
        $back = fn(Player $p) => $this->openMain($p);
        $this->menu($player, $this->logo . "经济系统导航", "请选择：", [
            ["查看自己的游戏币", fn(Player $p) => $this->dispatch($p, "money my")],
            ["查看某人的游戏币", fn(Player $p) => $this->form(
                $p,
                "游戏币查询",
                ["name" => ["label" => "请输入查询的玩家名：", "placeholder" => "<player_name>"]],
                function (Player $p, array $v): void {
                    if ($v["name"] === "") {
                        $this->tip($p, "游戏币查询", "§c未输入玩家名！", fn(Player $p) => $this->openMoney($p));
                        return;
                    }
                    $this->dispatch($p, "money get " . $v["name"]);
                }
            )],
            ["增加某人的游戏币", fn(Player $p) => $this->moneyChange($p, "add", "增加游戏币")],
            ["减少某人的游戏币", fn(Player $p) => $this->moneyChange($p, "remove", "减少游戏币")],
            ["支付游戏币", fn(Player $p) => $this->moneyChange($p, "pay", "支付游戏币")],
            ["查看游戏币排行榜", function (Player $p): void {
                $ranking = Economy::getInstance($this->plugin)->getRanking();
                $text = "玩家 | 游戏币\n--------------------------------\n";
                foreach ($ranking as $name => $money) {
                    $text .= $name . " => " . $money . "\n";
                }
                $this->openText($p, "游戏币排行榜", $text, fn(Player $p) => $this->openMoney($p));
            }],
            ["§c返回上一级", $back],
        ]);
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
        $this->menu($player, $this->logo . "营地系统导航", "请选择: ", [
            ["创建营地", fn(Player $p) => $this->campsiteInput($p, "campsite create", "创建营地", "请输入营地名：", "<campsite_name>")],
            ["加入营地", fn(Player $p) => $this->campsiteInput($p, "campsite join", "加入营地", "请输入营地ID：", "<campsite_id>")],
            ["营地传送", fn(Player $p) => $this->dispatch($p, "campsite gohome")],
            ["营地查询", fn(Player $p) => $this->campsiteInput($p, "campsite search", "营地查询", "请输入营地ID：", "<campsite_id>")],
            ["营地管理", fn(Player $p) => $this->openCampsiteManage($p)],
            ["退出营地", fn(Player $p) => $this->dispatch($p, "campsite quit")],
            ["§c返回上一级", fn(Player $p) => $this->openMain($p)],
        ]);
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
                $this->tip($p, $title, "§c内容不能为空！", fn(Player $p) => $this->openCampsite($p));
                return;
            }
            $this->dispatch($p, $command . " " . $v["value"]);
        });
    }

    public function openCampsiteManage(Player $player): void
    {
        $this->menu($player, $this->logo . "营地管理系统导航", "请选择: ", [
            ["设置营地传送点", fn(Player $p) => $this->dispatch($p, "campsite sethome")],
            ["营地召集", fn(Player $p) => $this->dispatch($p, "campsite call")],
            ["管理入营申请", fn(Player $p) => $this->openCampsiteApplication($p)],
            ["管理营地职称", fn(Player $p) => $this->form($p, "设置营地职称", [
                "name" => ["label" => "请输入玩家名：", "placeholder" => "<player_name>"],
                "post" => ["label" => "请输入职称：", "placeholder" => "<post_name>"],
            ], function (Player $p, array $v): void {
                if ($v["name"] === "" || $v["post"] === "") {
                    $this->tip($p, "设置营地职称", "§c玩家名和职称都要填！", fn(Player $p) => $this->openCampsiteManage($p));
                    return;
                }
                $this->dispatch($p, "campsite post " . $v["name"] . " " . $v["post"]);
            })],
            ["管理营地权力", fn(Player $p) => $this->openCampsitePower($p)],
            ["踢人", fn(Player $p) => $this->campsiteManageInput($p, "campsite out", "踢人", "请输入玩家名：", "<player_name>")],
            ["转让营地", fn(Player $p) => $this->campsiteManageInput($p, "campsite transfer", "营地转让", "请输入玩家名：", "<player_name>")],
            ["解散营地", fn(Player $p) => $this->dispatch($p, "campsite disband")],
            ["§c返回上一级", fn(Player $p) => $this->openCampsite($p)],
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

    /* ---------------------------------------------------------------- 同居系统 */

    public function openCohabitant(Player $player): void
    {
        $this->menu($player, $this->logo . "同居系统导航", "请选择: ", [
            ["申请同居", fn(Player $p) => $this->cohabitantInput($p, "cohabitant propose", "申请同居")],
            ["同居传送", fn(Player $p) => $this->dispatch($p, "cohabitant transfer")],
            ["强制解除同居", fn(Player $p) => $this->cohabitantInput($p, "cohabitant opdiv", "强制解除同居")],
            ["解除同居", fn(Player $p) => $this->dispatch($p, "cohabitant divorce")],
            ["§c返回上一级", fn(Player $p) => $this->openMain($p)],
        ]);
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
        $this->menu($player, $this->logo . "称号系统导航", "请选择: ", [
            ["查看我的称号", fn(Player $p) => $this->dispatch($p, "mebpre list")],
            ["更换使用称号", fn(Player $p) => $this->openPrefixChange($p)],
            ["给予称号", fn(Player $p) => $this->prefixEdit($p, "add", "给予玩家称号")],
            ["回收称号", fn(Player $p) => $this->prefixEdit($p, "del", "回收玩家称号")],
            ["管理称号权限", fn(Player $p) => $this->dispatch($p, "mebpre oppre")],
            ["§c返回上一级", fn(Player $p) => $this->openMain($p)],
        ]);
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
        $this->menu($player, $this->logo . "多世界系统导航", "请选择: ", [
            ["世界传送", fn(Player $p) => $this->worldPick($p, "mw go", "世界传送", "请选择要传送的世界：")],
            ["定点传送", fn(Player $p) => $this->openWorldTransfer($p)],
            ["查看所有世界", function (Player $p) use ($back): void {
                $mw = MultiWorld::getInstance($this->plugin);
                $text = "世界名 | 是否已加载 | 在线玩家\n-----------------------------------------------\n";
                foreach ($mw->getAllWolrdName() as $name) {
                    $online = $mw->isWorldLoaded($name) ? $mw->getOnlineNum($name) : 0;
                    $text .= $name . " | " . $mw->getLoadInfo($name) . "§r | " . $online . "\n";
                }
                $this->openText($p, "世界名单", $text, $back);
            }],
            ["查询世界信息", fn(Player $p) => $this->worldPick($p, "mw info", "查询世界信息", "请选择要查询的世界：")],
            ["设置世界描述", fn(Player $p) => $this->openWorldSetInfo($p)],
            ["加载世界", fn(Player $p) => $this->worldPick($p, "mw load", "加载世界", "请选择要加载的世界：")],
            ["卸载世界", fn(Player $p) => $this->worldPick($p, "mw unload", "卸载世界", "请选择要卸载的世界：")],
            ["§c返回上一级", fn(Player $p) => $this->openMain($p)],
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
        $this->menu($player, $this->logo . "op管理系统导航", "请选择: ", [
            ["新增op", fn(Player $p) => $this->form($p, "新增op", [
                "name" => ["label" => "请输入玩家名：", "placeholder" => "<player_name>"],
            ], function (Player $p, array $v): void {
                if ($v["name"] === "") {
                    $this->tip($p, "新增op", "§c未输入玩家名！", fn(Player $p) => $this->openOp($p));
                    return;
                }
                $this->dispatch($p, "mebop add " . $v["name"]);
            })],
            ["删除op", function (Player $p): void {
                $ops = Players::getInstance($this->plugin)->getOps();
                if ($ops === []) {
                    $this->tip($p, "删除op", "§c服务器还没有op。", fn(Player $p) => $this->openOp($p));
                    return;
                }
                $this->form($p, "删除op", [
                    "name" => ["label" => "请选择要删除的op：", "kind" => "dropdown", "options" => array_values($ops)],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebop del " . $v["name"]);
                });
            }],
            ["查看所有op", function (Player $p) use ($back): void {
                $text = "OP | 状态\n----------------\n";
                foreach (Players::getInstance($this->plugin)->getOps() as $name) {
                    $text .= $name . " | " . (Players::getInstance($this->plugin)->isOnline($name) ? "§a在线" : "§c离线") . "§r\n";
                }
                $this->openText($p, "op名单", $text, $back);
            }],
            ["禁用指令", function (Player $p): void {
                $commands = array_keys($this->plugin->getServer()->getCommandMap()->getCommands());
                sort($commands);
                $this->form($p, "禁用指令", [
                    "cmd" => ["label" => "请选择要禁用的指令：", "kind" => "dropdown", "options" => $commands],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebop licmd " . $v["cmd"]);
                });
            }],
            ["取消禁用指令", function (Player $p): void {
                $limited = Players::getInstance($this->plugin)->getAllLimitedCmd();
                if ($limited === []) {
                    $this->tip($p, "取消禁用指令", "§c当前没有被禁用的指令。", fn(Player $p) => $this->openOp($p));
                    return;
                }
                //配置里存的是带"/"的，指令要的是不带"/"的
                $names = array_map(static fn(string $cmd): string => ltrim($cmd, "/"), array_values($limited));
                $this->form($p, "取消禁用指令", [
                    "cmd" => ["label" => "请选择要取消禁用的指令：", "kind" => "dropdown", "options" => $names],
                ], function (Player $p, array $v): void {
                    $this->dispatch($p, "mebop unlicmd " . $v["cmd"]);
                });
            }],
            ["§c返回上一级", fn(Player $p) => $this->openMain($p)],
        ]);
    }

    /* ---------------------------------------------------------------- vip系统 */

    public function openVipManage(Player $player): void
    {
        $back = fn(Player $p) => $this->openVipManage($p);
        $this->menu($player, $this->logo . "vip管理系统导航", "请选择: ", [
            ["查看所有vip", fn(Player $p) => $this->openVipList($p, true, $back)],
            ["查看所有svip", fn(Player $p) => $this->openVipList($p, false, $back)],
            ["vip特权", fn(Player $p) => $this->openVipPrivilege($p, true)],
            ["svip特权", fn(Player $p) => $this->openVipPrivilege($p, false)],
            ["切换op管理vip的权限", fn(Player $p) => $this->dispatch($p, "mebvip opvip")],
            ["切换op管理svip的权限", fn(Player $p) => $this->dispatch($p, "mebsvip opsvip")],
            ["设置vip天数", fn(Player $p) => $this->openVipSetDay($p, true)],
            ["设置svip天数", fn(Player $p) => $this->openVipSetDay($p, false)],
            ["§c返回上一级", fn(Player $p) => $this->openMain($p)],
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
     */
    private function openVipPrivilege(Player $player, bool $isVip): void
    {
        $command = $isVip ? "mebvip" : "mebsvip";
        $label = $isVip ? "vip" : "svip";
        $this->menu($player, $this->logo . $label . "特权导航", "请选择: ", [
            ["查看剩余天数", function (Player $p) use ($isVip, $command, $label): void {
                $vips = Players::getInstance($this->plugin)->getVips($isVip);
                if ($vips === []) {
                    $this->tip($p, "查看" . $label . "剩余天数", "§c服务器还没有" . $label . "。", fn(Player $p) => $this->openVipPrivilege($p, $isVip));
                    return;
                }
                $this->form($p, "查看" . $label . "剩余天数", [
                    "name" => ["label" => "请选择玩家：", "kind" => "dropdown", "options" => array_values($vips)],
                ], function (Player $p, array $v) use ($command): void {
                    $this->dispatch($p, $command . " day " . $v["name"]);
                });
            }],
            ["每日签到", fn(Player $p) => $this->dispatch($p, $command . " sign")],
            ["模式切换", fn(Player $p) => $this->dispatch($p, $command . " fly")],
            ["设置聊天颜色", function (Player $p) use ($isVip, $command): void {
                //颜色代号直接交给color子指令，不用guicolor那套"下标换代号"的绕法
                $colors = Players::getInstance($this->plugin)->getAllColor($isVip);
                $options = array_map(static fn(string $code): string => "§" . $code . $code, $colors);
                $this->form($p, "更换聊天颜色", [
                    "color" => ["label" => "请选择颜色：", "kind" => "dropdown", "options" => $options, "values" => $colors],
                ], function (Player $p, array $v) use ($command): void {
                    $this->dispatch($p, $command . " color " . $v["color"]);
                });
            }],
            [$isVip ? "vip传送" : "svip强制传送", function (Player $p) use ($isVip, $command): void {
                $title = $isVip ? "vip传送" : "svip强制传送";
                $online = Players::getInstance($this->plugin)->getAllOnlinePlayerName();
                $self = strtolower($p->getName());
                $online = array_values(array_filter($online, static fn(string $name): bool => $name !== $self));
                if ($online === []) {
                    $this->tip($p, $title, "§c没有其他在线玩家。", fn(Player $p) => $this->openVipPrivilege($p, $isVip));
                    return;
                }
                $this->form($p, $title, [
                    "name" => ["label" => "请选择玩家：", "kind" => "dropdown", "options" => $online],
                ], function (Player $p, array $v) use ($command): void {
                    $this->dispatch($p, $command . " transfer " . $v["name"]);
                });
            }],
            ["§c返回上一级", fn(Player $p) => $this->openVipManage($p)],
        ]);
    }
}
