<?php

namespace App\Http\Controllers;

use App\Support\InformacionRelease;
use Illuminate\Http\JsonResponse;

final class VersionPortalController extends Controller
{
    public function __invoke(InformacionRelease $release): JsonResponse
    {
        return response()->json($release->obtener())->header('Cache-Control', 'no-store');
    }
}
