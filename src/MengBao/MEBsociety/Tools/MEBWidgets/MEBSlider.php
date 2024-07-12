<?php

namespace MengBao\MEBsociety\Tools\MEBWidgets;

/**
 * MEB滑块组件
 */
class MEBSlider
{

	private string $type;
	private string $text;
    private int $min;
    private int $max;
    private int $default;
	private array $slider;

	public function __construct(string $text = "", int $min = 0, int $max = 0, int $default = 0)
	{
		$this->type = "slider";
		$this->text = $text;
        $this->min= $min;
        $this->max = $max >= $min ? $max : $min;
        $this->default = ($default >= $min && $default <= $max) ? $default : $min;
		$this->slider = array(
            "type" => "slider",
            "text" => $text,
            "min" => $min,
            "max" => $max,
            "default" =>$default
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

    public function getMin(): int
	{
		return $this->min;
	}

    public function getMax(): int
	{
		return $this->max;
	}

    public function getDefault(): int
	{
		return $this->default;
	}

	public function getWidget(): array
	{
		return $this->slider;
	}
}
