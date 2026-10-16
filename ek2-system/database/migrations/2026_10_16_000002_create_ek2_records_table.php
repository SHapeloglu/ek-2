<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('ek2_records', function (Blueprint $t) {
        $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
        $t->foreignId('workplace_id')->constrained('workplaces')->restrictOnDelete();
        $t->string('tc_kimlik_no',11)->index(); $t->string('first_name',100); $t->string('last_name',100);
        $t->string('front_path')->nullable(); $t->string('back_path')->nullable();
        $t->enum('status',['draft','completed'])->default('draft')->index(); $t->timestamp('completed_at')->nullable();
        // OCR aktarımı: bekliyor → aktarildi (ek2:ocr-aktar komutu dosyaları OCR giriş klasörüne yazınca) / hata
        $t->enum('ocr_status',['bekliyor','aktarildi','hata'])->default('bekliyor')->index(); $t->timestamp('ocr_exported_at')->nullable(); $t->string('ocr_error',500)->nullable();
        $t->timestamps();
        $t->index(['doctor_id','created_at']);
    }); }
    public function down(): void { Schema::dropIfExists('ek2_records'); }
};
