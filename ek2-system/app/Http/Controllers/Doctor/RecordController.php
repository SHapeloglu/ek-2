<?php
namespace App\Http\Controllers\Doctor;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ek2Record;
use App\Models\Workplace;
use App\Rules\TcKimlikNo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class RecordController extends Controller {
    public function index(){ $records=Ek2Record::with('workplace')->where('doctor_id',auth()->id())->latest()->paginate(30); return view('doctor.records.index',compact('records')); }
    public function create(){ $workplaces=Workplace::where('is_active',true)->orderBy('name')->get(['id','name']); return view('doctor.records.create',compact('workplaces')); }
    public function store(Request $request){
        $data=$request->validate([
            'workplace_id'=>['required','integer',Rule::exists('workplaces','id')->where('is_active',true)],
            'tc_kimlik_no'=>['required','digits:11',new TcKimlikNo],'first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],
            'front_image'=>['required','image','mimes:jpeg,jpg','max:10240'], 'back_image'=>['required','image','mimes:jpeg,jpg','max:10240'],
        ]);
        $uuid=(string)Str::uuid(); $dir='ek2/'.now()->format('Y/m').'/'.$uuid;
        $front=$request->file('front_image')->storeAs($dir,'front.jpeg','local');
        $back=$request->file('back_image')->storeAs($dir,'back.jpeg','local');
        $record=Ek2Record::create(['doctor_id'=>auth()->id(),'workplace_id'=>$data['workplace_id'],'tc_kimlik_no'=>$data['tc_kimlik_no'],'first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'front_path'=>$front,'back_path'=>$back,'status'=>'completed','completed_at'=>now()]);
        AuditLog::kaydet('kayit_olustur',$record);
        return redirect()->route('doctor.records.index')->with('ok','EK-2 kaydı başarıyla yüklendi.');
    }
    public function show(Ek2Record $record){ $this->authorizeRecord($record); AuditLog::kaydet('kayit_goruntule',$record); return view('doctor.records.show',compact('record')); }
    public function image(Ek2Record $record,string $side){ $this->authorizeRecord($record); AuditLog::kaydet('foto_goruntule',$record,['sayfa'=>$side]); return $record->imageResponse($side); }
    private function authorizeRecord(Ek2Record $record): void { abort_unless($record->doctor_id===auth()->id(),403); }
}
