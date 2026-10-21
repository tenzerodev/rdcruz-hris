<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Personal Data Sheet — {{ $e->full_name }}</title>
@php
    $v = fn ($path) => data_get($p, $path) ?: '';
    $addr = fn ($k) => collect(\App\Support\Pds::ADDRESS)->map(fn ($f) => data_get($p, "$k.$f"))->filter()->join(', ');
    $yn = fn ($q, $a) => data_get($p, "questions.$q.answer") === $a ? '☒' : '☐';
    $d = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('m/d/Y') : '';
    // V. Work experience: this agency's service record first (most recent first), then outside entries.
    $work = $e->serviceRecords->sortByDesc('date_from')->map(fn ($r) => [
        'from' => $r->date_from->format('m/d/Y'), 'to' => $r->date_to?->format('m/d/Y') ?? 'Present', 'position' => $r->designation,
        'company' => $settings['agency_name'], 'monthly_salary' => $r->annual_salary ? number_format($r->annual_salary / 12, 2) : '',
        'salary_grade' => '', 'status' => $r->status, 'government' => 'Y',
    ])->concat(collect($p['work_experience'] ?? [])->map(fn ($w) => ['from' => $d($w['from'] ?? ''), 'to' => $d($w['to'] ?? '')] + $w))->values();
    $edu = $e->educations->keyBy('level');
