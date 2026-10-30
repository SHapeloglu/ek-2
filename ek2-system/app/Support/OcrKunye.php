<?php

namespace App\Support;

/**
 * OCR giriş klasörüne yazılan künye JSON'u (<uuid>.json, "kayıt hazır" işareti).
 *
 * Kişi verisi İÇERMEZ: T.C. kimlik no, ad, soyad, işyeri, doktor, muayene tarihi, cihaz ve konum künyeye yazılmaz.
 * OCR hattı kayıtları EK-2 veritabanından ve belge klasöründen kendisi okur; bu dosya yalnız "hangi kayıt, hangi iki
 * sayfa hazır" bilgisini taşır. Kişi alanı eklemek gerekirse önce gerekçesi ve okuyan taraf belirlenmeli.
 */
class OcrKunye
{
    /** Künyede bulunabilecek tek alanlar (test bunu denetler). */
    public const ALANLAR = ['kayit_uuid', 'kaynak', 'eski_form_id', 'sayfa1', 'sayfa2', 'yukleme_zamani'];

    /** @param object $r Ek2Record (yalnız uuid, source, legacy_form_id, completed_at okunur) @param array $sayfalar ['1'=>ad, '2'=>ad] */
    public static function olustur(object $r, array $sayfalar): array
    {
        return [
            'kayit_uuid' => $r->uuid, 'kaynak' => $r->source, 'eski_form_id' => $r->legacy_form_id,
            'sayfa1' => $sayfalar['1'], 'sayfa2' => $sayfalar['2'],
            'yukleme_zamani' => $r->completed_at?->toIso8601String(),
        ];
    }
}
