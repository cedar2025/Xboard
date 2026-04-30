@extends('layouts.app')
@section('title','下载日志')
@section('content')
<div class="card">
    <table>
        <thead><tr><th>时间</th><th>App</th><th>版本</th><th>设备</th><th>IP</th><th>User-Agent</th></tr></thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ optional($log->downloaded_at)->format('Y-m-d H:i:s') }}</td>
                <td>{{ $log->app->name }}</td>
                <td>{{ $log->version->platform }} / v{{ $log->version->version }} ({{ $log->version->build_number }})</td>
                <td><code>{{ $log->installation_id ?: '-' }}</code></td>
                <td>{{ $log->ip_address }}</td>
                <td class="muted">{{ $log->user_agent }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $logs->links() }}
</div>
@endsection