@endphp
<style>
    @page { size: 8.5in 13in; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font: 7.5pt/1.25 Arial, Helvetica, sans-serif; color: #000; background: #e5e7eb; }
    .toolbar { position: sticky; top: 0; background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; font: 14px system-ui, sans-serif; z-index: 2; }
    .toolbar button { background: #2563eb; color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; font: inherit; font-weight: 600; cursor: pointer; }
    .page { background: #fff; width: 8.5in; min-height: 13in; margin: 12px auto; padding: 8mm; box-shadow: 0 2px 10px rgba(0,0,0,.15); page-break-after: always; break-after: page; }
    .page:last-child { page-break-after: auto; break-after: auto; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td, th { border: 1px solid #000; padding: .8mm 1.2mm; vertical-align: top; }
    th, .l { background: #eaeaea; font-weight: normal; font-size: 6.8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sec { background: #8a8a8a; color: #fff; font-weight: bold; font-style: italic; font-size: 8pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .v { font-weight: bold; text-transform: uppercase; }
    .c { text-align: center; } .h { height: 5mm; }
    .title { text-align: center; font-weight: 900; font-size: 18pt; letter-spacing: 1px; margin: 1mm 0; }
    .warn { font-size: 6.8pt; font-style: italic; }
    .foot { font-size: 6.5pt; font-style: italic; text-align: center; margin-top: 1mm; }
    @media print { body { background: #fff; } .toolbar { display: none; } .page { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print</button><span>Personal Data Sheet — print on long bond paper (8.5 × 13 in), 4 pages</span></div>

{{-- Page 1: I. Personal information, II. Family background, III. Educational background --}}
<div class="page">
    <div style="display:flex;justify-content:space-between;align-items:flex-start"><div style="font-size:7pt;font-style:italic;font-weight:bold">CS Form No. 212<br>Revised 2017</div><x-print-header compact style="margin:0" /><span style="width:20mm"></span></div>
    <div class="title">PERSONAL DATA SHEET</div>
    <div class="warn">WARNING: Any misrepresentation made in the Personal Data Sheet and the Work Experience Sheet shall cause the filing of administrative/criminal case/s against the person concerned.</div>
    <table style="margin-top:1mm">
        <tr><td class="sec" colspan="6">I. PERSONAL INFORMATION</td></tr>
        <tr><td class="l">2. SURNAME</td><td class="v" colspan="5">{{ $e->last_name }}</td></tr>
        <tr><td class="l">FIRST NAME</td><td class="v" colspan="3">{{ $e->first_name }}</td><td class="l">NAME EXTENSION (JR., SR)</td><td class="v">{{ $e->suffix }}</td></tr>
        <tr><td class="l">MIDDLE NAME</td><td class="v" colspan="5">{{ $e->middle_name }}</td></tr>
        <tr><td class="l">3. DATE OF BIRTH (mm/dd/yyyy)</td><td class="v">{{ $e->birth_date?->format('m/d/Y') }}</td><td class="l" rowspan="2">16. CITIZENSHIP</td><td colspan="3" rowspan="2">{{ $v('personal.citizenship') ?: 'Filipino' }} {{ $v('personal.dual_by') ? '(by '.$v('personal.dual_by').')' : '' }} {{ $v('personal.dual_country') }}</td></tr>
        <tr><td class="l">4. PLACE OF BIRTH</td><td class="v">{{ $v('personal.place_of_birth') }}</td></tr>
        <tr><td class="l">5. SEX</td><td class="v">{{ $e->sex }}</td><td class="l" rowspan="3">17. RESIDENTIAL ADDRESS</td><td colspan="3" rowspan="3" class="v">{{ $addr('residential') }}</td></tr>
        <tr><td class="l">6. CIVIL STATUS</td><td class="v">{{ str_replace('_', ' ', (string) $e->civil_status) }}</td></tr>
        <tr><td class="l">7. HEIGHT (m)</td><td class="v">{{ $v('personal.height') }}</td></tr>
        <tr><td class="l">8. WEIGHT (kg)</td><td class="v">{{ $v('personal.weight') }}</td><td class="l" rowspan="3">18. PERMANENT ADDRESS</td><td colspan="3" rowspan="3" class="v">{{ $addr('permanent') }}</td></tr>
        <tr><td class="l">9. BLOOD TYPE</td><td class="v">{{ $v('personal.blood_type') }}</td></tr>
        <tr><td class="l">10. GSIS ID NO.</td><td class="v">{{ $e->gsis_no }}</td></tr>
        <tr><td class="l">11. PAG-IBIG ID NO.</td><td class="v">{{ $e->pagibig_no }}</td><td class="l">19. TELEPHONE NO.</td><td colspan="3" class="v">{{ $v('personal.telephone') }}</td></tr>
        <tr><td class="l">12. PHILHEALTH NO.</td><td class="v">{{ $e->philhealth_no }}</td><td class="l">20. MOBILE NO.</td><td colspan="3" class="v">{{ $e->mobile }}</td></tr>
        <tr><td class="l">13. SSS NO.</td><td class="v">{{ $e->sss_no }}</td><td class="l">21. E-MAIL ADDRESS</td><td colspan="3" style="font-weight:bold">{{ $e->email }}</td></tr>
        <tr><td class="l">14. TIN NO.</td><td class="v">{{ $e->tin }}</td><td class="l">UMID / PhilSys</td><td colspan="3" class="v">{{ $v('personal.umid_no') }} {{ $v('personal.philsys_no') ? '/ '.$v('personal.philsys_no') : '' }}</td></tr>
        <tr><td class="l">15. AGENCY EMPLOYEE NO.</td><td class="v" colspan="5">{{ $e->employee_no }}</td></tr>

        <tr><td class="sec" colspan="6">II. FAMILY BACKGROUND</td></tr>
        <tr><td class="l">22. SPOUSE'S SURNAME</td><td class="v" colspan="2">{{ $v('spouse.surname') }}</td><td class="l" colspan="2">23. NAME of CHILDREN</td><td class="l">DATE OF BIRTH</td></tr>
        @php $children = array_pad($p['children'] ?? [], 12, []); @endphp
        @php
            $rowsLeft = [
            ['FIRST NAME', trim($v('spouse.first').' '.$v('spouse.ext'))], ['MIDDLE NAME', $v('spouse.middle')], ['OCCUPATION', $v('spouse.occupation')],
            ['EMPLOYER/BUSINESS NAME', $v('spouse.employer')], ['BUSINESS ADDRESS', $v('spouse.business_address')], ['TELEPHONE NO.', $v('spouse.telephone')],
            ["24. FATHER'S SURNAME", $v('father.surname')], ['FIRST NAME', trim($v('father.first').' '.$v('father.ext'))], ['MIDDLE NAME', $v('father.middle')],
            ["25. MOTHER'S MAIDEN NAME — SURNAME", $v('mother.surname')], ['FIRST NAME', $v('mother.first')], ['MIDDLE NAME', $v('mother.middle')],
        ];
        @endphp
        @foreach ($rowsLeft as $i => [$label, $value])
            <tr><td class="l">{{ $label }}</td><td class="v" colspan="2">{{ $value }}</td><td colspan="2" class="v">{{ $children[$i]['name'] ?? '' }}</td><td class="c">{{ $d($children[$i]['birth_date'] ?? '') }}</td></tr>
        @endforeach

        <tr><td class="sec" colspan="6">III. EDUCATIONAL BACKGROUND</td></tr>
    </table>
    <table>
        <tr><th style="width:14%">26. LEVEL</th><th style="width:22%">NAME OF SCHOOL</th><th style="width:20%">BASIC EDUCATION/DEGREE/COURSE</th><th colspan="2">PERIOD OF ATTENDANCE<br>From · To</th><th>HIGHEST LEVEL / UNITS EARNED</th><th>YEAR GRADUATED</th><th>SCHOLARSHIP / ACADEMIC HONORS</th></tr>
        @foreach (\App\Models\Education::LEVELS as $lvl => $label)
            @php $x = $edu[$lvl] ?? null; @endphp
            <tr class="h"><td class="l">{{ strtoupper($label) }}</td><td class="v">{{ $x?->school }}</td><td class="v">{{ $x?->degree }}</td><td class="c">{{ $x?->year_from }}</td><td class="c">{{ $x?->year_to }}</td><td></td><td class="c">{{ $x?->year_graduated }}</td><td>{{ $x?->honors }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm"><tr><td class="l c" style="width:20%"><b>SIGNATURE</b></td><td></td><td class="l c" style="width:12%"><b>DATE</b></td><td style="width:20%"></td></tr></table>
    <div class="foot">CS FORM 212 (Revised 2017), Page 1 of 4</div>
</div>

{{-- Page 2: IV. Eligibility, V. Work experience --}}
<div class="page">
    <table>
        <tr><td class="sec" colspan="6">IV. CIVIL SERVICE ELIGIBILITY</td></tr>
        <tr><th style="width:30%">27. CAREER SERVICE / RA 1080 (BOARD/BAR) UNDER SPECIAL LAWS / CES / CSEE / BARANGAY ELIGIBILITY / DRIVER'S LICENSE</th><th>RATING</th><th>DATE OF EXAMINATION / CONFERMENT</th><th style="width:22%">PLACE OF EXAMINATION / CONFERMENT</th><th>LICENSE NUMBER</th><th>VALIDITY</th></tr>
        @foreach ($e->eligibilities->pad(7, null) as $x)
            <tr class="h"><td class="v">{{ $x?->name }}</td><td class="c">{{ $x?->rating }}</td><td class="c">{{ $x?->exam_date?->format('m/d/Y') }}</td><td>{{ $x?->exam_place }}</td><td class="c">{{ $x?->license_no }}</td><td class="c">{{ $x?->valid_until?->format('m/d/Y') }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm">
        <tr><td class="sec" colspan="8">V. WORK EXPERIENCE <span style="font-weight:normal;font-size:6.5pt">(Include private employment. Start from your recent work.)</span></td></tr>
        <tr><th colspan="2">28. INCLUSIVE DATES<br>From · To</th><th style="width:22%">POSITION TITLE</th><th style="width:22%">DEPARTMENT / AGENCY / OFFICE / COMPANY</th><th>MONTHLY SALARY</th><th>SALARY/ JOB/ PAY GRADE & STEP</th><th>STATUS OF APPOINTMENT</th><th>GOV'T SERVICE (Y/N)</th></tr>
        @foreach ($work->pad(28, null) as $w)
            <tr class="h"><td class="c">{{ $w['from'] ?? '' }}</td><td class="c">{{ $w['to'] ?? '' }}</td><td class="v">{{ $w['position'] ?? '' }}</td><td class="v">{{ $w['company'] ?? '' }}</td><td class="c">{{ $w['monthly_salary'] ?? '' }}</td><td class="c">{{ $w['salary_grade'] ?? '' }}</td><td class="c">{{ $w['status'] ?? '' }}</td><td class="c">{{ $w['government'] ?? '' }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm"><tr><td class="l c" style="width:20%"><b>SIGNATURE</b></td><td></td><td class="l c" style="width:12%"><b>DATE</b></td><td style="width:20%"></td></tr></table>
    <div class="foot">CS FORM 212 (Revised 2017), Page 2 of 4</div>
</div>

{{-- Page 3: VI. Voluntary work, VII. L&D, VIII. Other information --}}
<div class="page">
    <table>
        <tr><td class="sec" colspan="5">VI. VOLUNTARY WORK OR INVOLVEMENT IN CIVIC / NON-GOVERNMENT / PEOPLE / VOLUNTARY ORGANIZATION/S</td></tr>
        <tr><th style="width:38%">29. NAME & ADDRESS OF ORGANIZATION</th><th colspan="2">INCLUSIVE DATES<br>From · To</th><th>NUMBER OF HOURS</th><th style="width:28%">POSITION / NATURE OF WORK</th></tr>
        @foreach (collect($p['voluntary'] ?? [])->pad(7, []) as $x)
            <tr class="h"><td class="v">{{ $x['organization'] ?? '' }}</td><td class="c">{{ $d($x['from'] ?? '') }}</td><td class="c">{{ $d($x['to'] ?? '') }}</td><td class="c">{{ $x['hours'] ?? '' }}</td><td class="v">{{ $x['position'] ?? '' }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm">
        <tr><td class="sec" colspan="6">VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED</td></tr>
        <tr><th style="width:38%">30. TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING PROGRAMS</th><th colspan="2">INCLUSIVE DATES OF ATTENDANCE<br>From · To</th><th>NUMBER OF HOURS</th><th>Type of LD (Managerial / Supervisory / Technical / etc.)</th><th style="width:22%">CONDUCTED / SPONSORED BY</th></tr>
        @foreach ($e->trainings->pad(21, null) as $t)
            <tr class="h"><td class="v">{{ $t?->title }}</td><td class="c">{{ $t?->date_from?->format('m/d/Y') }}</td><td class="c">{{ $t?->date_to?->format('m/d/Y') }}</td><td class="c">{{ $t?->hours }}</td><td class="c">{{ $t ? (\App\Models\Training::TYPES[$t->type] ?? '') : '' }}</td><td>{{ $t?->sponsor }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm">
        <tr><td class="sec" colspan="3">VIII. OTHER INFORMATION</td></tr>
        <tr><th>31. SPECIAL SKILLS and HOBBIES</th><th>32. NON-ACADEMIC DISTINCTIONS / RECOGNITION</th><th>33. MEMBERSHIP IN ASSOCIATION/ORGANIZATION</th></tr>
        @php $n = max(7, count($p['skills'] ?? []), count($p['recognitions'] ?? []), count($p['memberships'] ?? [])); @endphp
        @for ($i = 0; $i < $n; $i++)
            <tr class="h"><td class="v">{{ $p['skills'][$i] ?? '' }}</td><td class="v">{{ $p['recognitions'][$i] ?? '' }}</td><td class="v">{{ $p['memberships'][$i] ?? '' }}</td></tr>
        @endfor
    </table>
    <table style="margin-top:1mm"><tr><td class="l c" style="width:20%"><b>SIGNATURE</b></td><td></td><td class="l c" style="width:12%"><b>DATE</b></td><td style="width:20%"></td></tr></table>
    <div class="foot">CS FORM 212 (Revised 2017), Page 3 of 4</div>
</div>

{{-- Page 4: Questions 34–40, references, declaration, government ID --}}
<div class="page">
    <table>
        @foreach (\App\Support\Pds::QUESTIONS as $q => $text)
            <tr><td class="l" style="width:62%">{{ $text }}</td>
                <td>{{ $yn($q, 'yes') }} YES &nbsp; {{ $yn($q, 'no') }} NO
                    <div style="margin-top:1mm">If YES, give details: <b>{{ data_get($p, "questions.$q.details") }}</b></div></td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm">
        <tr><td class="sec" colspan="3">41. REFERENCES <span style="font-weight:normal;font-size:6.5pt">(Person not related by consanguinity or affinity to applicant/appointee)</span></td></tr>
        <tr><th>NAME</th><th>ADDRESS</th><th>TEL. NO.</th></tr>
        @foreach (collect($p['references'] ?? [])->pad(3, []) as $r)
            <tr class="h"><td class="v">{{ $r['name'] ?? '' }}</td><td class="v">{{ $r['address'] ?? '' }}</td><td class="c">{{ $r['telephone'] ?? '' }}</td></tr>
        @endforeach
    </table>
    <table style="margin-top:1mm">
        <tr><td colspan="3" style="font-size:7pt">42. I declare under oath that I have personally accomplished this Personal Data Sheet which is a true, correct and complete statement pursuant to the provisions of pertinent laws, rules and regulations of the Republic of the Philippines. I authorize the agency head/authorized representative to verify/validate the contents stated herein. I agree that any misrepresentation made in this document and its attachments shall cause the filing of administrative/criminal case/s against me.</td></tr>
        <tr>
            <td style="width:38%"><div class="l" style="margin:-.8mm -1.2mm 1mm;padding:.8mm 1.2mm">Government Issued ID (i.e. Passport, GSIS, SSS, PRC, Driver's License, etc.)</div>
                Government Issued ID: <b>{{ $v('gov_id.type') }}</b><br>ID/License/Passport No.: <b>{{ $v('gov_id.number') }}</b><br>Date/Place of Issuance: <b>{{ $v('gov_id.issued') }}</b></td>
            <td style="height:30mm"><div style="height:22mm"></div><div class="l c">Signature (Sign inside the box)</div><div class="c" style="margin-top:1mm">Date Accomplished: ____________</div></td>
            <td style="width:22%"><div style="height:30mm;border:1px dashed #000;display:flex;align-items:center;justify-content:center;text-align:center;font-size:6.5pt">PHOTO<br>4.5 cm × 3.5 cm</div></td>
        </tr>
        <tr><td colspan="2" style="height:22mm;vertical-align:bottom" class="c">SUBSCRIBED AND SWORN to before me this _______________, affiant exhibiting his/her validly issued government ID as indicated above.<br><br>__________________________________<br>Person Administering Oath</td>
            <td><div style="height:22mm;border:1px solid #000"></div><div class="l c">Right Thumbmark</div></td></tr>
    </table>
    <x-verify-stamp compact type="pds" :key="'pds:'.$e->id" :employee="$e" :title="'Personal Data Sheet — '.$e->full_name" :summary="[
        'Name' => $e->full_name, 'Agency employee no.' => $e->employee_no, 'Birth date' => $e->birth_date?->format('M j, Y'),
        'PDS last updated' => $e->pds_updated_at?->format('M j, Y g:i A') ?? 'never', 'Eligibilities' => $e->eligibilities->count(), 'Trainings' => $e->trainings->count(),
    ]" />
    <div class="foot">CS FORM 212 (Revised 2017), Page 4 of 4 · data last updated {{ $e->pds_updated_at?->format('M j, Y') ?? 'never' }}</div>
</div>
</body>
</html>
