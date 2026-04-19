# Skripsi BE - Laravel API

Backend API untuk platform pembelajaran online berbasis Laravel 12.

## 1. Ringkasan Singkat

Pola arsitektur utama proyek ini:

`Route -> Controller -> FormRequest -> Service -> Model -> Resource -> JSON Response`

Artinya:
- validasi request ada di folder `app/Http/Requests`
- logika bisnis ada di folder `app/Services`
- akses database lewat Eloquent Model di `app/Models`
- format output API ada di `app/Http/Resources`

## 2. Stack dan Requirement

- PHP `^8.2`
- Laravel `^12.0`
- Laravel Sanctum (token auth)
- Database default di `.env.example`: PostgreSQL
- Queue default: `database`
- Test framework: Pest

## 3. Quick Start (Local)

1. Install dependency:

```bash
composer install
```

2. Buat file env dan app key:

```bash
cp .env.example .env
php artisan key:generate
```

Di Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

3. Atur koneksi database di `.env`.

Contoh default proyek ini:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=skripsi_be
DB_USERNAME=root
DB_PASSWORD=
```

4. Jalankan migrasi + seeder:

```bash
php artisan migrate --seed
```

5. Jalankan server API:

```bash
php artisan serve
```

6. Jalankan queue worker (penting untuk email verification karena notif di-queue):

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Opsional: set `FRONTEND_URL` di `.env` jika link verifikasi email ingin diarahkan ke frontend.

## 4. Akun Seeder Default

Seeder membuat 3 akun awal:

- admin: `admin@example.com` / `password`
- instructor: `instructor@example.com` / `password`
- student(user): `student@example.com` / `password`

Role yang dibuat oleh `RoleSeeder`:
- `admin`
- `instructor`
- `user`

## 5. Peta Arsitektur Proyek

Struktur folder penting:

```text
app/
  Console/Commands/
    MakeCrudCommand.php
  Http/
    Controllers/
      Api/
      Api/Admin/
    Requests/
      Auth/
      Admin/*
      Forum/
      Review/
      Order/
      LessonProgress/
      QuizAttempt/
    Resources/
  Models/
  Notifications/
  Services/

bootstrap/
  app.php            # global exception -> JSON untuk api/*

database/
  migrations/
  seeders/

routes/
  api.php            # seluruh endpoint API
```

## 6. Modul Domain Utama

Service yang tersedia saat ini:

- `AuthService`
- `CategoryService`
- `CourseService`
- `SectionService`
- `LessonService`
- `QuizService`
- `QuestionService`
- `OptionService`
- `VoucherService`
- `OrderService`
- `TransactionService`
- `EnrollmentService`
- `LessonProgressService`
- `QuizAttemptService`
- `ForumService`
- `ReviewService`
- `CertificateService`
- `UserService`
- `RoleService`

Interpretasi cepat:
- master data: category, course, section, lesson, quiz, question, option, voucher
- transaksi: order, transaction
- pembelajaran: enrollment, lesson progress, quiz attempt, certificate
- komunitas: forum dan review
- akses akun: auth, user, role

## 7. Ringkasan Endpoint API

Base URL local:

```text
http://127.0.0.1:8000/api
```

Kelompok endpoint:

1. Auth (`/api/auth/*`)
- register, login, logout
- verify email, resend verification
- forgot password, reset password

2. Student/User area (butuh `auth:sanctum`)
- enrollments, progress summary, next lesson, complete enrollment
- lesson progress
- quiz attempts + submit
- orders
- forum course
- reviews course
- certificates

3. Admin area (`/api/admin/*`, juga pakai `auth:sanctum`)
- CRUD resource utama: categories, courses, sections, lessons, quizzes, questions, options, vouchers, transactions, users
- orders + update status
- enrollment monitoring + sync progress
- grading quiz answer
- moderasi forum/review
- roles (read only)

Untuk melihat daftar route aktual dari kode:

```bash
php artisan route:list --path=api --except-vendor
```

## 8. Format Response API

### Sukses

Mayoritas endpoint mengembalikan format:

```json
{
  "success": true,
  "message": "...",
  "data": {},
  "meta": {}
}
```

`meta` biasanya muncul untuk endpoint pagination.

### Error

`bootstrap/app.php` sudah dipasang agar endpoint `api/*` selalu return JSON error.

Contoh umum:

```json
{
  "success": false,
  "message": "...",
  "errors": {
    "field": ["..."]
  }
}
```

## 9. Alur Bisnis Penting

### 9.1 Register -> Verify Email -> Login

- user register via `AuthService`
- sistem kirim email verifikasi (`VerifyApiEmail`, queued)
- user verifikasi via signed URL
- login berhasil jika akun aktif dan email sudah verified

### 9.2 Order -> Transaction -> Enrollment

- user membuat order (dengan validasi duplikasi course aktif/pending)
- sistem hitung subtotal, voucher, grand total
- sistem buat transaction awal
- saat order/transaction menjadi sukses, enrollment diaktifkan

### 9.3 Lesson Progress -> Enrollment Progress

- update progress lesson lewat `LessonProgressService`
- service otomatis sync progress enrollment
- enrollment bisa ditandai completed saat progress 100%

### 9.4 Quiz Attempt

- user mulai attempt jika belum melebihi `max_attempts`
- jawaban pilihan ganda auto-score
- jawaban essay/short answer perlu manual grading admin
- status attempt: `in_progress` -> `submitted`/`graded`

### 9.5 Certificate

- certificate hanya bisa digenerate jika enrollment `completed`
- jika sudah pernah generate, akan return data sertifikat yang sudah ada

### 9.6 Forum & Review

- forum student mensyaratkan enrollment aktif/completed
- review mensyaratkan enrollment completed
- moderasi admin/instructor diverifikasi di service sesuai role/course ownership

## 10. Konvensi Koding di Proyek Ini

Konvensi yang sebaiknya diikuti agar konsisten:

1. Jangan taruh logika bisnis besar di controller
- controller fokus menerima request dan mengembalikan response
- logika pindahkan ke service

2. Validasi selalu via FormRequest
- contoh: `StoreCourseRequest`, `UpdateCourseRequest`

3. Response gunakan Resource
- contoh: `CourseResource`, `OrderResource`, `EnrollmentResource`

4. Untuk CRUD admin baru, boleh mulai dari generator:

```bash
php artisan make:crud NamaModel
```

Command ini membuat stub:
- service
- admin controller
- store/update request
- resource

5. Untuk fitur yang kompleks, urutkan pengerjaan:
- migration
- model + relationship
- request validation
- service
- controller
- route
- resource
- test

## 11. Checklist Cepat untuk Junior Dev / AI Murah

Sebelum mengubah kode, baca dulu file ini berurutan:

1. `routes/api.php`
2. controller terkait (`app/Http/Controllers/...`)
3. service terkait (`app/Services/...`)
4. request validation (`app/Http/Requests/...`)
5. model + migration terkait
6. resource output (`app/Http/Resources/...`)

Prompt template untuk AI agar hasil lebih akurat:

```text
Kerjakan perubahan hanya pada modul X.
Ikuti pola Route -> Controller -> Request -> Service -> Model -> Resource.
Jangan ubah format response JSON yang sudah ada.
Sebelum edit, baca dulu file: routes/api.php, controller X, service X, request X.
Jika menambah endpoint, sertakan juga validasi request + resource + contoh response.
```

## 12. Perintah Harian yang Sering Dipakai

```bash
# Jalankan test
php artisan test

# Lihat route API
php artisan route:list --path=api --except-vendor

# Cek migrasi status
php artisan migrate:status

# Seed ulang (hati-hati di environment non-local)
php artisan db:seed
```

## 13. Catatan Penting

- Folder `docs/` saat ini kosong, jadi README ini adalah dokumentasi utama proyek.
- Otorisasi route admin saat ini berbasis middleware `auth:sanctum`; validasi role detail ada di beberapa service (contoh `ForumService`, `ReviewService`).
- Jika mau production-ready penuh, lanjutkan dengan policy/gate yang konsisten di seluruh endpoint admin.
