---
description: Panduan Manajemen Database (Multi-Database Architecture)
---

# Aturan & Panduan Database

Proyek ini menggunakan arsitektur **Multi-Database** untuk memisahkan data User/App dengan data High-Security (Certificate Authority).

## Arsitektur

1.  **Main Database Connection (`mysql`)**
    *   **Kegunaan**: Menyimpan data aplikasi umum (`users`, `tickets`, `certificates` (leaf), dll).
    *   **Reset Policy**: Boleh di-reset saat development (`php artisan migrate:fresh --seed`).
    *   **Dependency**: Terikat dengan logic aplikasi utama.

2.  **CA Database Connection (`mysql_ca`)**
    *   **Kegunaan**: KHUSUS untuk `ca_certificates` (Root & Intermediate CA).
    *   **Reset Policy**: **DILARANG RESET** sembarangan. Command `migrate:fresh` default TIDAK akan menyentuh database ini.
    *   **Driver**: Menggunakan `mysql` di Production (sama seperti Main DB), bukan SQLite atau D1 (kecuali ada instruksi spesifik).

## Aturan Migrasi

1.  **Pembuatan Tabel Baru**:
    *   Tentukan tabel masuk ke kategori mana (App vs CA).
    *   Jika CA, gunakan `Schema::connection('mysql_ca')->create(...)`.
    *   Jika App, gunakan `Schema::create(...)` biasa.

2.  **Data Safety**:
    *   Sebelum menjalankan query raw atau operasi destructive, pastikan koneksi yang dipilih benar.
    *   Gunakan command `php artisan ca:migrate-data` hanya jika perlu memindahkan data antar database.

## Cloudflare D1
*   Saat ini D1 **TIDAK DIGUNAKAN** untuk kompatibilitas penuh dengan server berbasis VPS/Hosting standar.
*   Jangan mengusulkan migrasi ke D1 kecuali infrastruktur berpindah ke Cloudflare Workers sepenuhnya.
