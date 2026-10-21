<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Punch;
use App\Services\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

trait HandlesFacePunch
{
    protected function validatePunchRequest(Request $request): array
    {
        $data = $request->validate([
            'descriptor' => ['required', 'array', 'size:'.FaceMatcher::LENGTH],
            'descriptor.*' => ['numeric'],
            'snapshot' => ['nullable', 'string', 'max:600000'],
            'liveness' => ['boolean'],
            'type' => ['nullable', 'in:'.implode(',', Punch::TYPES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $data['descriptor'] = FaceMatcher::validate($data['descriptor']);
        } catch (InvalidArgumentException $e) {
            abort(response()->json(['ok' => false, 'message' => $e->getMessage()], 422));
        }

        return $data;
    }

    protected function punchResponse(Punch $punch, string $message): JsonResponse
    {
        $employee = $punch->employee;

        return response()->json([
            'ok' => true,
            'message' => $message,
            'employee' => ['name' => $employee->display_name, 'employee_no' => $employee->employee_no],
            'punch' => [
                'type' => $punch->type,
                'label' => $punch->label,
                'time' => $punch->punched_at->format('g:i:s A'),
                'date' => $punch->punched_at->format('l, F j, Y'),
            ],
        ]);
    }

    protected function failed(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message] + $extra, $status);
    }
}
