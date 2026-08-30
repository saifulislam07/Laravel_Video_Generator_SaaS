@extends('adminlte::page')

@section('title', $title ?? 'Admin')

@section('content_header')
@stop

@section('content')
    {{ $slot }}
@stop
