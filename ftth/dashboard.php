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
    <title>Dashboard FTTH</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css" />
    <style>
        body { background-color: #f4f6f9; }
        .dark-tiles {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        .navbar { border-bottom: 3px solid #007bff; }
        .card-stats .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-stats .card-icon {
            font-size: 3rem;
            opacity: 0.3;
        }
        .card-stats h5 {
            font-weight: bold;
            font-size: 2.5rem;
        }
        .card-stats a {
            text-decoration: none;
            color: inherit;
        }
        .card-stats a:hover .card {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
        }
        .card {
            transition: all 0.3s ease;
        }
        #overviewMap {
            height: 500px;
            width: 100%;
            border-radius: .25rem;
        }
        /* Icons for map */
        .ftth-icon-otb, .ftth-icon-odc, .ftth-icon-odp { font-size: 20px; filter: drop-shadow(0 0 2px #000); text-align: center; }
        .ftth-icon-otb { color: #28a745; }
        .ftth-icon-odc { color: #007bff; }
        .ftth-icon-odp { color: #ffc107; }
        .ftth-icon-pelanggan { font-size: 20px; color: #17a2b8; filter: drop-shadow(0 0 2px #000); text-align: center; }
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
            <li class="nav-item active">
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
            <li class="nav-item">
                <a class="nav-link" href="map.php"><i class="fas fa-map-marked-alt"></i> Peta Jaringan</a>
            </li>
        </ul>
        <a href="../index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Utama</a>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row card-stats">
        <!-- OTB Card -->
        <div class="col-lg-3 col-md-6">
            <a href="otb.php">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div>
                            <div class="text-muted">Total OTB</div>
                            <h5 id="count-otb">0</h5>
                        </div>
                        <div class="card-icon text-success">
                            <i class="fas fa-server"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <!-- ODC Card -->
        <div class="col-lg-3 col-md-6">
            <a href="odc.php">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div>
                            <div class="text-muted">Total ODC</div>
                            <h5 id="count-odc">0</h5>
                        </div>
                        <div class="card-icon text-primary">
                            <i class="fas fa-hdd"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <!-- ODP Card -->
        <div class="col-lg-3 col-md-6">
            <a href="odp.php">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div>
                            <div class="text-muted">Total ODP</div>
                            <h5 id="count-odp">0</h5>
                        </div>
                        <div class="card-icon text-warning">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <!-- Map Card -->
        <div class="col-lg-3 col-md-6">
            <a href="map.php">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div>
                            <div class="text-muted">Lihat Peta</div>
                            <h5 id="count-routes">0 <small class="h6">Rute</small></h5>
                        </div>
                        <div class="card-icon text-info">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-globe-asia"></i> Tinjauan Peta Jaringan</h5>
        </div>
        <div class="card-body">
            <div id="overviewMap"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>
<script>
    const API_URL = '../include/api.php';
    let overviewMap;

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

    function loadDashboardData() {
        const ftthPromise = $.get(API_URL + '?action=get_ftth_data');
        const routePromise = $.get(API_URL + '?action=get_route_data');

        $.when(ftthPromise, routePromise).done(function(ftthRes, routeRes) {
            const ftthData = ftthRes[0] || [];
            const routeData = routeRes[0] || { automatic: {} };

            // 1. Update Counts
            let counts = { OTB: 0, ODC: 0, ODP: 0, PELANGGAN: 0 };
            ftthData.forEach(item => {
                if (counts.hasOwnProperty(item.type)) {
                    counts[item.type]++;
                }
            });
            $('#count-otb').text(counts.OTB);
            $('#count-odc').text(counts.ODC);
            $('#count-odp').text(counts.ODP);
            const totalRoutes = Object.keys(routeData.automatic || {}).length;
            $('#count-routes').html(`${totalRoutes} <small class="h6">Rute</small>`);

            // 2. Render Map with Marker Clustering
            if (overviewMap) {
                const clusterGroup = L.markerClusterGroup({
                    disableClusteringAtZoom: 18,
                    maxClusterRadius: 45,
                    chunkedLoading: true
                });
                ftthData.forEach(item => {
                    if (!item.lat || !item.lng) return;
                    const m = L.marker([item.lat, item.lng], { icon: getIcon(item.type) })
                        .bindPopup(`<b>${item.type}: ${item.name}</b>`);
                    clusterGroup.addLayer(m);
                });
                overviewMap.addLayer(clusterGroup);

                // Render automatic routes via canvas
                const canvasRenderer = L.canvas({ padding: 0.5 });
                if (routeData.automatic) {
                    for (const key in routeData.automatic) {
                        L.polyline(routeData.automatic[key], {
                            color: '#f8f9fa',
                            weight: 2,
                            opacity: 0.8,
                            dashArray: '8, 8',
                            renderer: canvasRenderer
                        }).addTo(overviewMap);
                    }
                }
            }
        }).fail(() => {
            alert('Gagal memuat data dashboard dari server.');
        });
    }

    // Init
    $(document).ready(function() {
        // Initialize map
        overviewMap = L.map('overviewMap').setView([<?= SERVER_LAT ?>, <?= SERVER_LNG ?>], <?= MAP_INITIAL_ZOOM ?>);
        L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Satellite'
        }).addTo(overviewMap);

        // Load all data
        loadDashboardData();
    });
</script>
</body>
</html>