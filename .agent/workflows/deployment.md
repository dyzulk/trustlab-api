---
description: SOP Deployment (CI/CD via aaPanel)
---

# Alur Kerja Deployment

Proyek ini menggunakan **CI/CD Otomatis** via aaPanel Webhook yang terintegrasi dengan GitHub/Git.

## 1. Automated Deployment (CI/CD)

Setiap kali Anda melakukan push ke branch `main`, script webhook di server akan berjalan.
**Apa yang dilakukan script otomatis:**
1.  `git pull origin main`
2.  `composer install` & `npm install` + `vite build`
3.  **Update Config:** Mengcopy isi `.env.production.editable` ke `.env` (Pastikan file editable sudah benar di repo!).
4.  `php artisan migrate --force` (Main & CA Database).
5.  `php artisan optimize`.

**Script Reference:**
*   **Repo (Public):** `scripts/deploy-webhook.example.sh` (Template aman, gunakan ini untuk copy-paste ke aaPanel lalu edit manual).
*   **Local (Private):** `scripts/deploy-webhook.local.sh` (Backup pribadi Anda dengan path asli, ter-ignore oleh git).

## 2. Manual Pre-Requisites (Sebelum Push)

Sebelum Anda push code, pastikan:
1.  **Environment Variables**:
    *   Jika ada perubahan config, update `.env.production.editable`.
    *   Ingat: Script akan menimpa `.env` server dengan isi `.env.production.editable`.
2.  **Database**:
    *   Jika membuat DB baru (seperti kasus CA ini), pastikan database fisik sudah dibuat di server MySQL (`CREATE DATABASE ...`).

## 3. Manual Post-Deployment (Intervensi Khusus)

Script CI/CD tidak menangani edge-cases. Anda perlu masuk ke server (SSH) untuk kasus berikut:

1.  **Data Migration Khusus**:
    *   Kasus: Memisahkan table CA ke database baru.
    *   Action: Login SSH, lalu jalankan:
        ```bash
        cd /www/wwwroot/trustlab-api-ftp/trustlab-api.dyzulk.com
        php artisan ca:migrate-data
        ```

2.  **Rollback**:
    *   Jika deploy gagal total, Anda mungkin perlu restore backup database manual via aaPanel atau `php artisan migrate:rollback`.

## 4. Platform Lain (Non-aaPanel)
Jika berpindah dari aaPanel, adaptasi script `scripts/deploy-webhook.example.sh`:
*   Ganti Path project (`PROJECT_PATH`).
*   Ganti Path PHP Binary (`PHP_BIN`).
*   Ganti mekanisme trigger (misal gunakan GitHub Actions, Jenkins, atau Laravel Forge).
