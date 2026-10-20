<?php
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
// T.C. Kimlik No algoritması: 11 hane, ilk hane 0 değil; 10. hane = (tek sıradakiler×7 − çift sıradakiler) mod 10; 11. hane = ilk 10 hanenin toplamı mod 10
class TcKimlikNo implements ValidationRule {
    public static function gecerli(string $tc): bool {
        if (!preg_match('/^[1-9][0-9]{10}$/', $tc)) return false;
        $d = array_map('intval', str_split($tc));
        $tek = $d[0]+$d[2]+$d[4]+$d[6]+$d[8]; $cift = $d[1]+$d[3]+$d[5]+$d[7];
        return ((($tek*7 - $cift) % 10) + 10) % 10 === $d[9] && array_sum(array_slice($d,0,10)) % 10 === $d[10];
    }
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (!self::gecerli((string) $value)) $fail('Geçerli bir T.C. Kimlik No girin.');
    }
}
