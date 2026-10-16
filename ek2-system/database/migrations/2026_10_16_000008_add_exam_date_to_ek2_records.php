<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('ek2_records', function (Blueprint $t) {
        // Formdaki muayene (onay) tarihi — OCR'dan, taranmışlarda etiketten ya da admin elle; aynı TC + aynı tarih = mükerrer
        $t->date('exam_date')->nullable()->after('last_name'); $t->string('exam_date_source',10)->nullable()->after('exam_date'); // etiket | ocr | elle
        $t->index(['tc_kimlik_no','exam_date']);
    }); }
    public function down(): void { Schema::table('ek2_records', function (Blueprint $t) { $t->dropIndex(['tc_kimlik_no','exam_date']); $t->dropColumn(['exam_date','exam_date_source']); }); }
};
