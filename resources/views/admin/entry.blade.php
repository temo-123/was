@extends('layouts.admin')

@section('title', __('New entrance'))

@section('content')
    <div data-vue="VisitorEntry" data-props='@json($config)'>
        <div class="text-center py-5"><span class="spinner-border"></span></div>
    </div>
@endsection
