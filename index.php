<?php
// C:\xampp\htdocs\genieacs\index.php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GenieACS Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        body { background-color: #f4f6f9; font-size: 0.8rem; }
        .table td { vertical-align: middle; padding: 0.4rem; }
        .badge-tag { font-size: 85%; }
        .ip-link { color: #2362a5; font-weight: bold; text-decoration: none; }
        .ip-link:hover { text-decoration: underline; }
        .limit-text {
            max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
        }
        /* FTTH Editor Icons */
        .ftth-icon-otb { color: #28a745; font-size: 24px; filter: drop-shadow(0 0 2px #000); }
        .ftth-icon-odc { color: #007bff; font-size: 22px; filter: drop-shadow(0 0 2px #000); }
        .ftth-icon-odp { color: #ffc107; font-size: 20px; filter: drop-shadow(0 0 2px #000); }
        .btn-xs { padding: .1rem .4rem; font-size: .7rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary shadow mb-4 justify-content-between py-1">
    <a class="navbar-brand font-weight-bold" href="#" style="font-size: 1.2rem;"><i class="fas fa-satellite-dish"></i> GenieACS Manager</a>
    <div class="btn-group">
        <button class="btn btn-sm btn-light shadow-sm mr-2" onclick="openSettings()"><i class="fas fa-cog"></i> Settings</button>
        <a href="logout.php" class="btn btn-sm btn-danger shadow-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="container-fluid px-3">
    <div class="card shadow">
        <div class="card-header bg-white py-2">
            <div class="row">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari: Username, IP, SN..." onkeyup="if(event.keyCode===13) searchDevice()">
                </div>
                <div class="col-md-2">
                    <select id="limitSelect" class="form-control form-control-sm" onchange="searchDevice()">
                        <option value="50">50 Data</option>
                        <option value="100">100 Data</option>
                        <option value="150">150 Data</option>
                        <option value="200">200 Data</option>
                        <option value="500">500 Data</option>
                        <option value="1000">1000 Data</option>
                        <option value="2000">2000 Data</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="btn-group w-100">
                        <button class="btn btn-primary btn-sm" onclick="searchDevice()">Cari</button>
                        <button class="btn btn-secondary btn-sm" onclick="searchDevice()"><i class="fas fa-sync"></i> Refresh</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <a href="map.php" class="btn btn-success btn-sm w-100" title="Lihat Peta"><i class="fas fa-map-marked-alt"></i> Peta</a>
                </div>
                <div class="col-md-2">
                    <a href="ftth/dashboard.php" class="btn btn-info btn-sm w-100"><i class="fas fa-project-diagram"></i> Topologi FTTH</a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0" id="deviceTable">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th width="3%">No</th>
                            <th width="12%">TAG</th>
                            <th width="5%">Mode</th>
                            <th width="15%">Username / SN</th>
                            <th width="10%">IP Service</th> <th width="10%">IP Mgmt</th>    <th width="12%">SSID</th>
                            <th width="5%">Client</th>
                            <th width="7%">RX (dBm)</th>
                            <th width="5%">Temp</th>
                            <th width="8%">Uptime</th>
                            <th width="1%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header bg-info text-white py-2"><h5 class="modal-title">Detail: <span id="detTitle"></span></h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><div class="row"><div class="col-md-6"><h6 class="text-primary font-weight-bold">Info Perangkat</h6><table class="table table-sm table-bordered"><tr><th width="35%">Model</th><td id="d_model">-</td></tr><tr><th>Tags</th><td id="d_tags" class="font-weight-bold text-info">-</td></tr><tr><th>Serial Number</th><td id="d_sn">-</td></tr><tr><th>Software</th><td id="d_sw">-</td></tr><tr><th>Uptime</th><td id="d_uptime">-</td></tr><tr><th>Clients</th><td id="d_clients">-</td></tr></table><h6 class="text-primary font-weight-bold mt-3">Optik (PON)</h6><div class="row text-center"><div class="col-4"><div class="border p-2 rounded">RX<br><strong id="d_rx">-</strong></div></div><div class="col-4"><div class="border p-2 rounded">Temp<br><strong id="d_temp">-</strong></div></div><div class="col-4"><div class="border p-2 rounded">Mode<br><strong id="d_mode">-</strong></div></div></div></div><div class="col-md-6"><h6 class="text-primary font-weight-bold" id="wan_title">WAN</h6><table class="table table-sm table-bordered"><tr><th width="35%">IP Address</th><td id="d_ip" class="font-weight-bold text-success">-</td></tr><tr><th id="lbl_user_gw">Username</th><td id="d_user_gw">-</td></tr><tr><th id="lbl_pass_mask">Password</th><td id="d_pass_mask">-</td></tr><tr><th>MAC</th><td id="d_mac">-</td></tr></table><h6 class="text-primary font-weight-bold mt-3">WLAN</h6><div class="alert alert-secondary py-2"><strong>SSID:</strong> <span id="d_ssid_all">-</span><br><strong>Pass:</strong> <code id="d_wifi_pass">-</code></div></div></div></div><div class="modal-footer bg-light py-2"><a href="#" id="btn_webui" target="_blank" class="btn btn-outline-primary btn-sm">Remote Web</a><button onclick="rebootDevice()" class="btn btn-danger btn-sm">Reboot</button><button data-dismiss="modal" class="btn btn-secondary btn-sm">Tutup</button></div></div></div></div>
<div class="modal fade" id="wifiModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-warning py-2"><h5 class="modal-title">Ganti WiFi</h5><button class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><input type="hidden" id="editDeviceId"><div class="form-group"><label>SSID</label><input type="text" id="editSSID" class="form-control"></div><div class="form-group"><label>Password</label><input type="text" id="editPass" class="form-control"></div></div><div class="modal-footer py-2"><button onclick="saveWifi()" class="btn btn-primary">Simpan</button></div></div></div></div>
<div class="modal fade" id="tagModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-dark text-white py-2"><h5 class="modal-title" id="tagModalTitle">Kelola Tag</h5><button class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body"><input type="hidden" id="tagDeviceId"><input type="hidden" id="tagDeviceSn"><div class="form-group"><label>Isi Tag (Kosongkan untuk menghapus)</label><input type="text" id="inputTag" class="form-control" placeholder="Contoh: ODP-A, VIP, GPS:-7.5,110.2"></div></div><div class="modal-footer py-2"><button onclick="saveTag()" class="btn btn-primary">Simpan</button></div></div></div></div>

<!-- Modal Settings -->
<div class="modal fade" id="settingsModal"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-dark text-white py-2"><h5 class="modal-title">Pengaturan Sistem</h5><button class="close text-white" data-dismiss="modal">&times;</button></div><div class="modal-body">
    <form id="settingsForm">
        <div class="row">
            <div class="col-md-6">
                <h6 class="font-weight-bold text-primary">GenieACS NBI</h6>
                <div class="form-group mb-2"><label class="small mb-0">Host IP</label><input type="text" name="genie_host" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Port</label><input type="text" name="genie_port" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Username</label><input type="text" name="genie_user" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Password</label><input type="password" name="genie_pass" class="form-control form-control-sm"></div>
            </div>
            <div class="col-md-6">
                <h6 class="font-weight-bold text-primary">Dashboard & Map</h6>
                <div class="form-group mb-2"><label class="small mb-0">User Login</label><input type="text" name="dash_user" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Pass Login</label><input type="text" name="dash_pass" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Lat Server</label><input type="text" name="server_lat" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Lng Server</label><input type="text" name="server_lng" class="form-control form-control-sm"></div>
                <div class="form-group mb-2"><label class="small mb-0">Map Initial Zoom</label><input type="number" name="map_zoom" class="form-control form-control-sm" placeholder="Contoh: 13"></div>
            </div>
        </div>
        <hr class="my-2">
        <h6 class="font-weight-bold text-primary">Telegram Bot</h6>
        <div class="row">
            <div class="col-md-8"><div class="form-group mb-2"><label class="small mb-0">Bot Token</label><input type="text" name="telegram_token" class="form-control form-control-sm"></div></div>
            <div class="col-md-4"><div class="form-group mb-2"><label class="small mb-0">Admin Chat ID</label><input type="text" name="telegram_admin_id" class="form-control form-control-sm"></div></div>
        </div>
    </form>
</div><div class="modal-footer py-2"><button onclick="saveSettings()" class="btn btn-primary btn-sm">Simpan Perubahan</button></div></div></div></div>

<!-- Modal FTTH Editor -->
<!-- Modal FTTH Editor (Dihapus, fungsionalitas dipindah ke /ftth/) -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<script>
    const API = 'include/api.php';
    let curId = '';

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

    // --- FUNGSI SCANNING IP REKURSIF (Sapu Jagat) ---
    // Mencari IP di semua index WAN (1, 2, 3...)
    function scanAllIps(igd) {
        let result = { ip: '', type: '', user: '' };
        
        let wanDevices = igd.WANDevice || {};
        
        // Loop WANDevice (biasanya index 1)
        for (let wKey in wanDevices) {
            if(wKey === '_object') continue;
            let connDevices = wanDevices[wKey].WANConnectionDevice || {};
            
            // Loop WANConnectionDevice (index 1, 2, 3...)
            for (let cKey in connDevices) {
                if(cKey === '_object') continue;
                let conns = connDevices[cKey];

                // Cek PPPoE
                let ppps = conns.WANPPPConnection || {};
                for (let pKey in ppps) {
                    let pObj = ppps[pKey];
                    let ip = clean(pObj.ExternalIPAddress, '');
                    if (ip && ip !== '0.0.0.0' && ip !== '-') {
                        return { ip: ip, type: 'PPPoE', user: clean(pObj.Username, '') };
                    }
                }

                // Cek IP/Static/DHCP
                let ips = conns.WANIPConnection || {};
                for (let iKey in ips) {
                    let iObj = ips[iKey];
                    let ip = clean(iObj.ExternalIPAddress, '');
                    if (ip && ip !== '0.0.0.0' && ip !== '-') {
                        // Simpan tapi jangan return dulu, prioritaskan PPPoE jika ada di iterasi lain
                        if(!result.ip) {
                            result = { ip: ip, type: 'Static/DHCP', user: '' };
                        }
                    }
                }
            }
        }
        return result;
    }

    function searchDevice() {
        let k = $('#searchInput').val();
        let l = $('#limitSelect').val();
        $('#deviceTable tbody').html('<tr><td colspan="12" class="text-center">Loading...</td></tr>');

        $.get(API + '?action=search&keyword=' + encodeURIComponent(k) + '&limit=' + l, function(res) {
            let html = '';
            
            // 1. Cek Error Spesifik dari API
            if(res.error) {
                let errMsg = res.error + (res.details ? ': ' + res.details : '');
                $('#deviceTable tbody').html('<tr><td colspan="12" class="text-center text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> ' + errMsg + '</td></tr>');
                return;
            }

            // 2. Cek apakah data kosong (Array valid tapi panjang 0)
            if(!res || !Array.isArray(res) || res.length === 0) html = '<tr><td colspan="12" class="text-center text-muted">Data tidak ditemukan</td></tr>';
            else {
                res.forEach((d, i) => {
                    let id = d._id; 
                    // Amankan ID dari tanda kutip tunggal, ganda, dan backslash agar tidak merusak HTML onclick
                    let safeId = id.replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/"/g, "&quot;");
                    
                    let localTag = d._local_tag || '';
                    let safeLocalTag = localTag.replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/"/g, "&quot;");

                    let tag = d._tags ? d._tags.join(', ') : '-';
                    let vp = d.VirtualParameters || {};
                    let igd = d.InternetGatewayDevice || {};
                    let dev = d.Device || {};
                    let sn = clean(vp.getSerialNumber, '') || getSafe(igd, 'DeviceInfo.SerialNumber', '') || getSafe(dev, 'DeviceInfo.SerialNumber', '') || '';

                    // --- 1. SCANNING IP & USERNAME OTOMATIS ---
                    let scan = scanAllIps(igd);
                    let serviceIp = scan.ip; // IP Hasil Scan (PPPoE/Static)
                    
                    // Fallback IP jika scan gagal, coba ambil dari VP
                    if(!serviceIp) serviceIp = clean(vp.pppoeIP, '');
                    if(serviceIp === '0.0.0.0') serviceIp = '';

                    // Username Logic (Cleaned HTML issue)
                    let userDisplay = '-';
                    if (scan.user) {
                        userDisplay = scan.user;
                    } else {
                        // Cek VP Username
                        let vpUser = clean(vp.pppoeUsername, '');
                        if (vpUser && vpUser !== '-') userDisplay = vpUser;
                        else userDisplay = id; // Fallback ke Serial Number
                    }

                    // --- 2. IP MANAGEMENT / WAN (Source IP) ---
                    // Menggunakan d._ip (IP Source Packet) atau d._lastInform.ip
                    let mgmtIp = clean(d._ip, '');
                    if(!mgmtIp || mgmtIp==='-') mgmtIp = (d._lastInform && d._lastInform.ip) ? d._lastInform.ip : '-';

                    // --- 3. IP REMOTE LINK ---
                    // Prioritas: Service IP > Mgmt IP
                    let remoteIp = serviceIp || mgmtIp;
                    if(remoteIp === '-') remoteIp = '';

                    // --- DATA LAIN ---
                    let ssid = clean(vp.SSID, '');
                    if(!ssid || ssid==='-') ssid = clean(vp.SSID_ALL, '');
                    if(!ssid || ssid==='-') ssid = getSafe(igd, 'LANDevice.1.WLANConfiguration.1.SSID', '-');

                    let clients = clean(vp.activedevices, '0');
                    let rx = clean(vp.RXPower);
                    let temp = clean(vp.gettemp);
                    let ponMode = clean(vp.getponmode, 'Unknown');
                    let uptime = clean(vp.getdeviceuptime);
                    
                    let rxVal = parseFloat(rx);
                    let rxColor = (!isNaN(rxVal) && rxVal < -27) ? 'text-danger font-weight-bold' : 'text-success';

                    // Tombol Remote Logic
                    let btnRemote = (remoteIp) ? `<a href="http://${remoteIp}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-globe"></i></a>` : `<button class="btn btn-sm btn-secondary disabled"><i class="fas fa-globe"></i></button>`;
                    
                    // Link Text Logic
                    let linkService = (serviceIp) ? `<a href="http://${serviceIp}" target="_blank" class="ip-link small">${serviceIp}</a>` : `<span class="text-muted small">-</span>`;
                    let linkMgmt = (mgmtIp !== '-') ? `<a href="http://${mgmtIp}" target="_blank" class="ip-link small text-secondary">${mgmtIp}</a>` : `<span class="text-muted small">-</span>`;

                    // Tombol Tag: Kuning (Edit) jika ada tag lokal, Abu-abu (Tambah) jika belum ada
                    let btnTagColor = localTag ? 'btn-warning' : 'btn-secondary';
                    let btnTagIcon = localTag ? 'fa-edit' : 'fa-tag';

                    html += `<tr>
                        <td class="text-center font-weight-bold">${i+1}</td>
                        <td><small class="limit-text" title="${tag}"><span class="badge badge-info badge-tag">${tag}</span></small></td>
                        <td>${ponMode}</td>
                        <td><span class="limit-text font-weight-bold text-dark" title="${userDisplay}">${userDisplay}</span></td>
                        <td>${linkService}</td>
                        <td>${linkMgmt}</td>
                        <td><span class="limit-text" title="${ssid}">${ssid}</span></td>
                        <td class="font-weight-bold text-primary">${clients}</td>
                        <td class="${rxColor}">${rx}</td>
                        <td>${temp}</td>
                        <td><small>${uptime}</small></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-info" onclick="det('${safeId}')"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-warning" onclick="wifi('${safeId}','${ssid}')"><i class="fas fa-key"></i></button>
                                <button class="btn btn-sm ${btnTagColor}" onclick="editTag('${safeId}', '${sn}', '${safeLocalTag}')" title="Kelola Tag"><i class="fas ${btnTagIcon}"></i></button>
                                ${btnRemote}
                            </div>
                        </td>
                    </tr>`;
                });
            }
            $('#deviceTable tbody').html(html);
        }).fail(function(xhr, status, error) {
            // Tampilkan error jika request gagal (bukan loading terus)
            let msg = xhr.status + " " + xhr.statusText;
            if(xhr.responseText) msg += "<br><small>" + xhr.responseText.substring(0, 100) + "...</small>";
            $('#deviceTable tbody').html('<tr><td colspan="12" class="text-center text-danger font-weight-bold">Gagal memuat data!<br>' + msg + '</td></tr>');
        });
    }

    // Modal Detail Logic (Sederhana)
    function det(id) {
        curId = id; 
        $('#detTitle').text(decodeURIComponent(id)); 
        $('#detailModal').modal('show');
        
        // Reset ke status memuat
        $('#d_model, #d_tags, #d_sn, #d_sw, #d_uptime, #d_rx, #d_temp, #d_mode, #d_ip, #d_user_gw, #d_mac, #d_ssid_all, #d_wifi_pass').text('...');
        $('#d_clients').text('0');
        $('#btn_webui').addClass('disabled').attr('href', '#');

        $.get(API + '?action=get_detail&id='+encodeURIComponent(id), function(d) {
            if (!d || d.error) {
                alert('Gagal mengambil data perangkat: ' + (d && d.error ? d.error : 'Data kosong'));
                $('#d_model, #d_tags, #d_sn, #d_sw, #d_uptime, #d_rx, #d_temp, #d_mode, #d_ip, #d_user_gw, #d_mac, #d_ssid_all, #d_wifi_pass').text('-');
                return;
            }
            let vp = d.VirtualParameters || {};
            let igd = d.InternetGatewayDevice || {};
            let dev = d.Device || {};

            let model = clean(vp.model, '') || d._deviceId?._ProductClass || clean(igd.DeviceInfo?.ModelName, '') || clean(dev.DeviceInfo?.ModelName, '-');
            $('#d_model').text(model || '-');
            
            let tags = d._tags && d._tags.length > 0 ? d._tags.join(', ') : (d._local_tag || '-');
            $('#d_tags').text(tags);

            let sn = clean(vp.getSerialNumber, '') || d._deviceId?._SerialNumber || clean(igd.DeviceInfo?.SerialNumber, '') || clean(dev.DeviceInfo?.SerialNumber, '-');
            $('#d_sn').text(sn || '-');
            
            let sw = clean(igd.DeviceInfo?.SoftwareVersion, '') || clean(dev.DeviceInfo?.SoftwareVersion, '-');
            $('#d_sw').text(sw || '-');
            
            let uptime = clean(vp.getdeviceuptime, '') || clean(igd.DeviceInfo?.UpTime, '') || clean(dev.DeviceInfo?.UpTime, '-');
            $('#d_uptime').text(uptime || '-');
            
            $('#d_clients').text(clean(vp.activedevices, '0'));
            
            let rx = clean(vp.RXPower, '-');
            let rxVal = parseFloat(rx);
            $('#d_rx').text(rx).css('color', (!isNaN(rxVal) && rxVal < -27) ? 'red':'green');
            
            $('#d_temp').text(clean(vp.gettemp, '-')); 
            $('#d_mode').text(clean(vp.getponmode, '-'));

            // Scan lagi untuk detail
            let scan = scanAllIps(igd);
            let ip = scan.ip || clean(vp.pppoeIP, '');
            let user = scan.user || clean(vp.pppoeUsername, '-');

            $('#d_ip').text(ip || '-');
            $('#d_user_gw').text(user);
            $('#d_pass_mask').text('******'); // Password hidden
            
            let mac = clean(vp.pppoeMac, '') || clean(vp.PonMac, '') || getSafe(igd, 'LANDevice.1.LANEthernetInterfaceConfig.1.MACAddress', '-');
            $('#d_mac').text(mac || '-'); 

            if(ip && ip !== '-') $('#btn_webui').attr('href', 'http://'+ip).removeClass('disabled');
            else $('#btn_webui').addClass('disabled').attr('href', '#');

            // --- LOGIKA SSID & PASSWORD (LEBIH LENGKAP) ---
            let ssid = clean(vp.SSID, '') || clean(vp.SSID_ALL, '') || getSafe(igd, 'LANDevice.1.WLANConfiguration.1.SSID', '-');
            $('#d_ssid_all').text(ssid);

            let pass = clean(vp.WlanPassword, '') || clean(vp.wlanPassword, '');
            if(!pass || pass === '-') pass = getSafe(igd, 'LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey', '');
            if(!pass || pass === '-') pass = getSafe(igd, 'LANDevice.1.WLANConfiguration.1.KeyPassphrase', '');
            if(!pass || pass === '-') pass = getSafe(igd, 'LANDevice.1.WLANConfiguration.1.PreSharedKey', '');
            $('#d_wifi_pass').text(pass || '-');
        }).fail(function(xhr) {
            alert('Gagal menghubungi server API: ' + xhr.status + ' ' + xhr.statusText);
        });
    }
    
    function wifi(id, s) { $('#editDeviceId').val(id); $('#editSSID').val(s); $('#wifiModal').modal('show'); }
    function saveWifi() { 
        let id=$('#editDeviceId').val(), s=$('#editSSID').val(), p=$('#editPass').val();
        if(!p && !confirm('Pass kosong?')) return;
        $.post(API+'?action=update_wifi', {device_id:id, ssid:s, password:p}, (res)=> { 
            if(res.status === 'success') {
                alert('Perintah ganti WiFi telah dikirim ke modem!');
                $('#wifiModal').modal('hide'); 
                searchDevice(); 
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan pada server'));
            }
        }, 'json');
    }

    function editTag(id, sn, currentTag) { 
        $('#tagDeviceId').val(id); 
        $('#tagDeviceSn').val(sn); 
        $('#inputTag').val(currentTag); // Isi otomatis dengan tag lama
        $('#tagModalTitle').text(currentTag ? 'Edit Tag' : 'Tambah Tag');
        $('#tagModal').modal('show'); 
    }

    function saveTag() {
        let id=$('#tagDeviceId').val(), sn=$('#tagDeviceSn').val(), t=$('#inputTag').val();
        
        let btn = $('#tagModal .btn-primary');
        btn.prop('disabled', true).text('Menyimpan...');

        // Tambahkan 'json' di parameter terakhir agar response diparsing otomatis
        $.post(API+'?action=add_tag', {device_id:id, sn:sn, tag:t}, function(res) {
            if(res.status === 'success') {
                alert('Tag berhasil disimpan!');
                $('#tagModal').modal('hide'); 
                searchDevice();
            } else {
                alert('Gagal: ' + (res.message || 'Unknown Error') + '\n' + (res.details || ''));
            }
        }, 'json').fail(function() {
            alert('Gagal menghubungi server API.');
        }).always(function() { btn.prop('disabled', false).text('Simpan'); });
    }

    function rebootDevice() { if(confirm('Reboot?')) $.post(API+'?action=reboot', {device_id:curId}, ()=> { alert('Sent'); $('#detailModal').modal('hide'); }); }

    function openSettings() {
        $.get(API + '?action=get_settings')
            .done(function(d) {
                for (let key in d) {
                    $(`#settingsForm [name="${key}"]`).val(d[key]);
                }
                $('#settingsModal').modal('show');
            })
            .fail(function(xhr) {
                alert("Gagal memuat pengaturan. Status: " + xhr.status + " (" + xhr.statusText + ")");
            });
    }

    function saveSettings() {
        let data = $('#settingsForm').serialize();
        $.post(API + '?action=save_settings', data, function(res) {
            if(res.status === 'success') {
                alert('Pengaturan berhasil disimpan! Halaman akan direfresh.');
                location.reload();
            } else { alert('Gagal menyimpan pengaturan.'); }
        }, 'json');
    }

    $(document).ready(searchDevice);
</script>

</body>
</html>