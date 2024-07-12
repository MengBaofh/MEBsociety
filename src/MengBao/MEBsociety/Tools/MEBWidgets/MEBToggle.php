<?php

namespace MengBao\MEBsociety\Tools\MEBWidgets;

/**
 * MEB开关组件
 */
class MEBToggle
{

	private string $type;
	private string $text;
	private array $toggle;

	public function __construct(string $text = "")
	{
		$this->type = "toggle";
		$this->text = $text;
		$this->toggle = array(
            "type" => "toggle",
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
		return $this->toggle;
	}
}
