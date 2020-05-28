<?php

namespace MBsociety;

use MBsociety\CallbackTask;
use MBsociety\utils\API;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\plugin\PluginBase;

use pocketmine\level\Position;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

use pocketmine\item\Item;

use pocketmine\utils\Config;

use pocketmine\Player;

use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener
{
	public static $instance;
	public function onLoad()
	{
		self::$instance=$this;
		$this->getLogger()->info("§c--------------------");
		$this->getLogger()->info("§aMBsociaty插件加载中...");
		$this->getLogger()->info("§a作者:梦宝(fanghao)");
		$this->getLogger()->info("§a欢迎加入我的QQ群:103442498。");
		$this->getLogger()->info("§c--------------------");
	}
	/**
	 * @return Main
	 */
	public static function getInstance(){
		return self::$instance;
	}
	public function onEnable()
	{
		$this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "sendsendtip"]), 9);
		@mkdir($this->getDataFolder(), 0777, true);
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, array(
			'版本(勿动)' => "1.2.1",
			'创建公会的金币' => 10000,
			'结婚需要的金币' => 2000,
			'OP不可使用的命令' => array("/op", "/deop"),
			'OP' => array(''),
			'最高权限' => '<填腐竹游戏名(请先删除此内容)>',
		));
		$this->bj = new Config($this->getDataFolder() . "信息开关.yml", Config::YAML, array(
			"MBsociety帮助" => true,
			"gui商店系统" => true,
			"底部显示" => true,
			"聊天显示" => true,
			"op管理系统" => true,
			"vip及其管理系统" => true,
			"称号系统" => true,
			"公会系统" => true,
			"婚姻系统" => true
		));
		$this->DBM = new Config($this->getDataFolder() . "底部信息.yml", Config::YAML, array(
			'底部信息' => "§f|§e在线人数:z §d游戏币:m §e物品ID:idw:ts §2数量:sl \n§f|§c权限:quanxian §b当前时间:dh:di:ds  §6当前地图:le\n§f|§e公会名字:ghna §d公会id:ghid §d情侣:lover",
			'注' => 'z=在线人数,lm=游戏币,idw=物品ID,ts=物品特殊值,sl=物品数量,quanxian=玩家权限,dh=获取当前小时,di=获取当前分钟,ds=获取当前秒钟,le=获取玩家所在地图,ghna=获取公会名字,ghid=获取公会数字id,lover=情侣,\n是换行'
		));
		$this->chatM = new Config($this->getDataFolder() . "聊天信息.yml", Config::YAML, array(
			'聊天信息' => "§e[{quanxian}§e]§r[{ghna}§r]§c{prefix}§a[§f{lover}§a]§7◆{playerna}§5>>> {color}{msg}",
			'注' => '{quanxian}=玩家权限,{ghna}=获取公会名字,{prefix}=获取玩家称号,{lover}=情侣,{playerna}=玩家名,{color}=玩家聊天颜色,{msg}=玩家聊天消息(必填项，不填则无法发出消息),\n是换行'
		));
		$this->api = new API($this);
		$this->sm = new Config($this->getDataFolder() . "Explain.txt", Config::YAML, array(
			"##Q:商店怎么设置" => "A:复制粘贴模板",
			"##Q:每个商店配置文件的最前面的数字是什么意思?" => "A:这是商店序号，必须，必须，必须按数字顺序填写，否则可能出现未知bug!!!(仅支持1-2000)",
			"##配置文件改动情况" => "公会配置文件改名为Guild.yml,数据迁移请自行解决"
		));
		$this->s = new Config($this->getDataFolder() . "sell.yml", Config::YAML, array(
			"1" => array(
				"text" => "§1钻石*100\n§e>>>§f§l点击出售§r",
				"id:damage" => "264:0",
				"count" => "100",
				"EachPrice" => "100",
				"type" => "path",
				"data" => "xxx"
			)
		));
		$this->pur = new Config($this->getDataFolder() . "purchase.yml", Config::YAML, array(
			"1" => array(
				"text" => "§1钻石*64\n§e>>>§f§l点击购买§r",
				"id:damage" => "264:0",
				"count" => "64",
				"EachPrice" => "100",
				"type" => "path",
				"data" => "xxxF"
			)
		));
		$this->preshop = new Config($this->getDataFolder() . "prefixshop.yml", Config::YAML, [
			"1" => array(
				"text" => "{称号}\n§e>>>§f§l点击购买§r",
				"EachPrice" => "100",
				"prefix" => "§b梦宝§a的§d粉丝§e哦"
			)
		]);
		$this->v = new Config($this->getDataFolder() . "vip.yml", Config::YAML, []);
		$this->vip = $this->v->getAll();
		$this->player = new Config($this->getDataFolder() . "player.yml", Config::YAML, []);
		$this->p = $this->player->getAll();
		$this->l = new Config($this->getDataFolder() . "love.yml", Config::YAML, []);
		$this->love = $this->l->getAll();
		$this->Guild = new Config($this->getDataFolder() . "Guild.yml", Config::YAML, []);
		$this->f = $this->Guild->getAll();
		$this->prefix = new Config($this->getDataFolder() . "prefix.yml", Config::YAML, []);
		$this->pre = $this->prefix->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info("§c--------------------------------");
		$this->getLogger()->info("§a正在检测配置文件版本");
		if (
			$this->config->get("版本(勿动)") !== $this->getDescription()->getVersion() or
			$this->config->get("版本(勿动)") == null
		) {
			$this->config->set("版本(勿动)", "1.2.1");
			$this->config->save();
			//rename($this->getDataFolder() . "信息开关.yml", $this->getDataFolder() . "信息开关_old.yml");
			$this->getLogger()->info("§c检测到你的配置文件不是最新版本，已为你自动更新配置文件,详见Explain.yml。");
			$this->getLogger()->info("§c--------------------------------");
		} else {
			$this->getLogger()->info("§a已是最新版本");
			$this->getLogger()->info("§c--------------------------------");
		}
	}
	public function onJoin(PlayerJoinEvent $event)
	{
		if (in_array($event->getPlayer()->getName(), $this->config->get("OP"))) {
			$event->getPlayer()->setOP(true);
			$this->getServer()->broadcastMessage("[MyOP]§e尊贵的§6OP管理员:§b" . $event->getPlayer()->getName() . "§6加入游戏!");
		}
	}
	public function onDisable()
	{
		$this->getLogger()->info("§c--------------------");
		$this->getLogger()->info("§eMBsociaty插件关闭，bye~");
		$this->getLogger()->info("§c--------------------");
	}
	public function onChat(PlayerChatEvent $event)
	{
		$daaa = $this->bj->get("聊天显示");
		if ($daaa) {
			$n = $event->getPlayer()->getName();
			$quanxian = $this->getRand($event->getPlayer());
			$ghna = $this->getGuildName($n);
			$prefix = $this->getPrefix($n);
			$lover = $this->getLover($n);
			$playerna = $event->getPlayer()->getName();
			$color = $this->getColor($n);
			$msg = $event->getMessage();
			$zfchat = array(
				"{quanxian}",
				"{ghna}",
				"{prefix}",
				"{lover}",
				"{playerna}",
				"{color}",
				"{msg}"
			);
			$zfschat = array(
				"$quanxian",
				"$ghna",
				"$prefix",
				"$lover",
				"$playerna",
				"$color",
				"$msg"
			);
			$chatmsg = str_replace($zfchat, $zfschat, $this->chatM->get("聊天信息"));
			$event->setCancelled(true);
			$this->getServer()->broadcastMessage("$chatmsg");
			unset($n, $daaa);
			return true;
		}
	}
	public function onCommandPreSend(PlayerCommandPreprocessEvent $event)
	{
		if ($event->getPlayer()->isOP()) {
			if (strpos($event->getMessage(), "/") !== 0) return; //“/"beginning？
			if ($event->getPlayer()->getName() !== $this->config->get("最高权限")) {
				$message = explode(" ", $event->getMessage());
				if (in_array($message[0], $this->config->get("OP不可使用的命令"))) {
					$event->setCancelled(true);
					$event->getPlayer()->sendMessage("§c你没有权限使用这个命令");
					unset($event, $message);
					return true;
				}
			}
		}
	}
	public function onPlayerJoin(PlayerJoinEvent $event)
	{
		$hc = "\n";
		$name = $event->getPlayer()->getName();
		if ($this->config->get($name) != null) {
			$event->getplayer->sendMessage(str_replace('《回车》', $hc, $this->config->get($name)));
			$this->config->set($name, null);
			$this->config->save();
		}
	}
	/**
	 * guild_name
	 */
	public function getGuild($name)
	{
		if ($this->hasGuild($name)) {
			return $this->p[$name]['Guild'];
		}
		return false;
	}
	/**
	 * guild_have?
	 */
	public function hasGuild($name)
	{
		return isset($this->p[$name]);
	}
	/**
	 * guild_onlinePlayer
	 */
	public function getOnlineMember($Guild)
	{
		$array = array();
		foreach ($this->getServer()->getOnlinePlayers() as $player) {
			$result = $this->getGuild($player->getName());
			if ($result !== false) {
				if ($result == $Guild) {
					$array[] = $player;
				}
			}
		}
		return $array;
	}
	/**
	 * guild_boss?
	 */
	public function isBoss($name)
	{
		if (isset($this->p[$name])) {
			$f = $this->p[$name]['Guild'];
			if ($this->f[$f]['owner'] == $name) {
				return true;
			}
		}
		return false;
	}
	public function isSameGuild($p1, $p2)
	{
		$p1 = $this->getGuild($p1);
		$p2 = $this->getGuild($p2);
		if ($p1 !== false) {
			if ($p2 !== false) {
				return $p1 == $p2;
			}
		}
		return false;
	}
	public  function isMarryed($name)
	{
		return isset($this->love[$name]);
	}
	public function getAdmins($Guild)
	{
		$array = array();
		foreach ($this->f[$Guild]['member'] as $key => $value) {
			foreach ($value as $player) {
				$array[] = $player;
			}
		}
		$array[] = $this->f[$Guild]['owner'];
		return $array;
	}
