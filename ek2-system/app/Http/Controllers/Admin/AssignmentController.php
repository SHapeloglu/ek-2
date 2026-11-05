<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller {
    private const MAX_GUN = 366;
    private const MAX_KAYIT = 2000;

    public function index(Request $request) {
        $request->validate(['from'=>'nullable|date','to'=>'nullable|date','workplace_id'=>'nullable|integer']);
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->addDays(30)->toDateString());
        $q = Assignment::with(['doctor','workplace'])->whereDate('starts_at','>=',$from)->whereDate('starts_at','<=',$to);
        if($request->filled('workplace_id')) $q->where('workplace_id', $request->integer('workplace_id'));
        $assignments = $q->orderBy('starts_at')->paginate(50)->withQueryString();
        $workplaces = Workplace::orderBy('name')->get();
        return view('admin.assignments.index', compact('assignments','workplaces','from','to'));
    }

    public function create() {
        return view('admin.assignments.create', [
            'doctors' => User::where('role','doctor')->where('is_active',true)->orderBy('name')->get(),
            'workplaces' => Workplace::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'doctor_ids' => 'required|array|min:1',
            'doctor_ids.*' => 'integer',
            'workplace_id' => 'required|integer|exists:workplaces,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'gunler' => 'required|array|min:1',
            'gunler.*' => 'integer|between:1,7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'note' => 'nullable|string|max:300',
        ]);
        $this->kontrolEt($data['doctor_ids'], $data['workplace_id'], $data['start_time'], $data['end_time']);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        if($start->diffInDays($end) > self::MAX_GUN) {
            throw ValidationException::withMessages(['end_date' => 'En fazla 366 günlük aralık girilebilir.']);
        }
        $adaylar = [];
        for($gun = $start->copy(); $gun->lte($end); $gun->addDay()) {
            if(!in_array($gun->dayOfWeekIso, $data['gunler'])) continue;
            foreach(array_unique($data['doctor_ids']) as $did) {
                $adaylar[] = [(int) $did, $gun->copy()->setTimeFromTimeString($data['start_time']), $gun->copy()->setTimeFromTimeString($data['end_time'])];
            }
        }
        if(count($adaylar) === 0) throw ValidationException::withMessages(['gunler' => 'Seçilen tarih aralığında seçilen günlere denk gelen tarih yok.']);
        if(count($adaylar) > self::MAX_KAYIT) throw ValidationException::withMessages(['end_date' => 'Bu ayarla en fazla '.self::MAX_KAYIT.' kayıt oluşturulabilir; aralığı daraltın.']);

        $cakisma = $this->cakismalar($adaylar, null);
        if($cakisma) throw ValidationException::withMessages(['conflict' => 'Şu görevlendirmeler mevcut bir görevle çakışıyor: '.implode('; ', array_slice($cakisma,0,5)).(count($cakisma)>5 ? ' ve '.(count($cakisma)-5).' tane daha' : '')]);

        $seri = (string) Str::uuid();
        $ilk = DB::transaction(function() use ($adaylar, $data, $seri) {
            $ilk = null;
            foreach($adaylar as [$did, $s, $e]) {
                $a = Assignment::create(['seri_id'=>$seri,'doctor_id'=>$did,'workplace_id'=>$data['workplace_id'],'starts_at'=>$s,'ends_at'=>$e,'note'=>$data['note'] ?? null,'created_by'=>auth()->id()]);
                $ilk ??= $a;
            }
            return $ilk;
        });
        AuditLog::kaydet('gorevlendirme_olustur', $ilk, ['adet'=>count($adaylar),'seri_id'=>$seri]);
        return redirect()->route('admin.assignments.index')->with('ok', count($adaylar).' görevlendirme oluşturuldu.');
    }

    public function edit(Assignment $assignment) {
        return view('admin.assignments.edit', [
            'a' => $assignment->load(['doctor','workplace']),
            'workplaces' => Workplace::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Assignment $assignment) {
        $data = $request->validate([
            'workplace_id' => 'required|integer|exists:workplaces,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'note' => 'nullable|string|max:300',
        ]);
        $this->kontrolEt([$assignment->doctor_id], $data['workplace_id'], $data["start_time"], $data["end_time"]);
        $gun = Carbon::parse($data['date'])->startOfDay();
        $s = $gun->copy()->setTimeFromTimeString($data['start_time']);
        $e = $gun->copy()->setTimeFromTimeString($data['end_time']);
        $cakisma = $this->cakismalar([[$assignment->doctor_id, $s, $e]], $assignment->id);
        if($cakisma) throw ValidationException::withMessages(['conflict' => 'Bu saat aralığı mevcut bir görevle çakışıyor.']);

        $assignment->update(['workplace_id'=>$data['workplace_id'],'starts_at'=>$s,'ends_at'=>$e,'note'=>$data['note'] ?? null]);
        AuditLog::kaydet('gorevlendirme_guncelle', $assignment);
        return redirect()->route('admin.assignments.index')->with('ok', 'Görevlendirme güncellendi.');
    }

    public function destroy(Assignment $assignment) {
        AuditLog::kaydet('gorevlendirme_sil', $assignment);
        $assignment->delete();
        return redirect()->route('admin.assignments.index')->with('ok', 'Görevlendirme silindi.');
    }

    public function destroySeries(Assignment $assignment) {
        if(!$assignment->seri_id) return $this->destroy($assignment);
        $silinen = Assignment::where('seri_id', $assignment->seri_id)->where('starts_at','>=', now())->delete();
        AuditLog::kaydet('gorevlendirme_seri_sil', $assignment, ['adet'=>$silinen]);
        return redirect()->route('admin.assignments.index')->with('ok', $silinen.' gelecek görevlendirme silindi.');
    }

    private function kontrolEt(array $doktorIds, $workplaceId, string $baslangic, string $bitis): void {
        if($bitis <= $baslangic) throw ValidationException::withMessages(['end_time' => 'Bitiş saati başlangıç saatinden sonra olmalı.']);
        $ids = array_unique(array_map('intval', $doktorIds));
        if(User::whereIn('id', $ids)->where('role','doctor')->where('is_active',true)->count() !== count($ids)) {
            throw ValidationException::withMessages(['doctor_ids' => 'Geçersiz ya da pasif bir doktor seçildi.']);
        }
        if(!Workplace::where('id', $workplaceId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['workplace_id' => 'Seçilen işyeri pasif.']);
        }
    }

    private function cakismalar(array $adaylar, ?int $haricId): array {
        $mesajlar = [];
        foreach($adaylar as [$did, $s, $e]) {
            $q = Assignment::where('doctor_id', $did)->where('starts_at','<',$e)->where('ends_at','>',$s);
            if($haricId) $q->where('id','!=',$haricId);
            if($q->exists()) {
                $d = User::find($did);
                $mesajlar[] = ($d ? $d->name.' '.$d->surname : '#'.$did).' '.$s->format('d.m.Y H:i');
            }
        }
        return $mesajlar;
    }
}
