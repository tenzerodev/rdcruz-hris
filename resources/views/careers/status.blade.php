@extends('layouts.app')
@section('title', 'Application status')
@section('bare', true)
@section('content')
@include('careers._head')
<section class="card" style="max-width:560px;margin:24px auto">
    <h1>Check my application</h1>
    @if ($applicant)
        <p><b>{{ $applicant->vacancy->title }}</b><br><span class="muted">{{ $applicant->reference_no }} · received {{ $applicant->created_at->format('F j, Y') }}</span></p>
        <p class="alert alert-ok" style="margin:0">{{ \App\Models\Applicant::PUBLIC_STATUS[$applicant->status] ?? $applicant->status }}</p>
    @else
        <form method="post" action="{{ route('careers.status') }}">@csrf
            <div class="field"><label for="reference_no">Reference number</label><input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no') }}" required placeholder="APP-26-XXXXXX"></div>
            <div class="field"><label for="email">Email you applied with</label><input type="email" id="email" name="email" value="{{ old('email') }}" required></div>
            <button class="btn btn-primary">Check</button>
        </form>
    @endif
</section>
@endsection
