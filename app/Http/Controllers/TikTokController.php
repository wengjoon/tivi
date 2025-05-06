<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\CacheService;

class TikTokController extends Controller
{
    protected $apiKey;
    protected $baseUrl;
    protected $cacheService;
    protected $maxRetries = 2; // Number of API retries to attempt

    public function __construct(CacheService $cacheService)
    {
        $this->apiKey = env('TIKTOK_API_KEY');
        $this->baseUrl = 'https://' . env('TIKTOK_API_HOST');
        $this->cacheService = $cacheService;
    }

    public function index()
    {
        $trendingData = $this->getTrendingVideos();
        
        // Separate videos and country information
        $trendingVideos = $trendingData['videos'] ?? [];
        $countryInfo = $trendingData['country'] ?? [
            'code' => 'US',
            'name' => 'United States'
        ];
        
        // If no videos were retrieved from API, use fallback mock data in development mode
        if (empty($trendingVideos) && config('app.debug')) {
            $mockData = $this->getMockTrendingVideos();
            $trendingVideos = $mockData['videos'] ?? [];
            $countryInfo = $mockData['country'] ?? $countryInfo;
            Log::info('Using mock trending videos data');
        }
        
        return view('home', [
            'trendingVideos' => $trendingVideos, 
            'countryInfo' => $countryInfo
        ]);
    }

    public function search(Request $request)
    {
        $username = $request->input('username');
        
        if (empty($username)) {
            return redirect()->route('home')->with('error', 'Please enter a username');
        }

        // Clean the username (remove @ if present)
        $username = ltrim($username, '@');

        return redirect()->route('user.profile', ['username' => $username]);
    }

