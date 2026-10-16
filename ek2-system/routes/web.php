<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\WorkplaceController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\RecordController as AdminRecordController;
use App\Http\Controllers\Doctor\DashboardController as DoctorDashboard;
use App\Http\Controllers\Doctor\RecordController as DoctorRecordController;

Route::middleware('guest')->group(function(){ Route::get('/login',[LoginController::class,'create'])->name('login'); Route::post('/login',[LoginController::class,'store'])->middleware('throttle:5,1')->name('login.store'); });
Route::post('/logout',[LoginController::class,'destroy'])->middleware('auth')->name('logout');
Route::get('/',fn()=>auth()->check()?(auth()->user()->isAdmin()?redirect()->route('admin.dashboard'):redirect()->route('doctor.dashboard')):redirect()->route('login'));

Route::prefix('admin')->name('admin.')->middleware(['auth','role:admin'])->group(function(){
    Route::get('/',AdminDashboard::class)->name('dashboard');
    Route::resource('doctors',DoctorController::class)->except(['show','destroy']);
    Route::resource('workplaces',WorkplaceController::class)->except(['show','destroy']);
    Route::get('records',[AdminRecordController::class,'index'])->name('records.index');
    Route::get('records/{record}',[AdminRecordController::class,'show'])->name('records.show');
    Route::get('records/{record}/image/{side}',[AdminRecordController::class,'image'])->name('records.image');
    Route::get('records/{record}/edit',[AdminRecordController::class,'edit'])->name('records.edit');
    Route::put('records/{record}',[AdminRecordController::class,'update'])->name('records.update');
    Route::post('records/{record}/cancel',[AdminRecordController::class,'cancel'])->name('records.cancel');
    Route::post('records/{record}/rotate/{side}/{yon}',[AdminRecordController::class,'rotate'])->name('records.rotate');
    Route::post('records/{record}/retake',[AdminRecordController::class,'retake'])->name('records.retake');
    Route::get('audit',[AuditController::class,'index'])->name('audit.index');
});
Route::prefix('app')->name('doctor.')->middleware(['auth','role:doctor'])->group(function(){
    Route::get('/',DoctorDashboard::class)->name('dashboard');
    Route::get('ek2',[DoctorRecordController::class,'index'])->name('records.index');
    Route::get('ek2/new',[DoctorRecordController::class,'create'])->name('records.create');
    Route::post('ek2',[DoctorRecordController::class,'store'])->name('records.store');
    Route::get('ek2/{record}',[DoctorRecordController::class,'show'])->name('records.show');
    Route::get('ek2/{record}/image/{side}',[DoctorRecordController::class,'image'])->name('records.image');
    Route::post('ek2/{record}/rotate/{side}/{yon}',[DoctorRecordController::class,'rotate'])->name('records.rotate');
    Route::post('ek2/{record}/page/{side}',[DoctorRecordController::class,'addPage'])->name('records.page');
});
