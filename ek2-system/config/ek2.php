<?php
// OCR aktarım klasörü: ek2:ocr-aktar komutu her kayıt için <uuid>1.jpeg (ön), <uuid>2.jpeg (arka) ve <uuid>.json (künye) yazar.
// faz1-trocr-main/homography_alan_kes.py bu klasörü --giris olarak okur (yalnız .jpeg, son karakter sayfa no).
// Fotoğraflar tek yerde: sunucuda BELGE_HOST_DIZINI (= konteynerde storage/app/private/ek2). OCR klasörüne kopya değil,
// bu sunucu yoluna işaret eden sembolik bağ yazılır.
return ['ocr_export_path'=>env('OCR_AKTARIM_DIZINI', storage_path('ocr_aktarim')), 'belge_host_dizini'=>rtrim(env('BELGE_HOST_DIZINI','/srv/ek2/belgeler'),'/')];
