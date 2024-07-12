<?php

namespace MengBao\MEBsociety\Tools\MEBWidgets;

/**
 * MEB按钮组件
 */
class MEBButton
{

	private string $type;
	private string $text;
	private string $imgPathType;  //"path" or "url" or null
	private string $imgPath;  //"textures/items/diamond_sword.png"
	private array $button;

	public function __construct(string $text = "", string $imgPathType = "", string $imgPath = "")
	{
		$this->type = "button";
		$this->text = $text;
		$this->imgPathType = $imgPathType;
		$this->imgPath = $imgPath;
		$this->button = array(
			"text" => $text,
			"image" => array(
				"type" => $imgPathType,
				"data" => $imgPath
			)
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

	public function getImgPathType(): string
	{
		return $this->imgPathType;
	}

	public function getImgPath(): string
	{
		return $this->imgPath;
	}

	public function getButton(): array
	{
		return $this->button;
	}
}
