<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ek2Record;
use App\Models\Workplace;
use App\Rules\TcKimlikNo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class RecordController extends Controller {
    public function index(Request $request){
        $eksikMi=$request->boolean('eksik');
        $records=Ek2Record::with(['doctor','workplace'])->when($eksikMi,fn($q)=>$q->eksik())->ara($request->input('q'))->latest()->paginate(50)->withQueryString();
        return view('admin.records.index',compact('records','eksikMi'));
    }
    public function show(Ek2Record $record){
        AuditLog::kaydet('kayit_goruntule',$record);
        $logs=AuditLog::with('user')->where('entity_type','ek2_records')->where('entity_id',$record->id)->latest('created_at')->limit(50)->get();
        return view('admin.records.show',compact('record','logs'));
    }
    public function image(Ek2Record $record,string $side){ AuditLog::kaydet('foto_goruntule',$record,['sayfa'=>$side]); return $record->imageResponse($side); }
    public function edit(Ek2Record $record){
        abort_if($record->status==='cancelled',403,'İptal edilmiş kayıt düzeltilemez.');
        $workplaces=Workplace::where('is_active',true)->orWhere('id',$record->workplace_id)->orderBy('name')->get(['id','name']);
        return view('admin.records.edit',compact('record','workplaces'));
    }
    public function update(Request $request, Ek2Record $record){
        abort_if($record->status==='cancelled',403,'İptal edilmiş kayıt düzeltilemez.');
        $data=$request->validate([
            'workplace_id'=>['required','integer',Rule::exists('workplaces','id')->where(fn($q)=>$q->where('is_active',true)->orWhere('id',$record->workplace_id))],
            'tc_kimlik_no'=>['required','digits:11',new TcKimlikNo],'first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],
            'note'=>['required','string','max:500'],
        ]);
        $note=$data['note']; unset($data['note']); $record->fill($data);
        $hadReview=(bool)$record->review_note; $record->review_note=null;
        if (!$record->isDirty() && !$hadReview) return back()->withErrors(['note'=>'Değişiklik yapılmadı.'])->withInput();
        $changed=array_keys($record->getDirty());
        // OCR'a gitmişse künye yeniden yazılsın
        if ($record->ocr_status!=='bekliyor') $record->ocr_status='bekliyor';
        $record->save(); AuditLog::kaydet('kayit_duzelt',$record,['alanlar'=>$changed,'not'=>$note]);
        return redirect()->route('admin.records.show',$record)->with('ok',"Kayıt düzeltildi; OCR'a yeniden aktarılacak.");
    }
    public function retake(Request $request, Ek2Record $record){
        abort_if($record->status==='cancelled',403);
        $data=$request->validate(['pages'=>['nullable','array'],'pages.*'=>['in:front,back'],'retake_note'=>['nullable','string','max:500']]);
        $pages=array_values(array_unique($data['pages'] ?? []));
        $record->update(['retake_pages'=>$pages ?: null,'retake_note'=>$pages ? ($data['retake_note'] ?? null) : null]);
        AuditLog::kaydet('yeniden_cekim_isaretle',$record,['sayfalar'=>$pages,'not'=>$data['retake_note'] ?? null]);
        return redirect()->route('admin.records.show',$record)->with('ok',$pages?'Doktordan yeniden çekim istendi.':'Yeniden çekim isteği kaldırıldı.');
    }
    public function rotate(Ek2Record $record,string $side,string $yon){ abort_if($record->status==='cancelled',403); $record->rotatePage($side,$yon); AuditLog::kaydet('sayfa_dondur',$record,['sayfa'=>$side,'yon'=>$yon]); return redirect()->route('admin.records.show',$record)->with('ok','Sayfa döndürüldü.'); }
    public function cancel(Request $request, Ek2Record $record){
        abort_if($record->status==='cancelled',403,'Kayıt zaten iptal.');
        $data=$request->validate(['cancel_reason'=>['required','string','max:500']]);
        // OCR'a aktarılmışsa ek2:ocr-aktar, OCR klasöründen dosyaları kaldırıp <uuid>.iptal.json bırakır
        $record->update(['status'=>'cancelled','cancelled_at'=>now(),'cancel_reason'=>$data['cancel_reason'],'ocr_status'=>$record->ocr_status==='aktarildi'?'bekliyor':$record->ocr_status]);
        AuditLog::kaydet('kayit_iptal',$record,['neden'=>$data['cancel_reason']]);
        return redirect()->route('admin.records.show',$record)->with('ok','Kayıt iptal edildi.');
    }
}
