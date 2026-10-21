{{-- Leave form fields (CSC Form 6): type, dates, details of leave, commutation. --}}
<div class="field"><label for="type">Type of leave</label>
    <select id="type" name="type" required>
        @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach
    </select></div>
<div class="row">
    <div class="field"><label for="date_from">From</label><input type="date" id="date_from" name="date_from" value="{{ old('date_from') }}" required></div>
    <div class="field"><label for="date_to">To</label><input type="date" id="date_to" name="date_to" value="{{ old('date_to') }}" required></div>
    <div class="field"><label for="half_day">Half day</label><select id="half_day" name="half_day"><option value="">Whole day</option><option value="am" @selected(old('half_day') === 'am')>AM only</option><option value="pm" @selected(old('half_day') === 'pm')>PM only</option></select></div>
</div>
<div class="leave-details">
    <div class="row" data-for="VL FL SPL">
        <div class="field"><label for="d-location">Where will it be spent?</label><select id="d-location" name="details[location]"><option value="ph">Within the Philippines</option><option value="abroad" @selected(old('details.location') === 'abroad')>Abroad</option></select></div>
        <div class="field"><label for="d-abroad">If abroad, specify</label><input type="text" id="d-abroad" name="details[abroad_where]" value="{{ old('details.abroad_where') }}"></div>
    </div>
    <div class="row" data-for="SL">
        <div class="field"><label for="d-sick">Sick leave</label><select id="d-sick" name="details[sick]"><option value="outpatient">Out patient</option><option value="hospital" @selected(old('details.sick') === 'hospital')>In hospital</option></select></div>
        <div class="field"><label for="d-illness">Illness</label><input type="text" id="d-illness" name="details[illness]" value="{{ old('details.illness') }}"></div>
    </div>
    <div class="field" data-for="SLBW"><label for="d-women">Illness (Special Leave Benefits for Women)</label><input type="text" id="d-women" name="details[women_illness]" value="{{ old('details.women_illness') }}"></div>
    <div class="field" data-for="STUDY"><label for="d-study">Study leave</label><select id="d-study" name="details[study]"><option value="masters">Completion of Master's degree</option><option value="bar_board">BAR/Board examination review</option></select></div>
    <div class="field" data-for="OTHERS"><label for="d-other">Specify</label><input type="text" id="d-other" name="details[other]" value="{{ old('details.other') }}"></div>
</div>
<div class="field"><label for="reason">Reason / remarks (optional)</label><input type="text" id="reason" name="reason" value="{{ old('reason') }}" maxlength="250"></div>
<label class="check"><input type="hidden" name="commutation" value="0"><input type="checkbox" name="commutation" value="1" @checked(old('commutation'))> Request commutation (monetization)</label>
@once
@push('scripts')
<script>
(() => {
  const type = document.getElementById('type');
  const sync = () => document.querySelectorAll('.leave-details [data-for]').forEach((el) => {
    const on = el.dataset.for.split(' ').includes(type.value);
    el.hidden = !on; el.querySelectorAll('input,select').forEach((i) => { i.disabled = !on; });
  });
  type.addEventListener('change', sync); sync();
})();
</script>
@endpush
@endonce
