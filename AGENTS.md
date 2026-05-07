# AGENTS.md (Local Rules for `skripsi-be`)

Dokumen ini adalah panduan lokal untuk agent saat mengubah backend Laravel `skripsi-be`.
Target utama: kode mudah dibaca, konsisten, dan aman untuk flow LMS.

## Project Overview

- Project: backend API Laravel untuk LMS.
- Stack utama:
  - Laravel (API)
  - PostgreSQL
  - Sanctum token auth
- Domain utama:
  - auth
  - course
  - order/transaction/payment
  - enrollment
  - lesson/quiz/forum/review/certificate
- Batas tanggung jawab:
  - Business logic inti ada di backend.
  - Frontend hanya konsumsi API.

## Build and Test Commands

Jalankan dari root `skripsi-be`:

```bash
php artisan optimize:clear
php artisan route:list --path=api
php artisan test
```

Untuk validasi syntax file tertentu:

```bash
php -l app/Services/OrderService.php
php -l routes/api.php
```

## Database Safety (Critical)

- Dilarang menjalankan command apa pun yang mengubah database.
- Larangan ini mencakup, tetapi tidak terbatas pada:
  - `php artisan migrate`
  - `php artisan migrate:fresh`
  - `php artisan migrate:refresh`
  - `php artisan db:seed`
  - `php artisan schema:*`
  - command SQL tulis (`INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `ALTER`, `DROP`)
- Jika perubahan membutuhkan aksi database, agent harus berhenti dan meminta user menjalankannya sendiri.

## Code Style Guidelines

- Prioritaskan readability dibanding abstraksi berlebihan.
- Controller harus tipis: validasi request, panggil service, bentuk response.
- Business logic utama diletakkan di `app/Services`.
- Gunakan private helper hanya jika:
  - dipakai lebih dari sekali, atau
  - benar-benar membuat method utama lebih jelas.
- Hindari method helper yang hanya membungkus satu baris query jika tidak menambah kejelasan.
- Nama method harus menjelaskan intent bisnis (contoh: `checkoutCart`, `activateOrderEnrollments`).
- Jangan ubah flow yang sudah stabil tanpa alasan jelas dan catatan di PR/report.

## Import and Namespace Rules

- Jangan menulis fully qualified class name inline di body kode.
- Contoh yang dilarang: `App\Http\Middleware\EnsureAdminAccess::class`.
- Wajib deklarasikan dependency di atas file dengan `use ...;`, lalu panggil short class name di kode.
- Contoh yang benar:
  - `use App\Http\Middleware\EnsureAdminAccess;`
  - lalu gunakan `EnsureAdminAccess::class`.
- Terapkan aturan ini untuk middleware, request, resource, service, policy, enum, dan class lainnya.

## API Documentation Rules

- Setiap menambah atau mengubah endpoint API (`routes/api.php`, controller, request/response schema), wajib update dokumentasi API pada commit/perubahan yang sama.
- Sumber dokumentasi request/collection untuk eksekusi manual ada di folder `bruno/` (file `.bru`) dan harus selalu sinkron dengan endpoint terbaru.
- Untuk endpoint baru, wajib tambah request Bruno yang relevan (struktur folder: role -> domain -> request).
- Perubahan endpoint belum dianggap selesai jika koleksi Bruno belum ikut diperbarui.

## Testing Instructions

- Minimal setelah perubahan:
  - lint syntax PHP file yang diubah (`php -l`)
  - cek route API jika ada perubahan route/middleware
  - jalankan test suite (`php artisan test`) jika environment mendukung
- Untuk fitur order/payment:
  - verifikasi state transition `cart -> pending -> completed/cancelled`
  - verifikasi enrollment aktif saat transaksi sukses
  - verifikasi student tidak bisa akses endpoint admin
- Saat hasil test gagal karena environment (contoh extension DB tidak tersedia), catat jelas penyebabnya.

## Security Considerations

- Endpoint admin wajib dibatasi middleware `admin` di backend.
- Jangan pernah mengandalkan guard frontend untuk keamanan API.
- Semua perhitungan harga/final amount harus dihitung ulang di backend.
- Jangan percaya nilai harga dari frontend.
- Update status order/transaction/enrollment harus dibungkus transaksi DB (`DB::transaction`).
- Pastikan query student selalu dibatasi `user_id` pemilik data.
- Jangan expose data sensitif yang tidak diperlukan di resource response.
