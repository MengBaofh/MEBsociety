<?php

namespace MengBao\MEBSociety\Units;

use pocketmine\plugin\PluginBase;

class Campsite  //打包好的方法
{
    /**
     * 营地所有者的职位名
     *
     * 2.0.8起从"营长"改叫"市长"。老数据里存的还是"营长"，
     * 所以判断的地方要同时认这两个名字(见isOwner)，
     * 而Main那边会在启动时做一次迁移，把存量数据统一改过来。
     */
    public const POST_OWNER = "市长";

    /** 2.0.7及更早版本的所有者职位名，仅用于兼容判断和迁移 */
    public const POST_OWNER_LEGACY = "营长";

    private static $instance;
    private $plugin;

    /**
     * 营地权力表
     *
     * 2.0.9起去掉了"设置营地传送点"，营地传送改由MEBTerritory的营地领地传送承担，
     * 权力ID随之重排。存量数据里多出来的旧权力键由Main::migrate清掉。
     */
    public $powerId = array(
        0 => "所有权力",
        1 => "召集营地成员",
        2 => "审核入营申请",
        3 => "踢人"
    );

    /** 2.0.9及更早版本的权力名，仅用于迁移 */
    public const POWER_LEGACY_SETHOME = "设置营地传送点";

    public $nameId = [];  //营地名与营地ID映射表

    // 私有构造函数，防止外部直接实例化
    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 获取服务器的全部营地ID
     */
    public function getAllCID(): array
    {
        return array_keys($this->plugin->campsites->getAll());
    }

    /**
     * 建立营地名和营地ID的映射
     */
    public function setNameId(): void
    {
        $this->nameId = array();
        $campsites = $this->plugin->campsites->getAll();
        foreach ($campsites as $CID => $value)
            $this->nameId[$value["name"]] = $CID;
    }

    /**
     * 获取全部权力表
     */
    public function getPowerId(): array
    {
        return $this->powerId;
    }

    /**
     * 统计服务器营地数量
     */
    public function totalNum(): int
    {
        return count($this->getAllCID());
    }

    /**
     * 通过游戏名获取CID，若加入营地则返回CID，否则返回-1
     */
    public function getCIDbyPlayerName(string $playerName): int
    {
        $CID = $this->plugin->playerConfig->get($playerName)["营地ID"];
        return $CID !== null ? $CID : -1;
    }

    /**
     * 通过游戏名获取营地职位
     */
    public function getCPost(string $playerName): string
    {
        return $this->plugin->playerConfig->get($playerName)["营地职位名"];
    }

    /**
     * 通过游戏名获取营地权力
     * 前提：是否加入营地
     */
    public function getCPower(string $playerName): array
    {
        return $this->plugin->playerConfig->get($playerName)["营地权力"];
    }

    /**
     * 权力及序号映射
     */
    public function getPowerNameByID(int $id): string
    {
        return $this->powerId[$id];
    }

    /**
     * 通过CID判断营地是否存在，存在则返回true
     */
    public function campsiteExistByCID(int $CID): bool
    {
        return in_array($CID, $this->getAllCID());
    }


    /**
     * 通过营地名判断营地是否存在
     */
    public function campsiteExistByName(string $campsiteName): bool
    {
        return array_key_exists($campsiteName, $this->nameId);
    }

    /**
     * 通过游戏名判断玩家是否加入营地
     */
    public function isJoinCampsite(string $playerName): bool
    {
        $CID = $this->getCIDbyPlayerName($playerName);
        return $CID !== -1 && in_array($CID, $this->nameId);
    }

    /**
     * 判断玩家是否为营地市长
     *
     * 老数据里的职位名可能还是"营长"，两个都要认，
     * 否则升级插件之后所有现存市长会瞬间失去身份。
     */
    public function isOwner(string $playerName): bool
    {
        $post = $this->getCPost($playerName);
        return $post === self::POST_OWNER || $post === self::POST_OWNER_LEGACY;
    }

