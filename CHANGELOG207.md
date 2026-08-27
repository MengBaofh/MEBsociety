# 更新日志

本文件记录 MEBSociety 的版本变更。

## [2.0.7]

Bug 修复版，只改了新增商品时的参数解析，配置文件结构和旧数据都不受影响，直接替换插件即可。

### Bug 修复

- **新增物品商品时，物品名带空格会报"<num>必须为正整数"。**

  核心的 `CommandStringHelper::parseQuoteAware()` 按空格拆参数，
  而 `/mebshop additem` 原本是按固定下标取参数的（`$args[1]` 物品名、`$args[2]` 数量），
  所以 `/mebshop additem white wool 1 1000 500` 会把 `wool` 当成 `<num>`，
  直接卡在"必须为正整数"这一步，根本建不出商品。
  原版里带空格的物品名不少（`white wool`、`light blue stained glass` 等），这条路等于是走不通的。

  现在改成从参数末尾往前剥数字，最多剥 3 个（`<num>` `<buy_price>` `<sell_price>`），
  剩下的用空格拼回去当物品名，所以下面几种写法都能用：

  ```
  /mebshop additem white wool 1 1000 500
  /mebshop additem "white wool" 1 1000 500
  /mebshop additem light blue stained glass 2 50 25
  ```

  剥数字时至少给名称留一个参数，不会出现名称被吃空的情况；
  数量、买价、卖价的校验规则没变，`abc` 这种非数字仍然会被挡住。

- **`/mebshop addpre` 的称号带空格时同样错位**，价格会取到称号的后半截。
  改成同一套解析（末尾只剥 1 个数字，前面全算称号），
  于是 `/mebshop addpre §b萌新 玩家 5000` 这种带空格的称号可以直接写了。

- **GUI「商店管理 → 新增称号商品」不再禁止称号里带空格。**
  2.0.6 是靠"称号里不能有空格"这条提示硬挡住的，
  现在 GUI 拼指令时会把物品名/称号用引号包起来（内部的 `"` 和 `\` 会转义），
  带空格的内容能原样传到指令里，那条限制就没必要了。

### 说明

物品名解析不出来时的行为没变：商品仍然会建成功，但会给管理员一条警告，
提示改 `Shops.yml`。也就是说 `white wool` 现在能正确进到解析这一步，
至于能不能解析成物品，看的是 `StringToItemParser` 认不认这个名字。