<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use OpenApi\Attributes as OA;

class SettingsController extends Controller
{
    #[OA\Get(
        path: '/api/settings',
        tags: ['Settings'],
        summary: 'Get public library settings (for users)',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        return response()->json([
            'loan.max_days' => Setting::getInt('loan.max_days', 30),
            'loan.grace_days' => Setting::getInt('loan.grace_days', 0),
            'fine.per_day_cents' => Setting::getInt('fine.per_day_cents', 100),
            'fine.cap_cents' => Setting::getInt('fine.cap_cents', 3000),
        ]);
    }
}
