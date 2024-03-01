<?

namespace MengBao\MEBsociety\Tools;

/**
 * 离线消息
 */
class OfflineMessage
{
    private array $offlineMessage;

    public function __construct()
    {
        $this->offlineMessage = array();
    }

    /**
     * 一键设置离线消息
     */
    public function setAllOM(array $offlineMessage): void
    {
        $this->offlineMessage = $offlineMessage;
    }

    /**
     * 获取offlineMessage数组
     */
    public function getAllOM(): array
    {
        return $this->offlineMessage;
    }

    /**
     * 获取玩家的离线消息数组
     */
    public function getOM(string $playerName): array
    {
        return $this->offlineMessage[$playerName];
    }

    /**
     * 获取玩家的离线消息，格式化为字符串
     */
    public function getOMString(string $playerName): string
    {
        $array = $this->getOM($playerName);
        $string = "";
        foreach($array as $key=>$msg){
            $string .= $key . " => " . $msg;
            $string .= "\n";
        }
        return $string;
    }

    /**
     * 判断玩家的离线消息是否为空
     */
    public function isEmptyOM(string $playerName): bool
    {
        return empty($this->getOM($playerName));
    }

    /**
     * 添加玩家的离线消息
     */
    public function addOM(string $playerName, string $msg): void
    {
        array_push($this->offlineMessage[$playerName], $msg);
    }


    /**
     * 清空玩家的离线消息
     */
    public function clearOM(string $playerName): void
    {
        $this->offlineMessage[$playerName] = array();
    }
}
