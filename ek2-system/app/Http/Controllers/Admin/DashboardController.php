<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Ek2Record;
use App\Models\User;
class DashboardController extends Controller {
    public function __invoke(){ return view('admin.dashboard',['doctorCount'=>User::where('role','doctor')->where('is_active',1)->count(),'recordCount'=>Ek2Record::count(),'todayCount'=>Ek2Record::whereDate('created_at',today())->count(),'ocrPending'=>Ek2Record::where('status','completed')->where('ocr_status','!=','aktarildi')->count()]); }
}
