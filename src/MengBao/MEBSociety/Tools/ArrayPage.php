<?php

namespace MengBao\MEBSociety\Tools;

/**
 * 数组自动分页，从1页开始
 */
class ArrayPage
{
    private array $array;  //分页后的数组，按页数索引
    private int $pages;  //总页数

    public function __construct(array $array, int $eachNum = 1)
    {
        $this->array = array();
        $page = 1; //从1开始，不然会吞键
        $eachNum = $eachNum <= 0 ? 1 : $eachNum;
        $eachPage = array();
        foreach ($array as $key => $value) {
            $eachPage[$key] = $value;
            if (count($eachPage) % $eachNum === 0) {
                $this->array[$page] = $eachPage;
                $eachPage = array();
                $page++;
            }
        }
        if (!empty($eachPage))
            $this->array[$page] = $eachPage;
        //总页数要按真正存下来的页数算。刚好整除时上面的$page已经多加了一次，
        //直接用$page会多报一页，翻到那一页就会取到不存在的下标。
        //空数组仍然算1页，不然调用方会收到"页码不合理(1~0)"这种提示
        $this->pages = max(1, count($this->array));
    }

    /**
     * 获取总页数
     */
    public function getTotalPages(): int
    {
        return $this->pages;
    }

    /**
     * 判断页码是否合理
     * 前提：页码是否为数字
     */
    public function isValidPage(int $page): bool
    {
        return $page > 0 && $page <= $this->getTotalPages();
    }

    /**
     * 获取某页内容
     */
    public function getContent(int $page = 1): array
    {
        return $this->array[$page] ?? array();
    }

    /**
     * 获取分页后的数组
     */
    public function getArrayPage(): array
    {
        return $this->array;
    }
}