    /**
     * 把存量数据里的"营长"统一改成"市长"，返回改了多少条
     *
     * 只改职位名，不动其他字段。插件启动时跑一次，
     * 已经迁移过的服务器再跑也不会有副作用(找不到"营长"就什么都不做)。
     */
    public function migrateOwnerPost(): int
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        $changed = 0;
        foreach ($playerConfig as $playerName => $playerArray) {
            if (!is_array($playerArray) || ($playerArray["营地职位名"] ?? null) !== self::POST_OWNER_LEGACY)
                continue;
            $playerConfig[$playerName]["营地职位名"] = self::POST_OWNER;
            $changed++;
        }
        if ($changed > 0) {
            $this->plugin->playerConfig->setAll($playerConfig);
            $this->plugin->playerConfig->save();
        }
        return $changed;
    }

    /**
     * 判断两人是否在同一个营地
     */
    public function isSameCampsite(string $playerNameA, string $playerNameB): bool
    {
        if ($this->getCIDbyPlayerName($playerNameA) === -1 || $this->getCIDbyPlayerName($playerNameB) === -1)
            return false;
        if ($this->getCIDbyPlayerName($playerNameA) === $this->getCIDbyPlayerName($playerNameB))
            return true;
        return false;
    }

    /**
     * 随机获取唯一CID
     */
    public function getRandCID(): int
    {
        $maxNum = 3 * $this->plugin->campsiteConfig->get("服务器最大营地个数");
        $rand = mt_rand(1, $maxNum >= PHP_INT_MAX ? PHP_INT_MAX : $maxNum);
        if ($this->campsiteExistByCID($rand))
            return $this->getRandCID();
        return $rand;
    }

    /**
     * 修改玩家营地职位名
     * 前提：玩家是否存在
     */
    public function changePost(string $playerName, ?string $newPost): void
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        $playerConfig[$playerName]["营地职位名"] = $newPost;
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 修改玩家营地ID
     * 前提：玩家是否存在
     */
    public function changePlayerCID(string $playerName, ?int $newCID): void
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        $playerConfig[$playerName]["营地ID"] = $newCID;
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 修改玩家营地权力, values=[
     *  0 => "所有权力",
     *  1 => "召集营地成员",
     *  2 => "审核入营申请",
     *  3 => "踢人"
     * ]
     * 前提：玩家是否存在
     */
    public function changePower(string $playerName, array $values): void
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        foreach ($values as $index => $value) {
            //权力表变短过，多出来的下标直接忽略
            if (!isset($this->powerId[$index]))
                continue;
            $playerConfig[$playerName]["营地权力"][$this->getPowerNameByID($index)] = $value;
        }
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 获取营地有指定权力的玩家名数组
     * 前提：CID是否存在
     */
    public function getHierarchByPowerName(int $CID, string $powerName): array
    {
        $arrays = [];
        $member = $this->getAllMember($CID);
        foreach ($member as $k => $name) {
            if ($this->getCPower($name)[$powerName] || $this->getCPower($name)["所有权力"])
                array_push($arrays, $name);
        }
        return $arrays;
    }

    /**
     * 创建营地
     * 前提：服务器的营地数量是否上限/是否已有营地/是否足够的钱/营地名是否已存在/营地名是否有特殊限制
     */
    public function createCampsite(string $campsiteName, string $ownerName): void
    {
        //campsites配置文件中新增一条
        $callNum = $this->plugin->campsiteConfig->get("营地每日召集次数上限") * $this->getPlayerCallScale($ownerName);  //创建时只有营长一个人
        $campsites = $this->plugin->campsites->getAll();
        $CID = $this->getRandCID();
        $campsites[$CID] = array(
            "name" => $campsiteName,  //营地名
            "owner" => $ownerName,
            "call" => $callNum,  //营地每日召集次数
            "member" => [$ownerName],  //营地成员名
            "id" => $CID,
            "application" => array(),  //入营申请人名单
            "level" => 1,  //营地等级，决定解锁哪些营地专属物品
            "donation" => array(  //捐献池，营地升级和圈领地的费用从这里出
                "money" => 0,
                "record" => array(),  //每个人的累计捐献额，谁出了多少大家都能查
            ),
            "welfare" => array(  //福利箱，市长设置内容，成员每周领一次
                "money" => 0,
                "items" => array(),  //专属物品标识 => 每人每周可领数量
                "claimed" => array(),  //玩家名 => 上次领取的周标识
            ),
        );
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        //更新playerConfig配置文件对应信息
        $this->changePower($ownerName, [true, true, true, true]);
        $this->changePlayerCID($ownerName, $CID);
        $this->changePost($ownerName, self::POST_OWNER);
        //更新营地名和营地ID映射
        $this->setNameId();
    }

    /**
     * 通过CID删除营地
     * 前提：营地是否存在/是否有权限
     */
    public function deleteCampsite(int $CID): void
    {
        //修改所有有关成员的playerconfig
        $member = $this->getAllMember($CID);
        foreach ($member as $key => $name) {
            $this->changePost($name, null);
            $this->changePower($name, [false, false, false, false]);
            $this->changePlayerCID($name, null);
        }
        //campsites文件中删除营地信息
        $campsites = $this->plugin->campsites->getAll();
        unset($campsites[$CID]);
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        //更新营地名和营地ID映射
        $this->setNameId();
    }

    /**
     * 通过CID增删营地成员，默认添加
     */
    public function changeMember(int $CID, string $playerName, bool $mode = true): void
    {
        $campsites = $this->plugin->campsites->getAll();
        if (!$mode)
            unset($campsites[$CID]["member"][array_search($playerName, $campsites[$CID]["member"])]);
        else
            array_push($campsites[$CID]["member"], $playerName);
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
    }

    /**
     * 市长转让
     * 前提：是否有营地/是否市长(所有权力)/是否同一个营地
     */
    public function changeOwner(int $CID, string $oldOwner, string $newOwner): void
    {
        $campsites = $this->plugin->campsites->getAll();
        $campsites[$CID]["owner"] = $newOwner;
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        $this->changePost($newOwner, self::POST_OWNER);
        $this->changePower($newOwner, [true, true, true, true]);
        $this->changePlayerCID($newOwner, $CID);
        $this->changePost($oldOwner, null);
        $this->changePower($oldOwner, [false, false, false, false]);
    }

    /**
     * 增删营地入营申请，成功返回1，删除对象不存在则返回-1
     * 前提：CID是否存在
     */
    public function changeApplication(int $CID, string $playerName, bool $mode = true): int
    {
        $campsites = $this->plugin->campsites->getAll();
        if ($mode)
            array_push($campsites[$CID]["application"], $playerName);
        else
            if (!in_array($playerName, $campsites[$CID]["application"]))
                return -1;
            else {
                unset($campsites[$CID]["application"][array_search($playerName, $campsites[$CID]["application"])]);
                $campsites[$CID]["application"] = array_values($campsites[$CID]["application"]);
            }
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
        return 1;
    }

    /**
     * 根据CID获取营地名
     * 前提：CID是否存在
     */
    public function getCName(int $CID): string
    {
        return $this->plugin->campsites->get($CID)["name"];
    }

    /**
     * 获取市长名
     * 前提：CID是否存在
     */
    public function getOwner(int $CID): string
    {
        return $this->plugin->campsites->get($CID)["owner"];
    }

    /**
     * 获取入营申请
     * 前提：CID是否存在
     */
    public function getApplication(int $CID): array
    {
        return $this->plugin->campsites->get($CID)["application"];
    }

    /**
     * 获取每页显示的入营申请数量
     */
    public function getAppEachNum(): int
    {
        return $this->plugin->campsiteConfig->get("每页显示的入营申请数量");
    }

    /**
     * 获取营地全部成员
     * 前提：CID是否存在
     */
    public function getAllMember(int $CID): array
    {
        return $this->plugin->campsites->get($CID)["member"];
    }

    /**
     * 获取营地召集次数
     * 前提：CID是否存在
     */
    public function getCallNum(int $CID): int
    {
        return $this->plugin->campsites->get($CID)["call"];
    }

    /**
     * 设置营地召集次数
     */
    public function setCallNum(int $CID, int $callNum): void
    {
        $campsites = $this->plugin->campsites->getAll();
        $campsites[$CID]["call"] = $callNum;
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
    }

    /**
     * 一键刷新所有营地召集次数
     */
    public function updateAllCallNum(): void
    {
        $defaultCallNum = $this->plugin->campsiteConfig->get("营地每日召集次数上限");
        $campsites = $this->plugin->campsites->getAll();
        foreach($this->getAllCID() as $CID)
            $campsites[$CID]["call"] = $defaultCallNum * $this->getCallScale($CID);
        $this->plugin->campsites->setAll($campsites);
        $this->plugin->campsites->save();
    }

    /**
     * 获取营地召集倍数(所有拥有召集权力的成员的召集倍数相乘)
     * 前提：CID是否存在
     */
    public function getCallScale(int $CID): int
    {
        $scale = 1;
        foreach($this->getHierarchByPowerName($CID, "召集营地成员") as $playerName)
            $scale *= $this->getPlayerCallScale($playerName);
        return $scale;
    }

    /**
     * 获取玩家的营地召集倍数
     * 前提：玩家是否存在
     */
    public function getPlayerCallScale(string $playerName): int
    {
        return $this->plugin->playerConfig->get($playerName)["营地召集倍数"];
    }

    /**
     * 设置玩家的营地召集倍数
     */
    public function setPlayerCallScale(string $playerName, int $num): void
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        $playerConfig[$playerName]["营地召集倍数"] = $num;
        $this->plugin->playerConfig->setAll($playerConfig);
        $this->plugin->playerConfig->save();
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): Campsite
    {
        // 如果实例不存在，或者参数不同，则创建新实例
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin)
            self::$instance = new self($plugin);
        self::$instance->setNameId();
        // 返回实例
        return self::$instance;
    }
}