<?

namespace MengBao\MEBsociety\Units;

use pocketmine\plugin\PluginBase;

use MengBao\MEBsociety\Tools\ArrayPage;

class Economy  //打包好的方法
{
    private static $instance;
    private $plugin;

    // 私有构造函数，防止外部直接实例化
    private function __construct(PluginBase $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 查看某人的游戏币，若玩家名不存在则返回float(-1)，成功则返回游戏币
     */
    public function getMoney(string $playerName): float
    {
        $playerConfig = $this->plugin->playerConfig->getAll();
        if (!isset($playerConfig[$playerName]))
            return -1;
        return $playerConfig[$playerName]["游戏币"];
    }

    /**
     * 增加某人的游戏币，若失败(玩家不存在)则返回-1，游戏币减为负值则返回-2，成功返回1
     */
    public function addMoney(string $playerName, float $money): int
    {
        $curMoney = $this->getMoney($playerName);
        if ($curMoney === (float) -1)
            return -1;
        if ($curMoney + $money < 0)  //游戏币不能为负值
            return -2;
        $playerArray = $this->plugin->playerConfig->get($playerName);
        $playerArray["游戏币"] += $money;
        $this->plugin->playerConfig->set($playerName, $playerArray);
        $this->plugin->playerConfig->save();
        return 1;
    }

    /**
     * 转移游戏币A->B，若AB有一个不存在则返回-1，A不够支付则返回-2，成功返回1
     */
    public function payMoney(string $playerNameA, string $playerNameB, float $money): int
    {
        $curMoneyA = $this->getMoney($playerNameA);
        $curMoneyB = $this->getMoney($playerNameA);
        if ($curMoneyA === (float) -1 || $curMoneyB === (float) -1)
            return -1;
        if ($curMoneyA - $money < 0)
            return -2;
        //A减少游戏币
        $this->addMoney($playerNameA, -$money);
        //B增加游戏币
        $this->addMoney($playerNameB, $money);
        return 1;
    }

    /**
     * 获取游戏币排行
     */
    public function getRanking(): array
    {
        $arrays = array();
        $playerConfig = $this->plugin->playerConfig->getAll();
        foreach ($playerConfig as $playerName => $playerArray)
            $arrays[$playerName] = $playerArray["游戏币"];
        arsort($arrays);  // 按值降序排序，保持键和值的关联
        $this->updateRanking($arrays);  //更新排行榜配置文件
        return $arrays;
    }

    /**
     * 更新排行榜
     */
    public function updateRanking(array $ranking): void
    {
        $eachNum = $this->plugin->economyConfig->get("排行榜每页展示的玩家数量");
        $arrayPage = new ArrayPage($ranking, $eachNum);
        $this->plugin->economyRanking->setAll($arrayPage->getArrayPage());
        $this->plugin->economyRanking->save();
    }

    /**
     * 静态方法获取实例
     */
    public static function getInstance(PluginBase $plugin): Economy
    {
        // 如果实例不存在，或者参数不同，则创建新实例
        if (!isset(self::$instance) || self::$instance->plugin !== $plugin) {
            self::$instance = new self($plugin);
        }
        // 返回实例
        return self::$instance;
    }
}