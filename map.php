<?php
// C:\xampp\htdocs\genieacs\map.php

session_start();

// 1. Cek Login
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. Kunci Akses Config
define('GENIE_ACCESS', true);
require_once 'include/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Map Monitoring</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        body { margin: 0; padding: 0; overflow: hidden; font-family: sans-serif; }
        #map { height: 100vh; width: 100vw; z-index: 1; background: #222; }
        .dark-tiles {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        
        .back-btn {
            position: absolute; top: 20px; left: 60px; z-index: 999;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            border: 1px solid #444;
        }

        .info-box {
            position: absolute; bottom: 30px; left: 20px; z-index: 999;
            background: rgba(30, 30, 30, 0.9);
            color: #fff; padding: 15px; width: 220px;
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.6);
            border: 1px solid #444;
        }

        /* --- STYLE POPUP --- */
        .leaflet-popup-content-wrapper {
            background: rgba(15, 23, 42, 0.95);
            color: #fff; border-radius: 8px; border: 1px solid #444;
            padding: 0; overflow: hidden;
        }
        .leaflet-popup-content { margin: 0; min-width: 280px !important; }
        .leaflet-popup-tip { background: rgba(15, 23, 42, 0.95); }
        .leaflet-container a.leaflet-popup-close-button { color: #fff; }

        .mini-header { background: #0d6efd; padding: 8px 12px; font-weight: bold; font-size: 0.9rem; border-bottom: 1px solid #444; }
        .mini-body { padding: 10px; font-size: 0.85rem; }
        .mini-table { width: 100%; }
        .mini-table td { padding: 3px 0; vertical-align: top; }
        .mini-label { color: #aaa; width: 35%; }
        .mini-val { font-weight: 500; }
        .mini-footer { padding: 8px; background: rgba(0,0,0,0.2); text-align: center; }

        /* --- ANIMASI GARIS --- */
        .flow-line {
            stroke-dasharray: 8, 8;
            animation: flow 1s linear infinite;
        }
        @keyframes flow {
            from { stroke-dashoffset: 16; }
            to { stroke-dashoffset: 0; }
        }

        /* --- ICON STYLES (UPDATE WARNA MERAH) --- */
        .server-icon { font-size: 35px; color: #00bcd4; filter: drop-shadow(0 0 5px #00bcd4); }
        
        .ont-icon-online { 
            font-size: 20px; 
            color: #00e676; /* Hijau Terang */
            filter: drop-shadow(0 0 4px #00e676); 
        }
        
        .ont-icon-offline { 
            font-size: 22px; /* Lebih besar dikit biar kelihatan */
            color: #ff0000;  /* MERAH TOTAL */
            filter: drop-shadow(0 0 5px #ff0000); /* Efek Cahaya Merah */
        }

        /* --- SIGNAL TEXT ON MAP --- */
        .signal-text {
            position: absolute;
            top: 18px; /* Posisi di bawah icon */
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 9px;
            white-space: nowrap;
            font-weight: bold;
            z-index: 500;
            box-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }

        /* --- TEMP TEXT ON MAP --- */
        .temp-text {
            position: absolute;
            top: 32px; /* Posisi di bawah label signal */
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.85);
            padding: 1px 5px; border-radius: 4px; font-size: 9px;
            white-space: nowrap; font-weight: bold; z-index: 500;
            color: #ffc107; /* Kuning untuk Suhu */
        }
    </style>
</head>
<body>

<a href="index.php" class="btn btn-dark back-btn font-weight-bold"><i class="fas fa-arrow-left"></i> Dashboard</a>
<button class="btn btn-success font-weight-bold" style="position: absolute; top: 20px; left: 190px; z-index: 999; box-shadow: 0 4px 10px rgba(0,0,0,0.5); border: 1px solid #444;" onclick="exportKML()">
    <i class="fas fa-file-export"></i> Export KML
</button>

<div class="info-box">
    <h6 class="font-weight-bold mb-3 text-info"><i class="fas fa-network-wired"></i> Status Jaringan</h6>
    <div class="small mb-2"><i class="fas fa-wifi text-success" style="color: #00e676;"></i> Online (< 5 menit)</div>
    <div class="small mb-2"><i class="fas fa-power-off text-danger" style="color: #ff0000;"></i> Offline / Mati</div>
    <div class="small"><i class="fas fa-server text-info" style="color: #00bcd4;"></i> Server Pusat</div>
    <hr class="my-2" style="border-top: 1px solid #555;">
    <div class="small">Terpetakan: <strong id="count-map" class="text-warning">0</strong> Device</div>
</div>

<div id="map"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- 1. KOORDINAT SERVER ---
    const serverLoc = [<?= defined('SERVER_LAT') ? SERVER_LAT : -6.175392 ?>, <?= defined('SERVER_LNG') ? SERVER_LNG : 106.827153 ?>];

    // --- 2. SETUP PETA (Tanpa Watermark / Tanpa API Key) ---
    var satelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
        maxZoom: 20, subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], attribution: '&copy; Google Satellite'
    });
    var darkLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, className: 'dark-tiles', attribution: '&copy; OpenStreetMap'
    });
    var streetLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20, subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], attribution: '&copy; Google Maps'
    });

    var map = L.map('map', {
        center: serverLoc, zoom: 14, layers: [satelliteLayer] 
    });

    L.control.layers({ 
        "Mode Satelit (Google)": satelliteLayer, 
        "Mode Gelap (NOC)": darkLayer, 
        "Mode Jalan (Google)": streetLayer 
    }).addTo(map);

    // Marker Server
    var serverIcon = L.divIcon({
        className: 'custom-div-icon',
        html: "<i class='fas fa-server server-icon'></i>",
        iconSize: [30, 42], iconAnchor: [15, 42]
    });
    L.marker(serverLoc, {icon: serverIcon}).addTo(map).bindPopup("<div class='text-dark p-2'><b>SERVER UTAMA</b><br>Pusat Jaringan</div>").openPopup();


    // --- 3. HELPER FUNCTIONS ---
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

    function formatBandwidth(val) {
        if (!val || val === '-' || val == 0) return null;
        // Jika input angka besar (bps), konversi ke Mbps
        let num = parseFloat(val);
        if (!isNaN(num) && num > 1000) {
            return (num / 1000000).toFixed(1) + ' Mbps';
        }
        return val; // Kembalikan aslinya (misal "10 Mbps" atau "20")
    }

    // Fungsi Scan IP Rekursif (Sama seperti index.php)
    function scanAllIps(igd) {
        let result = { ip: '', type: '', user: '' };
        let wanDevices = igd.WANDevice || {};
        
        for (let wKey in wanDevices) {
            if(wKey === '_object') continue;
            let connDevices = wanDevices[wKey].WANConnectionDevice || {};
            
            for (let cKey in connDevices) {
                if(cKey === '_object') continue;
                let conns = connDevices[cKey];

                // Cek PPPoE
                let ppps = conns.WANPPPConnection || {};
                for (let pKey in ppps) {
                    let pObj = ppps[pKey];
                    let ip = clean(pObj.ExternalIPAddress, '');
                    if (ip && ip !== '0.0.0.0' && ip !== '-') return { ip: ip, type: 'PPPoE', user: clean(pObj.Username, '') };
                }

                // Cek IP/Static/DHCP
                let ips = conns.WANIPConnection || {};
                for (let iKey in ips) {
                    let iObj = ips[iKey];
                    let ip = clean(iObj.ExternalIPAddress, '');
                    if (ip && ip !== '0.0.0.0' && ip !== '-') {
                        if(!result.ip) result = { ip: ip, type: 'Static/DHCP', user: '' };
                    }
                }
            }
        }
        return result;
    }

    // --- KML EXPORT FUNCTION ---
    let exportKmlString = "";

    function generateKML(devices) {
        let kml = `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n<Document>\n<name>GenieACS Map Export</name>\n`;
        
        kml += `
        <Style id="lineOnline">
          <LineStyle>
            <color>ff00ff00</color> <!-- Hijau -->
            <width>2</width>
          </LineStyle>
        </Style>
        <Style id="lineOffline">
          <LineStyle>
            <color>ff0000ff</color> <!-- Merah -->
            <width>2</width>
          </LineStyle>
        </Style>
        `;

        kml += `
        <Placemark>
            <name>SERVER UTAMA</name>
            <description>Pusat Jaringan</description>
            <Point>
                <coordinates>${serverLoc[1]},${serverLoc[0]},0</coordinates>
            </Point>
        </Placemark>
        `;

        devices.forEach(d => {
            let lat = null, lng = null;
            if (d.VirtualParameters && d.VirtualParameters.coordinates) {
                let parts = d.VirtualParameters.coordinates.split(',');
                if (parts.length >= 2) {
                    lat = parseFloat(parts[0]);
                    lng = parseFloat(parts[1]);
                }
            }
            if ((lat === null || isNaN(lat)) && d._tags && Array.isArray(d._tags)) {
                for (let t of d._tags) {
                    let match = t.match(/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/);
                    if (match) {
                        lat = parseFloat(match[1]);
                        lng = parseFloat(match[2]);
                        break;
                    }
                }
            }

            if(lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng)) {
                let lastInformTime = d._lastInform ? new Date(d._lastInform).getTime() : 0;
                let now = new Date().getTime();
                let isOnline = ((now - lastInformTime) / 1000 / 60) < 5;
                let displayTag = d._tags && d._tags.length > 0 ? d._tags.join(', ') : d._id;

                kml += `
                <Placemark>
                    <name>${displayTag}</name>
                    <description><![CDATA[<b>ID:</b> ${d._id}<br><b>Status:</b> ${isOnline ? 'Online' : 'Offline'}]]></description>
                    <Point>
                        <coordinates>${lng},${lat},0</coordinates>
                    </Point>
                </Placemark>
                <Placemark>
                    <name>Koneksi ke ${displayTag}</name>
                    <styleUrl>#${isOnline ? 'lineOnline' : 'lineOffline'}</styleUrl>
                    <LineString>
                        <tessellate>1</tessellate>
                        <coordinates>${serverLoc[1]},${serverLoc[0]},0 ${lng},${lat},0</coordinates>
                    </LineString>
                </Placemark>
                `;
            }
        });
        kml += `\n</Document>\n</kml>`;
        return kml;
    }

    function exportKML() {
        if (!exportKmlString) {
            alert("Data belum dimuat atau kosong.");
            return;
        }
        let blob = new Blob([exportKmlString], {type: "application/vnd.google-earth.kml+xml"});
        let link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "GenieACS_Map_" + new Date().toISOString().slice(0, 10) + ".kml";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // --- 4. LOAD DATA & RENDER ---
    function loadMapData() {
        $.get('include/api.php?action=search&keyword=&limit=1000', function(res) {
            // Cek jika API mengembalikan error atau data kosong
            if(res.error) { console.error("API Error:", res.error); return; }
            if(!res || !Array.isArray(res) || res.length === 0) return;
            
            exportKmlString = generateKML(res);
            
            let count = 0;
            
            res.forEach(d => {
                let lat = null, lng = null;

                // 1. Cek Virtual Parameters (Prioritas Utama)
                if (d.VirtualParameters && d.VirtualParameters.coordinates) {
                    let parts = d.VirtualParameters.coordinates.split(',');
                    if (parts.length >= 2) {
                        lat = parseFloat(parts[0]);
                        lng = parseFloat(parts[1]);
                    }
                }

                // 2. Cek Tags (Support format "GPS:lat,lng" atau "lat,lng" atau "Lokasi: lat, lng")
                if ((lat === null || isNaN(lat)) && d._tags && Array.isArray(d._tags)) {
                    for (let t of d._tags) {
                        // Regex: Cari pasangan angka (bisa negatif/desimal) yang dipisah koma
                        let match = t.match(/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/);
                        if (match) {
                            lat = parseFloat(match[1]);
                            lng = parseFloat(match[2]);
                            break; // Ambil yang pertama ditemukan
                        }
                    }
                }

                if(lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng)) {
                            count++;
                            let deviceLoc = [lat, lng];
                            
                            // Hitung Status
                            let lastInformTime = d._lastInform ? new Date(d._lastInform).getTime() : 0;
                            let now = new Date().getTime();
                            let diffMinutes = (now - lastInformTime) / 1000 / 60;
                            let isOnline = diffMinutes < 5; // Batas Online 5 Menit

                            // --- SIGNAL TEXT ---
                            let vp = d.VirtualParameters || {};
                            let rx = clean(vp.RXPower, '');
                            let signalHtml = '';
                            let tempHtml = '';

                            if (isOnline && rx && rx !== '-') {
                                let rxVal = parseFloat(rx);
                                let rxColor = (!isNaN(rxVal) && rxVal < -27) ? '#ff4d4d' : '#00e676';
                                signalHtml = `<div class="signal-text" style="color:${rxColor}">${rx} dBm</div>`;
                                
                                // --- TEMP (Ganti Traffic jadi Temp) ---
                                let temp = clean(vp.gettemp, '');
                                if (temp && temp !== '-') {
                                    tempHtml = `<div class="temp-text">${temp} °C</div>`;
                                }
                            }

                            // --- LOGIKA ICON & WARNA ---
                            let iconHtml = isOnline ? 
                                `<div style="position:relative"><i class='fas fa-wifi ont-icon-online'></i>${signalHtml}${tempHtml}</div>` : 
                                `<i class='fas fa-power-off ont-icon-offline'></i>`; 
                            
                            let lineColor = isOnline ? '#00e676' : '#ff0000'; // Garis Hijau / Merah

                            let deviceIcon = L.divIcon({
                                className: 'custom-div-icon', html: iconHtml,
                                iconSize: [20, 20], iconAnchor: [10, 10]
                            });

                            let marker = L.marker(deviceLoc, {icon: deviceIcon}).addTo(map);

                            // --- POPUP DINAMIS ---
                            marker.on('click', function() {
                                marker.unbindPopup(); // Hapus popup lama agar refresh
                                marker.bindPopup(`
                                    <div style="padding:10px; color:#333; text-align:center;">
                                        <div class="spinner-border text-primary spinner-border-sm"></div> Memuat Data...
                                    </div>
                                `).openPopup();

                                $.get('include/api.php?action=get_detail&id=' + encodeURIComponent(d._id), function(detail) {
                                    if(detail.error) { 
                                        let errMsg = detail.error + (detail.details ? '<br><small>'+detail.details+'</small>' : '');
                                        marker.getPopup().setContent("<div class='p-2 text-danger'>Gagal: " + errMsg + "</div>"); 
                                        return; 
                                    }
                                    
                                    let vp = detail.VirtualParameters || {};
                                    let igd = detail.InternetGatewayDevice || detail.Device || {};
                                    let d_raw = detail; 

                                    // Data Processing
                                    let displayTag = detail._tags && detail._tags.length > 0 ? detail._tags.join(', ') : detail._id;

                                    let ssid = clean(vp.SSID, '') || clean(vp.SSID_ALL, '') || getSafe(igd, 'LANDevice.1.WLANConfiguration.1.SSID', '-');
                                    
                                    let rx = clean(vp.RXPower);
                                    let rxVal = parseFloat(rx);
                                    let rxColor = (!isNaN(rxVal) && rxVal < -27) ? '#ff4d4d' : '#00e676'; 

                                    // Traffic Detail
                                    let down = clean(vp.Download, '') || clean(vp.download, '') || clean(vp.DownRate, '-');
                                    if (!down || down === '-') {
                                        down = formatBandwidth(getSafe(igd, 'WANDevice.1.WANCommonInterfaceConfig.Layer1DownstreamMaxBitRate', '-'));
                                    }

                                    let up = clean(vp.Upload, '') || clean(vp.upload, '') || clean(vp.UpRate, '-');
                                    if (!up || up === '-') {
                                        up = formatBandwidth(getSafe(igd, 'WANDevice.1.WANCommonInterfaceConfig.Layer1UpstreamMaxBitRate', '-'));
                                    }
                                    
                                    let temp = clean(vp.gettemp);
                                    let clients = clean(vp.activedevices, '0');
                                    let uptime = clean(vp.getdeviceuptime);

                                    // IP & Remote Link
                                    let scan = scanAllIps(igd);
                                    let ip = scan.ip;
                                    
                                    // Fallback IP (Jika scan gagal, coba sumber lain)
                                    if(!ip || ip === '-') ip = clean(vp.pppoeIP, '');
                                    if(!ip || ip === '-' || ip === '0.0.0.0') {
                                        if (d_raw._ip && d_raw._ip !== '-') ip = d_raw._ip;
                                        else if (d_raw._lastInform && d_raw._lastInform.ip) ip = d_raw._lastInform.ip;
                                    }
                                    if(!ip || ip === '0.0.0.0') ip = '-';

                                    let remoteLink = (ip !== '-' && ip !== '0.0.0.0') ? 
                                        `<a href="http://${ip}" target="_blank" class="btn btn-sm btn-primary w-100 font-weight-bold">Remote Web</a>` : 
                                        `<button class="btn btn-sm btn-secondary w-100 disabled">IP Tidak Ada</button>`;

                                    let ipDisplay = (ip !== '-' && ip !== '0.0.0.0') ? `<a href="http://${ip}" target="_blank" style="color:#4dabf7;font-weight:bold">${ip}</a>` : '-';

                                    // --- HTML POPUP ---
                                    let statusBadge = isOnline ? 
                                        '<span class="badge badge-success">ONLINE</span>' : 
                                        '<span class="badge badge-danger">OFFLINE</span>';

                                    let content = `
                                        <div class="mini-header">
                                            <i class="fas fa-tag"></i> ${displayTag}
                                        </div>
                                        <div class="mini-body">
                                            <table class="mini-table">
                                                <tr>
                                                    <td class="mini-label">Status</td>
                                                    <td class="mini-val">${statusBadge} <small class="text-muted">(${Math.floor(diffMinutes)}m ago)</small></td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">IP Addr</td>
                                                    <td class="mini-val">${ipDisplay}</td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">Signal</td>
                                                    <td class="mini-val" style="color:${rxColor}">${rx} dBm</td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">Traffic</td>
                                                    <td class="mini-val text-info">↓ ${down} | ↑ ${up}</td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">Temp</td>
                                                    <td class="mini-val">${temp} °C</td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">WiFi</td>
                                                    <td class="mini-val text-warning">${ssid}</td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">Clients</td>
                                                    <td class="mini-val">${clients} Devices</td>
                                                </tr>
                                                <tr>
                                                    <td class="mini-label">Uptime</td>
                                                    <td class="mini-val small">${uptime}</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="mini-footer">
                                            ${remoteLink}
                                        </div>
                                    `;
                                    
                                    if(marker.getPopup()) {
                                        marker.getPopup().setContent(content);
                                    }
                                }).fail(function() {
                                    marker.getPopup().setContent("<div class='p-2 text-danger'>Koneksi Gagal (Network Error)</div>");
                                });
                            });

                            // Garis Koneksi (Warna Dinamis)
                            L.polyline([serverLoc, deviceLoc], {
                                color: lineColor, // Merah jika offline
                                weight: isOnline ? 2 : 1, 
                                opacity: 0.7,
                                className: isOnline ? 'flow-line' : '' // Animasi hanya jika online
                            }).addTo(map);
                }
            });
            $('#count-map').text(count);
        });
    }

    $(document).ready(loadMapData);
</script>

</body>
</html>