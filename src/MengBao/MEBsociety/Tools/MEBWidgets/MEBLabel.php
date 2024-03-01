<?

namespace MengBao\MEBsociety\Tools\MEBWidgets;

/**
 * MEB文本组件
 */
class MEBLabel
{

	private string $type;
	private string $text;
	private array $label;

	public function __construct(string $text = "")
	{
		$this->type = "label";
		$this->text = $text;
		$this->label = array(
            "type" => "label",
			"text" => $text,
		);
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getText(): string
	{
		return $this->text;
	}

	public function getWidget(): array
	{
		return $this->label;
	}
}
