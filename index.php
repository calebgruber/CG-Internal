<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />

<!-- GOOGLE MATERIAL SYMBOLS -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />

<style>
/* ---------- THEME VARIABLES ---------- */
:root {
    --bg: #0b0f19;
    --bg2: #111423;
    --text: #ffffff;
    --text-muted: #cbd5e1;
    --card-bg: rgba(255,255,255,0.06);
    --input-bg: rgba(255,255,255,0.08);
    --input-border: rgba(255,255,255,0.15);
}

:root.light {
    --bg: #f5f7fa;
    --bg2: #ffffff;
    --text: #0b0f19;
    --text-muted: #475569;
    --card-bg: rgba(0,0,0,0.05);
    --input-bg: rgba(0,0,0,0.04);
    --input-border: rgba(0,0,0,0.15);
}

/* ---------- GLOBAL ---------- */
* {
    box-sizing: border-box;
}

body {
    background: var(--bg);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    margin: 0;
    color: var(--text);
    display: grid;
    grid-template-columns: 240px 1fr;
    height: 100vh;
}

a { color: inherit; text-decoration: none; }

.material-symbols-rounded {
    font-family: "Material Symbols Rounded";
    font-weight: normal;
    font-style: normal;
    font-size: 20px;
    line-height: 1;
    letter-spacing: normal;
    text-transform: none;
    display: inline-block;
    white-space: nowrap;
    word-wrap: normal;
    direction: ltr;
    -webkit-font-feature-settings: "liga";
    -webkit-font-smoothing: antialiased;
}

/* ---------- SIDEBAR ---------- */
.sidebar {
    background: var(--bg2);
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
    border-right: 1px solid rgba(255,255,255,0.08);
}

.logo {
    font-size: 20px;
    font-weight: 700;
}

.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 6px;
    border-radius: 1px;
    color: var(--text-muted);
}

.sidebar nav a:hover {
    background: rgba(255,255,255,0.06);
}

/* ---------- MAIN ---------- */
.main {
    padding: 32px;
    overflow-y: auto;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.top-btn {
    background: #3b82f6;
    border: none;
    padding: 10px 16px;
    border-radius: 2px;
    color: white;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

/* ---------- THEME TOGGLE ---------- */
.theme-toggle {
    background: var(--card-bg);
    border: 1px solid var(--input-border);
    padding: 8px 14px;
    border-radius: 1px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text);
}

/* ---------- GRID ---------- */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 32px;
}

/* ---------- CARD COMPONENT ---------- */
.card {
    position: relative;
    background: var(--card-bg);
    border-radius: 5px;
    border-left: 6px solid var(--primary);
    padding: 56px 24px 24px;
}

.card-full {
    grid-column: 1 / -1;
}

.card-tab {
    position: absolute;
    top: 0;
    left: 0;
    height: 44px;
    display: inline-flex;
    align-items: center;
    padding: 0 20px 0 12px;
    border-top-right-radius: 22px;
    border-bottom-right-radius: 22px;
    gap: 8px;
    white-space: nowrap;
    background: color-mix(in srgb, var(--primary) 25%, transparent);
}

.card-tab .material-symbols-rounded,
.card-tab .title {
    color: var(--primary);
    font-variation-settings: 'wght' 600;
}

:root.light .card-tab {
    background: var(--primary);
}

