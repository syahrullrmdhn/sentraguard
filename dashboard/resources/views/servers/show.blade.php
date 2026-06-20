@extends('layouts.app')

@section('title', 'Detail Server')

@section('content')
    @livewire('server-detail', ['server' => $server])
@endsection
