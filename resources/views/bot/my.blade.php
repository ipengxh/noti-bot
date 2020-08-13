@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        我的机器人
                        <div class="btn-group float-right">
                            <button class="btn btn-sm btn-outline-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">新建</button>
                            <div class="dropdown-menu" aria-labelledby="button-dropdown">
                                <a href="{{ route('bot.create', ['type' => 'url']) }}" class="dropdown-item">URL监视器</a>
                                <a href="{{ route('bot.create', ['type' => 'ping']) }}" class="dropdown-item">Ping</a>
                                <a href="{{ route('bot.create', ['type' => 'timer']) }}" class="dropdown-item">定时消息</a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif
                        <table class="table">
                            <thead>
                            <tr>
                                <th>名称</th>
                                <th>关键信息</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($bots as $bot)
                                <tr>
                                    <td>{{ $bot->name }}</td>
                                    <td>{{ $bot->config->url }}</td>
                                    <td>{{ $bot->created_at }}</td>
                                    <td>
                                        <a class="btn btn-outline-info btn-sm" href="{{ route('bot.edit', ['id' => $bot->id]) }}">
                                            update
                                        </a>
                                        <a class="btn btn-outline-danger btn-sm" href="{{ route('bot.destroy', ['id' => $bot->id]) }}" onclick="event.preventDefault();if (confirm('U sure?')){document.getElementById('destroy-form-{{ $bot->id }}').submit();}">
                                            delete
                                        </a>
                                        <form id="destroy-form-{{ $bot->id }}" action="{{ route('bot.destroy', ['id' => $bot->id]) }}" method="POST" style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