:root.light .card-tab .material-symbols-rounded,
:root.light .card-tab .title {
    color: var(--tab-foreground, #ffffff);
}

.card-content {
    font-size: 15px;
    color: var(--text-muted);
    line-height: 1.55;
}

/* ---------- ALERT COMPONENT ---------- */
.alert {
    position: relative;
    border-left: 6px solid var(--primary);
    border-radius: 5px;
    height: 44px;
    margin-bottom: 16px;
}

.alert-tab {
    position: absolute;
    top: 0;
    left: 0;
    height: 44px;
    display: inline-flex;
    align-items: center;
    padding: 0 20px 0 12px;
    border-top-right-radius: 22px;
    border-bottom-right-radius: 22px;
    gap: 8px;
    white-space: nowrap;
    background: color-mix(in srgb, var(--primary) 25%, transparent);
}

.alert-tab .material-symbols-rounded,
.alert-tab .title {
    color: var(--primary);
    font-variation-settings: 'wght' 600;
}

:root.light .alert-tab {
    background: var(--primary);
}

:root.light .alert-tab .material-symbols-rounded,
:root.light .alert-tab .title {
    color: var(--tab-foreground, #ffffff);
}

.alert-dismiss {
    margin-left: 8px;
    cursor: pointer;
    font-size: 20px;
}

/* ---------- FORM ---------- */
.form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.form input,
.form select {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    padding: 10px;
    border-radius: 2px;
    color: var(--text);
}

.submit-btn {
    margin-top: 8px;
    background: var(--primary);
    border: none;
    padding: 10px 16px;
    border-radius: 2px;
    color: white;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

/* ---------- TABLE ---------- */
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
}

.table th,
.table td {
    padding: 10px;
    border-bottom: 1px solid var(--input-border);
    color: var(--text-muted);
    text-align: left;
}

.table th {
    color: var(--text);
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <h2 class="logo">My Dashboard</h2>
    <nav>
        <a href="#"><span class="material-symbols-rounded">dashboard</span> Overview</a>
        <a href="#"><span class="material-symbols-rounded">analytics</span> Analytics</a>
        <a href="#"><span class="material-symbols-rounded">settings</span> Settings</a>
    </nav>
</aside>

<!-- MAIN -->
<main class="main">

    <!-- TOP BAR -->
    <header class="topbar">
        <h1>Overview</h1>

        <div style="display:flex; gap:12px;">
            <button class="theme-toggle" onclick="toggleTheme()">
                <span class="material-symbols-rounded">light_mode</span>
                Theme
            </button>

            <button class="top-btn">
                <span class="material-symbols-rounded">add</span>
                New Item
            </button>
        </div>
    </header>

    <!-- ALERTS CONTAINER -->
    <section id="alerts">
        <div class="alert" style="--primary:#f59e0b;">
            <div class="alert-tab">
                <span class="material-symbols-rounded">info</span>
                <span class="title">Notice: Scheduled maintenance at 11 PM</span>
            </div>
        </div>

        <div class="alert" style="--primary:#ef4444;">
            <div class="alert-tab">
                <span class="material-symbols-rounded">warning</span>
                <span class="title">Critical: API error rates elevated</span>
                <span class="material-symbols-rounded alert-dismiss" onclick="this.closest('.alert').remove()">close</span>
            </div>
        </div>
    </section>

    <!-- GRID -->
    <section class="grid">

        <!-- METRICS CARD 1 -->
        <div class="card" style="--primary:#3b82f6;">
            <div class="card-tab">
                <span class="material-symbols-rounded">insights</span>
                <span class="title">Key Metrics</span>
            </div>
            <div class="card-content">
                Daily active users, conversion rate, and uptime summary for the last 24 hours.
            </div>
        </div>

        <!-- METRICS CARD 2 -->
        <div class="card" style="--primary:#22c55e;">
            <div class="card-tab">
                <span class="material-symbols-rounded">trending_up</span>
                <span class="title">Growth</span>
            </div>
            <div class="card-content">
                Month-over-month growth trends across core product lines and regions.
            </div>
        </div>

        <!-- CREATE ALERT CARD -->
        <div class="card" style="--primary:#e11d48;">
            <div class="card-tab">
                <span class="material-symbols-rounded">add_alert</span>
                <span class="title">Create Alert</span>
            </div>
            <div class="card-content">
                <form class="form" onsubmit="createAlert(event)">
                    <label>Alert Text</label>
                    <input id="alertText" type="text" placeholder="Enter alert text">

                    <label>Primary Color</label>
                    <input id="alertColor" type="color" value="#ef4444">

                    <label>Icon (Material Symbol)</label>
                    <input id="alertIcon" type="text" placeholder="warning" value="warning">

                    <label>Dismissible?</label>
                    <select id="alertDismiss">
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>

                    <button class="submit-btn">
                        <span class="material-symbols-rounded">add</span>
                        Add Alert
                    </button>
                </form>
            </div>
        </div>

        <!-- FULL-WIDTH TABLE CARD -->
        <div class="card card-full" style="--primary:#6366f1;">
            <div class="card-tab">
                <span class="material-symbols-rounded">table</span>
                <span class="title">User Activity</span>
            </div>
            <div class="card-content">
                <table class="table">
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Last Login</th>
                        <th>Status</th>
                    </tr>
                    <tr>
                        <td>Alice Johnson</td>
                        <td>alice@example.com</td>
                        <td>2026-03-28 21:14</td>
                        <td>Active</td>
                    </tr>
                    <tr>
                        <td>Bob Smith</td>
                        <td>bob@example.com</td>
                        <td>2026-03-28 19:02</td>
                        <td>Pending</td>
                    </tr>
                    <tr>
                        <td>Charlie Lee</td>
                        <td>charlie@example.com</td>
                        <td>2026-03-28 17:45</td>
                        <td>Active</td>
                    </tr>
                    <tr>
                        <td>Dana Patel</td>
                        <td>dana@example.com</td>
                        <td>2026-03-28 16:10</td>
                        <td>Suspended</td>
                    </tr>
                </table>
            </div>
        </div>

    </section>
</main>

<script>
/* ---------- THEME TOGGLE ---------- */
function toggleTheme() {
    const root = document.documentElement;
    const nowLight = !root.classList.contains("light");
    root.classList.toggle("light");
    localStorage.setItem("theme", nowLight ? "light" : "dark");
    applyTabForegrounds();
}

(function() {
    const saved = localStorage.getItem("theme");
    if (saved === "light") document.documentElement.classList.add("light");
    applyTabForegrounds();
})();

/* ---------- COLOR PARSER ---------- */
function parseColor(input) {
    const ctx = document.createElement("canvas").getContext("2d");
    ctx.fillStyle = input;
    return ctx.fillStyle;
}

/* ---------- CONTRAST CHECK ---------- */
function isColorDark(color) {
    const ctx = document.createElement("canvas").getContext("2d");
    ctx.fillStyle = color;
    const rgb = ctx.fillStyle.match(/\d+/g).map(Number);
    const luminance = (0.299*rgb[0] + 0.587*rgb[1] + 0.114*rgb[2]);
    return luminance < 140;
}

/* ---------- APPLY TAB FOREGROUNDS (LIGHT MODE) ---------- */
function applyTabForegrounds() {
    const root = document.documentElement;
    const isLight = root.classList.contains("light");
    const elements = document.querySelectorAll(".card, .alert");

    elements.forEach(el => {
        const primary = el.style.getPropertyValue("--primary");
        if (!primary) return;
        const color = parseColor(primary);
        if (isLight) {
            const fg = isColorDark(color) ? "#ffffff" : "#000000";
            el.style.setProperty("--tab-foreground", fg);
        } else {
            el.style.removeProperty("--tab-foreground");
        }
    });
}

/* ---------- CREATE ALERT ---------- */
function createAlert(e) {
    e.preventDefault();

    const text = document.getElementById("alertText").value || "New Alert";
    const colorInput = document.getElementById("alertColor").value || "#ef4444";
    const icon = document.getElementById("alertIcon").value || "warning";
    const dismissible = document.getElementById("alertDismiss").value === "yes";

    const color = parseColor(colorInput);
    const alert = document.createElement("div");
    alert.className = "alert";
    alert.style.setProperty("--primary", color);

    const isLight = document.documentElement.classList.contains("light");
    if (isLight) {
        const fg = isColorDark(color) ? "#ffffff" : "#000000";
        alert.style.setProperty("--tab-foreground", fg);
    }

    alert.innerHTML = `
        <div class="alert-tab">
            <span class="material-symbols-rounded">${icon}</span>
            <span class="title">${text}</span>
            ${dismissible ? `<span class="material-symbols-rounded alert-dismiss" onclick="this.closest('.alert').remove()">close</span>` : ""}
        </div>
    `;

    const container = document.getElementById("alerts");
    container.prepend(alert);
}
</script>

</body>
</html>
