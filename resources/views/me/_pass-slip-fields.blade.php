<div class="row">
    <div class="field"><label for="ps_date">Date</label><input type="date" id="ps_date" name="date" value="{{ old('date', today()->toDateString()) }}" required></div>
    <div class="field"><label for="ps_type">Kind</label><select id="ps_type" name="type">@foreach (\App\Models\PassSlip::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach</select></div>
</div>
<div class="row">
    <div class="field"><label for="ps_out">Leave at</label><input type="time" id="ps_out" name="time_out" value="{{ old('time_out') }}" required></div>
    <div class="field"><label for="ps_back">Back by</label><input type="time" id="ps_back" name="time_back" value="{{ old('time_back') }}"><div class="hint">Leave blank if not coming back today.</div></div>
</div>
<div class="field"><label for="ps_dest">Destination</label><input type="text" id="ps_dest" name="destination" value="{{ old('destination') }}" required maxlength="200"></div>
<div class="field"><label for="ps_purpose">Purpose</label><input type="text" id="ps_purpose" name="purpose" value="{{ old('purpose') }}" required maxlength="250"></div>
