<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SettingsController extends Controller
{
    #[OA\Get(
        path: '/api/admin/settings',
        tags: ['Admin: Settings'],
        summary: 'Get library rules/settings',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $keys = [
            'loan.max_days',
            'loan.grace_days',
            'loan.block_on_overdue',
            'loan.block_on_unpaid_fines',
            'loan.max_unpaid_fines_cents',
            'fine.per_day_cents',
            'fine.cap_cents',
            'reminder.due_soon_days_before',
            'reminder.overdue_frequency_days',
        ];

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = Setting::getString($k, '');
        }

        return response()->json($out);
    }

    #[OA\Put(
        path: '/api/admin/settings',
        tags: ['Admin: Settings'],
        summary: 'Update library rules/settings',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent()),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request)
    {
        $data = $request->validate([
            'loan.max_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'loan.grace_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'loan.block_on_overdue' => ['nullable', 'boolean'],
            'loan.block_on_unpaid_fines' => ['nullable', 'boolean'],
            'loan.max_unpaid_fines_cents' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'fine.per_day_cents' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'fine.cap_cents' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'reminder.due_soon_days_before' => ['nullable', 'integer', 'min:0', 'max:30'],
            'reminder.overdue_frequency_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        foreach ($data as $k => $v) {
            if ($v === null) continue;
            Setting::put($k, $v);
        }

        return $this->index();
    }
}
