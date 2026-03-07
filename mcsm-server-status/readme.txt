=== MCSM Server Status ===
Contributors: sduweb
Tags: minecraft, mcsmanager, server status, game server
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

通过 MCSManager API 在 WordPress 页面展示 Minecraft 服务器状态列表。

== Description ==

本插件连接 MCSManager 面板，实时获取 Minecraft 服务器状态，并以卡片列表展示在 WordPress 页面上。

== Features ==

* 使用 `/api/instance` 按实例获取详细信息
* 逐条服务器配置（每条单独指定 daemonId + instanceId）
* 显示状态、在线人数、版本、运行时长
* 前端自动轮询刷新
* API Key 仅存储在后端
* 后端 Transient 缓存

== Installation ==

1. 将 `mcsm-server-status` 文件夹上传到 `/wp-content/plugins/` 目录
2. 在 WordPress 后台启用插件
3. 进入 设置 -> MCSM 服务器状态，填写面板地址、API Key、逐条服务器 JSON
4. 在页面插入短代码 `[mcsm_server_status]`

== Usage ==

基础用法：
`[mcsm_server_status]`

可选按节点筛选：
`[mcsm_server_status daemon="你的DaemonID"]`

逐条服务器配置示例（设置页中的 `mcsm_servers`）：

```json
[
  {
    "daemonId": "node-a",
    "instanceId": "50c73059001b436fa85c0d8221c157cf",
    "name": "MUA Lobby",
    "icon": "https://example.com/lobby.png",
    "link": "/server/lobby",
    "tag": "SJMC",
    "description": "大厅服务器"
  },
  {
    "daemonId": "node-b",
    "instanceId": "aabbccddeeff00112233445566778899",
    "name": "Survival"
  }
]
```

必填字段：
* `daemonId`
* `instanceId`（或 `instanceUuid`）

接口说明（插件后端调用）：
* `GET /api/instance`
* Query: `uuid`, `daemonId`
