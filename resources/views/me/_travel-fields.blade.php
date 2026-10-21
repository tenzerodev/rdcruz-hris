<div class="row">
    <div class="field"><label for="ttype">Type</label><select id="ttype" name="type">@foreach (\App\Models\TravelOrder::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="tfrom">From</label><input type="date" id="tfrom" name="date_from" value="{{ old('date_from') }}" required></div>
    <div class="field"><label for="tto">To</label><input type="date" id="tto" name="date_to" value="{{ old('date_to') }}" required></div>
</div>
<div class="row">
    <div class="field"><label for="time_from">Time from (partial day)</label><input type="time" id="time_from" name="time_from" value="{{ old('time_from') }}"></div>
    <div class="field"><label for="time_to">Time to</label><input type="time" id="time_to" name="time_to" value="{{ old('time_to') }}"></div>
</div>
<p class="hint" style="margin-top:-6px">Leave the times blank for whole days. With times, the hours are counted as time present on your DTR.</p>
<div class="field"><label for="destination">Destination / venue</label><input type="text" id="destination" name="destination" value="{{ old('destination') }}" required></div>
<div class="field"><label for="tpurpose">Purpose</label><input type="text" id="tpurpose" name="purpose" value="{{ old('purpose') }}" required></div>
