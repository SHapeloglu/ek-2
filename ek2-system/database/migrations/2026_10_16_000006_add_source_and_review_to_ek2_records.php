<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('ek2_records', function (Blueprint $t) {
        $t->enum('source',['telefon','tarama'])->default('telefon')->after('workplace_id'); // tarama: OCR projesinin eski taranmış formları
        $t->string('legacy_form_id',100)->nullable()->unique()->after('source'); // OCR'daki form_id (ör. formlar_yeni/<form_id>1.jpeg)
        $t->string('review_note',500)->nullable()->after('cancel_reason'); // doluysa "kontrol edilmeli"; admin düzeltince temizlenir
        $t->json('retake_pages')->nullable()->after('review_note'); $t->string('retake_note',500)->nullable()->after('retake_pages'); // ["front","back"]: net değil, yeniden çekilmeli
    }); }
    public function down(): void { Schema::table('ek2_records', function (Blueprint $t) { $t->dropUnique(['legacy_form_id']); $t->dropColumn(['source','legacy_form_id','review_note','retake_pages','retake_note']); }); }
};
