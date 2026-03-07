/**
 * MCSM Server Status - 前端轮询脚本
 */
(function () {
    'use strict';

    var config  = window.mcsmConfig || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce   = config.nonce || '';
    var interval = config.interval || 30000;

    if (!ajaxUrl) return;

    /**
     * 格式化运行时长 (毫秒 -> 可读字符串)
     */
    function formatUptime(ms) {
        var seconds = Math.floor(ms / 1000);
        if (seconds < 60) return seconds + '秒';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + '分钟';
        var hours = Math.floor(minutes / 60);
        var mins  = minutes % 60;
        if (hours < 24) return hours + '小时' + (mins > 0 ? mins + '分' : '');
        var days = Math.floor(hours / 24);
        var hrs  = hours % 24;
        return days + '天' + (hrs > 0 ? hrs + '小时' : '');
    }

    /**
     * 状态码 -> CSS class
     */
    var statusClassMap = {
        '-1': 'busy',
        '0': 'stopped',
        '1': 'stopping',
        '2': 'starting',
        '3': 'running'
    };

    /**
     * 请求数据并更新 DOM
     */
    function refresh() {
        var lists = document.querySelectorAll('.mcsm-server-list');
        if (!lists.length) return;

        lists.forEach(function (list) {
            var daemon = list.getAttribute('data-daemon') || '';
            var url = ajaxUrl + '?action=mcsm_get_status&nonce=' + encodeURIComponent(nonce);
            if (daemon) {
                url += '&daemon=' + encodeURIComponent(daemon);
            }

            // 标记刷新中
            var cards = list.querySelectorAll('.mcsm-server-card');
            cards.forEach(function (c) { c.classList.add('mcsm-refreshing'); });

            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json.success || !Array.isArray(json.data)) return;
                    updateCards(list, json.data);
                })
                .catch(function (err) {
                    console.warn('[MCSM] 刷新失败:', err);
                })
                .finally(function () {
                    cards.forEach(function (c) { c.classList.remove('mcsm-refreshing'); });
                });
        });
    }

    /**
     * 更新卡片数据
     */
    function updateCards(list, servers) {
        servers.forEach(function (server) {
            var card = list.querySelector('[data-uuid="' + server.instanceUuid + '"]');
            if (!card) return;

            // 更新状态 class
            var oldClasses = card.className.match(/mcsm-status-\w+/g);
            if (oldClasses) {
                oldClasses.forEach(function (cls) { card.classList.remove(cls); });
            }
            var newStatusClass = statusClassMap[String(server.statusCode)] || 'unknown';
            card.classList.add('mcsm-status-' + newStatusClass);

            // 更新状态文本
            var statusText = card.querySelector('.mcsm-status-text');
            if (statusText) statusText.textContent = server.statusLabel;

            // 更新在线人数
            var playersText = card.querySelector('.mcsm-players-text');
            if (playersText && server.currentPlayers >= 0) {
                var pText = server.currentPlayers;
                if (server.maxPlayers > 0) pText += '/' + server.maxPlayers;
                playersText.textContent = pText;
            }

            // 更新版本
            var versionText = card.querySelector('.mcsm-version-text');
            if (versionText && server.version) {
                versionText.textContent = server.version;
            }

            // 更新运行时长
            var uptimeText = card.querySelector('.mcsm-uptime-text');
            if (uptimeText) {
                if (server.statusCode === 3 && server.elapsed > 0) {
                    uptimeText.textContent = formatUptime(server.elapsed);
                    uptimeText.closest('.mcsm-meta-item').style.display = '';
                } else {
                    uptimeText.closest('.mcsm-meta-item').style.display = 'none';
                }
            }
        });
    }

    // 启动轮询
    if (interval > 0) {
        setInterval(refresh, interval);
    }
})();

