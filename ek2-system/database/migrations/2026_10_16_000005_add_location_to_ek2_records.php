<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('ek2_records', function (Blueprint $t) {
        $t->decimal('latitude',10,7)->nullable()->after('back_path'); $t->decimal('longitude',10,7)->nullable()->after('latitude');
        $t->string('location_source',10)->nullable()->after('longitude'); // exif | tarayici
        $t->unsignedInteger('location_accuracy_m')->nullable()->after('location_source');
        $t->string('address',500)->nullable()->after('location_accuracy_m'); $t->timestamp('address_at')->nullable()->after('address');
    }); }
    public function down(): void { Schema::table('ek2_records', function (Blueprint $t) {
        $t->dropColumn(['latitude','longitude','location_source','location_accuracy_m','address','address_at']);
    }); }
};
