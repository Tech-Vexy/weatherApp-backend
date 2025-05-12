<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openweathermap.org';
    private int $timeout = 30; // 30 seconds timeout

    public function __construct()
    {
        $this->apiKey = config('services.openweathermap.key');
    }

    /**
     * Make an HTTP request to the OpenWeatherMap API with error handling
     * 
     * @param string $endpoint
     * @param array $params
     * @return array|null
     * @throws \Exception
     */
    private function makeApiRequest(string $endpoint, array $params): ?array
    {
        $params['appid'] = $this->apiKey;
        $url = "{$this->baseUrl}{$endpoint}";
        
        try {
            Log::info("Making API request to: {$url}");
            
            $response = Http::timeout($this->timeout)
                ->retry(3, 1000)
                ->withOptions([
                    'verify' => false, // Temporarily disable SSL verification for troubleshooting
                ])
                ->get($url, $params);
                
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error("API request failed: {$response->status()}", [
                'url' => $url,
                'response' => $response->body()
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error("API request exception: {$e->getMessage()}", [
                'url' => $url,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Search for cities using OpenWeatherMap Geocoding API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchCity(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:5',
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 5);

        try {
            $data = $this->makeApiRequest('/geo/1.0/direct', [
                'q' => $query,
                'limit' => $limit,
            ]);

            if ($data !== null) {
                return response()->json($data);
            }

            return response()->json(['error' => 'Failed to fetch city data'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to weather service', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get current weather for a location
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function currentWeather(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'units' => 'nullable|string|in:standard,metric,imperial',
        ]);

        $lat = $request->input('lat');
        $lon = $request->input('lon');
        $units = $request->input('units', 'metric');

        try {
            $data = $this->makeApiRequest('/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lon,
                'units' => $units,
            ]);

            if ($data !== null) {
                return response()->json($data);
            }

            return response()->json(['error' => 'Failed to fetch weather data'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to weather service', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get weather forecast for next 3 days
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forecast(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'units' => 'nullable|string|in:standard,metric,imperial',
        ]);

        $lat = $request->input('lat');
        $lon = $request->input('lon');
        $units = $request->input('units', 'metric');

        try {
            $data = $this->makeApiRequest('/data/2.5/forecast', [
                'lat' => $lat,
                'lon' => $lon,
                'units' => $units,
                'cnt' => 24, // Get 24 entries (3 days with 8 measurements per day)
            ]);

            if ($data !== null) {
                $fullData = $data;
                
                // Process the data to organize by day
                $forecastData = $this->processForecastData($fullData);
                
                return response()->json($forecastData);
            }

            return response()->json(['error' => 'Failed to fetch forecast data'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to weather service', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get weather data by city name (combines geocoding and weather data)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cityWeather(Request $request): JsonResponse
    {
        $request->validate([
            'city' => 'required|string|min:2',
            'units' => 'nullable|string|in:standard,metric,imperial',
        ]);

        $city = $request->input('city');
        $units = $request->input('units', 'metric');

        try {
            // First, get the geocoding data for the city
            $geoData = $this->makeApiRequest('/geo/1.0/direct', [
                'q' => $city,
                'limit' => 1,
            ]);

            if (empty($geoData)) {
                return response()->json(['error' => 'City not found'], 404);
            }

            // Get the coordinates from the first geocoding result
            $location = $geoData[0];
            $lat = $location['lat'];
            $lon = $location['lon'];
            
            // Fetch current weather data
            $currentData = $this->makeApiRequest('/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lon,
                'units' => $units,
            ]);

            // Fetch forecast data
            $forecastData = $this->makeApiRequest('/data/2.5/forecast', [
                'lat' => $lat,
                'lon' => $lon,
                'units' => $units,
                'cnt' => 24, // Get 24 entries for 3 days
            ]);

            // Prepare the response
            $responseData = [
                'city' => [
                    'name' => $location['name'],
                    'country' => $location['country'] ?? null,
                    'state' => $location['state'] ?? null,
                    'lat' => $lat,
                    'lon' => $lon,
                ],
                'current' => $currentData,
                'forecast' => null
            ];

            // Process the forecast data if available
            if ($forecastData !== null) {
                $responseData['forecast'] = $this->processForecastData($forecastData);
            }

            return response()->json($responseData);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch weather data', 
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast data by city name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forecastByCity(Request $request): JsonResponse
    {
        $request->validate([
            'city' => 'required|string|min:2',
            'units' => 'nullable|string|in:standard,metric,imperial',
        ]);

        $city = $request->input('city');
        $units = $request->input('units', 'metric');

        try {
            // First, get the geocoding data for the city
            $geoData = $this->makeApiRequest('/geo/1.0/direct', [
                'q' => $city,
                'limit' => 1,
            ]);

            if (empty($geoData)) {
                return response()->json(['error' => 'City not found'], 404);
            }

            // Get the coordinates from the first geocoding result
            $location = $geoData[0];
            $lat = $location['lat'];
            $lon = $location['lon'];
            
            // Fetch forecast data
            $forecastData = $this->makeApiRequest('/data/2.5/forecast', [
                'lat' => $lat,
                'lon' => $lon,
                'units' => $units,
                'cnt' => 24, // Get 24 entries for 3 days
            ]);

            if ($forecastData === null) {
                return response()->json(['error' => 'Failed to fetch forecast data'], 500);
            }

            // Process the forecast data
            $processedData = $this->processForecastData($forecastData);
            
            // Add city information
            $processedData['city_info'] = [
                'name' => $location['name'],
                'country' => $location['country'] ?? null,
                'state' => $location['state'] ?? null,
                'lat' => $lat,
                'lon' => $lon,
            ];

            return response()->json($processedData);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch forecast data', 
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process and organize forecast data by day
     *
     * @param array $rawData
     * @return array
     */
    private function processForecastData(array $rawData): array
    {
        $processedData = [
            'city' => $rawData['city'],
            'daily_forecasts' => [],
        ];

        $forecastsByDay = [];

        // Group forecasts by day
        foreach ($rawData['list'] as $forecast) {
            $date = date('Y-m-d', $forecast['dt']);
            if (!isset($forecastsByDay[$date])) {
                $forecastsByDay[$date] = [];
            }
            $forecastsByDay[$date][] = $forecast;
        }

        // Keep only the first 4 days (today + 3 days)
        $days = array_keys($forecastsByDay);
        $daysToKeep = array_slice($days, 0, 4);

        foreach ($daysToKeep as $day) {
            $dailyForecasts = $forecastsByDay[$day];
            
            // Get average temp, min, max, etc.
            $temps = array_map(fn($f) => $f['main']['temp'], $dailyForecasts);
            $minTemps = array_map(fn($f) => $f['main']['temp_min'], $dailyForecasts);
            $maxTemps = array_map(fn($f) => $f['main']['temp_max'], $dailyForecasts);
            
            $processedData['daily_forecasts'][] = [
                'date' => $day,
                'day_of_week' => date('l', strtotime($day)),
                'avg_temp' => array_sum($temps) / count($temps),
                'min_temp' => min($minTemps),
                'max_temp' => max($maxTemps),
                'weather_condition' => $dailyForecasts[0]['weather'][0]['main'],
                'weather_description' => $dailyForecasts[0]['weather'][0]['description'],
                'weather_icon' => $dailyForecasts[0]['weather'][0]['icon'],
                'hourly_forecasts' => $dailyForecasts,
            ];
        }

        return $processedData;
    }
}
