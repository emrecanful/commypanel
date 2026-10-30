<?php

namespace App\Http\Controllers;

use App\Audit\AuditLogger;
use App\Dns\ServerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServerAddressController extends Controller
{
    public function update(Request $request, ServerAddress $server, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'ipv4' => ['nullable', 'ipv4'],
            'ipv6' => ['nullable', 'ipv6'],
        ]);

        if (blank($data['ipv4'] ?? null) && blank($data['ipv6'] ?? null)) {
            throw ValidationException::withMessages(['ipv4' => 'Enter at least an IPv4 or an IPv6 address.']);
        }

        $server->save($data['ipv4'] ?? null, $data['ipv6'] ?? null);
        $audit->record('server.address_set', null, ['ipv4' => $data['ipv4'] ?? null, 'ipv6' => $data['ipv6'] ?? null]);

        return back()->with('status', 'Server address saved.');
    }

    public function detect(ServerAddress $server, AuditLogger $audit): RedirectResponse
    {
        $found = $server->detect();

        if ($found['ipv4'] === null && $found['ipv6'] === null) {
            return back()->withErrors(['ipv4' => 'Could not detect the public address automatically. Enter it by hand.']);
        }

        $server->save($found['ipv4'] ?? $server->ipv4(), $found['ipv6'] ?? $server->ipv6());
        $audit->record('server.address_detected', null, $found);

        return back()->with('status', 'Server address detected. Check that it is correct.');
    }
}