    public function userProfile(Request $request, $username = null)
    {
        // If username is not provided in URL, try to get it from the query string
        if (empty($username) && $request->has('username')) {
            $username = $request->input('username');
            // Remove @ if present
            $username = ltrim($username, '@');
            // Redirect to the canonical URL
            return redirect()->route('user.profile', ['username' => $username]);
        }
        
        if (empty($username)) {
            return redirect()->route('home')->with('error', 'Please enter a username');
        }
        
        try {
            // Enable debug logging
            Log::debug("Attempting to fetch profile for username: {$username}");
            
            $forceRefresh = $request->has('refresh') || $request->attributes->get('force_refresh', false);
            
            // Fetch user info with caching
            $userInfo = $this->cacheService->getProfile($username, function() use ($username) {
                return $this->getUserInfo($username);
            }, $forceRefresh);
            
            // Log the full response for debugging
            Log::debug('API response for user profile', [
                'username' => $username,
                'response' => $userInfo,
            ]);
            
            if (!$userInfo) {
                Log::error("User info is null for username: {$username}");
                return redirect()->route('home')->with('error', 'Failed to retrieve user information. Please try again later.');
            }
            
            if (isset($userInfo['code']) && $userInfo['code'] !== 0) {
                $errorMessage = isset($userInfo['msg']) ? $userInfo['msg'] : 'User not found or API error occurred';
                Log::warning("API error for username: {$username}, code: {$userInfo['code']}, message: {$errorMessage}");
                
                // Instead of redirecting immediately, show error details in dev environment
                if (config('app.debug')) {
                    return view('error', [
                        'title' => 'API Error',
                        'message' => $errorMessage,
                        'details' => $userInfo,
                    ]);
                }
                
                return redirect()->route('home')->with('error', $errorMessage);
            }

            // Is the profile data stale?
            $isStaleProfile = isset($userInfo['is_stale']) && $userInfo['is_stale'];
            
            // Fetch user posts with caching
            $userPosts = $this->cacheService->getVideos($username, function() use ($username) {
                return $this->getUserPosts($username);
            }, null, $forceRefresh);
            
            // Is the videos data stale?
            $isStaleVideos = isset($userPosts['is_stale']) && $userPosts['is_stale'];
            
            // Schedule background refresh if we served stale data
            if ($isStaleProfile) {
                $this->cacheService->scheduleBackgroundRefresh('profile', ['username' => $username]);
            }
            
            if ($isStaleVideos) {
                $this->cacheService->scheduleBackgroundRefresh('videos', ['username' => $username]);
            }
            
            if (!$userPosts || isset($userPosts['code']) && $userPosts['code'] !== 0) {
                $errorMessage = isset($userPosts['msg']) ? $userPosts['msg'] : 'Could not fetch user videos';
                Log::warning('Failed to fetch videos for user: ' . $username, ['response' => $userPosts]);
                
                // Still show profile but with empty videos
                return view('profile', [
                    'user' => $userInfo['data']['user'],
                    'stats' => $userInfo['data']['stats'],
                    'videos' => [],
                    'cursor' => null,
                    'hasMore' => false,
                    'username' => $username,
                    'error' => $errorMessage,
                    'isStale' => $isStaleProfile || $isStaleVideos, // Indicate if any data is stale
                    'cachedAt' => $userInfo['cached_at'] ?? null
                ]);
            }
            
            return view('profile', [
                'user' => $userInfo['data']['user'],
                'stats' => $userInfo['data']['stats'],
                'videos' => $userPosts['data']['videos'] ?? [],
                'cursor' => $userPosts['data']['cursor'] ?? null,
                'hasMore' => $userPosts['data']['hasMore'] ?? false,
                'username' => $username,
                'isStale' => $isStaleProfile || $isStaleVideos, // Indicate if any data is stale
                'cachedAt' => $userInfo['cached_at'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user profile: ' . $e->getMessage(), [
                'username' => $username,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return more detailed error in debug mode
            if (config('app.debug')) {
                return view('error', [
                    'title' => 'Error Fetching Profile',
                    'message' => $e->getMessage(),
                    'details' => [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ]);
            }
            
            return redirect()->route('home')->with('error', 'Error: ' . $e->getMessage());
        }
    }
    
    public function loadMorePosts(Request $request)
    {
        try {
            // Log request data for debugging
            Log::debug('Load More Posts Request', [
                'username' => $request->input('username'),
                'cursor' => $request->input('cursor'),
                'input' => $request->all()
            ]);
            
            // Validate inputs - removed _token requirement
            $validator = validator($request->all(), [
                'username' => 'required|string',
                'cursor' => 'nullable|string'
            ]);
            
            if ($validator->fails()) {
                Log::warning('Validation failed for load more request', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'error' => 'Invalid request parameters: ' . implode(', ', $validator->errors()->all()),
                    'videos' => [],
                    'cursor' => null,
                    'hasMore' => false
                ], 400);
            }
            
            $username = $request->input('username');
            $cursor = $request->input('cursor', 0); // Default to 0 if not provided
            
            // Handle empty cursor string
            if ($cursor === '') {
                $cursor = 0;
            }
            
            // Initialize parameters for API request
            $params = [
                'unique_id' => $username,
                'count' => 10
            ];
            
            // Only add cursor if it's not 0 or empty
            if (!empty($cursor) && $cursor !== 0 && $cursor !== '0') {
                $params['cursor'] = $cursor;
            }
            
            Log::debug('Processed request parameters', [
                'username' => $username,
                'cursor' => $cursor,
                'cursor_type' => gettype($cursor),
                'api_params' => $params
            ]);
            
            // Make direct API request instead of using getUserPosts method
            try {
                $response = Http::timeout(30)->withHeaders([
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => env('TIKTOK_API_HOST')
                ])->get($this->baseUrl . '/user/posts', $params);
                
                Log::debug('Raw API response for load more', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body_length' => strlen($response->body()),
                    'body_preview' => substr($response->body(), 0, 200),
                ]);
                
                if (!$response->successful()) {
                    return response()->json([
                        'error' => 'API request failed with status: ' . $response->status(),
                        'videos' => [],
                        'cursor' => null,
                        'hasMore' => false
                    ], 200);
                }
                
                $userPostsResponse = $response->json();
                
                if (!$userPostsResponse) {
                    return response()->json([
                        'error' => 'Failed to decode JSON response from API',
                        'videos' => [],
                        'cursor' => null,
                        'hasMore' => false
                    ], 200);
                }
                
                if (isset($userPostsResponse['code']) && $userPostsResponse['code'] !== 0) {
                    $errorMessage = $userPostsResponse['msg'] ?? 'Unknown API error';
                    
                    Log::error('TikTok API error', [
                        'username' => $username,
                        'cursor' => $cursor,
                        'code' => $userPostsResponse['code'],
                        'message' => $errorMessage
                    ]);
                    
                    return response()->json([
                        'error' => $errorMessage,
                        'debug_info' => $userPostsResponse,
                        'videos' => [],
                        'cursor' => null,
                        'hasMore' => false
                    ], 200);
                }
                
                // Extract videos data
                $videos = $userPostsResponse['data']['videos'] ?? [];
                $nextCursor = $userPostsResponse['data']['cursor'] ?? null;
                $hasMore = $userPostsResponse['data']['hasMore'] ?? false;
                
                Log::debug('Successfully processed videos', [
                    'username' => $username,
                    'video_count' => count($videos),
                    'next_cursor' => $nextCursor,
                    'has_more' => $hasMore
                ]);
                
                return response()->json([
                    'videos' => $videos,
                    'cursor' => $nextCursor,
                    'hasMore' => $hasMore,
                    'cachedAt' => date('Y-m-d H:i:s')
                ]);
                
            } catch (\Exception $apiException) {
                Log::error('API request exception: ' . $apiException->getMessage(), [
                    'username' => $username,
                    'cursor' => $cursor,
                    'exception_type' => get_class($apiException),
                    'trace' => $apiException->getTraceAsString()
                ]);
                
                return response()->json([
                    'error' => 'API error: ' . $apiException->getMessage(),
                    'exception_type' => get_class($apiException),
                    'videos' => [],
                    'cursor' => null,
                    'hasMore' => false
                ], 200);
            }
            
        } catch (\Exception $e) {
            Log::error('Error in loadMorePosts: ' . $e->getMessage(), [
                'username' => $request->input('username'),
                'cursor' => $request->input('cursor'),
                'exception_type' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Server error: ' . $e->getMessage(),
                'exception_type' => get_class($e),
                'videos' => [],
                'cursor' => null,
                'hasMore' => false
            ], 200);
        }
    }
    
    /**
     * Get video details - new method to support direct video access
     * 
     * @param string $videoId
     * @return array
     */
    public function getVideoDetails($videoId)
    {
        $attempts = 0;
        $lastException = null;
        
        while ($attempts <= $this->maxRetries) {
            try {
                $response = Http::timeout(15)->withHeaders([
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => env('TIKTOK_API_HOST')
                ])->get($this->baseUrl . '/video/info', [
                    'video_id' => $videoId
                ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                Log::warning('Failed API call to get video details', [
                    'video_id' => $videoId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'attempt' => $attempts + 1
                ]);
                
                // If we get a 429 (too many requests) or 500+ error, retry
                if ($response->status() == 429 || $response->status() >= 500) {
                    $attempts++;
                    // Exponential backoff
                    if ($attempts <= $this->maxRetries) {
                        sleep(pow(2, $attempts));
                        continue;
                    }
                }
                
                return [
                    'code' => -1, 
                    'msg' => 'API request failed: ' . $response->status() . ' - ' . $response->body()
                ];
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Exception when getting video details: ' . $e->getMessage(), [
                    'video_id' => $videoId,
                    'trace' => $e->getTraceAsString(),
                    'attempt' => $attempts + 1
                ]);
                
                $attempts++;
                // Only retry for network-related exceptions
                if ($attempts <= $this->maxRetries && (
                    $e instanceof \Illuminate\Http\Client\ConnectionException ||
                    $e instanceof \GuzzleHttp\Exception\ConnectException ||
                    $e instanceof \GuzzleHttp\Exception\RequestException
                )) {
                    sleep(pow(2, $attempts));
                    continue;
                }
                
                return [
                    'code' => -1, 
                    'msg' => 'Exception: ' . $e->getMessage() . ' (' . get_class($e) . ')'
                ];
            }
        }
        
        return [
            'code' => -1, 
            'msg' => 'Max retries exceeded. Last error: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')
        ];
    }

    public function getUserInfo($username)
    {
        $attempts = 0;
        $lastException = null;
        
        while ($attempts <= $this->maxRetries) {
            try {
                $response = Http::timeout(15)->withHeaders([
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => env('TIKTOK_API_HOST')
                ])->get($this->baseUrl . '/user/info', [
                    'unique_id' => $username
                ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                Log::warning('Failed API call to get user info', [
                    'username' => $username,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'attempt' => $attempts + 1
                ]);
                
                // If we get a 429 (too many requests) or 500+ error, retry
                if ($response->status() == 429 || $response->status() >= 500) {
                    $attempts++;
                    // Exponential backoff
                    if ($attempts <= $this->maxRetries) {
                        sleep(pow(2, $attempts));
                        continue;
                    }
                }
                
                return [
                    'code' => -1, 
                    'msg' => 'API request failed: ' . $response->status() . ' - ' . $response->body()
                ];
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Exception when getting user info: ' . $e->getMessage(), [
                    'username' => $username,
                    'trace' => $e->getTraceAsString(),
                    'attempt' => $attempts + 1
                ]);
                
                $attempts++;
                // Only retry for network-related exceptions
                if ($attempts <= $this->maxRetries && (
                    $e instanceof \Illuminate\Http\Client\ConnectionException ||
                    $e instanceof \GuzzleHttp\Exception\ConnectException ||
                    $e instanceof \GuzzleHttp\Exception\RequestException
                )) {
                    sleep(pow(2, $attempts));
                    continue;
                }
                
                return [
                    'code' => -1, 
                    'msg' => 'Exception: ' . $e->getMessage() . ' (' . get_class($e) . ')'
                ];
            }
        }
        
        return [
            'code' => -1, 
            'msg' => 'Max retries exceeded. Last error: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')
        ];
    }

