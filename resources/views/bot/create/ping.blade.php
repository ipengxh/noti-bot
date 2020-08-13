@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        创建一个ping检测机器人 <a href="javascript:history.go(-1)" class="btn btn-sm btn-outline-info float-right">返回</a>
                    </div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif
                        <p>这个功能暂未开放</p>
                            <form action="{{ route('bot.store') }}" method="POST">
                                @csrf
                            </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
