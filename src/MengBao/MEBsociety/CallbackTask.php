<?

namespace MengBao\MEBsociety;

use pocketmine\scheduler\Task;

class CallbackTask extends Task
{
	protected $callable;
	protected $args;

	public function __construct(callable $callable, array $args = [])
	{
		$this->callable = $callable;
		$this->args = $args;
		$this->args[] = $this;
	}

	public function getCallable(): callable
	{
		return $this->callable;
	}

	public function onRun(): void
	{
		\call_user_func_array($this->callable, $this->args);
	}
}
