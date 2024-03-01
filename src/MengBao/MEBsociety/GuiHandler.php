<?php

namespace MengBao\MEBsociety;

use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;

use MengBao\MEBsociety\Tools\MEBWidgets\MEBInput;
use MengBao\MEBsociety\Tools\MEBWidgets\MEBButton;
use MengBao\MEBsociety\Tools\MEBWidgets\MEBToggle;
use MengBao\MEBsociety\Tools\MEBWidgets\MEBDropdown;
use MengBao\MEBsociety\Tools\MEBWindows\MEBForm;
use MengBao\MEBsociety\Tools\MEBWindows\MEBCustomForm;
use MengBao\MEBsociety\Tools\MEBWindows\MEBConfirmForm;

class GuiHandler
{
    private string $logo = "[MEBS]";
    const MAIN = 00000;
    const CONFIRM = 00001;
    const ONE_V_INPUT_FORM = 00002;  //单参数输入窗口
    const TEXT_FORM = 00003;  //文本提示窗口
    const TWO_V_INPUT_FORM = 00004;  //双参数输入窗口
    const TWO_D_ONE_I_FORM = 00005;  //2个下拉框+1个输入框窗口
    const ONE_DROPDOWN_FORM = 00006;  //单下拉框窗口
    const TOGGLE_FORM = 00007;  //开关窗口
    const MONEY = 10000;
    const CAMPSITE = 20000;
    const CAMPSITE_MANAGE = 20001;
    const CAMPSITE_APPLICATION = 20002;
    const COHABITANT = 30000;
    const MEBPRE = 40000;
    const MW = 50000;
    const MEBOP = 60000;
    const MEBVIP = 70000;
    const VIPPRIVILEGE = 70001;
    const SVIPPRIVILEGE = 70002;
    const SHOP = 80000;

