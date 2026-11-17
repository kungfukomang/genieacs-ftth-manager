# 🌐 GenieACS Manager & FTTH Network Topology GIS

[![PHP Version](https://img.shields.io/badge/PHP-7.4%20|%208.0%20|%208.1%20|%208.2-blue.svg)](https://www.php.net/)
[![GenieACS](https://img.shields.io/badge/GenieACS-v1.2.x-orange.svg)](https://genieacs.com/)
[![Leaflet](https://img.shields.io/badge/Leaflet-1.7.1-green.svg)](https://leafletjs.com/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**GenieACS Manager & FTTH Network GIS** adalah dashboard berbasis web yang komprehensif, modern, dan ringan untuk memonitor perangkat CPE/ONT melalui **GenieACS (TR-069)** sekaligus mengelola topologi jaringan kabel optik **FTTH (Fiber to the Home)** secara visual di atas peta interaktif.

---
<img width="1677" height="429" alt="Screenshot_4" src="https://github.com/user-attachments/assets/485cc5fe-25df-44a7-a863-d378051644c6" />
<img width="1677" height="839" alt="Screenshot_3" src="https://github.com/user-attachments/assets/32172c7d-79e7-4837-835c-f09cc899b142" />
<img width="1159" height="477" alt="Screenshot_2" src="https://github.com/user-attachments/assets/3a53299e-a5ad-4a2b-9c4e-41e2aabfad1f" />
<img width="1666" height="561" alt="Screenshot_1" src="https://github.com/user-attachments/assets/ab3c710b-cd2e-48d6-b902-56e1cdb6b002" />
<img width="1683" height="834" alt="Screenshot_5" src="https://github.com/user-attachments/assets/d2577ba2-9e8d-4ce1-9124-4be744867487" />


## ✨ Fitur Utama

### 📡 1. GenieACS TR-069 Device Manager
- **Status Real-Time:** Monitoring status online/offline, Redaman Optik (Rx Power dBm), IP Address PPPoE, Username, Suhu ONT, Uptime, Nama SSID, dan Jumlah Klien Wi-Fi yang sedang aktif.
- **Remote Control ONT:** Kirim perintah **Reboot ONT** dan **Summon / Connection Request (CR)** langsung dari web via GenieACS NBI (Northbound Interface).
- **Pencarian Cepat:** Cari perangkat berdasarkan Nama Pelanggan, Nomor Serial (SN), IP Address, atau Username PPPoE.
- **Live GIS Map ONT:** Visualisasi lokasi pelanggan ONT aktif dengan indikator warna status koneksi dan redaman sinyal.

### 🗺️ 2. Modul Topologi Jaringan FTTH GIS (`/ftth/`)
- **Hierarki Lengkap:** Pengelolaan terintegrasi untuk:
  - **OTB (Optical Termination Box)** — Pusat Server / Central Office
  - **ODC (Optical Distribution Cabinet)** — Titik Distribusi Utama
  - **ODP (Optical Distribution Point)** — Kotak Pembagi ke Pelanggan
  - **Pelanggan (Drop Core)** — Sambungan langsung ke ONT rumah pelanggan
- **Manajemen Port Visual:** Tampilan kotak port bernomor untuk melihat port yang tersedia (*idle*) dan terpakai (*used*).
- **Rute Otomatis Mengikuti Jalan:** Rute kabel ODC ke OTB dan ODP ke ODC otomatis mengikuti jalanan (*foot/road routing* via OSRM) dan disimpan dalam cache lokal.
- **Animasi Optik Modern (*Photon Pulse*):**
  - **Jalur Backbone (ODC ➔ OTB):** Garis Cyan Neon tebal dengan animasi partikel laser biru neon elektrik (`#00f2fe`).
  - **Jalur Distribusi (ODP ➔ ODC):** Garis Golden Amber dengan animasi partikel emas hangat (`#ffd600`).
  - **Jalur Drop Core (Pelanggan ➔ ODP):** Garis tipis teal yang rapi dan efisien.
- **Ultra High Performance:**
  - **Marker Clustering (`Leaflet.markercluster`):** Mengelompokkan ribuan titik pelanggan & ODP saat peta diperkecil (*zoom out*) agar browser bebas lag.
  - **Akselerasi GPU Canvas:** Menggambar ribuan kabel di satu canvas GPU tanpa membebani DOM.
  - **Kompresi GZIP:** Mengurangi ukuran transfer payload hingga **93%**.
- **Ekspor Google Earth (KML):** Sekali klik untuk mengekspor seluruh jaringan ke format `.kml` siap buka di Google Earth.

### 🤖 3. Integrasi Bot Telegram
- **Bot Monitoring Jarak Jauh:**
  - `/cek [keyword]` — Cek status redaman, IP, dan online ONT pelanggan.
  - `/reboot [sn]` — Reboot ONT via Telegram.
  - `/offline` — Menampilkan daftar ONT yang sedang padam/mati.
  - `/myid` — Mengetahui Chat ID pengguna.
- **Dua Mode Eksekusi:** Mendukung **Webhook HTTPS** maupun **Polling CLI Background** (`telegram_poll.php`).
- **Keamanan Admin ID:** Hanya Chat ID yang terdaftar sebagai admin yang dapat menjalankan perintah kritis.

---

## 🏗️ Struktur Direktori Proyek

```
genieacs-manager/
├── index.php                 # Dashboard utama monitoring CPE/ONT
├── login.php                 # Halaman otentikasi admin
├── logout.php                # Logout & pembersihan sesi
├── map.php                   # Peta monitoring live perangkat ONT
├── monitor.php               # Skrip pengecekan kesehatan jaringan (CLI)
├── set_webhook.php           # Pengaturan webhook Telegram
├── telegram_bot.php          # Handler webhook bot Telegram
├── telegram_poll.php         # Handler polling CLI bot Telegram
├── run_bot.bat               # Skrip Windows runner bot Telegram
├── nginx.conf.example        # Contoh konfigurasi Nginx aman & terkompresi
├── logo.png                  # Logo branding dashboard
│
├── ftth/                     # Modul Pemetaan Topologi Jaringan FTTH
│   ├── dashboard.php         # Ringkasan statistik FTTH & preview peta
│   ├── map.php               # Peta FTTH utama (Clustering, Animasi, KML)
│   ├── otb.php               # Manajemen Central OTB
│   ├── odc.php               # Manajemen Kabinet ODC
│   ├── odp.php               # Manajemen Kotak ODP
│   ├── pelanggan.php         # Manajemen Sambungan Pelanggan & SN ONT
│   └── index.html            # Proteksi direktori
│
├── include/
│   ├── api.php               # REST API backend (Session verified & I/O stream)
│   ├── config.php            # Konfigurasi aplikasi & koneksi NBI
│   └── processor.php         # Helper koneksi cURL ke GenieACS
│
└── data/                     # Database Penyimpanan JSON Lokal
    ├── .htaccess             # Blokir akses HTTP langsung di Apache
    ├── settings.json.example # Template pengaturan koneksi
    ├── settings.json         # Konfigurasi aktif (NBI, kredensial, telegram)
    ├── ftth.json             # Database item OTB, ODC, ODP, Pelanggan
    ├── route.json            # Database cache koordinat rute jalan
    ├── tags.json             # Database tag & koordinat GPS ONT
    └── sample_ftth.json      # Sampel data demo topologi
```

---

## 🚀 Panduan Instalasi & Deployment

Pilih metode instalasi yang paling sesuai dengan kebutuhan infrastruktur Anda:

### 🌟 Opsi 1: Docker Compose All-in-One (Paling Praktis & Cepat)
Menjalankan seluruh ekosistem: **MongoDB + GenieACS CWMP/NBI/FS/UI + Dashboard FTTH Manager** hanya dengan 1 perintah!

1. Clone repositori:
   ```bash
   git clone https://github.com/username/genieacs-manager.git
   cd genieacs-manager
   ```
2. Jalankan Docker Compose:
   ```bash
   docker compose up -d
   ```
3. Akses layanan:
   - **GenieACS CWMP (TR-069):** `http://IP-SERVER:7547`
   - **GenieACS Admin UI:** `http://IP-SERVER:3000`
   - **Dashboard FTTH Manager:** `http://IP-SERVER:8080/` *(Login: admin / admin123)*

---

### ⚡ Opsi 2: Skrip Otomatis Linux Installer (Ubuntu / Debian Native)
Skrip `install.sh` akan secara otomatis memasang Node.js 20 LTS, MongoDB, GenieACS 1.2.16, Systemd Services, Nginx, PHP 8.1, serta menginjeksi Provision Inform (ekstraksi SSID, PPPoE IP, Rx Power dBm, dan Summon Fix).

```bash
git clone https://github.com/username/genieacs-manager.git
cd genieacs-manager
sudo bash install.sh
```
*Tunggu hingga skrip selesai (sekitar 2-3 menit), dan server Anda langsung 100% aktif dan siap digunakan!*

---

### 🛠️ Opsi 3: Instalasi Manual di Ubuntu / Debian (Nginx + PHP-FPM)

1. **Clone Repositori:**
   ```bash
   cd /var/www
   git clone https://github.com/username/genieacs-manager.git
   cd genieacs-manager
   ```

2. **Atur Izin File & Direktori:**
   ```bash
   sudo chown -R www-data:www-data /var/www/genieacs-manager
   sudo chmod -R 775 /var/www/genieacs-manager/data
   ```

3. **Salin & Sesuaikan Pengaturan:**
   ```bash
   cp data/settings.json.example data/settings.json
   nano data/settings.json
   ```
   Isi konfigurasi Anda:
   ```json
   {
       "genie_host": "127.0.0.1",
       "genie_port": "7557",
       "genie_user": "admin",
       "genie_pass": "password_nbi_anda",
       "dash_user": "admin",
       "dash_pass": "password_web_anda",
       "server_lat": "-7.5906",
       "server_lng": "110.2193",
       "map_zoom": "13",
       "telegram_enabled": true,
       "telegram_token": "BOT_TOKEN_TELEGRAM_ANDA",
       "telegram_admin_id": "CHAT_ID_ANDA"
   }
   ```

4. **Konfigurasi Nginx (Sangat Penting):**
   Gunakan file `nginx.conf.example` untuk mengamankan folder `/data/` dan mengaktifkan GZIP:
   ```bash
   sudo cp nginx.conf.example /etc/nginx/sites-available/genieacs-manager
   sudo nano /etc/nginx/sites-available/genieacs-manager
   sudo ln -s /etc/nginx/sites-available/genieacs-manager /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl reload nginx
   ```

---

### B. Instalasi di Windows (XAMPP)

1. Unduh repositori ini dan ekstrak ke folder:
   `C:\xampp\htdocs\genieacs`
2. Buka browser dan buka:
   `http://localhost/genieacs/login.php`
3. Login menggunakan kredensial default:
   - **Username:** `admin`
   - **Password:** `admin123`
4. Masuk ke menu **Settings** di pojok kanan atas untuk menghubungkan ke IP server GenieACS Anda.

---

## 🔒 Keamanan (Security Hardening)

Dashboard ini telah dilengkapi dengan standar keamanan berlapis:
- **Proteksi Direktori Data:** Folder `/data/` diblokir total dari akses web langsung (mengembalikan error `403 Forbidden`). Pengguna internet tidak dapat mendownload file `.json` atau kredensial server.
- **Otentikasi API Mandatori:** Semua request ke `include/api.php` wajib menyertakan sesi login (`is_logged_in`). Request tanpa sesi akan ditolak dengan `401 Unauthorized`.
- **Eksekusi Bot CLI Terisolasi:** Skrip `monitor.php` dan `telegram_poll.php` dikunci untuk eksekusi CLI saja guna mencegah *denial-of-service* lewat peramban web.
- **Filtering Admin Telegram:** Setiap perintah bot diverifikasi terhadap daftar `TELEGRAM_ADMIN_ID`.

---

## 📄 Lisensi
Proyek ini dilisensikan di bawah lisensi [MIT License](LICENSE) — bebas digunakan, dimodifikasi, dan didistribusikan untuk keperluan pribadi maupun komersial.

