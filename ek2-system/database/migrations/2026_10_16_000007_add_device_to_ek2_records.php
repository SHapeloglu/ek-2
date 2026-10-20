<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('ek2_records', function (Blueprint $t) {
        $t->string('device_make',100)->nullable()->after('address_at'); $t->string('device_model',100)->nullable()->after('device_make'); // fotoğraf EXIF'i
        $t->string('device_user_agent',500)->nullable()->after('device_model'); // yükleme anındaki tarayıcı bilgisi
    }); }
    public function down(): void { Schema::table('ek2_records', function (Blueprint $t) { $t->dropColumn(['device_make','device_model','device_user_agent']); }); }
};
