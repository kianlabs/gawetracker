# Deploy GaweTracker ke Render (gratis) + Aiven MySQL (gratis)

Panduan ini menaikkan GaweTracker ke internet **tanpa kartu kredit**, memakai:

- **Render** — web service gratis untuk menjalankan container aplikasi.
- **Aiven** — MySQL gratis 1 GB sebagai basis data.

Hasil akhirnya: sebuah URL publik `https://<nama-service>.onrender.com` yang
bisa dibuka siapa saja, dan setiap orang bisa mendaftar akun sendiri
(registrasi terbuka, data terisolasi per pengguna).

> **Penting:** tier gratis Render secara resmi **tidak ditujukan untuk
> aplikasi produksi** ("Do not use them for production applications"). Anggap
> ini sebagai **panggung uji coba** untuk menunjukkan aplikasi ke orang lain,
> bukan rumah permanen. Baca bagian [Keterbatasan](#keterbatasan-tier-gratis)
> di bawah.

---

## Ringkasan alur

1. Buat MySQL gratis di Aiven, unduh `ca.pem`.
2. Hubungkan repo ini ke Render lewat **Blueprint** (`render.yaml` sudah ada).
3. Isi environment variable + unggah `ca.pem` sebagai **Secret File**.
4. Deploy. Container akan otomatis membuat tabel dan akun admin.
5. Uji `/up`, `/register`, `/login`.

Perkiraan waktu: 15–20 menit.

---

## Langkah 1 — Buat MySQL gratis di Aiven

1. Buka <https://aiven.io> → **Sign up**. Cukup email (atau login Google/GitHub).
   **Tidak perlu kartu kredit.**
2. Masuk ke **Aiven Console** → **Create service** → pilih **MySQL**.
3. Pilih plan **Free** dan region **Singapore** (paling dekat dengan Render
   Singapore). Beri nama, misalnya `gawetracker`.
4. Klik **Create service** dan tunggu sampai status **Running** (beberapa menit).
5. Buka halaman **Overview** service → bagian **Connection information**. Catat:

   | Yang dibutuhkan | Contoh |
   | --- | --- |
   | Host | `gawetracker-xxxx.aivencloud.com` |
   | Port | `12345` |
   | User | `avnadmin` |
   | Password | `AVNS_xxxxxxxx` |
   | Database | `defaultdb` |

6. Di bagian yang sama, unduh **CA certificate** dan simpan sebagai `ca.pem`.

> **Catatan Aiven free:** hanya **satu** service MySQL gratis per organisasi,
> dan service akan **dimatikan otomatis saat idle**. Bisa dinyalakan lagi kapan
> saja dari console (ada notifikasi email sebelumnya).

---

## Langkah 2 — Buat service di Render lewat Blueprint

1. Pastikan commit terbaru sudah ada di GitHub (`git push`).
2. Buka <https://dashboard.render.com> → **New +** → **Blueprint**.
3. Hubungkan akun GitHub dan pilih repo `gawetracker` (repo ini).
4. Render akan membaca `render.yaml` di root dan menampilkan service
   `gawetracker`. Klik **Apply** / **Create**.
5. Render akan meminta nilai untuk setiap variabel bertanda rahasia
   (`sync: false`) — isi sesuai [Langkah 3](#langkah-3--isi-environment-variable).

> `render.yaml` sudah menyetel: Docker, region **Singapore**, plan **free**,
> health check `/up`, dan start command ke `deploy/render/entrypoint.sh`.

---

## Langkah 3 — Isi environment variable

Isi nilai berikut di dashboard Render (tab **Environment**):

| Key | Nilai |
| --- | --- |
| `APP_KEY` | Hasil `php artisan key:generate --show` (lihat di bawah) |
| `APP_URL` | `https://<nama-service>.onrender.com` (isi setelah deploy pertama) |
| `DB_HOST` | Host Aiven |
| `DB_PORT` | Port Aiven |
| `DB_DATABASE` | Database Aiven (`defaultdb`) |
| `DB_USERNAME` | User Aiven (`avnadmin`) |
| `DB_PASSWORD` | Password Aiven |
| `ADMIN_EMAIL` | Email untuk akun admin |
| `ADMIN_NAME` | Nama admin (mis. `Kyan`) |
| `ADMIN_PASSWORD` | Kata sandi admin (pilih yang kuat) |

Variabel lain (`APP_ENV`, `LOG_CHANNEL`, `SESSION_DRIVER`,
`MYSQL_ATTR_SSL_CA`, `REGISTRATION_ENABLED`, dst.) sudah terisi otomatis dari
`render.yaml`.

### Membuat `APP_KEY`

Jalankan di komputer Anda (di root proyek ini):

```bash
php artisan key:generate --show
```

Salin seluruh hasilnya (harus diawali `base64:`) dan tempel ke nilai `APP_KEY`
di Render.

> **Jangan** memakai fitur "Generate Value" Render untuk `APP_KEY`. Render
> menghasilkan string base64 **tanpa awalan `base64:`**, dan Laravel akan
> menolaknya (error 500 "Unsupported cipher or incorrect key length").

---

## Langkah 4 — Unggah `ca.pem` sebagai Secret File

Ini membuat koneksi ke Aiven terenkripsi dan terverifikasi.

1. Di dashboard Render, buka service `gawetracker` → tab **Environment**.
2. Bagian **Secret Files** → **Add Secret File**.
3. **Filename:** `ca.pem` (harus persis ini — `render.yaml` menunjuk ke
   `/etc/secrets/ca.pem`).
4. **Contents:** tempel seluruh isi file `ca.pem` dari Aiven.
5. **Save** → Render akan melakukan deploy ulang otomatis.

> Ukuran total Secret Files dibatasi 1 MB — `ca.pem` hanya beberapa KB.

---

## Langkah 5 — Deploy dan verifikasi

1. Tunggu proses **Build** dan **Deploy** selesai (5–10 menit pada percobaan
   pertama).
2. Buka tab **Logs** dan cari baris dari entrypoint, misalnya:
   `[entrypoint] binding nginx to 0.0.0.0:10000`,
   `[entrypoint] database reachable`, `[entrypoint] running migrations`.
3. Uji health check: buka `https://<nama-service>.onrender.com/up`.
   Harus balas **200**.
4. Buka `https://<nama-service>.onrender.com/login` → halaman masuk muncul.
5. Buka `/register`, daftar akun uji, lalu login dan buat satu lamaran.
6. Login sebagai admin memakai `ADMIN_EMAIL` / `ADMIN_PASSWORD` yang tadi diisi.

Setelah URL publik diketahui, pastikan `APP_URL` sudah diisi dengan URL itu
(Render akan deploy ulang), supaya tautan dan cookie memakai domain yang benar.

---

## Keterbatasan tier gratis

Harap pahami dan sampaikan ke calon pengguna:

- **Cold start.** Instance gratis Render dimatikan setelah **15 menit** tanpa
  aktivitas. Permintaan pertama setelah itu butuh **± 50–60 detik** untuk
  bangun. Aplikasi tidak "rusak", hanya lambat sekali di awal.
- **Kuota 750 jam/bulan.** Satu instance gratis = 750 jam instance per bulan.
  Cukup untuk satu service yang nyala terus; jangan buat banyak service.
- **Aiven mati saat idle.** Jika lama tidak ada aktivitas database, Aiven
  mematikannya. Permintaan pertama bisa gagal; nyalakan lagi dari console
  Aiven, lalu **Manual Deploy → Restart** di Render (lihat Troubleshooting).
- **Tidak ada cron/worker.** Penemuan lowongan terjadwal
  (`jobs:discover-saved`, per jam) **tidak berjalan** di Render gratis.
  Fitur pencarian manual tetap bisa dipakai.
- **Batas database.** Aiven free: 1 GB disk, 1 GB RAM, `max_connections=76`.
- **Bukan untuk produksi.** Sesuai pernyataan Render sendiri.
- **Registrasi terbuka.** Siapa pun yang punya URL bisa mendaftar akun.
  Untuk menutup registrasi, ubah `REGISTRATION_ENABLED` menjadi `false` di
  Environment Render.

---

## Troubleshooting

| Gejala | Penyebab & solusi |
| --- | --- |
| Deploy gagal: *"no open ports detected"* | Start command tidak menjalankan entrypoint. Pastikan `dockerCommand` di `render.yaml` = `/bin/sh -c "/var/www/html/deploy/render/entrypoint.sh"`. |
| Halaman 500 setelah boot | `APP_KEY` salah. Harus diawali `base64:` dan berasal dari `key:generate --show`. Perbaiki lalu deploy ulang. |
| Log: `database still unreachable` / `skipping setup` | Service Aiven sedang mati (idle). Web server tetap naik, tetapi halaman yang butuh database (dashboard, lamaran) akan error sampai DB hidup. Nyalakan dari console Aiven, lalu **Manual Deploy → Restart** di Render agar migrasi & seed jalan. |
| Log: `connection failed: SSL certificate problem` | `ca.pem` belum diunggah sebagai Secret File, atau namanya bukan `ca.pem`. |
| Login gagal terus setelah ganti domain | `APP_URL` belum diperbarui, atau cookie `SESSION_SECURE_COOKIE` salah. Pastikan `APP_URL` = URL `onrender.com` dan `SESSION_SECURE_COOKIE=true`. |
| Perubahan kode tidak muncul | Pastikan commit sudah di-push ke branch yang dipakai (`render.yaml` → `branch`). |

---

## Setelah pindah ke `main`

`render.yaml` saat ini menunjuk branch `feat/public-multiuser`. Setelah
di-merge ke `main`, ubah baris `branch:` menjadi `main` (atau hapus barisnya
agar mengikuti branch default repo), lalu commit.

---

## Kalau butuh yang selalu hidup

Render + Aiven gratis ini hanya untuk uji coba. Untuk pemakaian serius
(selalu nyala, tanpa cold start), gunakan **VPS Indonesia** yang bisa dibayar
lewat transfer bank / QRIS / GoPay / OVO / DANA (mis. Rumahweb, Biznet Gio,
IDCloudHost, Hostinger). Kit deploy untuk VPS Linux (Nginx + PHP-FPM + MySQL)
sudah tersedia di `deploy/oracle/` dan bisa dipakai di VPS mana pun.
