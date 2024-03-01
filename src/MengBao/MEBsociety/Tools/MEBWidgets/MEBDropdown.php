<?

namespace MengBao\MEBsociety\Tools\MEBWidgets;

/**
 * MEB下拉框组件
 */
class MEBDropdown
{

	private string $type;
	private string $text;
    private array $options;
	private array $dropdown;

	public function __construct(string $text = "", array $options = [])
	{
		$this->type = "dropdown";
		$this->text = $text;
        $this->options= $options;
		$this->dropdown = array(
            "type" => "dropdown",
            "text" => $text,
            "options" => $options
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

    public function getOptions(): array
	{
		return $this->options;
	}

	public function getWidget(): array
	{
		return $this->dropdown;
	}
}
