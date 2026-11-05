@extends('layouts.base') @section('body')<style>
.wrap.mobile{font-size:1.2rem;padding:18px}
.wrap.mobile h1{font-size:2rem;margin:10px 0}
.wrap.mobile a{font-size:1.15rem}
.wrap.mobile .muted{font-size:1.1rem;line-height:1.5}
.wrap.mobile .card{padding:18px;margin-bottom:12px}
.wrap.mobile input,.wrap.mobile select{font-size:1.25rem;min-height:66px!important;padding:14px!important;border-radius:12px}
.wrap.mobile .btn{min-height:66px!important;font-size:1.25rem!important;padding:14px 22px!important;border-radius:14px;line-height:1.2}
.wrap.mobile .btn{display:flex;width:100%}
.wrap.mobile .top .btn{width:auto;display:inline-flex}
</style><div class="wrap mobile"><div class="top"><div><strong>Dr. {{ auth()->user()->name }} {{ auth()->user()->surname }}</strong><div class="muted">EK-2 Mobil</div></div><form method="post" action="{{ route('logout') }}">@csrf<button class="btn light">Çıkış</button></form></div><a class="btn" href="{{ route('doctor.records.create') }}">+ Yeni EK-2 Kaydı</a><br><br><a class="btn light" href="{{ route('doctor.assignments.index') }}">Görevlerim</a><br><br><a class="btn light" href="{{ route('doctor.records.index') }}">EK-2'lerim ({{ $count }})</a><br><br><div class="card"><strong>{{ $today }}</strong><div class="muted">Bugün yüklenen kayıt</div></div>@if($eksik>0)<br><a class="card" href="{{ route('doctor.records.index',['eksik'=>1]) }}" style="display:block;border-color:#b42318;background:#fdecea;color:#b42318;text-decoration:none"><strong>Yeniden çekilecek / eksik: {{ $eksik }} kayıt</strong><div class="muted" style="color:#b42318">Eksik ya da yeniden çekilmesi istenen sayfaları olan kayıtları görmek için dokunun.</div></a>@endif</div>@endsection
