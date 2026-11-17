<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}
define('GENIE_ACCESS', true);
require_once __DIR__ . '/../include/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peta Jaringan FTTH</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css" />
    <style>
        body, html { height: 100%; margin: 0; padding: 0; display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .navbar { border-bottom: 3px solid #007bff; flex-shrink: 0; }
        #map-container { flex-grow: 1; position: relative; width: 100%; height: 100%; }
        #map { width: 100%; height: 100%; background: #0b0f19; }

        /* Modern Floating Toolbar */
        .map-floating-bar {
            position: absolute;
            top: 15px;
            left: 60px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            max-width: calc(100% - 120px);
        }
        .tool-pill {
            background: rgba(15, 23, 42, 0.9);
            color: #e2e8f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: 0 4px 14px rgba(0,0,0,0.45);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(8px);
        }

        /* Marker Icons */
        .ftth-icon-otb, .ftth-icon-odc, .ftth-icon-odp { font-size: 20px; filter: drop-shadow(0 0 3px #000); text-align: center; }
        .ftth-icon-otb { color: #10b981; }
        .ftth-icon-odc { color: #00e5ff; }
        .ftth-icon-odp { color: #ffb300; }
        .ftth-icon-pelanggan { font-size: 18px; color: #06b6d4; filter: drop-shadow(0 0 2px #000); }

        /* Custom Marker Cluster Styling */
        .custom-cluster-pelanggan, .custom-cluster-odp { background: transparent; }
        .cluster-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.6);
            transition: transform 0.2s;
        }
        .cluster-badge:hover { transform: scale(1.12); }
        .cluster-pelanggan {
            background: rgba(6, 182, 212, 0.88);
            color: #ffffff;
            border: 2px solid #ffffff;
            width: 36px;
            height: 36px;
        }
        .cluster-odp {
            background: rgba(255, 179, 0, 0.92);
            color: #1e293b;
            border: 2px solid #ffffff;
            width: 34px;
            height: 34px;
        }

        /* Dark Basemap Filter */
        .dark-tiles {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }

        /* Popup Styling */
        .leaflet-popup-content-wrapper {
            background: rgba(15, 23, 42, 0.96);
            color: #fff;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 0;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.6);
        }
        .leaflet-popup-content { margin: 0; min-width: 290px !important; }
        .leaflet-popup-tip { background: rgba(15, 23, 42, 0.96); }
        .leaflet-container a.leaflet-popup-close-button { color: #fff; }
        .mini-header { background: #0d6efd; padding: 8px 12px; font-weight: bold; font-size: 0.92rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .mini-body { padding: 10px 12px; font-size: 0.85rem; }
        .mini-table { width: 100%; }
        .mini-table td { padding: 3px 0; vertical-align: top; }
        .mini-label { color: #94a3b8; width: 35%; }
        .mini-val-highlight { font-weight: bold; color: #38bdf8; }

        /* Modern Photon Laser Animation Particles - Distinct Colors & Sizes */
        .moving-photon-odc {
            width: 12px;
            height: 12px;
            background: radial-gradient(circle, #ffffff 20%, #00f2fe 60%, #0072ff 100%);
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 0 10px #00f2fe, 0 0 20px #00c6ff, 0 0 30px #0072ff;
            filter: drop-shadow(0 0 4px #00f2fe);
        }
        .moving-photon-odp {
            width: 9px;
            height: 9px;
            background: radial-gradient(circle, #ffffff 20%, #ffd600 60%, #ff6d00 100%);
            border-radius: 50%;
            border: 1.5px solid #ffffff;
            box-shadow: 0 0 8px #ffd600, 0 0 16px #ff9100, 0 0 24px #ff3d00;
            filter: drop-shadow(0 0 3px #ffd600);
        }

        /* Loading indicator */
        #loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.88);
            z-index: 2000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            transition: opacity 0.3s;
        }

        /* Search Dropdown */
        .search-container { position: relative; }
        #search-results {
            position: absolute;
            top: 36px;
            left: 0;
            width: 260px;
            max-height: 240px;
            overflow-y: auto;
            background: rgba(15, 23, 42, 0.97);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            display: none;
            z-index: 1001;
            box-shadow: 0 6px 18px rgba(0,0,0,0.6);
        }
        .search-item {
            padding: 7px 12px;
            font-size: 12px;
            color: #e2e8f0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            cursor: pointer;
        }
        .search-item:hover { background: #0284c7; color: #fff; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <a class="navbar-brand font-weight-bold" href="../index.php"><i class="fas fa-satellite-dish"></i> GenieACS Manager</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="otb.php"><i class="fas fa-server"></i> OTB</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="odc.php"><i class="fas fa-hdd"></i> ODC</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="odp.php"><i class="fas fa-box"></i> ODP</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pelanggan.php"><i class="fas fa-users"></i> Pelanggan</a>
            </li>
            <li class="nav-item active">
                <a class="nav-link font-weight-bold" href="map.php"><i class="fas fa-map-marked-alt"></i> Peta Jaringan</a>
            </li>
        </ul>
        <a href="../index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Utama</a>
    </div>
</nav>

<div id="map-container">
    <!-- Floating Toolbar -->
    <div class="map-floating-bar">
        <!-- Export KML -->
        <button class="btn btn-sm btn-success font-weight-bold shadow-sm" onclick="exportKML()" title="Ekspor data ke file Google Earth KML">
            <i class="fas fa-file-export"></i> Export KML
        </button>

        <!-- Toggle Modern Animation -->
        <button id="btn-toggle-anim" class="btn btn-sm btn-outline-info bg-dark font-weight-bold shadow-sm" onclick="toggleAnimation()" title="Nyalakan/matikan animasi aliran data optik">
            <i class="fas fa-wave-square"></i> Animasi Aliran: <span id="anim-status" class="badge badge-secondary ml-1">OFF</span>
            <span class="ml-1 small text-nowrap">
                <span style="color:#00f2fe; text-shadow:0 0 6px #00f2fe;">● ODC</span>
                <span style="color:#ffd600; text-shadow:0 0 6px #ffd600;">● ODP</span>
            </span>
        </button>

        <!-- Search Pelanggan / ODP -->
        <div class="search-container">
            <input type="text" id="map-search" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Cari Pelanggan / ODP..." style="width: 200px;" autocomplete="off">
            <div id="search-results"></div>
        </div>

        <!-- Quick Stats Badge -->
        <div class="tool-pill" id="stats-pill">
            <span><i class="fas fa-server text-success"></i> OTB: <b id="stat-otb">0</b></span>
            <span class="mx-1 text-muted">|</span>
            <span><i class="fas fa-hdd" style="color:#00e5ff"></i> ODC: <b id="stat-odc">0</b></span>
            <span class="mx-1 text-muted">|</span>
            <span><i class="fas fa-box" style="color:#ffb300"></i> ODP: <b id="stat-odp">0</b></span>
            <span class="mx-1 text-muted">|</span>
            <span><i class="fas fa-users" style="color:#06b6d4"></i> Pelanggan: <b id="stat-cust">0</b></span>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner-border text-info mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
        <h5 class="font-weight-bold mb-1">Memuat Peta Jaringan FTTH...</h5>
        <small class="text-muted">Mengoptimasi clustering &amp; GPU hardware acceleration</small>
    </div>

    <div id="map"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>

<script>
    const API_URL = '../include/api.php';
    let map;
    let ftthItems = [];
    let routeData = { automatic: {} };

    // High performance animation engine variables
    let isAnimationActive = false;
    let animationReqId = null;
    let animatedParticles = [];

    // Hardware-accelerated Canvas Renderer for polylines (Zero SVG DOM overhead)
    const canvasRenderer = L.canvas({ padding: 0.5 });

    // Cluster groups with optimized performance settings
    const clusterPelanggan = L.markerClusterGroup({
        disableClusteringAtZoom: 18,
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        chunkedLoading: true,
        chunkInterval: 100,
        chunkDelay: 10,
        iconCreateFunction: function(cluster) {
            const count = cluster.getChildCount();
            return L.divIcon({
                html: `<div class="cluster-badge cluster-pelanggan"><span>${count}</span></div>`,
                className: 'custom-cluster-pelanggan',
                iconSize: [36, 36],
                iconAnchor: [18, 18]
            });
        }
    });

    const clusterOdp = L.markerClusterGroup({
        disableClusteringAtZoom: 18,
        maxClusterRadius: 40,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        chunkedLoading: true,
        chunkInterval: 100,
        chunkDelay: 10,
        iconCreateFunction: function(cluster) {
            const count = cluster.getChildCount();
            return L.divIcon({
                html: `<div class="cluster-badge cluster-odp"><span>${count}</span></div>`,
                className: 'custom-cluster-odp',
                iconSize: [34, 34],
                iconAnchor: [17, 17]
            });
        }
    });

    // Independent layer groups for clear visual separation
    const layerOdcOtb = L.layerGroup();
    const layerDropCables = L.layerGroup();
    const layerBackboneCables = L.layerGroup();
    const layerDistributionCables = L.layerGroup();
    const layerAnimation = L.layerGroup();

    // Dictionaries for fast lookup & animation
    const markersByName = {};
    const backboneRoutes = [];
    const distributionRoutes = [];

    function getIcon(type) {
        let iconHtml, iconClass;
        switch(type) {
            case 'OTB': iconHtml = '<i class="fas fa-server"></i>'; iconClass = 'ftth-icon-otb'; break;
            case 'ODC': iconHtml = '<i class="fas fa-hdd"></i>'; iconClass = 'ftth-icon-odc'; break;
            case 'ODP': iconHtml = '<i class="fas fa-box"></i>'; iconClass = 'ftth-icon-odp'; break;
            case 'PELANGGAN': iconHtml = '<i class="fas fa-user"></i>'; iconClass = 'ftth-icon-pelanggan'; break;
            default: iconHtml = '<i class="fas fa-question-circle"></i>'; iconClass = '';
        }
        return L.divIcon({
            className: 'custom-div-icon ' + iconClass,
            html: iconHtml,
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
    }

    // Helper functions
    function clean(d, def = '-') {
        if (d === null || d === undefined || d === '') return def;
        if (typeof d === 'object') {
            if (d._value !== undefined && d._value !== null) return d._value;
            if (d.value !== undefined && d.value !== null) return d.value;
            return def;
        }
        return d;
    }

    function getSafe(obj, path, def = '-') {
        if (!obj) return def;
        let val = path.split('.').reduce((o, p) => (o ? o[p] : undefined), obj);
        return clean(val, def);
    }

    function scanAllIps(igd) {
        let result = { ip: '', user: '' };
        let wanDevices = igd.WANDevice || {};
        for (let wKey in wanDevices) {
            if(wKey === '_object') continue;
            let connDevices = wanDevices[wKey].WANConnectionDevice || {};
            for (let cKey in connDevices) {
                if(cKey === '_object') continue;
                let ppps = connDevices[cKey].WANPPPConnection || {};
                for (let pKey in ppps) {
                    let ip = clean(ppps[pKey].ExternalIPAddress, '');
                    if (ip && ip !== '0.0.0.0' && ip !== '-') return { ip: ip, user: clean(ppps[pKey].Username, '') };
                }
            }
        }
        return result;
    }

    // Modern Professional Animation Engine (Single 60 FPS Loop, GPU Accelerated)
    function startAnimation() {
        stopAnimation();
        isAnimationActive = true;
        $('#anim-status').text('ON').removeClass('badge-secondary').addClass('badge-success');
        $('#btn-toggle-anim').removeClass('btn-outline-info').addClass('btn-info text-white shadow');

        animatedParticles = [];

        // 1. Backbone Photon Pulses (OTB downstream to ODC - Vivid Neon Cyan)
        const odcPhotonIcon = L.divIcon({
            className: 'moving-photon-odc',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });

        backboneRoutes.forEach(route => {
            let pts = route.coords;
            if (!pts || pts.length < 2) return;

            // Flow runs downstream from central OTB to ODC
            let latlngs = [...pts].reverse();

            let totalDist = 0;
            for (let i = 0; i < latlngs.length - 1; i++) {
                totalDist += map.distance(latlngs[i], latlngs[i + 1]);
            }
            if (totalDist <= 0) return;

            const marker = L.marker(latlngs[0], { icon: odcPhotonIcon, zIndexOffset: 950 });
            layerAnimation.addLayer(marker);

            animatedParticles.push({
                marker: marker,
                latlngs: latlngs,
                totalDist: totalDist,
                currentDist: Math.random() * totalDist, // Staggered initial position for natural stream effect
                speed: 3.5 // Fast laser backbone speed
            });
        });

        // 2. Distribution Photon Pulses (ODC downstream to ODP - Vivid Amber Gold)
        const odpPhotonIcon = L.divIcon({
            className: 'moving-photon-odp',
            iconSize: [10, 10],
            iconAnchor: [5, 5]
        });

        distributionRoutes.forEach(route => {
            let pts = route.coords;
            if (!pts || pts.length < 2) return;

            // Flow runs downstream from ODC to ODP
            let latlngs = [...pts].reverse();

            let totalDist = 0;
            for (let i = 0; i < latlngs.length - 1; i++) {
                totalDist += map.distance(latlngs[i], latlngs[i + 1]);
            }
            if (totalDist <= 0) return;

            const marker = L.marker(latlngs[0], { icon: odpPhotonIcon, zIndexOffset: 900 });
            layerAnimation.addLayer(marker);

            animatedParticles.push({
                marker: marker,
                latlngs: latlngs,
                totalDist: totalDist,
                currentDist: Math.random() * totalDist,
                speed: 2.2 // Smooth distribution speed
            });
        });

        // Single high-efficiency animation frame loop
        function step() {
            if (!isAnimationActive) return;

            for (let i = 0; i < animatedParticles.length; i++) {
                const p = animatedParticles[i];
                p.currentDist += p.speed;
                if (p.currentDist > p.totalDist) {
                    p.currentDist = 0;
                }

                let accumulated = 0;
                const pts = p.latlngs;
                for (let j = 0; j < pts.length - 1; j++) {
                    const segDist = map.distance(pts[j], pts[j + 1]);
                    if (accumulated + segDist >= p.currentDist) {
                        const ratio = segDist > 0 ? (p.currentDist - accumulated) / segDist : 0;
                        const lat = pts[j][0] + (pts[j + 1][0] - pts[j][0]) * ratio;
                        const lng = pts[j][1] + (pts[j + 1][1] - pts[j][1]) * ratio;
                        p.marker.setLatLng([lat, lng]);
                        break;
                    }
                    accumulated += segDist;
                }
            }

            animationReqId = requestAnimationFrame(step);
        }

        step();
    }

    function stopAnimation() {
        isAnimationActive = false;
        $('#anim-status').text('OFF').removeClass('badge-success').addClass('badge-secondary');
        $('#btn-toggle-anim').removeClass('btn-info text-white shadow').addClass('btn-outline-info');
        if (animationReqId) {
            cancelAnimationFrame(animationReqId);
            animationReqId = null;
        }
        animatedParticles.forEach(p => layerAnimation.removeLayer(p.marker));
        animatedParticles = [];
    }

    function toggleAnimation() {
        if (isAnimationActive) {
            stopAnimation();
        } else {
            startAnimation();
        }
    }

    // Export KML
    function exportKML() {
        if (!ftthItems || ftthItems.length === 0) {
            alert("Data belum dimuat atau kosong.");
            return;
        }

        let kml = `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n<Document>\n<name>GenieACS FTTH Map Export</name>\n`;

        ftthItems.forEach(item => {
            if(item.lat && item.lng && !isNaN(item.lat) && !isNaN(item.lng)) {
                let desc = `<![CDATA[<b>Tipe:</b> ${item.type}<br>`;
                if(item.details?.port_count) desc += `<b>Kapasitas Port:</b> ${item.details.port_count}<br>`;
                if(item.details?.sn_ont) desc += `<b>SN ONT:</b> ${item.details.sn_ont}<br>`;
                if(item.details?.parent_otb) desc += `<b>Induk:</b> ${item.details.parent_otb} (Port ${item.details.parent_port || '-'})<br>`;
                if(item.details?.parent_odc) desc += `<b>Induk:</b> ${item.details.parent_odc} (Port ${item.details.parent_port || '-'})<br>`;
                if(item.details?.parent_odp) desc += `<b>Induk:</b> ${item.details.parent_odp} (Port ${item.details.parent_port || '-'})<br>`;
                desc += `]]>`;

                kml += `
                <Placemark>
                    <name>${item.name}</name>
                    <description>${desc}</description>
                    <Point><coordinates>${item.lng},${item.lat},0</coordinates></Point>
                </Placemark>
                `;
            }
        });

        if (routeData && routeData.automatic) {
            for (let key in routeData.automatic) {
                let coords = routeData.automatic[key];
                let coordStr = coords.map(c => `${c[1]},${c[0]},0`).join(' ');
                kml += `
                <Placemark>
                    <name>Rute ${key}</name>
                    <LineString><tessellate>1</tessellate><coordinates>${coordStr}</coordinates></LineString>
                </Placemark>
                `;
            }
        }

        const itemMap = new Map(ftthItems.map(item => [item.name, item]));
        ftthItems.forEach(item => {
            if (item.type === 'PELANGGAN' && item.details?.parent_odp) {
                let parentItem = itemMap.get(item.details.parent_odp);
                if (parentItem && item.lat && item.lng && parentItem.lat && parentItem.lng) {
                    kml += `<Placemark><name>Drop ${item.name}</name><LineString><tessellate>1</tessellate><coordinates>${item.lng},${item.lat},0 ${parentItem.lng},${parentItem.lat},0</coordinates></LineString></Placemark>`;
                }
            }
        });

        kml += `\n</Document>\n</kml>`;
        let blob = new Blob([kml], {type: "application/vnd.google-earth.kml+xml"});
        let link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "GenieACS_FTTH_Map_" + new Date().toISOString().slice(0, 10) + ".kml";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Detail popup on-demand loader
    function showDeviceDetailsPopup(marker, sn, customerName) {
        if (!sn) {
            marker.getPopup().setContent("<div class='p-3 text-warning'><i class='fas fa-exclamation-circle'></i> Serial Number ONT tidak diatur untuk pelanggan ini.</div>");
            return;
        }

        $.get(`../include/api.php?action=search&keyword=${encodeURIComponent(sn)}&limit=1`, function(res) {
            if (!res || res.length === 0) {
                marker.getPopup().setContent(`<div class='p-3 text-danger'><i class='fas fa-times-circle'></i> ONT dengan SN: <b>${sn}</b> tidak ditemukan di GenieACS.</div>`);
                return;
            }

            const detail = res[0];
            const vp = detail.VirtualParameters || {};
            const igd = detail.InternetGatewayDevice || detail.Device || {};

            const lastInformTime = detail._lastInform ? new Date(detail._lastInform).getTime() : 0;
            const now = new Date().getTime();
            const diffMinutes = (now - lastInformTime) / 1000 / 60;
            const isOnline = diffMinutes < 5;
            const statusBadge = isOnline ? '<span class="badge badge-success">ONLINE</span>' : '<span class="badge badge-danger">OFFLINE</span>';

            const scan = scanAllIps(igd);
            const ip = scan.ip || clean(vp.pppoeIP, '-');
            const user = scan.user || clean(vp.pppoeUsername, '-');
            const rx = clean(vp.RXPower, '-');
            const rxColor = (parseFloat(rx) < -27) ? '#ff4d4d' : '#00e676';
            const temp = clean(vp.gettemp, '-');
            const uptime = clean(vp.getdeviceuptime, '-');
            const ssid = clean(vp.SSID, '') || getSafe(igd, 'LANDevice.1.WLANConfiguration.1.SSID', '-');
            const clients = clean(vp.activedevices, '0');
            const clientClass = (clients !== '0' && clients !== '-') ? 'mini-val-highlight' : '';

            const content = `
                <div class="mini-header">
                    <i class="fas fa-user"></i> ${customerName}
                </div>
                <div class="mini-body">
                    <table class="mini-table">
                        <tr><td class="mini-label">Status</td><td class="mini-val">${statusBadge} <small class="text-muted">(${Math.floor(diffMinutes)}m ago)</small></td></tr>
                        <tr><td class="mini-label">PPPoE</td><td class="mini-val font-weight-bold text-info">${user}</td></tr>
                        <tr><td class="mini-label">IP Addr</td><td class="mini-val">${ip}</td></tr>
                        <tr><td class="mini-label">Signal Rx</td><td class="mini-val" style="color:${rxColor}; font-weight:bold;">${rx} dBm</td></tr>
                        <tr><td class="mini-label">WiFi Client</td><td class="mini-val ${clientClass}">${clients} Perangkat</td></tr>
                        <tr><td class="mini-label">Suhu ONT</td><td class="mini-val">${temp} &deg;C</td></tr>
                        <tr><td class="mini-label">SSID</td><td class="mini-val text-warning font-weight-bold">${ssid}</td></tr>
                        <tr><td class="mini-label">Uptime</td><td class="mini-val small">${uptime}</td></tr>
                        <tr><td class="mini-label">SN ONT</td><td class="mini-val small text-monospace">${sn}</td></tr>
                    </table>
                </div>
            `;
            marker.getPopup().setContent(content);
        }).fail(function() {
            marker.getPopup().setContent("<div class='p-3 text-danger'>Gagal menghubungi server untuk mencari data ONT.</div>");
        });
    }

    // Load data from server and populate layers
    function loadData() {
        const ftthPromise = $.ajax({
            url: API_URL + '?action=get_ftth_data&_=' + Date.now(),
            dataType: 'json',
            cache: false
        });
        const routePromise = $.ajax({
            url: API_URL + '?action=get_route_data&_=' + Date.now(),
            dataType: 'json',
            cache: false
        });

        $.when(ftthPromise, routePromise).done(function(ftthRes, routeRes) {
            let ftthRaw = ftthRes[0];
            let routeRaw = routeRes[0];
            if (typeof ftthRaw === 'string') {
                try { ftthRaw = JSON.parse(ftthRaw); } catch(e) { console.error(e); }
            }
            if (typeof routeRaw === 'string') {
                try { routeRaw = JSON.parse(routeRaw); } catch(e) { console.error(e); }
            }
            ftthItems = ftthRaw || [];
            routeData = routeRaw || { automatic: {} };
            if (!routeData.automatic) routeData.automatic = {};

            let countOtb = 0, countOdc = 0, countOdp = 0, countCust = 0;
            const itemMap = new Map();

            // 1. Process and organize markers
            ftthItems.forEach(item => {
                if (!item.lat || !item.lng) return;
                itemMap.set(item.name, item);

                const marker = L.marker([item.lat, item.lng], { icon: getIcon(item.type) });
                markersByName[item.name.toLowerCase()] = { marker: marker, item: item };

                if (item.type === 'PELANGGAN') {
                    countCust++;
                    marker.bindPopup(`
                        <div style="padding:15px; color:#fff; text-align:center;">
                            <div class="spinner-border text-info spinner-border-sm mb-2"></div><br>Memuat Data ONT ${item.name}...
                        </div>
                    `);
                    marker.on('click', function() {
                        showDeviceDetailsPopup(marker, item.details?.sn_ont, item.name);
                    });
                    clusterPelanggan.addLayer(marker);
                } else if (item.type === 'ODP') {
                    countOdp++;
                    marker.bindPopup(`
                        <div class="mini-header bg-warning text-dark"><i class="fas fa-box"></i> ODP: ${item.name}</div>
                        <div class="mini-body">
                            <b>Kapasitas Port:</b> ${item.details?.port_count || '-'}<br>
                            <b>Induk ODC:</b> ${item.details?.parent_odc || '-'} (Port: ${item.details?.parent_port || '-'})
                        </div>
                    `);
                    clusterOdp.addLayer(marker);
                } else if (item.type === 'ODC') {
                    countOdc++;
                    marker.bindPopup(`
                        <div class="mini-header" style="background:#0284c7"><i class="fas fa-hdd"></i> ODC: ${item.name}</div>
                        <div class="mini-body">
                            <b>Kapasitas Port:</b> ${item.details?.port_count || '-'}<br>
                            <b>Induk OTB:</b> ${item.details?.parent_otb || '-'} (Port: ${item.details?.parent_port || '-'})
                        </div>
                    `);
                    layerOdcOtb.addLayer(marker);
                } else if (item.type === 'OTB') {
                    countOtb++;
                    marker.bindPopup(`
                        <div class="mini-header bg-success"><i class="fas fa-server"></i> OTB: ${item.name}</div>
                        <div class="mini-body">
                            <b>Kapasitas Port:</b> ${item.details?.port_count || '-'}<br>
                            <b>Pusat Server / Central Office</b>
                        </div>
                    `);
                    layerOdcOtb.addLayer(marker);
                }
            });

            // Update stats
            $('#stat-otb').text(countOtb);
            $('#stat-odc').text(countOdc);
            $('#stat-odp').text(countOdp);
            $('#stat-cust').text(countCust);

            // 2. Render routes with Clear Hierarchy & Distinct Styling
            ftthItems.forEach(item => {
                let parentName = null;
                const childName = item.name;

                if (item.type === 'PELANGGAN') {
                    // A. DROP CORE CABLES (Pelanggan -> ODP)
                    parentName = item.details?.parent_odp;
                    if (parentName && itemMap.has(parentName)) {
                        const parentItem = itemMap.get(parentName);
                        const dropLine = L.polyline([[item.lat, item.lng], [parentItem.lat, parentItem.lng]], {
                            color: '#06b6d4',
                            weight: 1.2,
                            opacity: 0.6,
                            dashArray: '3, 4',
                            renderer: canvasRenderer
                        });
                        layerDropCables.addLayer(dropLine);
                    }
                } else if (item.type === 'ODP') {
                    // B. DISTRIBUTION CABLES (ODP -> ODC - Amber Gold)
                    parentName = item.details?.parent_odc;
                    if (parentName && itemMap.has(parentName)) {
                        const parentItem = itemMap.get(parentName);
                        const routeKey1 = `${childName}_to_${parentName}_foot`;
                        const routeKey2 = `${childName}_to_${parentName}`;
                        let routeCoords = [[item.lat, item.lng], [parentItem.lat, parentItem.lng]];
                        let isCached = false;

                        const cached = (routeData.automatic && (routeData.automatic[routeKey1] || routeData.automatic[routeKey2]));
                        if (cached && cached.length > 0) {
                            routeCoords = cached;
                            isCached = true;
                        }

                        const distLine = L.polyline(routeCoords, {
                            color: '#f59e0b',
                            weight: 2.4,
                            opacity: 0.9,
                            dashArray: isCached ? null : '5, 6',
                            renderer: canvasRenderer
                        });
                        layerDistributionCables.addLayer(distLine);

                        if (isCached) {
                            distributionRoutes.push({ name: childName, coords: routeCoords });
                        }
                    }
                } else if (item.type === 'ODC') {
                    // C. BACKBONE CABLES (ODC -> OTB - Neon Cyan Glowing)
                    parentName = item.details?.parent_otb;
                    if (parentName && itemMap.has(parentName)) {
                        const parentItem = itemMap.get(parentName);
                        const routeKey1 = `${childName}_to_${parentName}_foot`;
                        const routeKey2 = `${childName}_to_${parentName}`;
                        let routeCoords = [[item.lat, item.lng], [parentItem.lat, parentItem.lng]];
                        let isCached = false;

                        const cached = (routeData.automatic && (routeData.automatic[routeKey1] || routeData.automatic[routeKey2]));
                        if (cached && cached.length > 0) {
                            routeCoords = cached;
                            isCached = true;
                        }

                        const backboneLine = L.polyline(routeCoords, {
                            color: '#00f2fe',
                            weight: 3.8,
                            opacity: 0.95,
                            dashArray: isCached ? null : '8, 8',
                            renderer: canvasRenderer
                        });
                        layerBackboneCables.addLayer(backboneLine);

                        if (isCached) {
                            backboneRoutes.push({ name: childName, coords: routeCoords });
                        }
                    }
                }
            });

            // Hide loading overlay smoothly
            $('#loading-overlay').fadeOut(300);

        }).fail(function() {
            $('#loading-overlay').html(`
                <div class="text-danger font-weight-bold mb-2"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                <h5 class="text-danger">Gagal memuat data FTTH dari server</h5>
                <button class="btn btn-sm btn-outline-light mt-3" onclick="location.reload()"><i class="fas fa-redo"></i> Coba Lagi</button>
            `);
        });
    }

    // Fast Search and Jump
    function setupSearch() {
        const $input = $('#map-search');
        const $results = $('#search-results');

        $input.on('input', function() {
            const query = $(this).val().trim().toLowerCase();
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            let matches = [];
            for (let name in markersByName) {
                if (name.includes(query)) {
                    matches.push(markersByName[name]);
                    if (matches.length >= 15) break;
                }
            }

            if (matches.length === 0) {
                $results.html('<div class="p-2 text-muted text-center small">Tidak ditemukan</div>').show();
                return;
            }

            let html = '';
            matches.forEach(m => {
                let badgeClass = 'badge-info';
                if (m.item.type === 'ODP') badgeClass = 'badge-warning text-dark';
                if (m.item.type === 'ODC') badgeClass = 'badge-primary';
                if (m.item.type === 'OTB') badgeClass = 'badge-success';

                html += `
                    <div class="search-item" data-name="${m.item.name}">
                        <span class="badge ${badgeClass} mr-1">${m.item.type}</span>
                        <b>${m.item.name}</b>
                    </div>
                `;
            });

            $results.html(html).show();
        });

        // Search item click handler
        $results.on('click', '.search-item', function() {
            const name = $(this).data('name').toLowerCase();
            const target = markersByName[name];
            if (!target) return;

            $results.hide();
            $input.val(target.item.name);

            const latLng = [target.item.lat, target.item.lng];

            // If inside cluster, spiderfy or zoom to target
            if (target.item.type === 'PELANGGAN') {
                clusterPelanggan.zoomToShowLayer(target.marker, function() {
                    target.marker.openPopup();
                });
            } else if (target.item.type === 'ODP') {
                clusterOdp.zoomToShowLayer(target.marker, function() {
                    target.marker.openPopup();
                });
            } else {
                map.flyTo(latLng, 18);
                setTimeout(() => target.marker.openPopup(), 600);
            }
        });

        // Close search on click outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-container').length) {
                $results.hide();
            }
        });
    }

    // Init Map
    $(document).ready(function() {
        const satelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Satellite'
        });

        const darkLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            className: 'dark-tiles',
            attribution: '&copy; OpenStreetMap'
        });

        const streetLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps'
        });

        map = L.map('map', {
            center: [<?= SERVER_LAT ?>, <?= SERVER_LNG ?>],
            zoom: <?= MAP_INITIAL_ZOOM ?>,
            layers: [satelliteLayer, clusterPelanggan, clusterOdp, layerOdcOtb, layerDropCables, layerDistributionCables, layerBackboneCables, layerAnimation],
            preferCanvas: true
        });

        // Base Maps Control
        const baseMaps = {
            "Mode Satelit": satelliteLayer,
            "Mode Gelap": darkLayer,
            "Mode Jalan": streetLayer
        };

        // Independent Layer Control Overlays (Clear Differentiation)
        const overlayMaps = {
            '<span class="font-weight-bold" style="color:#00f2fe"><i class="fas fa-network-wired"></i> Jalur Backbone (ODC ➔ OTB)</span>': layerBackboneCables,
            '<span class="font-weight-bold" style="color:#f59e0b"><i class="fas fa-project-diagram"></i> Jalur Distribusi (ODP ➔ ODC)</span>': layerDistributionCables,
            '<span class="font-weight-bold" style="color:#06b6d4"><i class="fas fa-satellite-dish"></i> Kabel Drop Pelanggan</span>': layerDropCables,
            '<span class="text-info font-weight-bold"><i class="fas fa-users"></i> Pelanggan (Cluster)</span>': clusterPelanggan,
            '<span class="text-warning font-weight-bold"><i class="fas fa-box"></i> ODP (Cluster)</span>': clusterOdp,
            '<span class="text-primary font-weight-bold"><i class="fas fa-hdd"></i> ODC &amp; OTB Hubs</span>': layerOdcOtb
        };

        L.control.layers(baseMaps, overlayMaps, { collapsed: true, position: 'topright' }).addTo(map);

        setupSearch();
        loadData();
    });
</script>

</body>
</html>
