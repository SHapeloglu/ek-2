<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
class DoctorController extends Controller {
    public function index(){ $doctors=User::where('role','doctor')->withCount('records')->latest()->paginate(30); return view('admin.doctors.index',compact('doctors')); }
    public function create(){ return view('admin.doctors.create'); }
    public function store(Request $request){
        $data=$request->validate(['name'=>'required|string|max:100','surname'=>'required|string|max:100','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:10|max:72','is_active'=>'nullable|boolean']);
        $data['password']=Hash::make($data['password']); $data['role']='doctor'; $data['is_active']=$request->boolean('is_active'); $doctor=User::create($data); AuditLog::kaydet('doktor_olustur',$doctor);
        return redirect()->route('admin.doctors.index')->with('ok','Doktor oluşturuldu.');
    }
    public function edit(User $doctor){ abort_unless($doctor->isDoctor(),404); return view('admin.doctors.edit',compact('doctor')); }
    public function update(Request $request, User $doctor){
        abort_unless($doctor->isDoctor(),404);
        $data=$request->validate(['name'=>'required|string|max:100','surname'=>'required|string|max:100','email'=>['required','email','max:255',Rule::unique('users')->ignore($doctor->id)],'password'=>'nullable|string|min:10|max:72','is_active'=>'nullable|boolean']);
        if (!empty($data['password'])) $data['password']=Hash::make($data['password']); else unset($data['password']);
        $data['is_active']=$request->boolean('is_active'); $doctor->fill($data); $changed=array_keys($doctor->getDirty()); $doctor->save(); AuditLog::kaydet('doktor_guncelle',$doctor,['alanlar'=>$changed]);
        return redirect()->route('admin.doctors.index')->with('ok','Doktor güncellendi.');
    }
}
