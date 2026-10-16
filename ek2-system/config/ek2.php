<?php
// OCR aktarım klasörü: ek2:ocr-aktar komutu her kayıt için <uuid>1.jpeg (ön), <uuid>2.jpeg (arka) ve <uuid>.json (künye) yazar.
// faz1-trocr-main/homography_alan_kes.py bu klasörü --giris olarak okur (yalnız .jpeg, son karakter sayfa no).
return ['ocr_export_path'=>env('OCR_AKTARIM_DIZINI', storage_path('ocr_aktarim'))];
