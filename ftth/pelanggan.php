<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}
define('ITEM_TYPE', 'PELANGGAN');
define('ITEM_NAME_PLURAL', 'Pelanggan');
define('ITEM_ICON', 'fa-user');
define('ITEM_COLOR', 'info');
define('PARENT_TYPE', 'ODP');
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
            <li class="nav-item">
                <a class="nav-link" href="otb.php"><i class="fas fa-server"></i> OTB</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="odc.php"><i class="fas fa-hdd"></i> ODC</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="odp.php"><i class="fas fa-box"></i> ODP</a>
            </li>
            <li class="nav-item active">
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
                <button class="btn btn-danger" onclick="deleteSelected()"><i class="fas fa-trash-alt"></i> Hapus Terpilih</button>
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
                            <th width="5%"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                            <th width="5%">No</th>
                            <th>Nama Pelanggan</th>
                            <th>ID Pelanggan</th>
                            <th>SN ONT</th>
                            <th>Terhubung ke ODP</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="7" class="text-center">Memuat data...</td></tr>
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
                                <label for="edit-name">Nama Pelanggan</label>
                                <input type="text" class="form-control" id="edit-name" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-customer-id">ID Pelanggan</label>
                                <input type="text" class="form-control" id="edit-customer-id">
                            </div>
                            <div class="form-group">
                                <label for="edit-sn-ont">Serial Number ONT</label>
                                <input type="text" class="form-control" id="edit-sn-ont" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-parent-odp">Induk ODP</label>
                                <select id="edit-parent-odp" class="form-control" onchange="updateParentPorts()"></select>
                            </div>
                            <div class="form-group">
                                <label for="edit-parent-port">Port di ODP</label>
                                <select id="edit-parent-port" class="form-control"></select>
                            </div>
                            <hr>
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

