@extends('print._layout')
@section('title', 'CS Form No. 9 — Request for publication of vacant positions')
@section('page-size', 'A4 landscape')
@section('content')
<style>.page { width: 297mm; min-height: 210mm; }</style>
<div class="formno">CS Form No. 9<br>Revised 2025</div>
<x-print-header />
<h1>REQUEST FOR PUBLICATION OF VACANT POSITIONS</h1>
<p class="small">To: CIVIL SERVICE COMMISSION (CSC)<br>We hereby request the publication of the following vacant positions in the {{ $settings['agency_name'] ?? '' }} in the CSC website:</p>
<table class="f small">
    <thead><tr><th>No.</th><th>Position title</th><th>Plantilla item no.</th><th>Salary / job / pay grade</th><th>Monthly salary</th><th>Education</th><th>Training</th><th>Experience</th><th>Eligibility</th><th>Competency (if applicable)</th><th>Place of assignment</th></tr></thead>
    <tbody>
    @forelse ($vacancies as $i => $v)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $v->title }}</td><td>{{ $v->item_no }}</td><td class="c">{{ $v->salary_grade }}</td><td class="r">{{ $v->monthly_salary ? number_format($v->monthly_salary, 2) : '' }}</td>
            <td>{{ $v->education }}</td><td>{{ $v->training }}</td><td>{{ $v->experience }}</td><td>{{ $v->eligibility }}</td><td>{{ $v->competencies }}</td><td>{{ $v->place_of_assignment }}</td></tr>
    @empty <tr><td colspan="11" class="c">No open vacancies.</td></tr> @endforelse
    </tbody>
</table>
<p class="small" style="margin-top:4mm">Interested and qualified applicants should signify their interest in writing. Attach the following documents to the application letter and send to the address below not later than
    <b>{{ $vacancies->min('closing_on')?->format('F j, Y') ?? '__________' }}</b>.</p>
<ol class="small"><li>Fully accomplished Personal Data Sheet (PDS) with recent passport-sized picture (CS Form No. 212, Revised 2025) and Work Experience Sheet;</li><li>Performance rating in the last rating period (if applicable);</li><li>Photocopy of certificate of eligibility / rating / license; and</li><li>Photocopy of Transcript of Records.</li></ol>
<p class="small"><b>QUALIFIED APPLICANTS</b> are advised to hand in or send through courier/email their application to:<br>{{ $settings['hr_head_name'] ?? '' }}{{ ! empty($settings['hr_head_title']) ? ', '.$settings['hr_head_title'] : '' }} — {{ $settings['agency_name'] ?? '' }}, {{ $settings['agency_address'] ?? '' }}{{ ! empty($settings['hr_email']) ? ' · '.$settings['hr_email'] : '' }}
    <br>Online: {{ route('careers.index') }}</p>
<p class="small"><b>APPLICATIONS WITH INCOMPLETE DOCUMENTS SHALL NOT BE ENTERTAINED.</b> This Office does not discriminate on account of age, sex, sexual orientation and gender identity, civil status, disability, religion, ethnicity, or political affiliation.</p>
<div style="margin-top:12mm;margin-left:65%" class="sign"><div class="who">{{ $settings['agency_head_name'] ?? '' }}</div><span class="n">{{ $settings['agency_head_title'] ?? 'Head of Agency' }}</span></div>
@endsection
