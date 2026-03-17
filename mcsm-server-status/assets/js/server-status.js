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

            // Determine if we are currently showing a child
            var currentShowUuid = card.getAttribute('data-showing-uuid') || server.instanceUuid;
            var dataToShow = server;

             // If showing a child, try to find it in the new data
            if (currentShowUuid !== server.instanceUuid && server.children && Array.isArray(server.children)) {
                var foundChild = server.children.find(function(c) { return c.instanceUuid === currentShowUuid; });
                if (foundChild) {
                    dataToShow = foundChild;
                } else {
                    // Child not found in new data, fallback to parent
                    currentShowUuid = server.instanceUuid;
                    card.setAttribute('data-showing-uuid', currentShowUuid);
                }
            }
            // 更新状态 class
            var oldClasses = card.className.match(/mcsm-status-\w+/g);
            if (oldClasses) {
                oldClasses.forEach(function (cls) { card.classList.remove(cls); });
            }
            var newStatusClass = statusClassMap[String(dataToShow.statusCode)] || 'unknown';
            card.classList.add('mcsm-status-' + newStatusClass);

            // 更新状态文本
            var statusText = card.querySelector('.mcsm-status-text');
            if (statusText) statusText.textContent = dataToShow.statusLabel;

            // 更新在线人数
            var playersText = card.querySelector('.mcsm-players-text');
            if (playersText) {
                var pText = '--';
                if (dataToShow.currentPlayers >= 0) {
                    pText = dataToShow.currentPlayers;
                    if (dataToShow.maxPlayers > 0) pText += '/' + dataToShow.maxPlayers;
                }
                playersText.textContent = pText;
            }

            // 更新版本
            var versionText = card.querySelector('.mcsm-version-text');
            if (versionText) { // Version might be optional in template update
                versionText.textContent = dataToShow.version || '';
            }

            // 更新运行时长
            var uptimeText = card.querySelector('.mcsm-uptime-text');
            if (uptimeText) {
                if (dataToShow.statusCode === 3 && dataToShow.elapsed > 0) {
                    uptimeText.textContent = formatUptime(dataToShow.elapsed);
                    uptimeText.closest('.mcsm-meta-item').style.display = '';
                } else {
                    uptimeText.closest('.mcsm-meta-item').style.display = 'none';
                }
            }

             // Update Sub-server Switcher Data (Payloads)
             if (server.children && Array.isArray(server.children)) {
                // Update parent item in switcher
                var parentSubItem = card.querySelector('.mcsm-sub-item[data-uuid="' + server.instanceUuid + '"]');
                if (parentSubItem) {
                    parentSubItem.setAttribute('data-payload', JSON.stringify(server));
                    updateSubItemStatus(parentSubItem, server);
                }

                // Update children items
                server.children.forEach(function(child) {
                    var subItem = card.querySelector('.mcsm-sub-item[data-uuid="' + child.instanceUuid + '"]');
                    if (subItem) {
                        subItem.setAttribute('data-payload', JSON.stringify(child));
                        updateSubItemStatus(subItem, child);
                    }
                });
            }
        });
    }

    function updateSubItemStatus(el, data) {
         var old = el.className.match(/mcsm-status-\w+/g);
         if(old) old.forEach(function(c) { el.classList.remove(c); });
         
         var stClass = statusClassMap[String(data.statusCode)] || 'unknown';
         el.classList.add('mcsm-status-' + stClass);
    }
    
    // Make switcher function global
    window.mcsmSwitchChild = function(el) {
        var payloadRaw = el.getAttribute('data-payload');
        if (!payloadRaw) return;
        
        var payload;
        try {
            payload = JSON.parse(payloadRaw);
        } catch(e) { return; }
        
        var card = el.closest('.mcsm-server-card');
        if (!card) return;
        
        // Mark active
        var siblings = el.parentElement.querySelectorAll('.mcsm-sub-item');
        siblings.forEach(function(s) { s.classList.remove('active'); });
        el.classList.add('active');
        
        // Set state
        card.setAttribute('data-showing-uuid', payload.instanceUuid);
        
        // Trigger a "fake" refresh just for this card using the payload data
        // We can reuse update UI logic but it is embedded in updateCards.
        // Let's call updateCards with a single item list, but that expects "server" struct (parent).
        // If we just want to update the UI based on `payload`, we should duplicate the DOM update logic 
        // OR construct a fake "server" object where payload IS result.
        // BUT updateCards expects the input to be the Server Group (Parent), and then it decides what to show.
        // Since we updated `data-showing-uuid` above, passing the PARENT object to updateCards would work if we had it.
        // But we don't have the full parent object easily here unless we store it on the card.
        
        // Simpler: Just update DOM directly here.
        
        var statusClassMap = {
            '-1': 'busy', '0': 'stopped', '1': 'stopping', '2': 'starting', '3': 'running'
        };
        
        // Title
        var titleEl = card.querySelector('.entry-title a') || card.querySelector('.entry-title');
        if (titleEl) titleEl.textContent = payload.name;
        
        // Icon (optional)
        var iconEl = card.querySelector('.mcsm-icon');
        if (iconEl && payload.icon) iconEl.src = payload.icon;
        
        // Status Class
        var stClass = statusClassMap[String(payload.statusCode)] || 'unknown';
        var oldClasses = card.className.match(/mcsm-status-\w+/g);
        if (oldClasses) oldClasses.forEach(function (cls) { card.classList.remove(cls); });
        card.classList.add('mcsm-status-' + stClass);
        
        // Status Text
        var statusText = card.querySelector('.mcsm-status-text');
        if (statusText) statusText.textContent = payload.statusLabel || payload.statusText; // statusLabel from PHP, handle both if needed

        // Players
        var playersText = card.querySelector('.mcsm-players-text');
        if (playersText) {
             var pText = '--';
             if (payload.currentPlayers >= 0) {
                 pText = payload.currentPlayers;
                 if (payload.maxPlayers > 0) pText += '/' + payload.maxPlayers;
             }
             playersText.textContent = pText;
        }

        // Version
        var versionText = card.querySelector('.mcsm-version-text');
        if (versionText) versionText.textContent = payload.version || '';
        
        // Uptime
        var uptimeText = card.querySelector('.mcsm-uptime-text');
        if (uptimeText) {
            if (payload.statusCode === 3 && payload.elapsed > 0) {
                 uptimeText.textContent = formatUptime(payload.elapsed);
                 uptimeText.closest('.mcsm-meta-item').style.display = '';
            } else {
                 uptimeText.closest('.mcsm-meta-item').style.display = 'none';
            }
        }
        
        // Desc
        var descEl = card.querySelector('.mcsm-server-desc');
        if (descEl) descEl.textContent = payload.description || '';
    };

    // 启动轮询
    if (interval > 0) {
        setInterval(refresh, interval);
    }
})();
