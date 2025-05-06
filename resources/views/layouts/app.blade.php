<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('images/favicon/site.webmanifest') }}">
    <link rel="mask-icon" href="{{ asset('images/favicon/safari-pinned-tab.svg') }}" color="#fe2c55">
    <meta name="msapplication-TileColor" content="#fe2c55">
    <meta name="msapplication-config" content="{{ asset('images/favicon/browserconfig.xml') }}">
    <meta name="theme-color" content="#fe2c55">
    
    <!-- Primary Meta Tags -->
    @yield('meta-tags')
    
    <!-- Google Analytics -->
    @include('components.google-analytics')
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #000000;
            padding: 1rem;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #fe2c55;
        }
        .logo i {
            margin-right: 5px;
        }
        .header-search {
            max-width: 300px;
        }
        .page-header {
            background-color: #fe2c55;
            padding: 3rem 0;
            color: white;
            margin-bottom: 2rem;
        }
        .page-title {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .content-section {
            background-color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        footer {
            background-color: #f1f1f1;
            padding: 2rem 0;
            text-align: center;
            margin-top: 2rem;
        }
        @yield('additional-styles')
        .logo span {
            color: #ff0050;
        }
        .form-control:focus, .btn:focus {
            box-shadow: none;
            border-color: #ff0050;
        }
        .btn-danger {
            background-color: #ff0050;
            border-color: #ff0050;
        }
        .btn-outline-danger {
            border-color: #ff0050;
            color: #ff0050;
        }
        .btn-outline-danger:hover {
            background-color: #ff0050;
            border-color: #ff0050;
        }
        footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        .feature {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            background-color: #ffffff;
            height: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .feature-icon {
            background-color: #ff0050;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .hero-section {
            padding: 4rem 0;
            background-color: #f2f2f2;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        
        /* Trending Videos specific styling */
        .tiktok-video-container {
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            background-color: #000;
            max-height: 500px;
        }
        
        .tiktok-video-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background-color: #000;
        }
        
        /* Custom ratio for TikTok videos */
        .ratio-9x16 {
            --bs-aspect-ratio: calc(16 / 9 * 100%);
            max-height: 500px;
        }
        
        @media (max-width: 768px) {
            .ratio-9x16 {
                --bs-aspect-ratio: calc(16 / 9 * 100%);
                max-height: 400px;
            }
        }
        
        .video-item {
            margin-bottom: 25px;
        }
        
        .card {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand logo" href="{{ route('home') }}">
                <i class="fab fa-tiktok"></i> TikTok Viewer
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('how.it.works') ? 'active' : '' }}" href="{{ route('how.it.works') }}">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('popular.profiles') ? 'active' : '' }}" href="{{ route('popular.profiles') }}">Popular Profiles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('tiktok.tips') ? 'active' : '' }}" href="{{ route('tiktok.tips') }}">TikTok Tips</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('trending.videos') ? 'active' : '' }}" href="{{ route('trending.videos') }}">Trending Videos</a>
                    </li>
                </ul>
                <form class="d-flex header-search" action="{{ url('/user') }}" method="GET">
                    <input class="form-control me-2" type="search" name="username" placeholder="TikTok Username">
                    <button class="btn btn-danger" type="submit">Go</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    @if(session('error'))
        <div class="container">
            <div class="alert alert-danger my-3">
                {{ session('error') }}
            </div>
        </div>
    @endif

    @yield('content')

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-3">
                    <h5>TikTok Viewer</h5>
                    <p>The best way to anonymously view TikTok profiles and videos without logging in.</p>
                </div>
                <div class="col-lg-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('home') }}" class="text-decoration-none">Home</a></li>
                        <li><a href="{{ route('how.it.works') }}" class="text-decoration-none">How It Works</a></li>
                        <li><a href="{{ route('popular.profiles') }}" class="text-decoration-none">Popular Profiles</a></li>
                        <li><a href="{{ route('tiktok.tips') }}" class="text-decoration-none">TikTok Tips</a></li>
                        <li><a href="{{ route('trending.videos') }}" class="text-decoration-none">Trending Videos</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5>Legal</h5>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('privacy.policy') }}" class="text-decoration-none">Privacy Policy</a></li>
                        <li><a href="{{ route('terms.of.use') }}" class="text-decoration-none">Terms of Use</a></li>
                        <li><a href="{{ route('legal') }}" class="text-decoration-none">Legal Information</a></li>
                        <li><p class="small mt-2 mb-0">TikTok Viewer is not affiliated with TikTok. This is a third-party application.</p></li>
                    </ul>
                </div>
            </div>
            <div class="mt-3">
                <p>&copy; {{ date('Y') }} TikTok Viewer. All rights reserved.</p>
            </div>
        </div>
    </footer>

    @yield('schema-markup')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html> 