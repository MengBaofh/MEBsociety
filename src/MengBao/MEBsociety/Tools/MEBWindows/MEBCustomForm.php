<?php

namespace MengBao\MEBsociety\Tools\MEBWindows;

/**
 * MEB多组件窗口
 */
class MEBCustomForm
{

    private string $type;
    private string $title;
    private array $widgetArray;  //组件对象数组，顺序索引
    private array $content;
    private array $form;

	public function __construct(string $title = "", array $widgetArray = [])
	{
        $this->type = "custom_form";
        $this->title = $title;
        $this->widgetArray = $widgetArray;
        $this->createContent();
        $this->createForm();
	}

    public function createContent(): void
    {
        $this->content = array();
        foreach($this->widgetArray as $key => $widget)
            $this->content[$key] = $widget->getWidget();
    }

    public function createForm(): void
    {
        $this->form = array(
            "type" => $this->type,
            "title" => $this->title,
            "content" => $this->content
        );

    }

    public function getForm(): bool|string
    {
        return $this->getEncodedJson($this->form);
    }

    public function getEncodedJson($data): bool|string
	{
		return json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE);
	}
}
