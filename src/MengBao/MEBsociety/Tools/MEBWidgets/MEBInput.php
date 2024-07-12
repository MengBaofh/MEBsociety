<?php

namespace MengBao\MEBsociety\Tools\MEBWidgets;

/**
 * MEB输入框组件
 */
class MEBInput
{

	private string $type;
	private string $text;  //提示信息
    private string $placeholder;  //输入框默认信息
	private array $input;

	public function __construct(string $text = "", string $placeholder = "")
	{
		$this->type = "input";
		$this->text = $text;
        $this->placeholder= $placeholder;
		$this->input = array(
            "type" => "input",
			"text" => $text,
            "placeholder" => $placeholder
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

    public function getPlaceholder(): string
	{
		return $this->placeholder;
	}

	public function getWidget(): array
	{
		return $this->input;
	}
}
