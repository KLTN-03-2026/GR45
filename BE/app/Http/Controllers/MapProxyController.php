<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapProxyController extends Controller
{
    /**
     * Proxy chỉ đường (direction) từ OpenMapVN API
     * Tránh lỗi CORS khi gọi trực tiếp từ browser
     */
    public function direction(Request $request)
    {
        $apiKey = env('OPENMAP_API_KEY', '');

        $params = array_merge(
            $request->only(['origin', 'destination', 'vehicle']),
            ['apikey' => $apiKey]
        );

        try {
            $response = Http::timeout(10)->get('https://mapapis.openmap.vn/v1/direction', $params);
            $status = $response->status();
            // Tránh trả về 401 (lỗi từ map API ráp key sai) làm frontend nhầm là lỗi Token đăng nhập
            if ($status === 401 || $status === 403) {
                $status = 400; 
            }
            return response()->json($response->json(), $status);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Map proxy error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Proxy tìm đường OSRM (fallback)
     */
    public function osrmRoute(Request $request)
    {
        $coords = $request->query('coords');
        if (!$coords) {
            return response()->json(['error' => 'Missing coords'], 400);
        }

        try {
            $url = "https://router.project-osrm.org/route/v1/driving/{$coords}?overview=full&geometries=geojson";
            $response = Http::timeout(10)->get($url);

            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => 'OSRM proxy error: ' . $e->getMessage()], 500);
        }
    }
}
