<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class WorkplaceController extends Controller {
    public function index(){ $workplaces=Workplace::withCount('records')->orderBy('name')->paginate(30); return view('admin.workplaces.index',compact('workplaces')); }
    public function create(){ return view('admin.workplaces.create'); }
    public function store(Request $request){
        $workplace=Workplace::create($this->validated($request)); AuditLog::kaydet('isyeri_olustur',$workplace);
        return redirect()->route('admin.workplaces.index')->with('ok','İşyeri oluşturuldu.');
    }
    public function edit(Workplace $workplace){ return view('admin.workplaces.edit',compact('workplace')); }
    public function update(Request $request, Workplace $workplace){
        $workplace->fill($this->validated($request,$workplace)); $changed=array_keys($workplace->getDirty()); $workplace->save(); AuditLog::kaydet('isyeri_guncelle',$workplace,['alanlar'=>$changed]);
        return redirect()->route('admin.workplaces.index')->with('ok','İşyeri güncellendi.');
    }
    private function validated(Request $request, ?Workplace $workplace=null): array {
        $data=$request->validate(['name'=>'required|string|max:150','sgk_sicil_no'=>['nullable','string','max:26',Rule::unique('workplaces')->ignore($workplace?->id)],'address'=>'nullable|string|max:500','is_active'=>'nullable|boolean']);
        $data['is_active']=$request->boolean('is_active'); return $data;
    }
}
