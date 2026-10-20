<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
class LoginController extends Controller {
    public function create(){ return view('auth.login'); }
    public function store(Request $request){
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if (!Auth::attempt($credentials, $request->boolean('remember'))) { AuditLog::kaydet('giris_basarisiz',null,['email'=>mb_substr($credentials['email'],0,255)],User::where('email',$credentials['email'])->value('id')); return back()->withErrors(['email'=>'E-posta veya şifre hatalı.'])->onlyInput('email'); }
        $request->session()->regenerate();
        if (!$request->user()->is_active) { AuditLog::kaydet('giris_pasif_hesap'); Auth::logout(); return back()->withErrors(['email'=>'Hesabınız pasif durumda.']); }
        AuditLog::kaydet('giris');
        return redirect()->intended($request->user()->isAdmin()?route('admin.dashboard'):route('doctor.dashboard'));
    }
    public function destroy(Request $request){ AuditLog::kaydet('cikis'); Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
}
