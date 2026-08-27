# 更新日志

本文件记录 MEBSociety 的版本变更。

## [2.0.7]

Bug 修复版，只改了新增商品时的参数解析，配置文件结构和旧数据都不受影响，直接替换插件即可。

### Bug 修复

- **新增物品商品时，物品名带空格会报"<num>必须为正整数"。**

  核心的 `CommandStringHelper::parseQuoteAware()` 按空格拆参数，
  而 `/mebshop additem` 原本是按固定下标取参数的（`$args[1]` 物品名、`$args[2]` 数量），
  所以 `/mebshop additem white wool 1 1000 500` 会把 `wool` 当成 `<num>`，

  现在改成从参数末尾往前剥数字，最多剥 3 个（`<num>` `<buy_price>` `<sell_price>`），
  剩下的用空格拼回去当物品名，所以下面几种写法都能用：

  ```
  /mebshop additem white wool 1 1000 500
  /mebshop additem "white wool" 1 1000 500
  /mebshop additem light blue stained glass 2 50 25
  ```

  剥数字时至少给名称留一个参数，不会出现名称被吃空的情况。

- **`/mebshop addpre` 同步修复，不再禁止称号里带空格。**