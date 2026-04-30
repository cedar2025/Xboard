<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DownloadLog;

class LogController extends Controller
{
    public function downloads()
    {
        $logs = DownloadLog::with(['app', 'version'])
            ->orderByDesc('downloaded_at')
            ->paginate(50);

        return view('logs.downloads', compact('logs'));
    }

    public function audits()
    {
        $logs = AuditLog::with('admin')
            ->orderByDesc('id')
            ->paginate(50);

        return view('logs.audits', compact('logs'));
    }
}
