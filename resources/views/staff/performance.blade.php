@extends('layouts.panel')
@section('title', __('التقارير'))

@section('content')
<x-page-header :title="__('أدائي')" :subtitle="__('مقارنة بمتوسط الفريق خلال الفترة المختارة')" />
@include('reports.performance', ['routeName' => 'staff.performance', 'routeParams' => []])
@endsection
