@csrf
<label>İşyeri adı</label><input name="name" required maxlength="150" value="{{ old('name',$workplace->name??'') }}"><br><br>
<label>SGK işyeri sicil no (isteğe bağlı)</label><input name="sgk_sicil_no" maxlength="26" value="{{ old('sgk_sicil_no',$workplace->sgk_sicil_no??'') }}"><br><br>
<label>Adres (isteğe bağlı)</label><input name="address" maxlength="500" value="{{ old('address',$workplace->address??'') }}"><br><br>
<label><input style="width:auto" type="checkbox" name="is_active" value="1" {{ old('is_active',$workplace->is_active??true)?'checked':'' }}> Aktif (doktorlar seçebilir)</label><br><br>
@foreach($errors->all() as $e)<div class="err">{{ $e }}</div>@endforeach<button class="btn">Kaydet</button>
