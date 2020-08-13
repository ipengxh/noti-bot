@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        @if ('bot.create' == Route::currentRouteName())
                            新建一个URL监视器
                        @else
                            编辑URL监视器
                        @endif
                        <a href="javascript:history.go(-1)" class="btn btn-sm btn-outline-info float-right">返回</a>
                    </div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('bot.store', ['id' => $bot->id ?? null]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="1">
                            <div class="form-group">
                                <label for="name">名字</label>
                                <input class="form-control w-50" id="name" name="name" placeholder="取个容易定位的名字" value="{{ $bot->name ?? Request::input('name') }}" required/>
                            </div>
                            <div class="form-group">
                                <label for="url">URL</label>
                                <input class="form-control" id="url" name="url" placeholder="http(s)://example.com/xxx" value="{{ $bot->config->url ?? Request::input('url') }}" required/>
                            </div>
                            <div class="form-group">
                                <label for="host">绑定hosts</label>
                                <input class="form-control w-25" id="host" name="host" placeholder="192.168.0.1" value="{{ $bot->config->host ?? Request::input('host') }}"/>
                            </div>
                            <div class="form-group">
                                <label for="headers">自定义Headers</label>
                                <textarea name="headers" id="headers" cols="40" rows="3" class="form-control" placeholder="Cookie:my-cookie
Referrer:http://127.0.0.1/">{{ $bot->config->headers ?? Request::input('headers') }}</textarea>
                            </div>
                            <div class="form-group">
                                <label for="timeout">超时</label>
                                <div class="input-group w-25">
                                    <input class="form-control" id="timeout" name="timeout" type="number" value="{{ $bot->config->timeout ?? (Request::input('timeout') ?: 2) }}" aria-describedby="btnGroupAddon"/>
                                    <div class="input-group-append">
                                        <div class="input-group-text" id="btnGroupAddon">秒</div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group form-check">
                                @if (isset($bot) && ($bot->config->assert->is_json || Request::input('is_json')))
                                <input class="form-check-input" type="checkbox" id="is_json" name="is_json" checked/>
                                @else
                                    <input class="form-check-input" type="checkbox" id="is_json" name="is_json"/>
                                @endif
                                <label for="is_json" class="form-check-label">期望响应内容为json</label>
                            </div>
                            <div class="form-group form-check">
                                @if (isset ($bot) && ($bot->config->alert_modified || Request::input('alert_modified')))
                                <input class="form-check-input" type="checkbox" id="alert_modified" name="alert_modified" checked/>
                                @else
                                <input class="form-check-input" type="checkbox" id="alert_modified" name="alert_modified"/>
                                @endif
                                <label for="alert_modified" class="form-check-label">内容变更提醒</label>
                            </div>
                            <div class="form-group">
                                <label for="status_code">期望http状态码</label>
                                <input class="form-control w-25" id="status_code" name="status_code" value="{{ $bot->config->assert->http_status_code ?? (Request::input('status_code') ?: "200") }}"/>
                            </div>
                            <div class="form-group form-check">
                                @if (isset($bot) && in_array(Auth::user()->open_id, $bot->subscribes->pluck('to')->toArray()))
                                <input class="form-check-input" type="checkbox" id="subscribes" name="subscribes[]" value="{{ Auth::user()->open_id }}" checked/>
                                @else
                                    <input class="form-check-input" type="checkbox" id="subscribes" name="subscribes[]" value="{{ Auth::user()->open_id }}"/>
                                @endif
                                <label for="subscribes" class="form-check-label">为 noti-bot 订阅（私发消息）</label>
                            </div>
                            @foreach($myGroups as $myGroup)
                                <div class="form-group form-check">
                                    @if (isset($bot) && in_array($myGroup->chat_id, $bot->subscribes->pluck('to')->toArray()))
                                    <input class="form-check-input" type="checkbox" id="subscribes-{{ $myGroup->chat_id }}" name="subscribes[]" value="{{ $myGroup->chat_id }}" checked/>
                                    @else
                                    <input class="form-check-input" type="checkbox" id="subscribes-{{ $myGroup->chat_id }}" name="subscribes[]" value="{{ $myGroup->chat_id }}"/>
                                    @endif
                                    <label for="subscribes-{{ $myGroup->chat_id }}" class="form-check-label">[群组] {{ $myGroup->name }}</label>
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-primary mb-2 btn-sm">好</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
