@extends('layouts.admin')
@section('title', __('أداء :name', ['name' => $user->name]))

@section('admin')
<x-page-header :title="__('أداء :name', ['name' => $user->name])" :subtitle="__('مقارنة بمتوسط الفريق خلال الفترة المختارة')">
    <a href="{{ route('admin.reports', request()->only('preset', 'from', 'to')) }}" class="btn btn-quiet">{{ __('كل التقارير') }}</a>
</x-page-header>
@include('reports.performance', ['routeName' => 'admin.reports.employee', 'routeParams' => ['user' => $user->id]])
@endsection