//	public function isAdmins($name)
//	{
//		$f = $this->p[$name]['Guild'];
//		return in_array($name, $this->getAdmins($f));
//	}
	public function getList($Guild, $page = 1)
	{
		asort($Guild);
		$list = ceil(count($Guild) / 5);
		if ($page >= $list) $page = $list;
		$r = "§c申请加入公会列表" . "< $page/$list > \n";
		$num = 0;
		foreach ($Guild as $k => $v) {
			$num++;
			if ($num + 5 > $page * 5 && $num <= $page * 5) {
				$r .= ">> $k \n";
			}
		}
		return $r;
	}
	public function getPrefixList($prefix, $page = 1)
	{
		$list = ceil(count($prefix) / 5);
		if ($page >= $list) $page = $list;
		$r = "§a你的所有称号" . "< $page/$list > \n";
		$num = 0;
		foreach ($prefix as $k => $v) {
			$num++;
			if ($num + 5 > $page * 5 && $num <= $page * 5) {
				$r .= "> $k : $v\n";
			}
		}
		return $r;
	}
	public function getRandNum()
	{
		$rand = mt_rand(1, 10000);
		if ($this->getGudieByNum($rand) !== false) {
			return $this->getRandNum();
		} else {
			return $rand;
		}
	}
	public function getGudieByNum($id)
	{
		$array = $this->getAllNum();
		return (!isset($array[$id])) ? false : $array[$id];
	}
	public function getAllNum()
	{
		$array = array();
		foreach ($this->f as $key => $value) {
			$array[$value['id']] = $key;
		}
		return $array;
	}
	public function getPrefix($name)
	{
		return isset($this->pre[$name]) ? "[{$this->pre[$name]['use']}§c]" : "";
	}
	public function getRand($player)
	{
		if ($player->isOP()) {
			return "OP";
		} elseif ($this->isSvip($player->getName())) {
			return "SVIP";
		} elseif ($this->isVip($player->getName())) {
			return "VIP";
		} else {
			return "普通玩家";
		}
	}
	public function getGuildName($name)
	{
		return isset($this->p[$name]) ? $this->p[$name]['Guild'] : "§c无公会§f";
	}
	public function getLover($name)
	{
		return isset($this->love[$name]) ? "{$this->love[$name]}§d的爱人§a" : "单身";
	}
	public function addPrefix($name, $prefix)
	{
		if (!isset($this->pre[$name])) {
			$this->pre[$name] = array(
				'prefix' => array(),
				'use' => '',
			);
		}
		$count = count($this->pre[$name]['prefix']);
		$this->pre[$name]['prefix'][$count + 1] = $prefix;
		$this->pre[$name]['use'] = $prefix;
		$this->prefix->setAll($this->pre);
		$this->prefix->save();
		unset($name, $prefix, $count);
	}
	public function isVip($name)
	{
		return isset($this->vip[$name]);
	}
	public function isSvip($name)
	{
		if ($this->isVip($name)) {
			return !is_numeric($this->vip[$name]['time']);
		}
	}
	public function getColor($name)
	{
		return ($this->isVip($name)) ? $this->vip[$name]['color'] : "§b";
	}
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		switch ($command->getName()) {
			case "mbhelp":
				if (!$this->bj->get("MBsociety帮助")) {
					$sender->sendMessage("§c腐竹未开启MBsociety帮助");
					unset($sender, $args, $command);
					return true;
				}
				$sender->sendMessage("§c---§bMBsociety指令帮助§c---");
				$sender->sendMessage("§e/公会--公会指令");
				$sender->sendMessage("§e/结婚--结婚指令");
				$sender->sendMessage("§e/称号--称号指令");
				$sender->sendMessage("§e/Ushop--UI商店指令");
				$sender->sendMessage("§e/myop--管理op指令");
				$sender->sendMessage("§e/opvip--管理vip指令");
				$sender->sendMessage("§e/vip--vip特权指令");
				$sender->sendMessage("§c------------------------------------");
				break;
			case "Ushop":
				if (!$this->bj->get("gui商店系统")) {
					$sender->sendMessage("§c腐竹未开启gui商店系统");
					unset($sender, $args, $command);
					return true;
				}
				if (!$sender instanceof Player) {
					$sender->sendMessage("后台禁止输入");
					return true;
				}
				$this->getAPI()->myUI(10000, $sender);
				return true;
				break;
			case "公会":
				if (!$this->bj->get("公会系统")) {
					$sender->sendMessage("§c腐竹未开启公会指令");
					unset($sender, $args, $command);
					return true;
				}
				if (!isset($args[0])) {
					$sender->sendMessage("§c*请输入'/公会 help'查看帮助");
					unset($sender, $args, $command);
					return true;
				}
				switch ($args[0]) {
					case "help":
						$sender->sendMessage("§e> /公会 创建 --- 创建公会");
						$sender->sendMessage("§e> /公会 设置家 --- 设置公会传送点");
						$sender->sendMessage("§e> /公会 回家 --- 传送到公会传送点");
						$sender->sendMessage("§e> /公会 转让 --- 转让公会");
						$sender->sendMessage("§e> /公会 传送 --- 公会传送");
						$sender->sendMessage("§e> /公会 解散 --- 公会解散");
						$sender->sendMessage("§e> /公会 申请 --- 申请加入");
						$sender->sendMessage("§e> /公会 接受 --- 同意进入公会");
						$sender->sendMessage("§e> /公会 拒绝 --- 拒绝进入公会");
						$sender->sendMessage("§e> /公会 职位 <公会长老/副会长> --- 公会职位设置");
						$sender->sendMessage("§e> /公会 信息 ---公会信息(公会id等等)");
						$sender->sendMessage("§e> /公会 踢人---会长踢人");
						unset($command, $args, $sender);
						return true;
						break;
					case "创建":

						if ($this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c你已经加入一个公会了");
							unset($command, $args, $sender);
							return true;
						}
						if (EconomyAPI::getInstance()->myMoney($sender) < $this->config->get("创建公会的金币")) {
							$sender->sendMessage("§c你没有足够的金币,总共需要" . $this->config->get("创建公会的金币"));
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§c用法:/公会 创建 <名字>");
							unset($command, $args, $sender);
							return true;
						}
						$args[1] = trim($args[1]);
						if (isset($this->f[$args[1]])) {
							$sender->sendMessage("§c这个公会已经存在,请换个名字");
							unset($command, $args, $sender);
							return true;
						}
						$num = $this->getRandNum();
						$this->f[$args[1]] = array(
							'owner' => $sender->getName(),
							'home' => 'none',
							'tp' => date("d") . ":3", //会长每日传送
							'member' => array('副会长' => array(), '公会长老' => array()),
							'id' => $num,
							'question' => array(),
						);
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						$this->p[$sender->getName()] = array(
							'Guild' => $args[1],
							'lv' => "会长",
						);
						$this->player->setAll($this->p);
						$this->player->save();
						$sender->sendMessage("§a成功创立" . $args[1] . "公会");
						$this->getServer()->broadcastMessage("§b玩家" . $sender->getName() . "§a创建了" . $args[1] . "§a公会,§e输入 /公会 申请 " . $num . " §e即可加入!");
						unset($command, $args, $sender);
						return true;
						break;
					case "设置家":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!$this->isBoss($sender->getName())) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						$this->f[$f]['home'] = (int) $sender->x . ":" . (int) $sender->y . ":" . (int) $sender->z . ":" . $sender->getLevel()->getFolderName();
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						$sender->sendMessage("§a成功设置当前坐标为公会基地");
						unset($command, $args, $sender);
						return true;
						break;
					case "回家":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						if ($this->f[$f]['home'] == "none") {
							$sender->sendMessage("§c此公会还没有设置家");
							unset($command, $args, $sender, $f);
							return true;
						}
						$f = explode(":", $this->f[$f]['home']);
						$level = $this->getServer()->getLevelByName($f[3]);
						if ($level !== false) {
							$sender->teleport(new Position($f[0], $f[1], $f[2], $level));
							$sender->sendMessage("§a成功传送");
						}
						unset($command, $args, $sender, $f);
						return true;
						break;
					case "转让":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!$this->isBoss($sender->getName())) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§c用法:/公会 转让 <玩家>");
							unset($command, $args, $sender);
							return true;
						}
						if ($args[1] == $sender->getName()) {
							$sender->sendMessage("§c你不能选择你自己");
							unset($command, $args, $sender);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player == null) {
							$sender->sendMessage("§c玩家不在线");
							unset($command, $args, $sender, $player);
							return true;
						}
						if (!$this->isSameGuild($sender->getName(), $player->getName())) {
							$sender->sendMessage("§c" . $player->getName() . "与你不在相同公会中");
							unset($command, $args, $sender, $player);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						$this->f[$f]['owner'] = $args[1];
						unset($this->p[$sender->getName()]);
						$sender->sendMessage("§a成功转让公会给" . $player->getName());
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						unset($command, $args, $sender, $player, $f);
						return true;
						break;
					case "踢人":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!in_array($this->p[$sender->getName()]['lv'], array("会长", "副会长"))) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cusgae:/公会 踢人 <玩家>");
							unset($sender, $args, $command);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if ($args[1] == $sender->getName()) {
							$sender->sendMessage("§c你不能选择你自己");
							unset($command, $sender, $args, $f, $player);
							return true;
						}
						if (!$this->isSameGuild($sender->getName(), $args[1])) {
							$sender->sendMessage("§c{$args[1]}不在与你用一个公会");
							unset($command, $sender, $args, $f, $player);
							return true;
						}
						$lv = $this->p[$args[1]]['lv'];
						if (!$this->isBoss($sender->getName())) {
							if (in_array($lv, array("会长,副会长"))) {
								$sender->sendMessage("§c权限不足,无法踢出");
								unset($command, $sender, $args, $f, $player);
								return true;
							}
						}
						if ($lv !== "普通成员") {
							unset($this->f[$f]['member'][$lv][array_search($args[1], $this->f[$f]['member'][$lv])]);
							$this->Guild->setAll($this->f);
							$this->Guild->save();
						}
						unset($this->p[$args[1]]);
						$this->player->setAll($this->p);
						$this->player->save();
						$sender->sendMessage("§a成功踢出{$args[1]}");
						if ($player !== null) {
							$player->sendMessage("§c你已经被{$sender->getName()}踢出了公会");
						}
						unset($command, $sender, $args, $f, $player, $lv);
						return true;
						break;
					case "申请":
						if ($this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c你已经加入一个公会了");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§c用法:/公会 申请 <公会号>");
							unset($command, $args, $sender);
							return true;
						}
						$id = $this->getGudieByNum($args[1]);
						if (!$id) {
							$sender->sendMessage("§c公会号不存在");
							unset($command, $args, $sender, $id);
							return true;
						}
						if (isset($this->f[$id]['question'][$sender->getName()])) {
							$sender->sendMessage("§c你已经发送过请求了");
							unset($command, $args, $sender, $id);
							return true;
						}
						$this->f[$id]['question'][$sender->getName()] = time();
						$sender->sendMessage("§a成功发送申请");
						foreach ($this->getAdmins($id) as $key) {
							$player = $this->getserver()->getPlayerExact($key);
							if ($player !== null) {
								$player->sendMessage("§a收到了{$sender->getName()}的入会申请,输入'/公会 同意 <名字>,即可批准");
							}
						}
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						unset($command, $args, $sender, $id, $player);
						return true;
						break;
					case "同意":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!in_array($this->p[$sender->getName()]['lv'], array("会长", "副会长"))) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cusgae:/公会 同意 <玩家>");
							unset($sender, $args, $command);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if (!isset($this->f[$f]['question'][$args[1]])) {
							$sender->sendMessage("§c没有找到{$args[1]}的请求");
							unset($sender, $args, $command, $player);
							return true;
						}
						if ($this->hasGuild($args[1])) {
							$sender->sendMessage("§c{$args[1]}已经加入一个公会了");
							unset($sender, $args, $command, $player);
							return true;
						}
						unset($this->f[$f]['question'][$args[1]]);
						$this->p[$args[1]] = array(
							'Guild' => $f,
							'lv' => '普通成员',
						);
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						$this->player->setAll($this->p);
						$this->player->save();
						$sender->sendMessage("§a成功同意了{$args[1]}加入公会");
						if ($player !== null) {
							$player->sendMessage("§a{$sender->getName()}已经同意了你的加入公会申请");
						}
						unset($sender, $args, $command, $player);
						return true;
						break;
					case "拒绝":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!in_array($this->p[$sender->getName()]['lv'], array("会长", "副会长"))) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cusgae:/公会 拒绝 <玩家>");
							unset($sender, $args, $command);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if (!isset($this->f[$f]['question'][$args[1]])) {
							$sender->sendMessage("§c没有找到{$args[1]}的请求");
							unset($sender, $args, $command, $player);
							return true;
						}
						unset($this->f[$f]['question'][$args[1]]);
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						$sender->sendMessage("§c成功拒绝了{$args[1]}的请求");
						unset($sender, $args, $command, $player);
						return true;
						break;
					case "列表":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!in_array($this->p[$sender->getName()]['lv'], array("会长", "副会长"))) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						if (!isset($args[1])) {
							$sender->sendMessage($this->getList($this->f[$f]['question']));
						} else {
							$sender->sendMessage($this->getList($this->f[$f]['question'], (int) $args[1]));
						}
						unset($sender, $args, $command);
						return true;
						break;
					case "信息":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						$sender->sendMessage("§c公会号:{$this->f[$f]['id']}");
						$sender->sendMessage("§c会长:{$this->f[$f]['owner']}");
						unset($command, $args, $sender, $f);
						return true;
						break;
					case "职位":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!$this->isBoss($sender->getName())) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						if (!isset($args[2])) {
							$sender->sendMessage("§cusgae:/公会 职位 <职位> <玩家>");
							unset($sender, $f, $args, $command);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[2]);
						if ($player !== null) {
							$args[2] = $player->getName();
						}
						if ($args[2] == $sender->getName()) {
							$sender->sendMessage("§c你不能选择你自己");
							unset($sender, $f, $args, $command, $player);
							return true;
						}
						if (!$this->isSameGuild($sender->getName(), $args[2])) {
							$sender->sendMessage("§c{$args[2]}不在与你用一个公会");
							unset($command, $sender, $args, $f, $player);
							return true;
						}
						switch ($args[1]) {
							case "副会长":
								if (in_array($args[2], $this->f[$f]['member']['副会长'])) {
									unset($this->f[$f]['member']['副会长'][array_search($args[2], $this->f[$f]['member']['副会长'])]);
									$this->Guild->setAll($this->f);
									$this->Guild->save();
									$sender->sendMessage("§a成功移除玩家{$args[2]}的{$args[1]}的权限");
									if ($player !== null) {
										$player->sendMessage("§c你被{$sender->getName()}取消了{$args[1]}的职位");
									}
									unset($command, $sender, $args, $f, $player);
									return true;
								}
								$count = count($this->f[$f]['member']['副会长']);
								if ($count <= 2) {
									$sender->sendMessage("§c副会长职位已经上限了");
									unset($command, $sender, $args, $f, $player, $count);
									return true;
								}
								$this->f[$f]['member']['副会长'][] = $args[2];
								$this->Guild->setAll($this->f);
								$this->Guild->save();
								$this->p[$args[2]]['lv'] = $args[1];
								$this->player->setAll($this->p);
								$this->player->save();
								$sender->sendMessage("§a成功将{$args[1]}的职位了{$args[2]}");
								if ($player !== null) {
									$player->sendMessage("§a{$sender->getName()}将{$args[1]}的职位了你");
								}
								return true;
								break;
							case "公会长老":
								if (in_array($args[2], $this->f[$f]['member']['公会长老'])) {
									unset($this->f[$f]['member']['公会长老'][array_search($args[2], $this->f[$f]['member']['公会长老'])]);
									$this->Guild->setAll($this->f);
									$this->Guild->save();
									$sender->sendMessage("§a成功移除玩家{$args[2]}的{$args[1]}的权限");
									if ($player !== null) {
										$player->sendMessage("§c你被{$sender->getName()}取消了{$args[1]}的职位");
									}
									unset($command, $sender, $args, $f, $player);
									return true;
								}
								$count = count($this->f[$f]['member']['副会长']);
								if ($count <= 2) {
									$sender->sendMessage("§c公会长老职位已经上限了");
									unset($command, $sender, $args, $f, $player, $count);
									return true;
								}
								$this->f[$f]['member']['公会长老'][] = $args[2];
								$this->Guild->setAll($this->f);
								$this->Guild->save();
								$this->p[$args[2]]['lv'] = $args[1];
								$this->player->setAll($this->p);
								$this->player->save();
								$sender->sendMessage("§a成功将{$args[1]}的职位了{$args[2]}");
								if ($player !== null) {
									$player->sendMessage("§a{$sender->getName()}将{$args[1]}的职位了你");
								}
								return true;
								break;
							default:
								$sender->sendMessage("§c职位输入错误,职位列表:副会长,公会长老");
								unset($command, $sender, $args, $f, $player);
								return true;
								break;
						}
						break;
					case "传送":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cusage:/公会 传送 <全体/接受/拒绝>");
							unset($sender, $args, $sender);
							return true;
						}
						switch ($args[1]) {
							case "全体":
								if (!$this->isBoss($sender->getName())) {
									$sender->sendMessage("§c你没有权限使用这个命令");
									unset($command, $args, $sender);
									return true;
								}
								$f = $this->getGuild($sender->getName());
								$i = explode(":", $this->f[$f]['tp']);
								if ($i[0] !== date("d")) {
									$this->f[$f]['tp'] = date("d") . ":3";
									$this->Guild->setAll($this->f);
								}
								$math = $i[1] - 1;
								if ($math <= 0) {
									$sender->sendMessage("§c你今天传送的次数已经到达上限");
									unset($command, $f, $args, $sender, $math);
									return true;
								}
								$this->f[$f]['tp'] = date("d") . ":" . $math;
								$this->Guild->setAll($this->f);
								$this->Guild->save();
								$x = 0;
								foreach ($this->getOnlineMember($f) as $key) {
									if ($key->getName() !== $sender->getName()) {
										$x++;
										$key->sendMessage("§c会长发出了传送请求,你可以输入'/公会 传送 接受',进行接受传送");
										$this->tpa[$key->getName()] = $sender->getName();
									}
								}
								$sender->sendMessage("§a成功发送{$x}条请求,请耐心等待");
								unset($command, $args, $sender, $f);
								return true;
								break;
							case "接受":
								if (!isset($this->tpa[$sender->getName()])) {
									$sender->sendMessage("§c你没有收到传送的请求");
									unset($command, $args, $sender);
									return true;
								}
								$player = $this->tpa[$sender->getName()];
								$player = $this->getServer()->getPlayerExact($player);
								if ($player == null) {
									$sender->sendMessage("§c会长不在线");
									unset($command, $args, $sender);
									return true;
								}
								$sender->teleport($player->getPosition());
								unset($this->tpa[$sender->getName()]);
								$sender->sendMessage("§a成功传送");
								unset($command, $args, $sender);
								return true;
								break;
							case "拒绝":
								if (!isset($this->tpa[$sender->getName()])) {
									$sender->sendMessage("§c你没有收到传送的请求");
									unset($command, $args, $sender);
									return true;
								}
								unset($this->tpa[$sender->getName()]);
								$sender->sendMessage("§a成功拒绝");
								unset($command, $args, $sender);
								return true;
								break;
							default:
								$sender->sendMessage("§c用法错误");
								unset($command, $args, $sender);
								return true;
								break;
						}
					case "解散":
						if (!$this->hasGuild($sender->getName())) {
							$sender->sendMessage("§c请先加入公会");
							unset($command, $args, $sender);
							return true;
						}
						if (!$this->isBoss($sender->getName())) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						$f = $this->getGuild($sender->getName());
						foreach ($this->getOnlineMember($f) as $key) {
							$key->sendMessage("§c你的公会已经被会长解散");
						}
						foreach ($this->p as $key => $value) {
							if ($value['Guild'] == $f) {
								unset($this->p[$key]);
							}
						}
						$this->player->setAll($this->p);
						$this->player->save();
						unset($this->f[$f]);
						$sender->sendMessage("§c成功解散公会");
						$this->Guild->setAll($this->f);
						$this->Guild->save();
						unset($command, $args, $sender, $f);
						return true;
						break;
				}
			case "结婚":
				if (!$this->bj->get("婚姻系统")) {
					$sender->sendMessage("§c腐竹未开启结婚指令");
					unset($sender, $args, $command);
					return true;
				}
				if (!isset($args[0])) {
					$sender->sendMessage("§c请输入'/结婚 help'查看所有帮助");
					unset($command, $sender, $args);
					return true;
				}
				switch ($args[0]) {
					case "help":
						$sender->sendMessage("§c结婚指令: ");
						$sender->sendMessage("§a> 求婚 : /结婚 求婚");
						$sender->sendMessage("§a> 离婚 : /结婚 离婚");
						$sender->sendMessage("§a> 传送 : /结婚 传送");
						$sender->sendMessage("§a> 接受 : /结婚 接受");
						$sender->sendMessage("§a> 拒绝 : /结婚 拒绝");
						unset($command, $sender, $args);
						return true;
						break;
					case "求婚":
						if (!isset($args[1])) {
							$sender->sendMessage("§c用法:/结婚 求婚 <玩家>");
							unset($command, $sender, $args);
							return true;
						}
						if (EconomyAPI::getInstance()->myMoney($sender) < $this->config->get("结婚需要的金币")) {
							$sender->sendMessage("§c你没有足够的金币.总共需要{$this->config->get("结婚需要的金币")}个金币");
							unset($command, $sender, $args);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player == null) {
							$sender->sendMessage("§c对方不在线");
							unset($command, $sender, $args, $player);
							return true;
						}
						if ($sender->getName() == $player->getName()) {
							$sender->sendMessage("§c你不能选择你自己");
							unset($command, $sender, $args, $player);
							return true;
						}
						if ($this->isMarryed($player->getName())) {
							$sender->sendMessage("§c他已经结婚过了");
							unset($command, $sender, $args, $player);
							return true;
						}
						if ($this->isMarryed($sender->getName())) {
							$sender->sendMessage("§c你已经结婚过了");
							unset($command, $sender, $args, $player);
							return true;
						}
						$this->lq[$player->getName()] = $sender->getName();
						$player->sendMessage("§e" . $sender->getName() . "跟你求婚啦,是否接受他?\n" . "§c> 输入'/结婚 接受' 或者 输入'/结婚 拒绝'");
						$sender->sendMessage("§a成功发送请求");
						unset($command, $sender, $args, $player);
						return true;
						break;
					case "接受":
						if (!isset($this->lq[$sender->getName()])) {
							$sender->sendMessage("§c没有人向你发送请求哦");
							unset($command, $sender, $args);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($this->lq[$sender->getName()]);
						if ($player == null) {
							$sender->sendMessage("§c他已经离开了");
							unset($command, $sender, $args, $player);
							return true;
						}
						if ($this->isMarryed($player->getName())) {
							$sender->sendMessage("§c他已经结婚过了");
							unset($command, $sender, $args, $player);
							return true;
						}
						if ($this->isMarryed($sender->getName())) {
							$sender->sendMessage("§c你已经结婚过了");
							unset($command, $sender, $args, $player);
							return true;
						}
						if (EconomyAPI::getInstance()->myMoney($player) < $this->config->get("结婚需要的金币")) {
							$sender->sendMessage("§c对方没有足够的金币");
							unset($command, $sender, $args, $player);
							return true;
						}
						$this->love[$sender->getName()] = $player->getName();
						$this->love[$player->getName()] = $sender->getName();
						$this->l->setAll($this->love);
						$this->l->save();
						$this->getServer()->broadcastMessage("§c恭喜{$player->getName()}向{$sender->getName()}求婚成功!~");
						unset($this->lq[$sender->getName()]);
						unset($command, $sender, $args, $player);
						return true;
						break;
					case "拒绝":
						if (!isset($this->lq[$sender->getName()])) {
							$sender->sendMessage("§c没有人向你发送请求哦");
							unset($command, $sender, $args);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($this->lq[$sender->getName()]);
						if ($player !== null) {
							$player->sendMessage("§c" . $sender->getName() . "拒绝了你的请求");
						}
						unset($this->lq[$sender->getName()]);
						$sender->sendMessage("§a成功拒绝");
						unset($command, $sender, $args, $player);
						return true;
						break;
					case "离婚":
						if (!$this->isMarryed($sender->getName())) {
							$sender->sendMessage("§c你还没有情侣呢");
							unset($command, $sender, $args);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($this->love[$sender->getName()]);
						if ($player !== null) {
							$player->sendMessage("§c" . $sender->getName() . "跟你离婚啦");
						}
						unset($this->love[$sender->getName()]);
						unset($this->love[$player->getName()]);
						$this->l->setAll($this->love);
						$this->l->save();
						$this->getServer()->broadcastMessage("§c{$sender->getName()}与{$player->getName()}感情不和,已经离婚了!~");
						unset($command, $sender, $args, $player);
						return true;
						break;
					case "传送":
						if (!$this->isMarryed($sender->getName())) {
							$sender->sendMessage("§c你还没有情侣呢");
							unset($command, $sender, $args);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($this->love[$sender->getName()]);
						if ($player == null) {
							$sender->sendMessage("§c你的情侣不在线");
							unset($command, $sender, $args, $player);
							return true;
						}
						$sender->teleport($player->getPosition());
						$sender->sendMessage("§a成功传送");
						unset($command, $sender, $args, $player);
						return true;
						break;
				}
				break;
			case "myop":
				if (!$this->bj->get("op管理系统")) {
					$sender->sendMessage("§c腐竹未开启管理op指令");
					unset($sender, $args, $command);
					return true;
				}
				if (!isset($args[0])) {
					$sender->sendMessage("§c/myop add/remove/list");
					unset($command, $args, $sender);
					return true;
				}
				$o = $this->config->get("OP");
				switch ($args[0]) {
					case "add":
						if (!isset($args[1])) {
							$sender->sendMessage("§cusage:/myop add <玩家名>");
							unset($command, $args, $sender);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if (isset($o[$args[1]])) {
							$sender->sendMessage("§c玩家{$args[1]}已经是一个OP了");
							unset($command, $args, $sender, $player);
							return true;
						}
						$o[] = $args[1];
						if ($player !== null) {
							$player->sendMessage("§ayou are now op");
						}
						$this->config->set("OP", $o);
						$this->config->save();
						$sender->sendMessage("§a成功添加{$args[1]}为OP");
						if ($player !== null) {
							$player->setOP(true);
						}
						unset($command, $args, $sender, $r, $day);
						return true;
						break;
					case "remove":
						if (!isset($args[1])) {
							$sender->sendMessage("§cusage:/myop remove <玩家名>");
							unset($command, $args, $sender);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if (!in_array($args[1], $o)) {
							$sender->sendMessage("§c{$args[1]}不是一个OP");
							unset($command, $player, $args, $sender);
							return true;
						}
						unset($o[array_search($args[1], $o)]);
						$this->config->set("OP", $o);
						$this->config->save();
						$sender->sendMessage("§a成功移除OP:{$args[1]}");
						unset($command, $args, $sender, $player);
						return true;
						break;
					case "list":
						$r = "§a所有的OP:\n" . "§e";
						foreach ($o as $k) {
							$r .= $k . ",";
						}
						$sender->sendMessage($r);
						unset($command, $args, $sender, $r);
						return true;
						break;
					default:
						$sender->sendMessage("§c/myop add/remove/list");
						unset($command, $args, $sender);
						return true;
				}
				break;
			case "vip":
				if (!$this->bj->get("vip及其管理系统")) {
					$sender->sendMessage("§c腐竹未开启vip系统");
					unset($sender, $args, $command);
					return true;
				}
				if (!$this->isVip($sender->getName())) {
					$sender->sendMessage("§c你不是VIP,请联系腐竹开通");
					unset($sender, $args, $command);
					return true;
				}
				if (!isset($args[0])) {
					$sender->sendMessage("/vip <tp/fly/color/sign>");
					unset($sender, $args, $command);
					return true;
				}
				switch ($args[0]) {
					case "tp":
						if (!isset($args[0])) {
							$sender->sendMessage("§c请输入你要传送的玩家,/vip tp <游戏名>");
							unset($command, $sender, $args);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("/vip tp <游戏名>");
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player == null) {
							$sender->sendMessage("§c玩家{$args[1]}不在线");
							unset($command, $args, $sender, $player);
							return true;
						}
						$sender->teleport($player->getPosition());
						$sender->sendMessage("§a成功传送");
						unset($command, $args, $sender, $player);
						return true;
						break;
					case "sign":
						$v = $this->vip[$sender->getName()];
						if ($v['sign'] > time()) {
							$m = ($v['sign'] - time()) / 60;
							$sender->sendMessage("§c你今天已经签过到了,{$m}分钟之后才可以签到");
							unset($sender, $args, $command, $m);
							return true;
						}
						if (time() - $v['sign'] < 170000) {
							$sender->sendMessage("§a恭喜你完成了连续签到");
							$a = $this->vip[$sender->getName()]['signs'] += 1;
						} else {
							$this->vip[$sender->getName()]['signs'] = 0;
							$a = 1;
						}
						$this->vip[$sender->getName()]['sign'] = time() + 86400;
						$a = $a * 1000;
						$sender->sendMessage("§a成功签到,你获得了{$a}个金币");
						EconomyAPI::getInstance()->addMoney($sender, $a);
						$this->v->setAll($this->vip);
						$this->v->save();
						unset($command, $args, $sender, $a);
						return true;
						break;
					case "fly": //setallowflight
						if ($sender->getAllowFlight() == true) {
							$sender->setAllowFlight(false);
							//$sender->allowFlight = false;
						} else {
							$sender->setAllowFlight(true);
						}
						$sender->sendMessage("§a切换飞行成功");
						unset($command, $args, $sender);
						return true;
						break;
					case "color":
						if (!isset($args[1])) {
							$sender->sendMessage("§c§c用法:/vip color <颜色>");
							unset($command, $args, $sender);
							return true;
						}
						$array = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f", "o", "m", "r");
						if (!in_array($args[1], $array)) {
							$sender->sendMessage("§c未发现颜色: {$args[1]}");
							unset($command, $args, $sender, $array);
							return true;
						}
						$this->vip[$sender->getName()]['color'] = "§" . $args[1];
						$sender->sendMessage("§a成功更换颜色 §{$args[1]}");
						$this->v->setAll($this->vip);
						$this->v->save();
						unset($command, $args, $sender, $array);
						return true;
						break;
					default:
						$sender->sendMessage("/vip <tp/fly/color/sign>");
						unset($sender, $args, $command);
						return true;
				}
			case "称号":
				if (!$this->bj->get("称号系统")) {
					$sender->sendMessage("§c腐竹未开启称号指令");
					unset($sender, $args, $command);
					return true;
				}
				if (!isset($args[0])) {
					if ($sender->isOP()) {
						$sender->sendMessage("用法: /称号 change/list/give");
						return true;
					} else {
						$sender->sendMessage("用法:/称号 change/list");
					}
					return true;
					unset($command, $args, $sender);
					return true;
				}
				switch ($args[0]) {
					case "change":
						if (!isset($this->pre[$sender->getName()])) {
							$sender->sendMessage("§c你还没有任何称号哦");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§c用法:/称号 change <称号编号>");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($this->pre[$sender->getName()]['prefix'][$args[1]])) {
							$sender->sendMessage("§c未发现此称号编码,请输入'/称号 list'查看");
							unset($command, $sender, $args);
							return true;
						}
						$this->pre[$sender->getName()]['use'] = $this->pre[$sender->getName()]['prefix'][$args[1]];
						$this->prefix->setAll($this->pre);
						$this->prefix->save();
						$sender->sendMessage("§a成功更改当前称号为{$this->pre[$sender->getName()]['prefix'][$args[1]]}");
						unset($command, $args, $sender);
						return true;
						break;
					case "list":
						if (!isset($this->pre[$sender->getName()])) {
							$sender->sendMessage("§c你还没有任何称号哦");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[1])) {
							$sender->sendMessage($this->getPrefixList($this->pre[$sender->getName()]['prefix']));
						} else {
							$sender->sendMessage($this->getPrefixList($this->pre[$sender->getName()]['prefix'], (int) $args[1]));
						}
						unset($command, $args, $sender);
						return true;
						break;
					case "give":
						if (!$sender->isOP()) {
							$sender->sendMessage("§c你没有权限使用这个命令");
							unset($command, $args, $sender);
							return true;
						}
						if (!isset($args[2])) {
							$sender->sendMessage("§c用法:/称号 give <玩家> <称号>");
							unset($command, $args, $sender);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						$this->addPrefix($args[1], $args[2]);
						$sender->sendMessage("§a成功赠送玩家{$args[1]}称号:{$args[2]}");
						unset($player, $command, $args, $sender);
						return true;
						break;
					default:
						$sender->sendMessage("§c用法错误");
						unset($command, $args, $sender);
						return true;
						break;
				}
			case "opvip":
				if (!$this->bj->get("vip及其管理系统")) {
					$sender->sendMessage("§c腐竹未开启vip系统");
					unset($sender, $args, $command);
					return true;
				}
				if (!isset($args[0])) {
					$sender->sendMessage("§c/opvip add/remove/list/day");
					unset($command, $args, $sender);
					return true;
				}
				switch ($args[0]) {
					case "add":
						if (!isset($args[2])) {
							$sender->sendMessage("§cusage:/opvip add <游戏名> <天数>");
							unset($command, $args, $sender);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if (isset($this->vip[$args[1]])) {
							$sender->sendMessage("§c玩家{$args[1]}已经是一个VIP了");
							unset($command, $args, $sender, $player);
							return true;
						}
						$day = (int) $args[2];
						if ($day <= 0) {
							$r = "永久";
							$day = "none";
						} else {
							$r = (int) $args[2];
							$day = $r * 86400;
						}
						$this->vip[$args[1]] = array(
							'time' => $day,
							'sign' => 0,
							'signs' => 0,
							'light' => ($r == "永久") ? 50 : $r,
							'color' => '§a',
						);
						$this->v->setAll($this->vip);
						$this->v->save();
						$sender->sendMessage("§a成功添加{$args[1]}为VIP,天数为{$r}");
						if ($player !== null) {
							$player->sendMessage("§a你现在成为一个VIP,天数为{$r}");
						}
						unset($command, $args, $sender, $r, $day);
						return true;
						break;
					case "remove":
						if (!isset($args[1])) {
							$sender->sendMessage("§cusage:/opvip remove <游戏名>");
							unset($command, $args, $sender);
							return true;
						}
						$player = $this->getServer()->getPlayerExact($args[1]);
						if ($player !== null) {
							$args[1] = $player->getName();
						}
						if (!isset($this->vip[$args[1]])) {
							$sender->sendMessage("§c{$args[1]}不是一个VIP");
							unset($command, $player, $args, $sender);
							return true;
						}
						unset($this->vip[$args[1]]);
						$this->v->setAll($this->vip);
						$this->v->save();
						$sender->sendMessage("§a成功移除VIP:{$args[1]}");
						unset($command, $args, $sender, $player);
						return true;
						break;
					case "list":
						$r = "§a所有的VIP:\n";
						foreach ($this->vip as $k => $v) {
							$r .= $k . ",";
						}
						$sender->sendMessage($r);
						unset($command, $args, $sender, $r);
						return true;
						break;
					default:
						$sender->sendMessage("§c/opvip add/remove/list/day");
						unset($command, $args, $sender);
						return true;
				}
		}
		return true;
	}
	//商店ui
	public function getSellcfg()
	{
		$sellcfg = $this->s->getAll();
		return $sellcfg;
	}
	public function getPurcfg()
	{
		$purcfg = $this->pur->getAll();
		return $purcfg;
	}
	public function getPrecfg()
	{
		$precfg = $this->preshop->getAll();
		return $precfg;
	}
	public function getAPI()
	{
		return $this->api;
	}
	public function onReceive(DataPacketReceiveEvent $event)
	{
		$pk = $event->getPacket();
		if (!($pk instanceof ModalFormResponsePacket)) return;
		$player = $event->getPlayer();
		$id = $pk->formId;
		$n = $player->getName();
		$data = json_decode($pk->formData);
		switch ($id) {
			case 10000:
				if ($pk->formData == "null\n") return;
				if ((int) $data == 0) {
					$this->getAPI()->myUI(6000, $player);
					return;
				}
				if ((int) $data == 1) {
					$this->getAPI()->myUI(8000, $player);
					return;
				}
				if ((int) $data == 2) {
					$this->getAPI()->myUI(4000, $player);
					return;
				}
				break;
			case 6000:
				if ($pk->formData == "null\n") return;
				$this->sellFinal($data, $player);
				return;
				break;
			case 8000:
				if ($pk->formData == "null\n") return;
				$this->purFinal($data, $player);
				return;
				break;
			case 4000:
				if ($pk->formData == "null\n") return;
				$this->prefixFinal($data, $player);
				return;
				break;
		}
	}
	public function sellMiddle($id, $damage, $text, $player)
	{
		$itemininventory = Item::get($id, $damage, $text);
		if ($player->getInventory()->contains($itemininventory)) {
			return true;
		}
		return false;
	}
	public function purMiddle($EachPrice, $text, $player)
	{
		if (EconomyAPI::getInstance()->myMoney($player) < $EachPrice * $text) {
			return false;
		}
		return true;
	}
	public function sellFinal($Key, $player)
	{
		$k = (string) ($Key + 1);
		$sellcfg = $this->s->get("$k");
		if ($sellcfg == null) return $player->sendMessage('§c代号' . "$k" . '§c的商品未找到!');
		$item = $sellcfg["id:damage"];
		$id = explode(":", "$item")[0];
		$damage = explode(":", "$item")[1];
		$count = $sellcfg["count"];
		if (!$this->sellMiddle($id, $damage, $count, $player)) return $player->sendMessage("§c您的物品不足!!");
		$EachPrice = $sellcfg["EachPrice"];
		$itemininventory = Item::get($id, $damage, $count);
		$player->getInventory()->removeItem($itemininventory);
		EconomyAPI::getInstance()->addMoney($player, $EachPrice * $count);
		$player->sendMessage("§a成功出售" . "$count" . "§a个" . "$id");
		return true;
	}
	public function purFinal($Key, $player)
	{
		$k = (string) ($Key + 1);
		$purcfg = $this->pur->get("$k");
		if ($purcfg == null) return $player->sendMessage('§c代号' . "$k" . '§c的商品未找到!');
		$item = $purcfg["id:damage"];
		$id = explode(":", "$item")[0];
		$damage = explode(":", "$item")[1];
		$count = $purcfg["count"];
		$EachPrice = $purcfg["EachPrice"];
		if (!$this->purMiddle($EachPrice, $count, $player)) return $player->sendMessage("§c你的金币不足!!");
		EconomyAPI::getInstance()->reduceMoney($player, $EachPrice * $count);
		$itemininventory = Item::get($id, $damage, $count);
		$player->getInventory()->addItem($itemininventory);
		$player->sendMessage("§a成功购买" . "$count" . "§a个" . "$id");
		return true;
	}
	public function prefixFinal($Key, $player)
	{
		$k = (string) ($Key + 1);
		$precfg = $this->preshop->get("$k");
		$EachPrice = $precfg["EachPrice"];
		$prefix = $precfg["prefix"];
		if (!$this->purMiddle($EachPrice, 1, $player)) return $player->sendMessage("§c你的金币不足!!");
		EconomyAPI::getInstance()->reduceMoney($player, $EachPrice * 1);
		$this->addPrefix($player->getName(), $prefix);
		$player->sendMessage("§a成功购买称号" . "$prefix");
		return true;
	}
	public function sendsendtip()
	{
		$pup = $this->bj->get("底部显示");
		if ($pup) {
			$z = count($this->getServer()->getOnlinePlayers());
			date_default_timezone_set('Asia/Chongqing');
			foreach ($this->getServer()->getOnlinePlayers() as $player) {
				$name = $player->getName();
				if ($player->isOnline()) {
					$m = EconomyAPI::getInstance()->myMoney($player->getName());
					$ghid2 = $this->getGuildName($name);
					if (!isset($this->f["$ghid2"])) {
						$ghid1 = "无公会";
					} else {
						$ghid1 = $this->f["$ghid2"]['id'];
					}
					$le = $player->getLevel()->getName();
					$beibao = $player->getInventory();
					$item = $beibao->getItemInHand();
					$id = $item->getID();
					$sl = $item->getcount();
					$ts = $item->getDamage();
					$quanxian = $this->getRand($player);
					$lover = $this->getLover($name);
					$zf = array(
						"m",
						"z",
						"idw",
						"sl",
						"ts",
						"quanxian",
						"dh",
						"di",
						"ds",
						"le",
						"ghna",
						"ghid",
						"lover"
					);
					$zfs = array(
						"$m",
						"$z",
						"$id",
						"$sl",
						"$ts",
						"{$quanxian}",
						"" . date("H") . "",
						"" . date("i") . "",
						"" . date("s") . "",
						"$le",
						"$ghid2",
						"$ghid1",
						"$lover"
					);
					$popup = str_replace($zf, $zfs, $this->DBM->get("底部信息"));
					$player->sendPopup("$popup");
				}
			}
		}
	}
}//class
/*插件开源
未经允许禁止转载
作者:梦宝(fanghao)
*/
