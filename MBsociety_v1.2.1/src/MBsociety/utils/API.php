<?php
/**
 * UI模板
 * MB系列
 * */
namespace MBsociety\utils;

use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use MBsociety\Main as main;

class API
{
    const MAIN = 10000;
	const Sell_Main = 6000;
	const Purchase_Main = 8000;
	public function __construct($owner)
	{
		$this->owner = $owner;
	}
    public function myUI($id, $player)
    {
        switch($id)
        {
            case 10000:
                $json=$this->Main();
                break;
            case 6000:
                $json=$this->sellmain();
                break;
            case 8000:
                $json=$this->purchasemain();
                break;
            case 4000:
                $json=$this->prefixmain();
                break;
        }
	    $pk = new ModalFormRequestPacket();
        $pk->formId = $id;
        $pk->formData = $json;
        $player->dataPacket($pk);
    }
    public function Main()
    {
		$data=[];
		$data["type"]="form";
		$data["title"]="§b===§bGUI商店系统§b===";//最上面
		$data["content"]="§e您是要...";
		$first["text"]="§a点击进入§6出售商店(卖)";
		$data["buttons"][]=$first;

		$sec["text"]="§a点击进入§b购买商店(买)";
		$data["buttons"][]=$sec;

		$th["text"]="§a点击进入§e称号商店";
		$data["buttons"][]=$th;
		
		$json = $this->getEncodedJson($data);//不能少
		return $json;//不能少
    }
	public function sellmain()
	{
		$data=[];
		$data["type"]="form";
		$data["title"]="§l§b===§l§6GUI出售商店§l§b===";//最上面
		$data["content"]="§e§m选择你要出售的物品吧";
		$sellcfg=main::getInstance()->getSellcfg();
		foreach($sellcfg as $key=>$v){
		    $text=$v["text"];
		    $type=$v["type"];
		    $datas=$v["data"];
		    $first["text"]="$text";
		    $first["image"]["type"] = "$type";
		    $first["image"]["data"] = "$datas";
		    $data["buttons"][]=$first;
		}
		$json = $this->getEncodedJson($data);
		return $json;//不能少
	}
	public function purchasemain()
	{
		$data=[];
		$data["type"]="form";
		$data["title"]="§l§b===§l§dGUI购买商店§l§b===";//最上面
		$data["content"]="§e§m选择你喜欢的物品买下吧";
		$purcfg=main::getInstance()->getPurcfg();
		foreach($purcfg as $key=>$v){
		    $text=$v["text"];
		    $type=$v["type"];
		    $datas=$v["data"];
		    $first["text"]="$text";
		    $first["image"]["type"] = "$type";
		    $first["image"]["data"] = "$datas";
		    $data["buttons"][]=$first;
		}
		$json = $this->getEncodedJson($data);//不能少
		return $json;//不能少
	}
	public function prefixmain()
	{
		$data=[];
		$data["type"]="form";
		$data["title"]="§l§b===§l§eGUI称号商店§l§b===";
		$data["content"]="§2§m选择你喜欢的称号买下吧";
		$precfg=main::getInstance()->getPrecfg();
		foreach($precfg as $key=>$v){
		    $prefix=$v["prefix"];
		    $zf=array(
		        "{称号}",
		        );
		    $zfs=array(
		        "$prefix",
		        );
		    $va=str_replace($zf,$zfs,$v);
		    $text=$va["text"];
		    $first["text"]="$text";
		    $data["buttons"][]=$first;
		}
		$json = $this->getEncodedJson($data);//不能少
		return $json;//不能少
	}
	public function getOwner()
	{
		return $this->owner;
	}
	public function getEncodedJson($data)
	{
		return json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE);
	}
}