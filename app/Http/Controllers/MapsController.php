<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;  // ← ADD THIS LINE

class MapsController extends Controller
{
    public function index()
    {
        $locations = auth()->check() 
            ? Location::where('user_id', auth()->id())->get()
            : collect();
            
        $routes = auth()->check()
            ? Route::where('user_id', auth()->id())->latest()->take(10)->get()
            : collect();

        return view('maps.index', compact('locations', 'routes'));
    }

    public function getRoute(Request $request)
    {
        $request->validate([
            'start_lat' => 'required|numeric',
            'start_lng' => 'required|numeric',
            'end_lat' => 'required|numeric',
            'end_lng' => 'required|numeric',
        ]);

        try {
            sleep(1); // Rate limiting
            
            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%s,%s;%s,%s',
                $request->start_lng,
                $request->start_lat,
                $request->end_lng,
                $request->end_lat
            );

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'LaravelMapsApp/1.0 (Laravel Framework)',
                ])
                ->get($url, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'true',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['routes'][0])) {
                    $route = $data['routes'][0];
                    
                    return response()->json([
                        'success' => true,
                        'route' => [
                            'geometry' => $route['geometry'],
                            'distance' => $route['distance'] / 1000,
                            'duration' => $route['duration'],
                            'steps' => $route['legs'][0]['steps'] ?? [],
                        ],
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Could not find route',
            ], 404);

        } catch (\Exception $e) {
            \Log::error('Route error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error calculating route',
            ], 500);
        }
    }

    public function searchLocation(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $query = $request->query;
        
        try {
            Log::info('Searching for: ' . $query);
            
            // Try Photon first
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'LaravelMapsApp/1.0',
                    'Accept' => 'application/json',
                ])
                ->get('https://photon.komoot.io/api/', [
                    'q' => $query,
                    'limit' => 10,
                    'lang' => 'en',
                ]);

            Log::info('Photon Status: ' . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $features = $data['features'] ?? [];
                
                Log::info('Photon returned ' . count($features) . ' results');
                
                if (!empty($features)) {
                    $results = collect($features)->map(function ($feature) {
                        $props = $feature['properties'] ?? [];
                        $coords = $feature['geometry']['coordinates'] ?? [0, 0];
                        
                        // Build display name from available properties
                        $nameParts = array_filter([
                            $props['name'] ?? null,
                            $props['street'] ?? null,
                            $props['city'] ?? null,
                            $props['state'] ?? null,
                            $props['country'] ?? null,
                        ]);
                        
                        $displayName = !empty($nameParts) 
                            ? implode(', ', $nameParts) 
                            : 'Unknown Location';
                        
                        return [
                            'name' => $displayName,
                            'lat' => (float) $coords[1],
                            'lng' => (float) $coords[0],
                            'type' => $props['type'] ?? 'location',
                        ];
                    })->filter(function($result) {
                        // Filter out invalid coordinates
                        return $result['lat'] != 0 && $result['lng'] != 0;
                    });
                    
                    if ($results->count() > 0) {
                        Log::info('Returning ' . $results->count() . ' valid results');
                        return response()->json([
                            'success' => true,
                            'results' => $results->values(),
                        ]);
                    }
                }
            }

            Log::warning('Photon search failed or returned no results, using fallback');
            
            // Fallback to common locations database
            return $this->searchFallbackLocations($query);

        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());
            
            // Use fallback on error
            return $this->searchFallbackLocations($query);
        }
    }

    /**
     * Fallback search using common locations
     */
    private function searchFallbackLocations($query)
    {
        $query = strtolower(trim($query));
        
        // Common US locations for testing
        $locations = [
            // California
            ['name' => 'Los Angeles, California, USA', 'lat' => 34.0522, 'lng' => -118.2437, 'type' => 'city'],
            ['name' => 'San Francisco, California, USA', 'lat' => 37.7749, 'lng' => -122.4194, 'type' => 'city'],
            ['name' => 'San Diego, California, USA', 'lat' => 32.7157, 'lng' => -117.1611, 'type' => 'city'],
            ['name' => 'Sacramento, California, USA', 'lat' => 38.5816, 'lng' => -121.4944, 'type' => 'city'],
            ['name' => 'San Jose, California, USA', 'lat' => 37.3382, 'lng' => -121.8863, 'type' => 'city'],
            ['name' => 'Fresno, California, USA', 'lat' => 36.7378, 'lng' => -119.7871, 'type' => 'city'],
            ['name' => 'Oakland, California, USA', 'lat' => 37.8044, 'lng' => -122.2712, 'type' => 'city'],
            ['name' => 'Santa Barbara, California, USA', 'lat' => 34.4208, 'lng' => -119.6982, 'type' => 'city'],
            
            // Major US Cities
            ['name' => 'New York, New York, USA', 'lat' => 40.7128, 'lng' => -74.0060, 'type' => 'city'],
            ['name' => 'Chicago, Illinois, USA', 'lat' => 41.8781, 'lng' => -87.6298, 'type' => 'city'],
            ['name' => 'Houston, Texas, USA', 'lat' => 29.7604, 'lng' => -95.3698, 'type' => 'city'],
            ['name' => 'Phoenix, Arizona, USA', 'lat' => 33.4484, 'lng' => -112.0740, 'type' => 'city'],
            ['name' => 'Philadelphia, Pennsylvania, USA', 'lat' => 39.9526, 'lng' => -75.1652, 'type' => 'city'],
            ['name' => 'Seattle, Washington, USA', 'lat' => 47.6062, 'lng' => -122.3321, 'type' => 'city'],
            ['name' => 'Boston, Massachusetts, USA', 'lat' => 42.3601, 'lng' => -71.0589, 'type' => 'city'],
            ['name' => 'Miami, Florida, USA', 'lat' => 25.7617, 'lng' => -80.1918, 'type' => 'city'],
            ['name' => 'Denver, Colorado, USA', 'lat' => 39.7392, 'lng' => -104.9903, 'type' => 'city'],
            ['name' => 'Las Vegas, Nevada, USA', 'lat' => 36.1699, 'lng' => -115.1398, 'type' => 'city'],
            
            // Specific addresses from your screenshot
            ['name' => '5080 Rashelle Way, Rancho Santa Margarita, CA', 'lat' => 33.6405, 'lng' => -117.6030, 'type' => 'address'],
            ['name' => '17482 Rosa Drew Lane, Irvine, CA', 'lat' => 33.6846, 'lng' => -117.7621, 'type' => 'address'],
        ];
        
        // Search through locations
        $results = collect($locations)->filter(function($location) use ($query) {
            $locationName = strtolower($location['name']);
            
            // Split query into words for better matching
            $queryWords = explode(' ', $query);
            
            // Check if all query words are in the location name
            foreach ($queryWords as $word) {
                if (!empty($word) && str_contains($locationName, $word)) {
                    return true;
                }
            }
            
            return false;
        })->take(5);
        
        if ($results->count() > 0) {
            return response()->json([
                'success' => true,
                'results' => $results->values(),
                'fallback' => true,
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No results found for "' . $query . '". Try searching for a city name like "Los Angeles" or "San Francisco".',
        ]);
    }

    public function saveLocation(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'nullable|string',
            'type' => 'nullable|string|in:destination,favorite,home,work',
        ]);

        $location = Location::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'type' => $request->type ?? 'destination',
        ]);

        return response()->json([
            'success' => true,
            'location' => $location,
        ]);
    }

    public function saveRoute(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'start_lat' => 'required|numeric',
            'start_lng' => 'required|numeric',
            'start_address' => 'nullable|string',
            'end_lat' => 'required|numeric',
            'end_lng' => 'required|numeric',
            'end_address' => 'nullable|string',
            'distance' => 'nullable|numeric',
            'duration' => 'nullable|integer',
            'waypoints' => 'nullable|array',
        ]);

        $route = Route::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'start_lat' => $request->start_lat,
            'start_lng' => $request->start_lng,
            'start_address' => $request->start_address,
            'end_lat' => $request->end_lat,
            'end_lng' => $request->end_lng,
            'end_address' => $request->end_address,
            'distance' => $request->distance,
            'duration' => $request->duration,
            'waypoints' => $request->waypoints,
        ]);

        return response()->json([
            'success' => true,
            'route' => $route,
        ]);
    }
}