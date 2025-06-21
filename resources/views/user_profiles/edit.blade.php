@extends('layouts.core')

@section('title', 'Edit | User Profile Management')
    
@section('content')
    @include('user_profiles.header')
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">User Profile Details</h5>
            <div class="card-content p-2">
                {{ Form::model($user_profile, ['route' => ['user_profiles.update', $user_profile], 'method' => 'PATCH', 'class' => 'form']) }}
                    @include('user_profiles.form')
                    <div class="text-center">
                        <a href="{{ route('user_profiles.index') }}" class="btn btn-secondary">Cancel</a>
                        {{ Form::submit('Submit', ['class' => 'btn btn-primary']) }}
                    </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
@stop
