<?php
namespace App\Support;
// JPEG'in EXIF yön etiketini (0x0112) yerinde değiştirerek kayıpsız 90° döndürme. Etiket yoksa / aynalıysa null döner.
class ExifYon {
    private const SAAT_YONU = [1=>6, 6=>3, 3=>8, 8=>1];
    public static function dondur(string $jpeg, bool $saatYonunde): ?string {
        if (substr($jpeg,0,2)!=="\xFF\xD8") return null;
        $p=2; $n=strlen($jpeg);
        while ($p+4<=$n && $jpeg[$p]==="\xFF") {
            $isaret=ord($jpeg[$p+1]); $uz=unpack('n',substr($jpeg,$p+2,2))[1];
            if ($isaret===0xDA) break; // görüntü verisi başladı
            if ($isaret===0xE1 && substr($jpeg,$p+4,6)==="Exif\0\0") {
                $t=$p+10; $le=substr($jpeg,$t,2)==='II';
                $u16=fn($o)=>unpack($le?'v':'n',substr($jpeg,$o,2))[1]; $u32=fn($o)=>unpack($le?'V':'N',substr($jpeg,$o,4))[1];
                $ifd=$t+$u32($t+4); $adet=$u16($ifd);
                for ($i=0;$i<$adet;$i++) {
                    $e=$ifd+2+$i*12;
                    if ($u16($e)!==0x0112) continue;
                    $eski=$u16($e+8); $harita=$saatYonunde?self::SAAT_YONU:array_flip(self::SAAT_YONU);
                    if (!isset($harita[$eski])) return null; // aynalı yönler (2,4,5,7)
                    return substr_replace($jpeg,pack($le?'v':'n',$harita[$eski]),$e+8,2);
                }
                return null;
            }
            $p+=2+$uz;
        }
        return null;
    }
}