    public function handle(int $id, Player $player, string $title = "", string $content = "", string|array $text = "", string|array $placehoder = ""): void
    {
        switch ($id) {
            case self::MAIN:
                $json = $this->Main();
                break;
            case self::CONFIRM:
                $json = $this->Confirm($title, $content, $text);
                break;
            case self::ONE_V_INPUT_FORM:
                $json = $this->OneVInputForm($title, $text, $placehoder);
                break;
            case self::TEXT_FORM:
                $json = $this->TextForm($title, $content);
                break;
            case self::TWO_V_INPUT_FORM:
                $json = $this->TwoVInputForm($title, $text, $placehoder);
                break;
            case self::TWO_D_ONE_I_FORM:
                $json = $this->TwoDOneIForm($title, $text, $placehoder);
                break;
            case self::ONE_DROPDOWN_FORM:
                $json = $this->OneDropdownForm($title, $text, $placehoder);
                break;
            case self::TOGGLE_FORM:
                $json = $this->ToggleForm($title, $text);
                break;
            case self::MONEY:
                $json = $this->Money();
                break;
            case self::CAMPSITE:
                $json = $this->Campsite();
                break;
            case self::CAMPSITE_MANAGE:
                $json = $this->CampsiteManage();
                break;
            case self::CAMPSITE_APPLICATION:
                $json = $this->CampsiteApplication($text);
                break;
            case self::COHABITANT:
                $json = $this->Cohabitant();
                break;
            case self::MEBPRE:
                $json = $this->Mebpre();
                break;
            case self::MW:
                $json = $this->Mw();
                break;
            case self::MEBOP:
                $json = $this->Mebop();
                break;
            case self::MEBVIP:
                $json = $this->Mebvip();
                break;
            case self::VIPPRIVILEGE:
                $json = $this->vip();
                break;
            case self::SVIPPRIVILEGE:
                $json = $this->svip();
                break;
            case self::SHOP:
                //$json = $this->Shop();
                //break;
            default:
                $json = $this->TextForm($this->logo . "GUI导航", "未找到页面: " . $id);
        }
        $pk = new ModalFormRequestPacket();
        $pk->formId = $id;
        $pk->formData = $json;
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /**
     * 主界面，/mebui唤出
     */
    public function Main()
	{
        $buttonTexts = ["经济系统", "营地系统", "同居系统", "称号系统", "多世界系统", "op系统", "vip系统", "商店系统"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * 确认窗口
     */
    public function Confirm(string $title = "", string $content = "", array $text = []): bool|string
    {
        $form = new MEBConfirmForm($title, $content, $text);
		return $form->getForm();
    }

    /**
     * 单参数输入窗口
     */
    public function OneVInputForm(string $title = "", string $text = "", string $placehoder = ""): bool|string
    {
        $input = new MEBInput($text, $placehoder);
        $form = new MEBCustomForm($title, [$input]);
        return $form->getForm();
    }

    /**
     * 文本显示窗口
     */
    public function TextForm(string $title = "", string $content = ""): bool|string
    {
        $form = new MEBForm($title, $content, []);
        return $form->getForm();
    }

    /**
     * 双参数输入窗口
     */
    public function TwoVInputForm(string $title = "", array $text = [], array $placehoder = []): bool|string
    {
        $input = array();
        foreach($text as $key => $value)
            $input[$key] = new MEBInput($value, $placehoder[$key]);
        $form = new MEBCustomForm($title, $input);
        return $form->getForm();
    }

    /**
     * 下拉框+输入框窗口
     */
    public function TwoDOneIForm(string $title = "", array $text = [], array $placeholder = []): bool|string
    {
        $widget = [];
        foreach($placeholder as $key => $value){
            if (is_array($value))  //是下拉框
                $widget[$key] = new MEBDropdown($text[$key], $value);
            else
                $widget[$key] = new MEBInput($text[$key], $value);
        }
        $form = new MEBCustomForm($title, $widget);
        return $form->getForm();
    }


    /**
     * 单下拉框窗口
     */
    public function OneDropdownForm(string $title = "", string $text = "", array $placeholder = []): bool|string
    {
        $dropdown = new MEBDropdown($text, $placeholder);
        $form = new MEBCustomForm($title, [$dropdown]);
		return $form->getForm();
    }


    /**
     * 开关窗口
     */
    public function ToggleForm(string $title = "", array $text = []): bool|string
    {
        $toggle = [];
        foreach($text as $key => $toggleText)
            $toggle[$key] = new MEBToggle($toggleText);
        $form = new MEBCustomForm($title, $toggle);
		return $form->getForm();
    }

    /**
     * 经济系统界面
     */
    public function Money(): bool|string
	{
        $buttonTexts = ["查看自己的游戏币", "查看某人的游戏币", "增加某人的游戏币", "减少某人的游戏币", "支付游戏币", "查看游戏币排行榜", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText);
		$form = new MEBForm($this->logo . "经济系统导航", "请选择：", $buttonArray);
		return $form->getForm();
	}

    /**
     * 营地系统界面
     */
    public function Campsite(): bool|string
	{
        $buttonTexts = ["创建营地", "加入营地", "营地传送", "营地查询", "营地管理", "退出营地", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "营地系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * 营地管理系统界面
     */
    public function CampsiteManage(): bool|string
	{
        $buttonTexts = ["设置营地传送点", "营地召集", "管理入营申请", "管理营地职称", "管理营地权力","踢人","转让营地","解散营地", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "营地管理系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * 入营申请管理界面
     */
    public function CampsiteApplication(array $text): bool|string
	{
        $playerNameArray = array();
        foreach ($text as $key => $name)
            $playerNameArray[$key] = "Player: " . $name;
        $buttonTexts = array_merge(["全部同意", "全部拒绝", "§c返回上一级"], $playerNameArray);
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "入营申请管理系统", "点击玩家可以管理其入营申请！", $buttonArray);
		return $form->getForm();
	}

    /**
     * 同居系统界面
     */
    public function Cohabitant(): bool|string
	{
        $buttonTexts = ["申请同居", "同居传送", "强制解除同居", "解除同居", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "同居系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * 称号系统界面
     */
    public function Mebpre(): bool|string
	{
        $buttonTexts = ["查看我的称号", "更换使用称号", "给予称号", "回收称号", "管理称号权限", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "称号系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * 多世界系统界面
     */
    public function Mw(): bool|string
	{
        $buttonTexts = ["世界传送", "定点传送", "查看所有世界", "查询世界信息", "设置世界描述", "加载世界", "卸载世界", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "多世界系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * op管理系统界面
     */
    public function Mebop(): bool|string
	{
        $buttonTexts = ["新增op", "删除op", "查看所有op", "禁用指令", "取消禁用指令", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "op管理系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}
    
    /**
     * vip管理系统界面
     */
    public function Mebvip(): bool|string
	{
        $buttonTexts = ["查看所有vip", "查看所有svip", "vip特权", "svip特权", "切换op管理vip的权限", "切换op管理svip的权限", "设置vip天数", "设置svip天数", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "vip管理系统导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}

    /**
     * vip特权界面
     */
    public function vip(): bool|string
	{
        $buttonTexts = ["查看剩余天数", "每日签到", "模式切换", "设置聊天颜色", "vip传送", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "vip特权导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}
    
    /**
     * svip特权界面
     */
    public function svip(): bool|string
	{
        $buttonTexts = ["查看剩余天数", "每日签到", "模式切换", "设置聊天颜色", "svip强制传送", "§c返回上一级"];
        $buttonArray = array();
        foreach ($buttonTexts as $key => $buttonText)
            $buttonArray[$key] = new MEBButton($buttonText, "", "");
		$form = new MEBForm($this->logo . "svip特权导航", "请选择: ", $buttonArray);
		return $form->getForm();
	}
}