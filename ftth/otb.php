<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}
define('ITEM_TYPE', 'OTB');
define('ITEM_NAME_PLURAL', 'OTB');
define('ITEM_ICON', 'fa-server');
define('ITEM_COLOR', 'success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola <?= ITEM_NAME_PLURAL ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        body { background-color: #f4f6f9; }
        .navbar { border-bottom: 3px solid #007bff; }
        #map-picker { height: 400px; cursor: crosshair; }
        .table-hover tbody tr:hover { background-color: #f1f1f1; }
        .port-box-container { display: flex; flex-wrap: wrap; gap: 5px; cursor: pointer; }
        .port-box {
            width: 28px; height: 28px; line-height: 28px;
            font-size: 0.7rem; text-align: center; font-weight: bold;
            border: 1px solid #ced4da; border-radius: 4px;
            transition: all 0.2s ease;
        }
        .port-box.available { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .port-box.used { background-color: #dc3545; color: white; border-color: #c82333; }
        #port-detail-container { display: flex; flex-wrap: wrap; gap: 8px; }
        #port-detail-container .port-box { width: 40px; height: 40px; line-height: 40px; font-size: 0.9rem; }
        .table .port-summary { max-width: 250px; } /* Lebarkan kolom port */
        .table .port-box-container { gap: 3px; }
        .table .port-box { width: 18px; height: 18px; line-height: 18px; font-size: 0.6rem; text-align: center; }
        .table .port-box:hover { transform: scale(1.5); z-index: 10; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <a class="navbar-brand" href="../index.php"><i class="fas fa-satellite-dish"></i> GenieACS Manager</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="nav-item active">
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
            <li class="nav-item">
                <a class="nav-link" href="map.php"><i class="fas fa-map-marked-alt"></i> Peta Jaringan</a>
            </li>
        </ul>
        <a href="../index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Utama</a>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-<?= ITEM_COLOR ?>"><i class="fas <?= ITEM_ICON ?>"></i> Daftar <?= ITEM_NAME_PLURAL ?></h5>
            <div>
                <button class="btn btn-success" onclick="downloadTemplate()"><i class="fas fa-file-excel"></i> Template</button>
                <button class="btn btn-secondary" onclick="openImportModal()"><i class="fas fa-file-import"></i> Import</button>
                <button class="btn btn-<?= ITEM_COLOR ?>" onclick="openEditModal()"><i class="fas fa-plus"></i> Tambah Baru</button>
                <button class="btn btn-dark" onclick="saveAllData(event)"><i class="fas fa-save"></i> Simpan Semua Perubahan</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="itemsTable">
                    <thead class="thead-light text-center">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th width="20%">Status Port</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit/Tambah -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="editForm" onsubmit="saveItem(); return false;">
                    <input type="hidden" id="edit-index">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit-name">Nama <?= ITEM_NAME_PLURAL ?></label>
                                <input type="text" class="form-control" id="edit-name" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-port-count">Jumlah Port</label>
                                <input type="number" class="form-control" id="edit-port-count" required min="1">
                            </div>
                            <div class="form-group">
                                <label for="edit-coords">Koordinat (Latitude, Longitude)</label>
                                <input type="text" class="form-control" id="edit-coords" required placeholder="-7.12345, 110.12345">
                            </div>
                            <div class="alert alert-info py-2"><i class="fas fa-info-circle"></i> Klik pada peta untuk memilih atau mengubah koordinat.</div>
                            <button type="submit" class="btn btn-primary btn-block">Simpan Item</button>
                        </div>
                        <div class="col-md-8">
                            <div id="map-picker" class="border rounded"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Port Detail -->
<div class="modal fade" id="portModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="portModalTitle">Detail Status Port</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="port-detail-container"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Data OTB</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    <b>Perhatian:</b> Gunakan file Excel (.xlsx atau .xls) dari tombol Download Template.
                </div>
                <div class="form-group">
                    <input type="file" class="form-control-file" id="importFile" accept=".xlsx, .xls">
                </div>
                <button class="btn btn-primary btn-block" onclick="processImport()">Proses Import Data</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<!-- Tambahkan library SheetJS untuk memproses file Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    const API_URL = '../include/api.php';
    const ITEM_TYPE = '<?= ITEM_TYPE ?>';
    let allFtthData = [];
    let mapPicker, mapMarker;

    function renderTable() {
        const tableBody = $('#itemsTable tbody');
        tableBody.empty();
        rebuildConnections(); // Recalculate connections before rendering

        let counter = 0;
        allFtthData.forEach((item, index) => {
            if (item.type === ITEM_TYPE) {
                counter++;
                const createPortBoxes = (item) => {
                    const portCount = item.details?.port_count || 0;
                    const connections = item.details?.port_connections || {};
                    let boxes = '<div class="port-box-container">';
                    if (portCount === 0) {
                        boxes += '<span class="text-muted small">N/A</span>';
                    } else {
                        for (let i = 1; i <= portCount; i++) {
                            const isUsed = connections.hasOwnProperty(i);
                            const childName = isUsed ? connections[i] : 'Tersedia';
                            const portClass = isUsed ? 'used' : 'available';
                            // Tambahkan nomor port di dalam kotak
                            boxes += `<div class="port-box ${portClass}" title="Port ${i}: ${childName}">${i}</div>`;
                        }
                    }
                    return boxes + '</div>';
                };

                tableBody.append(`
                    <tr>
                        <td class="text-center">${counter}</td>
                        <td>${item.name}</td>
                        <td>${item.lat}</td>
                        <td>${item.lng}</td>
                        <td class="port-summary" onclick="showPortDetails(${index})">${createPortBoxes(item)}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info" onclick="openEditModal(${index})"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteItem(${index})"><i class="fas fa-trash"></i> Hapus</button>
                        </td>
                    </tr>
                `);
            }
        });
        if (counter === 0) {
            tableBody.append('<tr><td colspan="6" class="text-center text-muted">Belum ada data.</td></tr>');
        }
    }

    function openEditModal(index = null) {
        $('#editForm')[0].reset();
        const isNew = index === null;
        $('#modalTitle').text(isNew ? 'Tambah <?= ITEM_NAME_PLURAL ?> Baru' : 'Edit <?= ITEM_NAME_PLURAL ?>');
        $('#edit-index').val(isNew ? '' : index);

        if (!isNew) {
            const item = allFtthData[index];
            $('#edit-name').val(item.name);
            $('#edit-port-count').val(item.details?.port_count || '');
            if (item.lat && item.lng) {
                $('#edit-coords').val(`${item.lat}, ${item.lng}`);
            }
        }
        
        $('#editModal').modal('show');
    }

    function deleteItem(index) {
        if (confirm(`Anda yakin ingin menghapus item "${allFtthData[index].name}"? Perubahan akan permanen setelah Anda menekan "Simpan Semua Perubahan".`)) {
            allFtthData.splice(index, 1);
            renderTable();
        }
    }

    function saveItem() {
        const index = $('#edit-index').val();
        const isNew = index === '';

        let itemData;

        if (isNew) {
            itemData = { type: ITEM_TYPE, details: {} };
        } else {
            itemData = allFtthData[parseInt(index)];
            // Pastikan 'details' adalah objek, bukan array atau null, untuk menjaga konsistensi data.
            if (typeof itemData.details !== 'object' || itemData.details === null || Array.isArray(itemData.details)) {
                itemData.details = {};
            }
        }

        // Update data dari form ke objek
        const coordsVal = $('#edit-coords').val().split(',');
        if (coordsVal.length !== 2) {
            alert('Format koordinat salah. Gunakan format: Latitude, Longitude');
            return;
        }
        const lat = parseFloat(coordsVal[0].trim());
        const lng = parseFloat(coordsVal[1].trim());

        if (isNaN(lat) || isNaN(lng)) {
            alert('Koordinat Latitude atau Longitude tidak valid.');
            return;
        }
        itemData.name = $('#edit-name').val();
        itemData.lat = lat;
        itemData.lng = lng;
        itemData.details.port_count = parseInt($('#edit-port-count').val());

        if (isNew) {
            allFtthData.push(itemData);
        }
        
        const btn = $('#editForm button[type="submit"]');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

        rebuildConnections();

        $.ajax({
            url: API_URL + '?action=save_ftth_data',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(allFtthData),
            success: function(res) {
                if (res && res.status === 'success') {
                    renderTable();
                    $('#editModal').modal('hide');
                } else {
                    alert('GAGAL MENYIMPAN!\n\nServer merespon: ' + (res.message || 'Pesan error tidak tersedia.'));
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = `Gagal menghubungi server.\n\nStatus: ${status}\nError: ${error}`;
                if (xhr.responseText) {
                    errorMsg += `\n\nResponse:\n${xhr.responseText.substring(0, 200)}...`;
                }
                alert(errorMsg);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    }

    function showPortDetails(index) {
        const item = allFtthData[index];
        const portCount = item.details?.port_count || 0;
        const connections = item.details?.port_connections || {};
        
        $('#portModalTitle').text(`Detail Port untuk: ${item.name}`);
        const container = $('#port-detail-container');
        container.empty();

        if (portCount === 0) {
            container.text('Jumlah port belum diatur untuk item ini.');
        } else {
            for (let i = 1; i <= portCount; i++) {
                const isUsed = connections.hasOwnProperty(i);
                const childName = isUsed ? connections[i] : 'Tersedia';
                const portClass = isUsed ? 'used' : 'available';
                container.append(`<div class="port-box ${portClass}" title="Port ${i}: ${childName}">${i}</div>`);
            }
        }
        $('#portModal').modal('show');
    }

    function downloadTemplate() {
        const ws_data = [
            ["Nama_OTB", "Jumlah_Port", "Koordinat"],
            ["OTB-Pusat", 144, "-7.12345, 110.12345"],
            ["OTB-Cabang", 72, "-7.12346, 110.12346"]
        ];
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Template");
        XLSX.writeFile(wb, "Template_OTB.xlsx");
    }

    function openImportModal() {
        $('#importFile').val('');
        $('#importModal').modal('show');
    }

    function processImport() {
        const fileInput = document.getElementById('importFile');
        if (!fileInput.files.length) {
            alert("Pilih file Excel terlebih dahulu.");
            return;
        }
        const file = fileInput.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            const json = XLSX.utils.sheet_to_json(worksheet, {header: 1});
            let importedCount = 0;
            
            for (let i = 1; i < json.length; i++) {
                const row = json[i];
                if (!row || row.length === 0) continue;
                
                const nama = row[0] || "";
                const portCount = parseInt(row[1]) || 0;
                const coord = row[2] ? row[2].toString().split(',') : [];
                const lat = parseFloat(coord[0]);
                const lng = parseFloat(coord[1]);
                
                if (nama.toString().trim() !== "" && !isNaN(lat) && !isNaN(lng) && portCount > 0) {
                    allFtthData.push({
                        type: ITEM_TYPE,
                        name: nama.toString().trim(),
                        lat: lat,
                        lng: lng,
                        details: { port_count: portCount }
                    });
                    importedCount++;
                }
            }
            if (importedCount > 0) {
                alert(importedCount + " data OTB berhasil di-import.\nKlik 'Simpan Semua Perubahan' untuk memperbarui server.");
                renderTable();
                $('#importModal').modal('hide');
            } else {
                alert("Tidak ada data yang valid untuk di-import. Pastikan format Excel sesuai template.");
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function rebuildConnections() {
        // 1. Reset all port connections for OTB and ODC
        allFtthData.forEach(item => {
            if (item.type === 'OTB' || item.type === 'ODC' || item.type === 'ODP') {
                if (item.details && typeof item.details === 'object') {
                    item.details.port_connections = {};
                }
            }
        });

        const itemMap = new Map(allFtthData.map((item, index) => [item.name, { ...item, originalIndex: index }]));

        // 2. Rebuild connections based on child's parent and parent_port properties
        allFtthData.forEach(childItem => {
            let parentName = null;
            let parentPort = null;

            if (childItem.type === 'ODC' && childItem.details?.parent_otb) {
                parentName = childItem.details.parent_otb;
                parentPort = childItem.details.parent_port;
            } else if (childItem.type === 'ODP' && childItem.details?.parent_odc) {
                parentName = childItem.details.parent_odc;
                parentPort = childItem.details.parent_port;
            } else if (childItem.type === 'PELANGGAN' && childItem.details?.parent_odp) {
                parentName = childItem.details.parent_odp;
                parentPort = childItem.details.parent_port;
            }

            if (parentName && parentPort && itemMap.has(parentName)) {
                const parentData = itemMap.get(parentName);
                const parentIndex = parentData.originalIndex;
                
                if (!allFtthData[parentIndex].details) allFtthData[parentIndex].details = {};
                if (!allFtthData[parentIndex].details.port_connections) allFtthData[parentIndex].details.port_connections = {};

                allFtthData[parentIndex].details.port_connections[parentPort] = childItem.name;
            }
        });
    }

    function saveAllData(event) {
        if (!confirm(`Simpan semua perubahan ke server? Ini akan menimpa file data yang ada.`)) return;
        
        rebuildConnections(); // Ensure connections are up-to-date before saving
        const btn = $(event.currentTarget); // Get the button that was clicked
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: API_URL + '?action=save_ftth_data',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(allFtthData),
            success: function(res) {
                if (res && res.status === 'success') {
                    alert('Data berhasil disimpan!');
                } else {
                    // Tampilkan pesan error yang lebih detail dari server
                    alert('GAGAL MENYIMPAN!\n\nServer merespon: ' + (res.message || 'Pesan error tidak tersedia.'));
                }
            },
            error: function(xhr, status, error) {
                // Tampilkan error teknis dari koneksi
                let errorMsg = `Gagal menghubungi server.\n\nStatus: ${status}\nError: ${error}`;
                if (xhr.responseText) {
                    errorMsg += `\n\nResponse:\n${xhr.responseText.substring(0, 200)}...`;
                }
                alert(errorMsg);
            },
            complete: function() {
                // Kembalikan tombol ke keadaan semula
                btn.prop('disabled', false).html(originalText);
            }
        });
    }

    // Init
    $(document).ready(function() {
        $.get(API_URL + '?action=get_ftth_data', function(data) {
            allFtthData = Array.isArray(data) ? data : [];
            renderTable();
        }).fail(() => alert('Gagal memuat data awal dari server.'));

        $('#editModal').on('shown.bs.modal', function () {
            if (!mapPicker) {
                mapPicker = L.map('map-picker').setView([-7.59, 110.21], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapPicker);
                mapPicker.on('click', e => {
                    $('#edit-coords').val(`${e.latlng.lat.toFixed(6)}, ${e.latlng.lng.toFixed(6)}`);
                    if (mapMarker) mapMarker.setLatLng(e.latlng);
                    else mapMarker = L.marker(e.latlng).addTo(mapPicker);
                });
            }
            setTimeout(() => mapPicker.invalidateSize(), 10);

            const coordsVal = $('#edit-coords').val().split(',');
            let lat, lng;
            if (coordsVal.length === 2) {
                lat = parseFloat(coordsVal[0]);
                lng = parseFloat(coordsVal[1]);
            }

            if (mapMarker) mapMarker.remove();
            mapMarker = null;
            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                const pos = [lat, lng];
                mapMarker = L.marker(pos).addTo(mapPicker);
                mapPicker.setView(pos, 16);
            }
        });
    });
</script>
</body>
</html>