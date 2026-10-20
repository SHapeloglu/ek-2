<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        User::updateOrCreate(['email'=>env('ADMIN_EMAIL','admin@example.com')],[
            'name'=>env('ADMIN_NAME','System'),'surname'=>env('ADMIN_SURNAME','Admin'),
            'password'=>Hash::make(env('ADMIN_PASSWORD','CHANGE_ME_NOW')),'role'=>'admin','is_active'=>true,
        ]);
    }
}
