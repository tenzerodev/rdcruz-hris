@php $id = $loan ? 'l'.$loan->id.'_' : ''; $val = fn ($f, $d = null) => old($f, $loan?->{$f} ?? $d); @endphp
<div class="row">
    <div class="field"><label for="{{ $id }}lender">Remit to</label><select id="{{ $id }}lender" name="lender">@foreach (\App\Models\Loan::lenders() as $k => $l)<option value="{{ $k }}" @selected($val('lender') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $id }}kind">Kind</label><select id="{{ $id }}kind" name="kind">@foreach (\App\Models\Loan::KINDS as $k => $l)<option value="{{ $k }}" @selected($val('kind', 'loan') === $k)>{{ $l }}</option>@endforeach</select></div>
</div>
<div class="field"><label for="{{ $id }}name">Name on the payslip</label><input type="text" id="{{ $id }}name" name="name" value="{{ $val('name') }}" list="loan-names" required placeholder="e.g. GSIS Consolidated Loan"></div>
<datalist id="loan-names">
    @foreach (['GSIS Consolidated Loan', 'GSIS Multi-Purpose Loan', 'GSIS Emergency Loan', 'GSIS Policy Loan', 'GSIS Computer Loan', 'Pag-IBIG Multi-Purpose Loan', 'Pag-IBIG Calamity Loan', 'Pag-IBIG Housing Loan', 'Pag-IBIG MP2 Savings', 'Cooperative Loan', 'Cooperative Share Capital', 'Union Dues'] as $n)<option value="{{ $n }}">@endforeach
</datalist>
<div class="row">
    <div class="field"><label for="{{ $id }}reference_no">Reference / loan no.</label><input type="text" id="{{ $id }}reference_no" name="reference_no" value="{{ $val('reference_no') }}"></div>
    <div class="field"><label for="{{ $id }}amortization">Per month (₱)</label><input type="number" step="0.01" min="1" id="{{ $id }}amortization" name="amortization" value="{{ $val('amortization') }}" required></div>
</div>
<div class="row">
    <div class="field"><label for="{{ $id }}total_amount">Total payable (₱)</label><input type="number" step="0.01" min="1" id="{{ $id }}total_amount" name="total_amount" value="{{ $val('total_amount') }}"><div class="hint">Leave blank for contributions.</div></div>
</div>
<div class="row">
    <div class="field"><label for="{{ $id }}start_period">First month</label><input type="month" id="{{ $id }}start_period" name="start_period" value="{{ $val('start_period', now()->format('Y-m')) }}" required></div>
    <div class="field"><label for="{{ $id }}end_period">Last month</label><input type="month" id="{{ $id }}end_period" name="end_period" value="{{ $val('end_period') }}"><div class="hint">Optional.</div></div>
</div>
<div class="field"><label for="{{ $id }}notes">Notes</label><input type="text" id="{{ $id }}notes" name="notes" value="{{ $val('notes') }}"></div>
