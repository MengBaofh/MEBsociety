<?php

namespace MengBao\MEBsociety\Tools\MEBWindows;

/**
 * MEB按钮窗口
 */
class MEBForm
{

    private string $type;
    private string $title;
    private string $content;  //顶部提示消息
    private array $buttonArray;  //按钮对象数组，顺序索引
    private array $form;

    public function __construct(string $title = "", string $content = "", array $buttonArray = [])
    {
        $this->type = "form";
        $this->title = $title;
        $this->content = $content;
        $this->buttonArray = $buttonArray;
        $this->createForm();
    }

    public function createForm(): void
    {
        $this->form = array(
            "type" => $this->type,
            "title" => $this->title,
            "content" => $this->content,
            "buttons" => array()
        );
        foreach ($this->buttonArray as $key => $button)
            $this->form["buttons"][$key] = $button->getButton();
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
