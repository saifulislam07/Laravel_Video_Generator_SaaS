<?php

namespace App\Http\Controllers;

use App\Models\VideoRender;
use App\Services\VideoRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShotstackWebhookController extends Controller
{
    public function __invoke(Request $request, VideoRenderService $service): JsonResponse
    {
        $secret = config('services.shotstack.webhook_secret');

        abort_if(
            filled($secret) && ! hash_equals((string) $secret, (string) $request->query('secret')),
            Response::HTTP_FORBIDDEN,
        );

        $renderId = $request->input('id');
        $status = $request->input('status');

        if (blank($renderId) || blank($status)) {
            return response()->json(['message' => 'ignored'], Response::HTTP_ACCEPTED);
        }

        $render = VideoRender::where('shotstack_render_id', $renderId)->first();

        if (! $render) {
            return response()->json(['message' => 'unknown render'], Response::HTTP_ACCEPTED);
        }

        $service->applyWebhook(
            $render,
            $status,
            $request->input('url'),
            $request->input('error') ?: null,
        );

        return response()->json(['message' => 'ok']);
    }
}
