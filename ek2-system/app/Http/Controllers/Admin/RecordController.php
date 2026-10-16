<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ek2Record;
use App\Models\Workplace;
use App\Rules\TcKimlikNo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
class RecordController extends Controller {
    public function index(Request $request){
        $eksikMi=$request->boolean('eksik'); $mukerrerMi=$request->boolean('mukerrer'); $cakismaMi=$request->boolean('cakisma'); $pasifMi=$request->boolean('pasif');
        $records=Ek2Record::with(['doctor','workplace'])->when($eksikMi,fn($q)=>$q->eksik())->when($mukerrerMi,fn($q)=>$q->mukerrer())->when($cakismaMi,fn($q)=>$q->tcCakisma())->when($pasifMi,fn($q)=>$q->where('status','cancelled'))->ara($request->input('q'))->sirala($request->input('sira'))->paginate(50)->withQueryString();
        $mukerrerIdler=Ek2Record::mukerrer()->whereIn('id',$records->pluck('id'))->pluck('id')->all(); $cakismaIdler=Ek2Record::tcCakisma()->whereIn('id',$records->pluck('id'))->pluck('id')->all();
        return view('admin.records.index',compact('records','eksikMi','mukerrerMi','cakismaMi','pasifMi','mukerrerIdler','cakismaIdler'));
    }
    public function show(Ek2Record $record){
        AuditLog::kaydet('kayit_goruntule',$record);
        $logs=AuditLog::with('user')->where('entity_type','ek2_records')->where('entity_id',$record->id)->latest('created_at')->limit(50)->get();
        return view('admin.records.show',compact('record','logs'));
    }
    public function image(Ek2Record $record,string $side){ AuditLog::kaydet('foto_goruntule',$record,['sayfa'=>$side]); return $record->imageResponse($side); }
    public function edit(Ek2Record $record){
        abort_if($record->status==='cancelled',403,'Pasif kayıt düzeltilemez; önce aktife alın.');
        $workplaces=Workplace::where('is_active',true)->orWhere('id',$record->workplace_id)->orderBy('name')->get(['id','name']);
        return view('admin.records.edit',compact('record','workplaces'));
    }
    public function update(Request $request, Ek2Record $record){
        abort_if($record->status==='cancelled',403,'Pasif kayıt düzeltilemez; önce aktife alın.');
        $data=$request->validate([
            'workplace_id'=>['required','integer',Rule::exists('workplaces','id')->where(fn($q)=>$q->where('is_active',true)->orWhere('id',$record->workplace_id))],
            'tc_kimlik_no'=>['required','digits:11',new TcKimlikNo],'first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],
            'exam_date'=>['nullable','date','before_or_equal:today','after:1990-01-01'],'note'=>['required','string','max:500'],
        ]);
        $note=$data['note']; unset($data['note']); $record->fill($data); if ($record->isDirty('exam_date')) $record->exam_date_source=$record->exam_date ? 'elle' : null;
        $hadReview=(bool)$record->review_note; $record->review_note=null;
        if (!$record->isDirty() && !$hadReview) return back()->withErrors(['note'=>'Değişiklik yapılmadı.'])->withInput();
        $changed=array_keys($record->getDirty());
        // OCR'a gitmişse künye yeniden yazılsın
        if ($record->ocr_status!=='bekliyor') $record->ocr_status='bekliyor';
        $record->save(); AuditLog::kaydet('kayit_duzelt',$record,['alanlar'=>$changed,'not'=>$note]);
        $pasif=Ek2Record::mukerrerleriPasifeAl();
        return redirect()->route('admin.records.show',$record)->with('ok',"Kayıt düzeltildi; OCR'a yeniden aktarılacak.".($pasif?" Aynı TC ve onay tarihli {$pasif} kayıt mükerrer olarak pasife düştü.":''));
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
        abort_if($record->status==='cancelled',403,'Kayıt zaten pasif.');
        $data=$request->validate(['cancel_reason'=>['required','string','max:500']]);
        // OCR'a aktarılmışsa ek2:ocr-aktar, OCR klasöründen dosyaları kaldırıp <uuid>.iptal.json bırakır
        $record->update(['status'=>'cancelled','cancelled_at'=>now(),'cancel_reason'=>$data['cancel_reason'],'ocr_status'=>$record->ocr_status==='aktarildi'?'bekliyor':$record->ocr_status]);
        AuditLog::kaydet('pasife_al',$record,['neden'=>$data['cancel_reason']]);
        return redirect()->route('admin.records.show',$record)->with('ok','Kayıt pasife alındı.');
    }
    public function activate(Ek2Record $record){
        abort_unless($record->status==='cancelled',403,'Kayıt zaten aktif.');
        $neden=$record->cancel_reason;
        $record->update(['status'=>'completed','cancelled_at'=>null,'cancel_reason'=>null,'ocr_status'=>'bekliyor']);
        AuditLog::kaydet('aktife_al',$record,['onceki_neden'=>$neden]);
        // Aktife alınan kayıt başka bir aktif kayıtla mükerrerse admin'in seçimi kalır, diğerleri pasife düşer
        $n=Ek2Record::mukerrerleriPasifeAl($record->id);
        return redirect()->route('admin.records.show',$record)->with('ok','Kayıt aktife alındı.'.($n?" Aynı TC ve onay tarihli {$n} kayıt pasife düştü.":''));
    }
    // Kalıcı silme: yalnız pasif kayıt. Fotoğraflar 30 gün "silinen/" klasöründe bekler (yedek_belgeler.sh temizler);
    // OCR'a gitmişse bağlar kaldırılır, <uuid>.iptal.json bırakılır. Erişim kaydında uuid / form adı / neden kalır.
    public function destroy(Request $request, Ek2Record $record){
        abort_unless($record->status==='cancelled',403,'Yalnız pasif kayıt silinebilir.');
        $data=$request->validate(['onay'=>['required','in:SİL,SIL,sil']],['onay.in'=>'Silmek için kutuya SİL yazın.']);
        $hedef=config('ek2.ocr_export_path');
        foreach (["{$record->uuid}1.jpeg","{$record->uuid}2.jpeg","{$record->uuid}.json"] as $ad) if (is_link("{$hedef}/{$ad}") || is_file("{$hedef}/{$ad}")) @unlink("{$hedef}/{$ad}");
        if ($record->ocr_exported_at) @file_put_contents("{$hedef}/{$record->uuid}.iptal.json", json_encode(['kayit_uuid'=>$record->uuid,'silindi'=>now()->toIso8601String(),'neden'=>$record->cancel_reason], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $disk=Storage::disk('local'); $tasinan=[];
        $klasor='ek2/silinen/'.now()->format('Ymd').'/'.$record->uuid;
        foreach (array_unique(array_filter([$record->front_path ? dirname($record->front_path) : null, $record->back_path ? dirname($record->back_path) : null])) as $dir) {
            foreach ($disk->files($dir) as $f) { $disk->move($f, $klasor.'/'.basename($f)); $tasinan[]=basename($f); }
            if (!$disk->files($dir) && !$disk->directories($dir)) $disk->deleteDirectory($dir);
        }
        AuditLog::kaydet('kayit_sil',$record,['uuid'=>$record->uuid,'form_id'=>$record->legacy_form_id,'neden'=>$record->cancel_reason,'fotograflar'=>$klasor,'dosya'=>count($tasinan)]);
        $record->delete();
        return redirect()->route('admin.records.index')->with('ok','Kayıt silindi. Fotoğrafları 30 gün boyunca silinenler klasöründe saklanır.');
    }
}
