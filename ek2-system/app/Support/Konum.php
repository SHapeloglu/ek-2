<?php
namespace App\Support;
use Illuminate\Support\Facades\Http;
use Throwable;
// Fotoğraf EXIF'inden GPS okuma ve koordinat → adres (OpenStreetMap Nominatim; yalnız koordinat gönderilir)
class Konum {
    /** @return array{lat:float,lon:float}|null */
    public static function exif(string $path): ?array {
        $e = @exif_read_data($path, 'GPS');
        if (!$e || empty($e['GPSLatitude']) || empty($e['GPSLongitude'])) return null;
        $lat = self::derece($e['GPSLatitude'], $e['GPSLatitudeRef'] ?? 'N'); $lon = self::derece($e['GPSLongitude'], $e['GPSLongitudeRef'] ?? 'E');
        if ($lat === null || $lon === null || ($lat == 0.0 && $lon == 0.0) || abs($lat) > 90 || abs($lon) > 180) return null;
        return ['lat'=>$lat, 'lon'=>$lon];
    }
    private static function derece(array $dms, string $ref): ?float {
        $v = []; foreach (array_slice($dms, 0, 3) as $p) { [$a, $b] = array_pad(explode('/', (string) $p, 2), 2, '1'); if ((float) $b == 0.0) return null; $v[] = (float) $a / (float) $b; }
        if (count($v) < 3) return null;
        $d = $v[0] + $v[1] / 60 + $v[2] / 3600; return in_array(strtoupper($ref), ['S','W'], true) ? -$d : $d;
    }
    /** @return array{device_make:?string,device_model:?string}|null fotoğrafı çeken cihazın EXIF marka / modeli */
    public static function cihaz(string $path): ?array {
        $e = @exif_read_data($path, 'IFD0'); if (!$e) return null;
        $t = fn($v) => ($v = trim(preg_replace('/\s+/', ' ', (string) $v))) === '' ? null : mb_substr($v, 0, 100);
        $make = $t($e['Make'] ?? ''); $model = $t($e['Model'] ?? '');
        return ($make || $model) ? ['device_make'=>$make, 'device_model'=>$model] : null;
    }
    public static function adres(float $lat, float $lon, int $timeout = 5): ?string {
        try {
            $r = Http::timeout($timeout)->withHeaders(['User-Agent'=>'EK2-Sistemi/1.0 (+https://ek-2.selimkilic.com.tr)'])
                ->get('https://nominatim.openstreetmap.org/reverse', ['format'=>'jsonv2','lat'=>$lat,'lon'=>$lon,'zoom'=>18,'accept-language'=>'tr']);
            $a = $r->successful() ? $r->json('display_name') : null;
            return $a ? mb_substr($a, 0, 500) : null;
        } catch (Throwable) { return null; }
    }
}
