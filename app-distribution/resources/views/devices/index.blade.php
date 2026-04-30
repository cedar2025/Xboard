@extends('layouts.app')
@section('title','设备统计')
@section('content')
<div class="card">
    <table>
        <thead><tr><th>设备</th><th>App</th><th>平台</th><th>版本</th><th>系统</th><th>首次/最近活跃</th></tr></thead>
        <tbody>
        @foreach($devices as $device)
            <tr>
                <td><code>{{ $device->installation_id }}</code><br><span class="muted">{{ $device->ip_address }}</span></td>
                <td>{{ $device->app->name }}</td>
                <td>{{ $device->platform }} / {{ $device->channel }} @if($device->arch)<span class="badge">{{ $device->arch }}</span>@endif</td>
                <td>v{{ $device->version ?: '-' }}<br><span class="muted">build {{ $device->build_number }}</span></td>
                <td>{{ $device->os_version ?: '-' }}</td>
                <td>{{ optional($device->first_seen_at)->format('Y-m-d H:i') ?: '-' }}<br>{{ optional($device->last_seen_at)->format('Y-m-d H:i') ?: '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $devices->links() }}
</div>
@endsection
