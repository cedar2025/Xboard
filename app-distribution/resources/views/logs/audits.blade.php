@extends('layouts.app')
@section('title','审计日志')
@section('content')
<div class="card">
    <table>
        <thead><tr><th>时间</th><th>管理员</th><th>动作</th><th>目标</th><th>IP</th><th>元数据</th></tr></thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                <td>{{ $log->admin?->email ?: '-' }}</td>
                <td><span class="badge">{{ $log->action }}</span></td>
                <td>{{ class_basename($log->target_type) }} #{{ $log->target_id }}</td>
                <td>{{ $log->ip_address }}</td>
                <td><code>{{ $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : '-' }}</code></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $logs->links() }}
</div>
@endsection
