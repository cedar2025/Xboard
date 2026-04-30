<?php

namespace App\Http\Controllers;

use App\Models\DeviceInstallation;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = DeviceInstallation::with('app')
            ->orderByDesc('last_seen_at')
            ->paginate(50);

        return view('devices.index', compact('devices'));
    }
}
