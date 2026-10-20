@csrf
<div class="grid" style="grid-template-columns:1fr 1fr"><div><label>Ad</label><input name="name" required value="{{ old('name',$doctor->name??'') }}"></div><div><label>Soyad</label><input name="surname" required value="{{ old('surname',$doctor->surname??'') }}"></div></div><br>
<label>E-posta</label><input type="email" name="email" required value="{{ old('email',$doctor->email??'') }}"><br><br>
<label>{{ isset($doctor)?'Yeni şifre (değişmeyecekse boş bırakın)':'Geçici şifre' }}</label><input type="password" name="password" {{ isset($doctor)?'':'required' }}><br><br>
<label><input style="width:auto" type="checkbox" name="is_active" value="1" {{ old('is_active',$doctor->is_active??true)?'checked':'' }}> Aktif</label><br><br>
@foreach($errors->all() as $e)<div class="err">{{ $e }}</div>@endforeach<button class="btn">Kaydet</button>
