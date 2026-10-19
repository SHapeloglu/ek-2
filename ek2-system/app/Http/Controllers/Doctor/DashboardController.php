<?php
namespace App\Http\Controllers\Doctor;
use App\Http\Controllers\Controller;
use App\Models\Ek2Record;
class DashboardController extends Controller {
 public function __invoke(){ $count=Ek2Record::where('doctor_id',auth()->id())->count(); $today=Ek2Record::where('doctor_id',auth()->id())->whereDate('created_at',today())->count(); $eksik=Ek2Record::where('doctor_id',auth()->id())->eksik()->count(); return view('doctor.dashboard',compact('count','today','eksik')); }
}
