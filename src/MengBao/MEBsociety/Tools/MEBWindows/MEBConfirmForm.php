<?php

namespace MengBao\MEBsociety\Tools\MEBWindows;

/**
 * MEB确认窗口
 */
class MEBConfirmForm
{

    private string $logo = "[MEBS]";
    private string $type;
    private string $title;
    private string $content;
    private string $trueButton;
    private string $falseButton;
    private array $form;

    public function __construct(string $title = "", string $content = "", array $text = [])
	{
        $this->type = "modal";
        $this->title = $title === "" ? $this->logo . "操作确认窗口" : $title;
        $this->content = $content;
        $this->trueButton = $text[0];
        $this->falseButton = $text[1];
        $this->createForm();
	}

    public function createForm(): void
    {
        $this->form = array(
            "type" => $this->type,
            "title" => $this->title,
            "content" => $this->content,
            "button1" => $this->trueButton,  //true
            "button2" => $this->falseButton,  //false
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
