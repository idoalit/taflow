# Fipro

Sistem bimbingan tugas akhir: dari proposal sampai ujian hasil, dalam satu alur. Dibangun dengan Laravel 12, Inertia (React), dan Octane (FrankenPHP).

## Deployment dengan Docker

### Gambaran

Stack Docker di repositori ini hanya berisi **aplikasinya**. Layanan pendukung adalah server tersendiri yang dihubungi lewat jaringan:

| Komponen | Keterangan |
|---|---|
| Aplikasi | Satu image (FrankenPHP + Octane) dari `Dockerfile`, dijalankan lewat `compose.yaml`. Tidak menyimpan data apa pun di dalam kontainer. |
| MySQL / MariaDB | Wajib. Boleh di server lain. |
| Redis | Untuk cache dan sesi. Opsional, bisa diganti database (lihat [Tanpa Redis](#tanpa-redis)). |
| MinIO / S3 | Penyimpanan berkas: proposal, dokumen ujian, tanda tangan, logo. Sangat disarankan (lihat [Tanpa MinIO](#tanpa-minio)). |
| SMTP | Untuk tautan aktivasi akun dan setel ulang kata sandi. |
| Gateway (nginx) | Mengakhiri HTTPS di depan aplikasi. |
| Authelia | Opsional, untuk login SSO. |

Prasyarat di server aplikasi: Docker Engine dengan plugin Compose v2, dan Git.

### 1. Siapkan database

Di server database, buat database dan pengguna yang hanya boleh terhubung dari IP server aplikasi. Contoh berikut memakai server aplikasi `10.0.0.20` dan server database `10.0.0.10`:

```sql
CREATE DATABASE fipro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fipro'@'10.0.0.20' IDENTIFIED BY 'ganti-dengan-kata-sandi-kuat';
GRANT ALL PRIVILEGES ON fipro.* TO 'fipro'@'10.0.0.20';
FLUSH PRIVILEGES;
```

Lalu, masih di server database:

- Set `bind-address` di konfigurasi MySQL ke IP privat server database, bukan `127.0.0.1`, lalu restart MySQL.
- Buka port 3306 hanya untuk server aplikasi, misalnya `ufw allow from 10.0.0.20 to any port 3306`.

Koneksi dari kontainer keluar memakai IP host-nya, jadi yang diizinkan adalah IP server aplikasi.

Uji koneksi dari server aplikasi:

```bash
docker run --rm -it mysql:8 mysql -h 10.0.0.10 -u fipro -p -e 'SELECT 1'
```

### 2. Ambil kode dan isi konfigurasi

```bash
git clone https://github.com/idoalit/taflow.git fipro
cd fipro
cp .env.production.example .env.production
```

Isi `.env.production`. Setiap bagian di file itu sudah diberi penjelasan. Yang wajib diisi:

```env
APP_NAME=Fipro
COMPOSE_PROJECT_NAME=fipro          # nama stack; image-nya menjadi fipro:latest
APP_URL=https://fipro.poltekkes-smg.ac.id

DB_HOST=10.0.0.10
DB_DATABASE=fipro
DB_USERNAME=fipro
DB_PASSWORD=ganti-dengan-kata-sandi-kuat

REDIS_HOST=...                      # atau lihat "Tanpa Redis"
AWS_ENDPOINT=https://minio...       # beserta AWS_BUCKET dan kuncinya
MAIL_HOST=...                       # beserta MAIL_USERNAME dan MAIL_PASSWORD
INSTITUTION_NAME="Poltekkes Kemenkes Semarang"
```

`APP_KEY` diisi di langkah berikutnya. File `.env.production` diabaikan git dan tidak pernah masuk ke image, jadi jangan di-commit.

### 3. Build image dan buat APP_KEY

```bash
docker compose --env-file .env.production build
docker run --rm fipro:latest php artisan key:generate --show
```

Salin keluaran perintah kedua (`base64:...`) ke `APP_KEY` di `.env.production`. Buat kunci ini **sekali saja**. Mengganti `APP_KEY` di kemudian hari membuat sesi dan data terenkripsi tidak bisa dibaca lagi.

### 4. Jalankan

```bash
docker compose --env-file .env.production up -d
```

Setiap `up`, service `migrate` jalan lebih dulu: migrasi database, lalu peran dan izin bawaan. Aplikasi baru dinyalakan kalau langkah itu berhasil. Periksa hasilnya:

```bash
docker compose --env-file .env.production ps
docker compose --env-file .env.production logs migrate
curl -I http://127.0.0.1:8000/up     # 200 berarti aplikasi sehat
```

### 5. Buat akun superadmin pertama

```bash
docker compose --env-file .env.production run --rm --no-deps app \
    php artisan app:superadmin admin@poltekkes-smg.ac.id --name="Superadmin"
```

Perintah ini mencetak tautan untuk menyetel kata sandi. Buka tautan itu, setel kata sandi, lalu masuk. Dari layar **Pengaturan Sistem**, lengkapi identitas institusi dan unggah logonya. Logo ini dipakai di kop dokumen, halaman login, dan favicon.

Akun lain (admin prodi, dosen, mahasiswa) dibuat dari dalam aplikasi atau lewat impor Excel. Pemiliknya menerima surel aktivasi untuk menyetel kata sandi sendiri.

### 6. Pasang gateway HTTPS

Aplikasi hanya melayani HTTP polos di port 8000. Secara bawaan port itu hanya dibuka di `127.0.0.1`. Ada dua cara memasang gateway:

- **Nginx di host yang sama:** biarkan `APP_BIND=127.0.0.1` dan isi `TRUSTED_PROXIES=127.0.0.1`.
- **Gateway di mesin lain:** isi `APP_BIND` dengan IP privat server aplikasi, dan `TRUSTED_PROXIES` dengan IP gateway.

Tanpa `TRUSTED_PROXIES`, aplikasi membuat tautan `http://` dan mencatat IP gateway untuk semua pengguna.

Contoh konfigurasi nginx:

```nginx
server {
    listen 443 ssl http2;
    server_name fipro.poltekkes-smg.ac.id;

    ssl_certificate     /etc/ssl/fipro/fullchain.pem;
    ssl_certificate_key /etc/ssl/fipro/privkey.pem;

    # Sama dengan post_max_size di docker/php.ini.
    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host              $host;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host  $host;
        proxy_read_timeout 90s;
    }
}
```

Setelah mengubah `.env.production`, jalankan ulang dengan `docker compose --env-file .env.production up -d`.

### 7. Login SSO dengan Authelia (opsional)

Daftarkan client di konfigurasi Authelia:

```yaml
identity_providers:
  oidc:
    clients:
      - client_id: 'fipro'
        client_name: 'Fipro'
        client_secret: '$pbkdf2-sha512$...'   # hash dari secret
        public: false
        authorization_policy: 'two_factor'
        require_pkce: true
        pkce_challenge_method: 'S256'
        redirect_uris:
          - 'https://fipro.poltekkes-smg.ac.id/auth/sso/callback'
        scopes: ['openid', 'profile', 'email', 'groups']
        response_types: ['code']
        grant_types: ['authorization_code']
        token_endpoint_auth_method: 'client_secret_post'
```

Secret bisa dibuat dengan `authelia crypto rand --length 64`, lalu di-hash dengan `authelia crypto hash generate pbkdf2 --variant sha512` untuk dimasukkan ke konfigurasi Authelia. Isikan secret aslinya, bukan hash, ke `.env.production`:

```env
AUTHELIA_BASE_URL=https://auth.poltekkes-smg.ac.id
AUTHELIA_CLIENT_ID=fipro
AUTHELIA_CLIENT_SECRET=secret-asli
```

Tombol SSO muncul di halaman login begitu `AUTHELIA_BASE_URL` dan `AUTHELIA_CLIENT_ID` terisi. Aturan login SSO:

- Hanya akun yang emailnya sudah terdaftar di Fipro yang bisa masuk.
- Fipro tidak meminta 2FA-nya sendiri setelah login SSO, jadi gunakan `authorization_policy: 'two_factor'` di Authelia.

### Memperbarui ke versi baru

```bash
git pull
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d
```

Migrasi database jalan otomatis sebelum versi baru melayani pengguna. Backup database sebelum memperbarui.

### Perintah yang sering dipakai

```bash
# Log aplikasi
docker compose --env-file .env.production logs -f app

# Menjalankan perintah artisan
docker compose --env-file .env.production run --rm --no-deps app php artisan about

# Menghentikan stack
docker compose --env-file .env.production down
```

### Tanpa Redis

Simpan cache dan sesi di database. Tabelnya sudah dibuat oleh migrasi.

```env
CACHE_STORE=database
SESSION_DRIVER=database
```

Kosongkan atau abaikan variabel `REDIS_*`.

### Tanpa MinIO

Dengan `DOCUMENTS_DISK=local`, berkas tersimpan di dalam kontainer dan **hilang setiap kali kontainer dibuat ulang**, misalnya saat memperbarui versi. Kalau tidak memakai MinIO/S3, tambahkan volume untuk `storage/app` ke service `app` dan `migrate` di `compose.yaml`, dan pastikan folder itu ikut dibackup:

```yaml
    volumes:
      - ./data/storage-app:/app/storage/app
```

Folder `./data/storage-app` di host harus bisa ditulis oleh pengguna kontainer (UID `10001`):

```bash
mkdir -p data/storage-app && sudo chown -R 10001:10001 data/storage-app
```
