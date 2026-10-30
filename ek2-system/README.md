# EK-2 Hasta Belge Sistemi

Hekimin EK-2 (işe giriş / periyodik muayene) formunun ön ve arka yüzünü telefonla çekip hasta ve işyeri bilgisiyle yüklediği, kayıtların OCR hattına (`SHapeloglu/trocr-faz1`) aktarıldığı Laravel 13 uygulaması. Tarayıcı (scanner) ile tarama adımının yerini alır.

Canlı adres: https://ek-2.selimkilic.com.tr

## Roller
- `admin`: doktor ve işyeri hesaplarını yönetir; tüm kayıtları, tam TC'yi ve fotoğrafları görür; kaydı düzeltir (not zorunlu) veya iptal eder (neden zorunlu); erişim kaydını izler.
- `doctor`: mobil sihirbazla yeni EK-2 kaydı açar (işyeri → TC → ad → soyad → ön foto → arka foto → kontrol) ve yalnız kendi kayıtlarını görür.

## Akış
1. Doktor kaydı yükler → `ocr_status = bekliyor`.
2. `php artisan ek2:ocr-aktar` her kayıt için OCR giriş klasörüne yazar:
   - `<uuid>1.jpeg` (ön), `<uuid>2.jpeg` (arka) — `homography_alan_kes.py` kuralı: yalnız `.jpeg`, son karakter sayfa no
   - `<uuid>.json` — künye (yalnız kayıt kimliği, kaynak, eski form adı, iki sayfa adı, yükleme zamanı; **T.C., ad, işyeri, doktor, konum, cihaz YAZILMAZ**); en son yazılır, "kayıt hazır" işaretidir
3. Admin kaydı düzeltirse kayıt yeniden aktarılır (künye ve fotoğraflar üzerine yazılır).
4. Admin kaydı iptal ederse aktarım klasöründeki dosyalar silinir, yerine `<uuid>.iptal.json` bırakılır.

## Kurulum (Contabo)
- Veritabanı: ortak MySQL konteyneri `mysql-ortak` (`/opt/mysql-ortak`, Docker ağı `ortak-db`), veritabanı `ek2_system`.
- `.env.example` → `.env`; `APP_KEY` için `base64:` + `openssl rand -base64 32`; DB ve admin bilgilerini girin.
- `mkdir ocr_aktarim && chown 82:82 ocr_aktarim` (konteynerdeki `www-data` = uid 82)
- `docker compose up -d --build`
- `docker exec ek2-app php artisan migrate --seed --force` (ilk admin `.env`'deki `ADMIN_EMAIL` / `ADMIN_PASSWORD`)
- Sistem nginx: `docker/nginx/sistem-nginx-ek-2.conf` → `/etc/nginx/sites-available/ek-2`, `nginx -t`, `systemctl reload nginx`, `certbot --nginx -d ek-2.selimkilic.com.tr`
- Güncelleme: `git pull` → `docker compose up -d --build` → `docker exec ek2-app php artisan migrate --force`

Kod imajın içindedir; `storage` adlı Docker volume'ünde fotoğraflar, oturumlar ve günlükler durur.

## Güvenlik
- Fotoğraflar `storage/app/private/ek2` altında; public URL yok, yalnız yetki kontrollü controller üzerinden ve `no-store` ile verilir.
- Doktor yalnız `doctor_id = auth()->id()` olan kayıtlara erişir; listelerde TC maskelenir.
- TC, T.C. Kimlik No algoritmasıyla hem sunucuda hem sihirbazda doğrulanır.
- Giriş denemesi dakikada 5 ile sınırlı; oturum çerezi şifreli ve yalnız HTTPS.
- `audit_logs`: giriş/çıkış, başarısız giriş, kayıt oluşturma/görüntüleme, fotoğraf açma, düzeltme, iptal, OCR aktarımı, doktor/işyeri değişiklikleri (admin panelinde "Erişim Kaydı").
