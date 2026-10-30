<?php
// Çalıştırma: php tests/ocr_kunye_test.php   (yalnız vendor/autoload.php gerekir; veritabanı gerekmez)
require __DIR__.'/../vendor/autoload.php';

use App\Support\OcrKunye;
use Carbon\Carbon;

$hata = 0;
function kontrol(string $ad, bool $ok): void { global $hata; echo ($ok ? 'OK   ' : 'HATA ').$ad.PHP_EOL; $hata += $ok ? 0 : 1; }

// Kayıt: kişi alanları dolu bir kayıt (hepsinin künyeye GİRMEMESİ gerekir)
$r = (object) [
    'uuid' => '11111111-2222-3333-4444-555555555555', 'source' => 'telefon', 'legacy_form_id' => null,
    'tc_kimlik_no' => '10000000146', 'first_name' => 'GIZLIAD', 'last_name' => 'GIZLISOYAD',
    'exam_date' => Carbon::parse('2026-10-01'), 'completed_at' => Carbon::parse('2026-10-02 10:00:00', 'UTC'),
    'device_make' => 'GIZLIMARKA', 'device_model' => 'GIZLIMODEL', 'latitude' => 41.01, 'longitude' => 28.97, 'address' => 'GIZLIADRES',
    'workplace' => (object) ['name' => 'GIZLIISYERI', 'sgk_sicil_no' => 'GIZLISGK', 'address' => 'GIZLIISADRES'],
    'doctor' => (object) ['name' => 'GIZLIDOKTOR', 'surname' => 'GIZLISOY'],
];
$k = OcrKunye::olustur($r, ['1' => 'uuid1.jpeg', '2' => 'uuid2.jpeg']);
$json = json_encode($k, JSON_UNESCAPED_UNICODE);

kontrol('anahtarlar tam olarak izinli alanlar', array_keys($k) === OcrKunye::ALANLAR);
kontrol('kayıt kimliği ve sayfalar doğru', $k['kayit_uuid'] === $r->uuid && $k['sayfa1'] === 'uuid1.jpeg' && $k['sayfa2'] === 'uuid2.jpeg');
kontrol('yükleme zamanı ISO 8601', $k['yukleme_zamani'] === '2026-10-02T10:00:00+00:00');
foreach (['10000000146', 'GIZLIAD', 'GIZLISOYAD', 'GIZLIMARKA', 'GIZLIMODEL', 'GIZLIADRES', 'GIZLIISYERI', 'GIZLISGK', 'GIZLIISADRES', 'GIZLIDOKTOR', '41.01', '28.97', '2026-10-01'] as $deger) {
    kontrol("künyede yok: {$deger}", !str_contains($json, $deger));
}
$tarihsiz = (object) ['uuid' => 'u', 'source' => 'tarama', 'legacy_form_id' => 'ornek', 'completed_at' => null];
$k2 = OcrKunye::olustur($tarihsiz, ['1' => 'a.jpeg', '2' => 'b.jpeg']);
kontrol('tamamlanma zamanı yoksa null', $k2['yukleme_zamani'] === null && $k2['eski_form_id'] === 'ornek');

echo ($hata ? "HATA: {$hata}" : 'HATA: 0').PHP_EOL;
exit($hata ? 1 : 0);
