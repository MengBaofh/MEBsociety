<?php

namespace MengBao\MEBSociety\Units;

use pocketmine\Server;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldException;

class MultiWorld  //打包好的方法
{
    private static $instance;
    private $plugin;

    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 获取服务器的默认世界
     */
    public function getDefaultWorld(): ?World
    {
        return Server::getInstance()->getWorldManager()->getDefaultWorld();
    }

    /**
     * 定点传送,not loaded return -2; success return 1
     */
    public function transportPlayer(Player $player, string $worldName, ?int $x = null, ?int $y = null, ?int $z = null): int
    {
        if (!Server::getInstance()->getWorldManager()->isWorldLoaded($worldName))
            return -2;
        $world = $this->getWorldByFolderName($worldName);  //get the world by its folderName
        if ($x === null || $y === null || $z === null)
            $player->teleport($world->getSpawnLocation());
        else
            $player->teleport(new Position($x, $y, $z, $world));
        return 1;
    }

    /**
     * 通过文件夹名获取世界对象
     */
    public function getWorldByFolderName(string $worldName): ?World
    {
        return Server::getInstance()->getWorldManager()->getWorldByName($worldName);
    }

    /**
     * 通过文件夹名判断世界是否存在
     * ???
     */
    public function isWorldExist(string $worldName): bool
    {
        return Server::getInstance()->getWorldManager()->isWorldGenerated($worldName);
    }

    /**
     * 通过文件夹名判断世界是否已加载
     */
    public function isWorldLoaded(string $worldName): bool
    {
        return Server::getInstance()->getWorldManager()->isWorldLoaded($worldName);
    }

    /**
     * 通过文件夹名加载世界,not generated return -1; has loaded return -2; else errer return -3; success return 1
     */
    public function loadWorldByName(string $worldName): int
    {
        if (!$this->isWorldExist($worldName))  //check if the world exists
            return -1;
        if ($this->isWorldLoaded($worldName))
            return -2;
        try {
            Server::getInstance()->getWorldManager()->loadWorld($worldName, true);
        } catch (WorldException $e) {
            return -3;
        }
        return 1;
    }
    /**
     * 通过文件夹名卸载世界,not generated return -1; not loaded return -2; else errer return -3; success return 1
     */
    public function unloadWorldByName(string $worldName): int
    {
        if (!$this->isWorldExist($worldName))
            return -1;
        if (!$this->isWorldLoaded($worldName))
            return -2;
        try {
            $world = $this->getWorldByFolderName($worldName);
            Server::getInstance()->getWorldManager()->unloadWorld($world);
        } catch (\InvalidArgumentException $e) {
            return -3;
        }
        return 1;
    }

    /**
     * 获取服务器全部的世界(文件夹)名
     *
     * 路径用核心给的数据目录拼，不写死"./worlds"：
     * 相对路径依赖启动时的工作目录，用启动脚本或面板开服时
     * 工作目录不一定是服务端根目录，那样一个世界都扫不到。
     * 同时只认文件夹，worlds里放的说明文件之类不该被当成世界。
     */
    public function getAllWolrdName(): array
    {
        $worldsDir = Server::getInstance()->getDataPath() . "worlds";
        $worldNames = [];
        if (!is_dir($worldsDir) || !($handle = opendir($worldsDir)))
            return $worldNames;
        while (false !== ($entry = readdir($handle)))
            if ($entry !== "." && $entry !== ".." && is_dir($worldsDir . DIRECTORY_SEPARATOR . $entry))
                array_push($worldNames, $entry);
        closedir($handle);
        return $worldNames;
    }

    /**
     * 是否开启开服自动加载全部世界
     */
    public function hasAutoLoad(): bool
    {
        return (bool) $this->plugin->multiWorldConfig->get("自动加载全部世界", true);
    }

    /**
     * 开服时自动加载全部世界
     *
     * 以前每次开服都要管理员手动 /mw load 一遍，漏掉哪个那个世界就传送不了；
     * 而且"是否已加载"是存在配置里的，重启后配置说已加载、实际没加载，
     * 玩家点传送只会拿到一句"世界未加载"。
     *
     * 现在开服时直接把所有已生成的世界加载好，
     * 并按真实状态回写"是否已加载"，配置和实际就不会再对不上。
     *
     * @return array{loaded: int, failed: string[]} 成功加载的个数与失败的世界名
     */
    public function loadAllWorlds(): array
    {
        $loaded = 0;
        $failed = [];
        foreach ($this->getAllWolrdName() as $worldName) {
            if ($this->isWorldLoaded($worldName))
                continue;  //默认世界这类核心已经加载过的跳过
            $result = $this->loadWorldByName($worldName);
            if ($result === 1)
                $loaded++;
            elseif ($result === -3)
                //-1(未生成)只说明那是个普通文件夹，不算失败；-3才是真的加载出错
                $failed[] = $worldName;
        }
        return array("loaded" => $loaded, "failed" => $failed);
    }

    /**
     * 设置世界描述
     * 前提：世界是否存在
     */
    public function setInfo(string $worldName, string $information): void
    {
        $worlds = $this->plugin->worlds->getAll();
        $worlds[$worldName]["描述"] = $information;
        $this->plugin->worlds->setAll($worlds);
        $this->plugin->worlds->save();
    }

    /**
     * 获取世界描述
     * 前提：世界是否存在
     */
    public function getInfo(string $worldName): ?string
    {
        return $this->plugin->worlds->get($worldName)["描述"];
    }

    /**
     * 设置世界加载情况，默认未加载(false)
     * 前提：世界是否存在
     */
    public function setLoadInfo(string $worldName, bool $temp = false): void
    {
        $worlds = $this->plugin->worlds->getAll();
        $worlds[$worldName]["是否已加载"] = $temp;
        $this->plugin->worlds->setAll($worlds);
        $this->plugin->worlds->save();
    }

    /**
     * 获取世界加载情况
     * 前提：世界是否存在
     */
    public function getLoadInfo(string $worldName): string
    {
        if ($this->plugin->worlds->get($worldName)["是否已加载"])
            return "§a是";
        else
            return "§c否";
    }

    /**
     * 获取每页显示的世界数量
     */
    public function getWorldEachNum(): int
    {
        return $this->plugin->multiWorldConfig->get("每页显示的世界数量");
    }

    /**
     * 获取世界在线玩家数量
     * 前提：世界是否存在
     */
    public function getOnlineNum(string $worldName): int
    {
        return count($this->getWorldByFolderName($worldName)->getPlayers());
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): MultiWorld
    {
        // 如果实例不存在，或者参数不同，则创建新实例
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin)
            self::$instance = new self($plugin);
        // 返回实例
        return self::$instance;
    }
}