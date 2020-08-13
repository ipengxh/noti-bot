@extends('layouts.app')
@section('content')
    <style>
        .code {display: block;overflow: auto;background: #f4f4f4;padding: 5px 10px;border: 1px solid #eee;word-wrap:break-word; white-space: pre-wrap;}
    </style>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        探索奇怪的功能
                    </div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <h3>1. 一键发送周报到飞书</h3>
                        <p>在Chrome中新增一个书签，名称为“周报 -> 飞书”，将“网址”设置为以下内容，<b>在周报填写页面点击</b>这个书签，即可一键发送你填写的周报内容至你的飞书</p>
                        <code> javascript: (function() { var thisTr = $("#data-list tr.this-work"); var _thisWorkData = {}; for (var i = 0, _len = thisTr.length; i < _len; i++) { var tr = thisTr.eq(i); var projectId = $.trim(tr.find("input[name='projectId']").val()); var projectName = $.trim(tr.find("input[name='projectName']").val()); var reportContent = $.trim(tr.find("input[name='reportContent']").val()); var completePercent = $.trim(tr.find("input[name='completePercent']").val()); if (reportContent == "" && completePercent == "") { continue; } if (!_thisWorkData[projectId]) { _thisWorkData[projectId] = {}; _thisWorkData[projectId].thisWeek = [ { projectId: projectId, projectName: projectName, reportContent: reportContent, completePercent: completePercent }]; }else{ _thisWorkData[projectId].thisWeek.push({ projectId: projectId, projectName: projectName, reportContent: reportContent, completePercent: completePercent }); } } var nextTr = $("#data-list tr.next-work"); for (var i = 0, _len = nextTr.length; i < _len; i++) { var tr = nextTr.eq(i); var projectId = $.trim(tr.find("input[name='projectId']").val()); var projectName = $.trim(tr.find("input[name='projectName']").val()); var reportContent = $.trim(tr.find("input[name='reportContent']").val()); if (reportContent == "") { continue; } if (!_thisWorkData[projectId]) { _thisWorkData[projectId] = {}; _thisWorkData[projectId] = { thisWeek : [{ projectId: projectId, projectName: projectName, reportContent: '', completePercent: '' }], nextWork: [{ projectId: projectId, projectName: projectName, reportContent: reportContent }] }; continue; } if (!_thisWorkData[projectId].nextWork) { _thisWorkData[projectId].nextWork = [{ projectId: projectId, projectName: projectName, reportContent: reportContent }]; continue; } _thisWorkData[projectId].nextWork.push({ projectId: projectId, projectName: projectName, reportContent: reportContent }) } var report = ''; var count = 1; if (_thisWorkData[0]) { _thisWorkData[99999] = _thisWorkData[0]; delete _thisWorkData[0]; } for (var p in _thisWorkData){ for (var i = 0; i < _thisWorkData[p].thisWeek.length; i++) { if (i == 0) { report += count + '.' + _thisWorkData[p].thisWeek[i].projectName + "\n"; report += " 上周工作总结：\n" } if (_thisWorkData[p].thisWeek[i].reportContent != '') { report += ' ' + (i+1) + "." + _thisWorkData[p].thisWeek[i].reportContent + ' ' + _thisWorkData[p].thisWeek[i].completePercent + "%\n"; } } if (_thisWorkData[p].nextWork) { for (var i = 0; i < _thisWorkData[p].nextWork.length; i++) { if (i == 0) { report += " 本周工作计划：\n" } if (_thisWorkData[p].nextWork[i].reportContent != '') { report += ' ' + (i+1) + "." + _thisWorkData[p].nextWork[i].reportContent + "\n" } } } count++; report += "\n"; } console.log(report); $.get( "{{ env('APP_URL') }}/send", { token: "{{ Auth::user()->token }}", text: report } ); })(); </code>
                        <hr>
                        <h3>2. ssh登录提醒</h3>
                        <p>执行</p>
                        <p>curl "{{ env('APP_URL') }}/scripts?script=sshrc&token={{ Auth::user()->token }}" -o /etc/ssh/sshrc</p>
                        <p><b>或者</b></p>
                        <p>编辑 /etc/ssh/sshrc 文件（不存在就创建一个），填写以下内容，在每次登录ssh时，你的飞书会收到一个登录提醒</p>
                        <div class="code">
#!/bin/bash
USER=`whoami`
IP=`echo $SSH_CLIENT | awk '{print $1}'`
HOST_IP=`echo $SSH_CONNECTION | awk '{print $3}'`
HOST_NAME=`hostname`
curl -s "{{ env('APP_URL') }}/send?token={{ Auth::user()->token }}&text=用户${USER}在${IP}于\$\{date\} \$\{time\}通过ssh登录了${HOST_NAME}(${HOST_IP})" -o /dev/null &
                        </div>
                        <hr>
                        <h3>3. 命令执行通知</h3>
                        <p>执行</p>
                        <p>curl "{{ env('APP_URL') }}/scripts?script=noti&token={{ Auth::user()->token }}" -o /usr/bin/noti</p>
                        <p>chmod +x /usr/bin/noti</p>
                        <p><b>或者</b></p>
                        <p>将以下内容保存为 /usr/bin/noti，并执行 chmod +x /usr/bin/noti，执行 noti date 命令，你的飞书会收到命令开始执行和执行结束的提醒</p>
                        <p>执行 noti date，即可收到相应提醒消息。注意，在第一次执行时，会要求输入token，这时输入你的token即可 {{ Auth::user()->token }}</p>
                        <div class="code">
#!/bin/bash
command=$@
if [[ $SUDO_USER ]]; then
run_user=$SUDO_USER
else
run_user=`whoami`
fi
use_sudo=`test`
time=`date "+%Y-%m-%d %H:%M:%S"`
hostname=`hostname`
dir=`pwd`
if [[ "root" == $run_user ]]; then
noti_config="/root/.noti"
else
noti_config="/home/${run_user}/.noti"
fi
if [ ! -f $noti_config ]; thena
read -p "输入您的token：" token
echo $token > $noti_config
chown $run_user $noti_config
else
token=`head -1 $noti_config`
fi
url="{{ env('APP_URL') }}/send?token=${token}"
data="text=${time} 用户 \${me} 在 ${hostname} 的 ${dir} 目录以${run_user}用户执行命令: ${command}"
curl -X POST "${url}" --data-urlencode "${data}" --output /dev/null -s &
start_time=$[$(date +%s%N)/1000000]
output=`${command}`
exit_code=$?
end_time=$[$(date +%s%N)/1000000]
run_time=`expr $end_time - $start_time`
if [[ $run_time -gt 1000 ]]; then
run_time=`expr $run_time / 1000`秒
else
run_time=${run_time}毫秒
fi
finish=`date "+%Y-%m-%d %H:%M:%S"`
if [[ $output ]]; then
data="text=${finish} 命令: ${command} 执行完成，耗时${run_time}，退出码为 ${exit_code}
输出内容：${output}"
else
data="text=${finish} 命令: ${command} 执行完成，耗时${run_time}，退出码为 ${exit_code}"
fi
curl -X POST "${url}" --data-urlencode "${data}" --output /dev/null -s &
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
