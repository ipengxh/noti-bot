@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">给自己发条消息</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                        <form action="{{ route('api.example.test') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <textarea class="form-control" id="example-text" rows="3" name="content" placeholder="在这里写点什么..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary mb-2 btn-sm">试试</button>
                        </form>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">API</div>
                <div class="card-body">
                    <h5 style="color: red;">请不要泄露自己的token，否则其他人可以冒充你的身份发送消息</h5>
                    <h4><b># 调用API给自己发条消息</b></h4>
                    <p>你的API Token: {{ Auth::user()->token }}</p>
                    <p>打开 <a href="{{ route('api', ['token' => Auth::user()->token, 'text' => '${me} 于 ${date} ${time} 发送消息：hello, world']) }}" target="_blank">{{ urldecode(route('api', ['token' => Auth::user()->token, 'text' => '${me} 于 ${date} ${time} 发送消息：hello, world'])) }}</a></p>
                    <p>或者使用curl执行: </p>
                    <p>curl "{{ urldecode(route('api', ['token' => Auth::user()->token, 'text' => '${me} 于 ${date} ${time} 发送消息：hello, world'])) }}"</p>
                    <p>特殊语法： ${me} 我的姓名；${date} 当前日期；${time} 当前时间</p>
                    <h4><b># 调用API给别人发条消息</b></h4>
                    <p>打开 <a href="{{ route('api', ['token' => Auth::user()->token, 'to_name' => Auth::user()->name, 'to_email' => Auth::user()->email, 'text' => 'hello, world']) }}" target="_blank">{{ urldecode(route('api', ['token' => Auth::user()->token, 'to_name' => Auth::user()->name, 'to_email' => Auth::user()->email, 'text' => 'hello, world'])) }}</a></p>
                    <p>或者使用curl执行: </p>
                    <p>curl "{{ urldecode(route('api', ['token' => Auth::user()->token, 'to_name' => Auth::user()->name, 'to_email' => Auth::user()->email, 'text' => 'hello, world'])) }}"</p>
                    <p><b style="color: #E33">优先匹配邮箱地址，因为可能部分同学的姓名重复了</b></p>
                    <h4><b># 调用API给群组发送消息</b></h4>
                    <p>特殊语法：@{姓名} 在群组中@某人；@{all} 在群组中@所有人</p>
                    @foreach($groups as $group)
                        <h5><b>## {{ $group->name }} ##</b></h5>
                        <p>打开 <a href="{{ route('api', ['token' => Auth::user()->token, 'chat' => $group->chat_id, 'text' => "hello, world @{" . Auth::user()->name . "}"]) }}" target="_blank">{{ urldecode(route('api', ['token' => Auth::user()->token, 'chat' => $group->chat_id, 'text' => "hello, world @{" . Auth::user()->name . "}"])) }}</a></p>
                        <p>或者使用curl执行: </p>
                        <p>curl "{{ urldecode(route('api', ['token' => Auth::user()->token, 'chat' => $group->chat_id, 'text' => "hello, world @{" . Auth::user()->name . "}"])) }}"</p>
                    @endforeach
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">Gitlab webhook</div>
                <div class="card-body">
                    <p>打开Gitlab - {project} - Settings - Integrations</p>
                    <p>填入 {{ route('gitlab.webhook', ['token' => Auth::user()->token]) }}</p>
                    <p>为群组订阅：</p>
                    @foreach($groups as $group)
                        <p><b>## {{ $group->name }}</b></p>
                        <p>填入 {{ route('gitlab.webhook', ['token' => Auth::user()->token, 'chat' => $group->chat_id]) }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
