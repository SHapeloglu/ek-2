@extends('layouts.base') @section('body')<div class="wrap"><a href="{{ route('admin.records.show',$record) }}">← Kayıt</a><h1>Kaydı Düzelt</h1><div class="card"><form method="post" action="{{ route('admin.records.update',$record) }}">@csrf @method('PUT')
<label>İşyeri</label><select name="workplace_id" required>@foreach($workplaces as $w)<option value="{{ $w->id }}" @selected(old('workplace_id',$record->workplace_id)==$w->id)>{{ $w->name }}</option>@endforeach</select><br><br>
<label>T.C. Kimlik No</label><input name="tc_kimlik_no" autocomplete="off" inputmode="numeric" maxlength="11" required value="{{ old('tc_kimlik_no',$record->tc_kimlik_no) }}"><br><br>
<div class="grid" style="grid-template-columns:1fr 1fr"><div><label>Ad</label><input name="first_name" required value="{{ old('first_name',$record->first_name) }}"></div><div><label>Soyad</label><input name="last_name" required value="{{ old('last_name',$record->last_name) }}"></div></div><br>
<label>Muayene (onay) tarihi</label><input type="date" name="exam_date" value="{{ old('exam_date',$record->exam_date?->format('Y-m-d')) }}"><br><br>
<label>Düzeltme notu (erişim kaydına yazılır)</label><input name="note" required maxlength="500" value="{{ old('note') }}" placeholder="Örn. TC yanlış girilmiş, kimlikten kontrol edildi"><br><br>
<p class="muted">Fotoğraf okunmuyorsa düzeltme yerine kaydı pasife alın; doktor yeni kayıt açar.</p>
@foreach($errors->all() as $e)<div class="err">{{ $e }}</div>@endforeach<button class="btn">Kaydet</button></form></div></div>@endsection
