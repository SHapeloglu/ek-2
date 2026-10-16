@extends('layouts.base') @section('title','Giriş') @section('body')
<div class="wrap mobile" style="padding-top:8vh"><div class="card"><h1>EK-2 Sistemi</h1><p class="muted">Yetkili kullanıcı girişi</p>
<form method="post" action="{{ route('login.store') }}">@csrf
<label>E-posta</label><input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"><br><br>
<label>Şifre</label><input type="password" name="password" required autocomplete="current-password"><br>@error('email')<p class="err">{{ $message }}</p>@enderror<br>
<button class="btn">Giriş Yap</button></form></div></div>@endsection