    public function getUserPosts($username, $cursor = 0)
    {
        $attempts = 0;
        $lastException = null;
        
        Log::debug('Fetching user posts', [
            'username' => $username,
            'cursor' => $cursor,
            'api_key_length' => strlen($this->apiKey),
            'max_retries' => $this->maxRetries
        ]);
        
        while ($attempts <= $this->maxRetries) {
            try {
                // Build query parameters
                $params = [
                    'unique_id' => $username,
                    'count' => 10
                ];
                
                // Only add cursor if it's not 0
                if ($cursor !== 0 && !empty($cursor)) {
                    $params['cursor'] = $cursor;
                }
                
                Log::debug('Making TikTok API request for user posts', [
                    'endpoint' => $this->baseUrl . '/user/posts',
                    'params' => $params,
                    'attempt' => $attempts + 1
                ]);
                
                // Make the API request
                $response = Http::timeout(15)->withHeaders([
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => env('TIKTOK_API_HOST')
                ])->get($this->baseUrl . '/user/posts', $params);
                
                // Log the raw response for debugging
                Log::debug('Raw API response', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body_length' => strlen($response->body()),
                    'body_preview' => substr($response->body(), 0, 500),
                ]);
                
                if ($response->successful()) {
                    $jsonResponse = $response->json();
                    Log::debug('Successful response', [
                        'code' => $jsonResponse['code'] ?? 'not set',
                        'has_data' => isset($jsonResponse['data']),
                        'has_videos' => isset($jsonResponse['data']['videos']),
                        'video_count' => isset($jsonResponse['data']['videos']) ? count($jsonResponse['data']['videos']) : 0
                    ]);
                    
                    return $jsonResponse;
                }
                
                Log::warning('Failed API call to get user posts', [
                    'username' => $username,
                    'cursor' => $cursor,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'attempt' => $attempts + 1
                ]);
                
                // If we get a 429 (too many requests) or 500+ error, retry
                if ($response->status() == 429 || $response->status() >= 500) {
                    $attempts++;
                    // Exponential backoff
                    if ($attempts <= $this->maxRetries) {
                        $sleepTime = pow(2, $attempts);
                        Log::info("Retrying after {$sleepTime} seconds (attempt {$attempts})");
                        sleep($sleepTime);
                        continue;
                    }
                }
                
                return [
                    'code' => -1, 
                    'msg' => 'API request failed: ' . $response->status() . ' - ' . $response->body(),
                    'status_code' => $response->status()
                ];
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Exception when getting user posts: ' . $e->getMessage(), [
                    'username' => $username,
                    'cursor' => $cursor,
                    'exception_type' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                    'attempt' => $attempts + 1
                ]);
                