<!-- Modal Import -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Data Pelanggan</h5>
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
    const PARENT_TYPE = '<?= PARENT_TYPE ?>';
    let allFtthData = [];
    let mapPicker, mapMarker;

    function renderTable() {
        const tableBody = $('#itemsTable tbody');
        tableBody.empty();
        let counter = 0;

        allFtthData.forEach((item, index) => {
            if (item.type === ITEM_TYPE) {
                counter++;
                tableBody.append(`
                    <tr>
                        <td class="text-center"><input type="checkbox" class="row-checkbox" value="${index}"></td>
                        <td class="text-center">${counter}</td>
                        <td>${item.name}</td>
                        <td>${item.details?.customer_id || '-'}</td>
                        <td>${item.details?.sn_ont || '-'}</td>
                        <td class="text-center"><span class="badge badge-warning">${item.details?.parent_odp || '-'}</span> Port <b>${item.details?.parent_port || '-'}</b></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info" onclick="openEditModal(${index})"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteItem(${index})"><i class="fas fa-trash"></i> Hapus</button>
                        </td>
                    </tr>
                `);
            }
        });
        if (counter === 0) {
            tableBody.append('<tr><td colspan="7" class="text-center text-muted">Belum ada data pelanggan.</td></tr>');
        }
    }

    function openEditModal(index = null) {
        $('#editForm')[0].reset();
        const isNew = index === null;
        $('#modalTitle').text(isNew ? 'Tambah Pelanggan Baru' : 'Edit Data Pelanggan');
        $('#edit-index').val(isNew ? '' : index);

        const parentDropdown = $('#edit-parent-odp');
        parentDropdown.empty().append('<option value="">-- Pilih ODP --</option>');
        allFtthData.forEach(item => {
            if (item.type === PARENT_TYPE) {
                parentDropdown.append(`<option value="${item.name}">${item.name}</option>`);
            }
        });

        let currentParent, currentPort;
        if (!isNew) {
            const item = allFtthData[index];
            $('#edit-name').val(item.name);
            $('#edit-customer-id').val(item.details?.customer_id || '');
            $('#edit-sn-ont').val(item.details?.sn_ont || '');
            currentParent = item.details?.parent_odp || '';
            currentPort = item.details?.parent_port || '';
            parentDropdown.val(currentParent);
            if (item.lat && item.lng) {
                $('#edit-coords').val(`${item.lat}, ${item.lng}`);
            }
        }
        
        updateParentPorts(currentParent, currentPort);
        $('#editModal').modal('show');
    }

    function updateParentPorts(selectedParent = null, selectedPort = null) {
        const parentName = selectedParent !== null ? selectedParent : $('#edit-parent-odp').val();
        const portDropdown = $('#edit-parent-port');
        portDropdown.empty().prop('disabled', true);

        if (!parentName) return;

        const parent = allFtthData.find(i => i.name === parentName);
        if (!parent || !parent.details?.port_count) return;

        const parentPortCount = parent.details.port_count;
        const usedPorts = new Map();
        const currentItemName = $('#edit-name').val();

        // Find all ports used by other customers on the same parent ODP
        allFtthData.forEach(item => {
            if (item.type === ITEM_TYPE && item.details?.parent_odp === parentName && item.name !== currentItemName) {
                usedPorts.set(String(item.details.parent_port), item.name);
            }
        });

        portDropdown.append('<option value="">-- Pilih Port --</option>');
        for (let i = 1; i <= parentPortCount; i++) {
            if (!usedPorts.has(String(i))) {
                portDropdown.append(`<option value="${i}">Port ${i}</option>`);
            } else {
                // If the port is used by another device, disable it
                portDropdown.append(`<option value="${i}" disabled>Port ${i} (by ${usedPorts.get(String(i))})</option>`);
            }
        }

        // If we are editing, ensure the currently selected port is available in the dropdown
        if (selectedPort) {
             const currentOption = portDropdown.find(`option[value="${selectedPort}"]`);
             if(currentOption) currentOption.prop('disabled', false);
             portDropdown.val(selectedPort);
        }
        portDropdown.prop('disabled', false);
    }

    function deleteItem(index) {
        if (confirm(`Anda yakin ingin menghapus pelanggan "${allFtthData[index].name}"?`)) {
            allFtthData.splice(index, 1);
            renderTable();
        }
    }

    function toggleSelectAll(source) {
        $('.row-checkbox').prop('checked', source.checked);
    }

    function deleteSelected() {
        const selected = [];
        $('.row-checkbox:checked').each(function() {
            selected.push(parseInt($(this).val()));
        });
        if (selected.length === 0) {
            alert("Pilih setidaknya satu data untuk dihapus.");
            return;
        }
        if (confirm(`Anda yakin ingin menghapus ${selected.length} data pelanggan terpilih?\nPerubahan akan permanen setelah Anda menekan "Simpan Semua Perubahan".`)) {
            // Sort dari index terbesar ke terkecil agar saat dihapus (splice), index di bawahnya tidak bergeser salah
            selected.sort((a, b) => b - a).forEach(index => allFtthData.splice(index, 1));
            $('#selectAll').prop('checked', false);
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
            if (typeof itemData.details !== 'object' || itemData.details === null || Array.isArray(itemData.details)) {
                itemData.details = {};
            }
        }

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
        itemData.details.customer_id = $('#edit-customer-id').val();
        itemData.details.sn_ont = $('#edit-sn-ont').val();
        itemData.details.parent_odp = $('#edit-parent-odp').val();
        itemData.details.parent_port = $('#edit-parent-port').val();

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

    function rebuildConnections() {
        allFtthData.forEach(item => {
            if (item.type === 'OTB' || item.type === 'ODC' || item.type === 'ODP') {
                if (item.details && typeof item.details === 'object') {
                    item.details.port_connections = {};
                }
            }
        });
        const itemMap = new Map(allFtthData.map((item, index) => [item.name, { ...item, originalIndex: index }]));
        allFtthData.forEach(childItem => {
            let parentName = null, parentPort = null;
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
        
        rebuildConnections();

        const btn = $(event.currentTarget);
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

    function downloadTemplate() {
        const ws_data = [
            ["Nama_Pelanggan", "ID_Pelanggan", "SN_ONT", "Induk_ODP", "Port_ODP", "Koordinat"],
            ["Budi Santoso", "CUST-001", "ZTEGC1234567", "ODP-A", 1, "-7.12345, 110.12345"],
            ["Siti Aminah", "CUST-002", "HWTC12345678", "ODP-A", 2, "-7.12346, 110.12346"]
        ];
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Template");
        XLSX.writeFile(wb, "Template_Pelanggan.xlsx");
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
            
            // Konversi sheet menjadi array of arrays
            const json = XLSX.utils.sheet_to_json(worksheet, {header: 1});
            
            let importedCount = 0;
            // Mulai dari index 1 untuk melewati baris Header tabel
            for (let i = 1; i < json.length; i++) {
                const row = json[i];
                if (!row || row.length === 0) continue;
                
                const nama = row[0] || "";
                const custId = row[1] || "";
                const sn = row[2] || "";
                const odp = row[3] || "";
                const port = row[4] || "";
                const coord = row[5] ? row[5].toString().split(',') : [];
                const lat = parseFloat(coord[0]);
                const lng = parseFloat(coord[1]);
                
                if (nama.toString().trim() !== "" && !isNaN(lat) && !isNaN(lng)) {
                    allFtthData.push({
                        type: ITEM_TYPE,
                        name: nama.toString().trim(),
                        lat: lat,
                        lng: lng,
                        details: { 
                            customer_id: custId.toString().trim(), 
                            sn_ont: sn.toString().trim(), 
                            parent_odp: odp.toString().trim(), 
                            parent_port: port.toString().trim() 
                        }
                    });
                    importedCount++;
                }
            }
            if (importedCount > 0) {
                alert(importedCount + " data pelanggan berhasil di-import.\nKlik 'Simpan Semua Perubahan' untuk memperbarui server.");
                renderTable();
                $('#importModal').modal('hide');
            } else {
                alert("Tidak ada data yang valid untuk di-import. Pastikan format Excel sesuai template.");
            }
        };
        reader.readAsArrayBuffer(file);
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