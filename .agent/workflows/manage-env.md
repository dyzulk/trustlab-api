---
description: Memahami dan Mengelola Environment Variables (5-File System)
---

# Aturan Manajemen Environment Variables

Proyek ini menggunakan sistem **5-File Environment** yang ketat untuk mencegah kesalahan konfigurasi produksi. AI dan Developer Wajib mengikuti aturan ini.

## Struktur File

1.  **`.env`** (Local Development)
    *   Digunakan untuk pengembangan visual/lokal.
    *   Berisi kredensial lokal (localhost, root, dll).
    *   **Aturan:** Menjadi acuan utama *struktur* dan *urutan* key untuk file lainnya.

2.  **`.env.example.for.local`** (Template Local)
    *   Template untuk developer lain.
    *   Struktur HARUS sama persis dengan `.env`.
    *   Value kosong atau default aman.

3.  **`.env.example.for.production`** (Template Production)
    *   Gambaran konfigurasi produksi.
    *   Struktur HARUS sama persis dengan `.env`.
    *   Value disesuaikan untuk konteks produksi (misal `APP_ENV=production`, `APP_DEBUG=false`).

4.  **`.env.production.editable`** (Staging/Pre-Production)
    *   File ini berisi konfigurasi produksi yang *siap* untuk diedit/standardisasi.
    *   **CRITICAL:** Struktur dan urutan key HARUS 100% sama dengan `.env`.
    *   Berisi kredensial RILL/ASLI dari server produksi.

5.  **`.env.production.soft.copy`** (Snapshot Server - **READ ONLY**)
    *   Merupakan salinan langsung dari server saat ini.
    *   **DILARANG EDIT** file ini kecuali server aktual telah berubah.
    *   File ini digunakan sebagai validasi/referensi state server sekarang.
    *   Jangan menambahkan config baru di sini sebelum server di-update.

## Workflow Perubahan Environment

Jika Anda perlu menambahkan Variable baru (misal `DB_CA_...`):

1.  **Tambahkan di `.env`** lokal terlebih dahulu.
2.  **Standardisasi urutan** di `.env.production.editable` (copy struktur `.env`, lalu isi value produksi).
3.  **Update Template** `.env.example.for.local` dan `.env.example.for.production`.
4.  **JANGAN SENTUH** `.env.production.soft.copy` (biarkan apa adanya sampai deployment selesai dan snapshot baru diambil).

## Prompting AI
Untuk memastikan AI mengerti konteks ini, mintalah:
> "Baca aturan environment di `.agent/workflows/manage-env.md` sebelum melakukan perubahan pada file .env"