                $attempts++;
                // Only retry for network-related exceptions
                if ($attempts <= $this->maxRetries && (
                    $e instanceof \Illuminate\Http\Client\ConnectionException ||
                    $e instanceof \GuzzleHttp\Exception\ConnectException ||
                    $e instanceof \GuzzleHttp\Exception\RequestException
                )) {
                    $sleepTime = pow(2, $attempts);
                    Log::info("Retrying after {$sleepTime} seconds (attempt {$attempts})");
                    sleep($sleepTime);
                    continue;
                }
                
                return [
                    'code' => -1, 
                    'msg' => 'Exception: ' . $e->getMessage() . ' (' . get_class($e) . ')',
                    'exception_type' => get_class($e)
                ];
            }
        }
        
        return [
            'code' => -1, 
            'msg' => 'Max retries exceeded. Last error: ' . ($lastException ? $lastException->getMessage() : 'Unknown error'),
            'retry_count' => $attempts
        ];
    }
    
    /**
     * Method to manually warm the cache for trending profiles
     * This could be triggered by a scheduled task/cron job
     */
    public function warmTrendingCache()
    {
        // In a real application, you would fetch this list from an analytics service
        // Here we're just using a hardcoded list of example trending profiles
        $trendingProfiles = [
            'tiktok',
            'charlidamelio',
            'addisonre',
            'khaby.lame',
            'bellapoarch'
        ];
        
        $this->cacheService->warmTrendingProfilesCache($trendingProfiles);
        
        return response()->json([
            'success' => true,
            'message' => 'Cache warming scheduled for ' . count($trendingProfiles) . ' trending profiles'
        ]);
    }

    /**
     * Show the "How It Works" page with detailed explanation of anonymous viewing
     */
    public function howItWorks()
    {
        return view('pages.how-it-works');
    }

    /**
     * Show the "Popular TikTok Profiles" page
     */
    public function popularProfiles()
    {
        // These could be retrieved from a database, but for now, we'll hardcode some popular profiles
        $popularProfiles = [
            [
                'username' => 'charlidamelio',
                'name' => 'Charli D\'Amelio',
                'followers' => '149.5M',
                'description' => 'One of the most-followed creators on TikTok known for her dance videos',
                'image' => '/images/profile-placeholder.svg',
            ],
            [
                'username' => 'khaby.lame',
                'name' => 'Khaby Lame',
                'followers' => '160.3M',
                'description' => 'Known for his silent comedy videos where he mocks overly complicated life hacks',
                'image' => '/images/profile-placeholder.svg',
            ],
            [
                'username' => 'addisonre',
                'name' => 'Addison Rae',
                'followers' => '88.9M',
                'description' => 'Dancer and actress known for her dance videos and collaborations',
                'image' => '/images/profile-placeholder.svg',
            ],
            [
                'username' => 'zachking',
                'name' => 'Zach King',
                'followers' => '78.6M',
                'description' => 'Digital illusionist known for his "magic" videos with creative editing',
                'image' => '/images/profile-placeholder.svg',
            ],
            [
                'username' => 'bellapoarch',
                'name' => 'Bella Poarch',
                'followers' => '92.7M',
                'description' => 'Singer and content creator known initially for her lip-syncing and facial expressions',
                'image' => '/images/profile-placeholder.svg',
            ],
            [
                'username' => 'willsmith',
                'name' => 'Will Smith',
                'followers' => '72.2M',
                'description' => 'Actor and entertainer sharing comedy and behind-the-scenes content',
                'image' => '/images/profile-placeholder.svg',
            ],
        ];
        
        return view('pages.popular-profiles', compact('popularProfiles'));
    }

    /**
     * Show the "TikTok Tips" page
     */
    public function tikTokTips()
    {
        return view('pages.tiktok-tips');
    }
    
    /**
     * Test cache functionality
     * This is useful for debugging cache issues
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function testCache()
    {
        try {
            // Test cache connection
            $cacheTest = $this->cacheService->testCacheConnection();
            
            // Check cache directory
            $cachePath = config('cache.stores.file.path');
            $cachePathExists = file_exists($cachePath);
            $cachePathWritable = is_writable($cachePath);
            
            return response()->json([
                'cache_test' => $cacheTest,
                'cache_path_exists' => $cachePathExists,
                'cache_path_writable' => $cachePathWritable,
                'storage_path' => storage_path(),
                'base_path' => base_path(),
                'laravel_version' => app()->version(),
                'php_version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Cache test failed',
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Get trending videos for the user's region
     * 
     * @param int|null $cursor Optional cursor for pagination
     * @return array
     */
    public function getTrendingVideos($cursor = null)
    {
        try {
            // Set challenge ID and count
            $challengeId = '344004';
            $count = 20;
            
            // Get API credentials
            $apiKey = env('TIKTOK_API_KEY', '9891cfdd37mshf9c6d55e2721869p19f1f3jsnb23e66f69219');
            $apiHost = env('TIKTOK_API_HOST', 'tiktok-scraper7.p.rapidapi.com');
            $apiBaseUrl = env('TIKTOK_API_URL', 'https://tiktok-scraper7.p.rapidapi.com');
            
            // Construct the API URL for challenge posts
            $apiUrl = $apiBaseUrl . '/challenge/posts';
            
            // Prepare query parameters
            $params = [
                'challenge_id' => $challengeId,
                'count' => $count
            ];
            
            // Add cursor to the query parameters if provided
            if (!is_null($cursor)) {
                $params['cursor'] = $cursor;
            }
            
            Log::info("Fetching challenge videos from API: {$apiUrl} with challenge_id={$challengeId}, count={$count}" . 
                (!is_null($cursor) ? ", cursor={$cursor}" : ""));
            Log::info("API Key length: " . strlen($apiKey) . " characters");
            Log::info("API Host: " . $apiHost);
            
            $response = Http::withHeaders([
                'x-rapidapi-key' => $apiKey,
                'x-rapidapi-host' => $apiHost
            ])->get($apiUrl, $params);
            
            Log::info("API Response Status: " . $response->status());
            Log::info("API Response Body (first 500 chars): " . substr($response->body(), 0, 500));
            
            if ($response->successful() && isset($response['data']['videos']) && is_array($response['data']['videos'])) {
                $apiVideos = $response['data']['videos'];
                Log::info("Successfully fetched " . count($apiVideos) . " challenge videos");
                
                // Transform videos to ensure they have the expected fields
                $transformedVideos = [];
                
                foreach ($apiVideos as $video) {
                    // Create a new video with the standardized structure
                    $transformedVideo = [
                        'title' => $video['title'] ?? 'No title',
                        'desc' => $video['title'] ?? 'No description', // Using title as description if not available
                        'cover' => $video['cover'] ?? $video['origin_cover'] ?? $video['ai_dynamic_cover'] ?? '',
                        'play' => $video['play'] ?? $video['wmplay'] ?? '',
                        'digg_count' => $video['digg_count'] ?? 0,
                        'comment_count' => $video['comment_count'] ?? 0,
                        'share_count' => $video['share_count'] ?? 0,
                        'aweme_id' => $video['aweme_id'] ?? $video['video_id'] ?? ''
                    ];
                    
                    // Preserve author info if available
                    if (isset($video['author'])) {
                        $transformedVideo['author'] = $video['author'];
                    }
                    
                    // Add the transformed video to our collection
                    $transformedVideos[] = $transformedVideo;
                }
                
                return [
                    'videos' => $transformedVideos,
                    'country' => [
                        'code' => 'US',
                        'name' => 'United States'
                    ],
                    'cursor' => $response['data']['cursor'] ?? null,
                    'hasMore' => $response['data']['hasMore'] ?? false
                ];
            } else {
                Log::warning("Failed to fetch challenge videos: " . $response->body());
                
                // Return empty array with country info
                return [
                    'videos' => [],
                    'country' => [
                        'code' => 'US',
                        'name' => 'United States'
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::error("Error fetching challenge videos: " . $e->getMessage());
            Log::error("Exception stack trace: " . $e->getTraceAsString());
            
            return [
                'videos' => [],
                'country' => [
                    'code' => 'US',
                    'name' => 'United States'
                ]
            ];
        }
    }

    /**
     * Test the TikTok API to ensure it's working correctly
     */
    public function testApi()
    {
        try {
            $apiBaseUrl = 'https://tiktok-scraper7.p.rapidapi.com';
            $apiUrl = $apiBaseUrl . '/feed/list?region=US&count=5';
            $apiKey = env('TIKTOK_API_KEY');
            
            $response = Http::withHeaders([
                'x-rapidapi-key' => $apiKey,
                'x-rapidapi-host' => 'tiktok-scraper7.p.rapidapi.com'
            ])->get($apiUrl);
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'status' => $response->status(),
                    'has_data' => isset($response['data']),
                    'is_data_array' => isset($response['data']) && is_array($response['data']),
                    'data_count' => isset($response['data']) && is_array($response['data']) ? count($response['data']) : 0,
                    'first_item_sample' => isset($response['data'][0]) ? [
                        'has_title' => isset($response['data'][0]['title']),
                        'has_cover' => isset($response['data'][0]['cover']),
                        'has_download' => isset($response['data'][0]['download']),
                        'has_author_name' => isset($response['data'][0]['author_name']),
                        'has_unique_id' => isset($response['data'][0]['unique_id']),
                    ] : null,
                    'response_json' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get mock trending videos data for development and testing
     * 
     * @return array
     */
    private function getMockTrendingVideos()
    {
        return [
            'videos' => [
                [
                    'title' => 'Amazing dance challenge #dance #viral',
                    'desc' => 'Amazing dance challenge #dance #viral',
                    'cover' => 'https://via.placeholder.com/400x600',
                    'play' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                    'digg_count' => 1500000,
                    'comment_count' => 25000,
                    'share_count' => 75000,
                    'author' => [
                        'unique_id' => 'dancequeen',
                        'nickname' => 'Dance Queen'
                    ]
                ],
                [
                    'title' => 'Cooking my favorite pasta recipe #food #cooking',
                    'desc' => 'Cooking my favorite pasta recipe #food #cooking',
                    'cover' => 'https://via.placeholder.com/400x600',
                    'play' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                    'digg_count' => 800000,
                    'comment_count' => 12000,
                    'share_count' => 35000,
                    'author' => [
                        'unique_id' => 'chefmaster',
                        'nickname' => 'Chef Master'
                    ]
                ],
                [
                    'title' => 'Check out this amazing view! #travel #nature',
                    'desc' => 'Check out this amazing view! #travel #nature',
                    'cover' => 'https://via.placeholder.com/400x600',
                    'play' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
                    'digg_count' => 2000000,
                    'comment_count' => 45000,
                    'share_count' => 120000,
                    'author' => [
                        'unique_id' => 'worldtraveler',
                        'nickname' => 'World Traveler'
                    ]
                ],
            ],
            'country' => [
                'code' => 'DEMO',
                'name' => 'Demo Country'
            ]
        ];
    }

    /**
     * Show the trending videos page with initial videos
     */
    public function trendingVideosPage(Request $request)
    {
        // Check if a cursor was passed in the URL
        $cursor = $request->input('cursor', null);
        
        // Get initial trending videos
        $trendingData = $this->getTrendingVideos($cursor);
        
        // Separate videos and country information
        $trendingVideos = $trendingData['videos'] ?? [];
        $countryInfo = $trendingData['country'] ?? [
            'code' => 'US',
            'name' => 'United States'
        ];
        
        // Get cursor from response for subsequent requests
        $nextCursor = $trendingData['cursor'] ?? 1;
        
        // Store video IDs in session to track which videos have been shown
        $videoIds = collect($trendingVideos)->pluck('aweme_id')->filter()->toArray();
        session(['shown_video_ids' => $videoIds]);
        
        return view('pages.trending-videos', [
            'trendingVideos' => $trendingVideos,
            'countryInfo' => $countryInfo,
            'cursor' => $nextCursor,
            'hasMore' => $trendingData['hasMore'] ?? true // Get hasMore from response
        ]);
    }
    
    /**
     * Load more trending videos via AJAX
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadMoreTrendingVideos(Request $request)
    {
        try {
            // Log request for debugging
            Log::info("Load more trending videos request", [
                'request_body' => $request->all(),
                'content_type' => $request->header('Content-Type')
            ]);
            
            // Get videos that have already been shown
            $shownVideoIds = session('shown_video_ids', []);
            
            // Set challenge ID and count
            $challengeId = '344004';
            $count = 20;
            $cursor = $request->input('cursor', 1); // Default cursor is 1 for next page
            
            // Get API credentials
            $apiKey = env('TIKTOK_API_KEY', '9891cfdd37mshf9c6d55e2721869p19f1f3jsnb23e66f69219');
            $apiHost = env('TIKTOK_API_HOST', 'tiktok-scraper7.p.rapidapi.com');
            $apiBaseUrl = env('TIKTOK_API_URL', 'https://tiktok-scraper7.p.rapidapi.com');
            
            // Construct the API URL for challenge posts
            $apiUrl = $apiBaseUrl . '/challenge/posts';
            
            Log::info("Loading more challenge videos from API: {$apiUrl} with challenge_id={$challengeId}, count={$count}, cursor={$cursor}");
            
            $response = Http::withHeaders([
                'x-rapidapi-key' => $apiKey,
                'x-rapidapi-host' => $apiHost
            ])->get($apiUrl, [
                'challenge_id' => $challengeId,
                'count' => $count,
                'cursor' => $cursor
            ]);
            
            // Log the API response for debugging
            Log::info("API Response Status: " . $response->status());
            Log::info("API Response Body (first 500 chars): " . substr($response->body(), 0, 500));
            
            if ($response->successful() && isset($response['data']['videos']) && is_array($response['data']['videos'])) {
                $apiVideos = $response['data']['videos'];
                
                // Filter out videos that have already been shown
                $newVideos = collect($apiVideos)->filter(function ($video) use ($shownVideoIds) {
                    return !in_array($video['aweme_id'] ?? '', $shownVideoIds);
                })->values()->all();
                
                // Transform videos to ensure they have the expected fields
                $transformedVideos = [];
                foreach ($newVideos as $video) {
                    // Store the video ID to prevent duplicates
                    $videoId = $video['aweme_id'] ?? '';
                    if (!empty($videoId)) {
                        $shownVideoIds[] = $videoId;
                    }
                    
                    // Create a new video with the standardized structure
                    $transformedVideo = [
                        'title' => $video['title'] ?? 'No title',
                        'desc' => $video['title'] ?? 'No description',
                        'digg_count' => $video['digg_count'] ?? 0,
                        'comment_count' => $video['comment_count'] ?? 0,
                        'share_count' => $video['share_count'] ?? 0,
                        'aweme_id' => $videoId
                    ];
                    
                    // Handle video URL
                    $transformedVideo['play'] = $video['play'] ?? $video['wmplay'] ?? '';
                    
                    // Handle video cover image
                    $transformedVideo['cover'] = $video['cover'] ?? $video['origin_cover'] ?? $video['ai_dynamic_cover'] ?? '';
                    
                    // Preserve author info if available
                    if (isset($video['author'])) {
                        $transformedVideo['author'] = $video['author'];
                    }
                    
                    // Add the transformed video to our collection
                    $transformedVideos[] = $transformedVideo;
                }
                
                // Update the session with new shown video IDs
                session(['shown_video_ids' => $shownVideoIds]);
                
                // Get next cursor from response
                $nextCursor = $response['data']['cursor'] ?? null;
                $hasMore = $response['data']['hasMore'] ?? false;
                
                $result = [
                    'success' => true,
                    'videos' => $transformedVideos,
                    'hasMore' => $hasMore,
                    'cursor' => $nextCursor
                ];
                
                Log::info("Returning success response with " . count($transformedVideos) . " videos and cursor: " . $nextCursor);
                
                return response()->json($result);
            } else {
                Log::warning("Failed to fetch more challenge videos: " . $response->body());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch more challenge videos',
                    'videos' => [],
                    'hasMore' => false
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error fetching more challenge videos: " . $e->getMessage());
            Log::error("Exception trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching more challenge videos: ' . $e->getMessage(),
                'videos' => [],
                'hasMore' => false
            ]);
        }
    }
} 