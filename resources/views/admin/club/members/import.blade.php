@extends('layouts.admin')

@section('title', __('Import club members'))

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.club.import.preview') }}" enctype="multipart/form-data" class="card">
                @csrf
                <div class="card-body">
                    <label for="file" class="form-label">{{ __('Excel file') }} <small class="text-body-secondary">(.xlsx, .ods, .csv)</small></label>
                    <input id="file" name="file" type="file" accept=".xlsx,.ods,.csv" required class="form-control @error('file') is-invalid @enderror">
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Nothing is saved yet: on the next page you will see which rows are new and which members are already added.') }}</div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-search"></i> {{ __('Check the file') }}</button>
                    <a href="{{ route('admin.club.members.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">{{ __('File format') }}</h3></div>
                <div class="card-body">
                    <p>{{ __('The first row must contain column names. Recognised columns (English or Georgian):') }}</p>
                    <ul class="small">
                        <li><b>{{ __('First name') }}</b> / სახელი, <b>{{ __('Last name') }}</b> / გვარი — {{ __('or one "Full name" column') }}</li>
                        <li><b>{{ __('ID number') }}</b> / პირადი ნომერი</li>
                        <li><b>{{ __('Phone') }}</b> / ტელეფონი</li>
                        <li>{{ __('Email') }} / ელფოსტა, {{ __('Date of birth') }} / დაბადების თარიღი, {{ __('Member since') }}, {{ __('Comment') }}</li>
                    </ul>
                    <p class="small mb-2">{{ __('Each member needs a name and an ID number or phone. A row is skipped as "already added" when its ID number, phone or email belongs to an existing member.') }}</p>
                    <a href="{{ route('admin.club.members.template') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> {{ __('Download template') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
