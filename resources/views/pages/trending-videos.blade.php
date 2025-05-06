@extends('layouts.app')

@section('title', 'Trending TikTok Videos - Anonymous TikTok Viewer')
@section('meta_description', 'Watch the latest trending TikTok videos from around the world without logging in. View popular content anonymously with no account required.')

@section('content')
<div class="container">
    <div class="trending-page-header my-4 text-center">
        <h1 class="display-5 fw-bold">Trending TikTok Videos</h1>
        <p class="lead">Discover the most popular TikTok videos in {{ $countryInfo['name'] ?? 'Your Region' }} ({{ $countryInfo['code'] ?? '' }}) right now</p>
    </div>
    
    <div class="trending-videos-container mb-5">
        <div class="row g-4" id="videos-container">
            @foreach($trendingVideos as $video)
                <div class="col-md-6 col-lg-4 video-item">
                    <div class="card h-100">
                        <div class="tiktok-video-container ratio ratio-9x16">
                            @if(isset($video['play']))
                                <video controls poster="{{ $video['cover'] ?? '' }}" preload="none">
                                    <source src="{{ $video['play'] }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            @else
                                <div class="d-flex align-items-center justify-content-center bg-light h-100">
                                    <i class="fas fa-video-slash text-muted fa-3x"></i>
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">{{ Str::limit($video['title'] ?? 'No title', 50) }}</h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-heart"></i> {{ number_format($video['digg_count'] ?? 0) }}
                                    <i class="fas fa-comment ms-2"></i> {{ number_format($video['comment_count'] ?? 0) }}
                                    <i class="fas fa-share ms-2"></i> {{ number_format($video['share_count'] ?? 0) }}
                                </small>
                            </p>
                            @if(isset($video['author']['unique_id']))
                                <a href="/user/${video.author.unique_id}" class="btn btn-outline-danger btn-sm">
                                    View Profile
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        @if($hasMore)
            <div class="text-center mt-5" id="load-more-container">
                <button id="load-more-btn" class="btn btn-danger px-5 py-2">
                    Load More Videos
                </button>
                <div id="loading-spinner" class="spinner-border text-danger mt-3 d-none" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        @endif
    </div>
    
    <div class="trending-info my-5">
        <h2>About Trending TikTok Videos</h2>
        <p>
            Our trending videos page shows you what's popular on TikTok right now in your region. 
            These videos are updated regularly and reflect the content that's gaining traction across the platform.
        </p>
        <p>
            When you use our service, you can watch trending TikTok videos anonymously without:
        </p>
        <ul>
            <li>Creating a TikTok account</li>
            <li>Logging in</li>
            <li>Getting tracked by the TikTok algorithm</li>
            <li>Installing the TikTok app</li>
        </ul>
        <p>
            This makes it the perfect way to stay up to date with what's trending on TikTok while maintaining your privacy.
        </p>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadMoreBtn = document.getElementById('load-more-btn');
        const videosContainer = document.getElementById('videos-container');
        const loadingSpinner = document.getElementById('loading-spinner');
        let currentCursor = {{ $cursor ?? 1 }}; // Get cursor from controller
        
        // Update URL with current cursor
        function updateUrlWithCursor(cursor) {
            if (window.history && window.history.pushState) {
                const url = new URL(window.location.href);
                url.searchParams.set('cursor', cursor);
                window.history.pushState({ cursor: cursor }, '', url.toString());
            }
        }
        
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                // Show loading spinner
                loadingSpinner.classList.remove('d-none');
                loadMoreBtn.disabled = true;
                
                // Fetch more videos
                fetch('{{ route("load.more.trending") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        cursor: currentCursor
                    })
                })
                .then(response => {
                    // Check if response is valid
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide loading spinner
                    loadingSpinner.classList.add('d-none');
                    loadMoreBtn.disabled = false;
                    
                    if (data.success) {
                        // Update cursor for next request
                        if (data.cursor) {
                            currentCursor = data.cursor;
                            // Update URL with the new cursor
                            updateUrlWithCursor(currentCursor);
                        }
                        
                        // Append new videos
                        data.videos.forEach(video => {
                            const videoHtml = createVideoCard(video);
                            videosContainer.insertAdjacentHTML('beforeend', videoHtml);
                        });
                        
                        // Hide the load more button if no more videos
                        if (!data.hasMore) {
                            loadMoreBtn.style.display = 'none';
                        }
                    } else {
                        // Show error message
                        console.error('API Error:', data.message);
                        alert(data.message || 'Failed to load more videos');
                    }
                })
                .catch(error => {
                    console.error('Error loading more videos:', error);
                    loadingSpinner.classList.add('d-none');
                    loadMoreBtn.disabled = false;
                    alert('Error loading more videos. Please try again later.');
                });
            });
        }
        
        // Function to create a video card HTML
        function createVideoCard(video) {
            const authorName = video.author ? (video.author.nickname || video.author.unique_id || 'Unknown') : 'Unknown';
            let authorLink = '';
            
            if (video.author && video.author.unique_id) {
                authorLink = `
                    <a href="/user/${video.author.unique_id}" class="btn btn-outline-danger btn-sm mt-2">
                        View Profile
                    </a>
                `;
            }
            
            return `
                <div class="col-md-6 col-lg-4 video-item">
                    <div class="card h-100 shadow-sm">
                        <div class="video-thumbnail">
                            <a href="javascript:void(0);" class="play-video" data-video-url="${video.play}">
                                <img src="${video.cover}" class="card-img-top" alt="${video.title}">
                                <div class="play-icon">
                                    <i class="bi bi-play-circle-fill"></i>
                                </div>
                            </a>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title text-truncate">${video.title}</h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="bi bi-person-circle"></i> ${authorName}
                                </small>
                            </p>
                            <div class="d-flex justify-content-between video-stats">
                                <span><i class="bi bi-heart-fill text-danger"></i> ${formatNumber(video.digg_count)}</span>
                                <span><i class="bi bi-chat-fill text-primary"></i> ${formatNumber(video.comment_count)}</span>
                                <span><i class="bi bi-share-fill text-success"></i> ${formatNumber(video.share_count)}</span>
                            </div>
                            ${authorLink}
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Format numbers to K, M, B
        function formatNumber(num) {
            if (num >= 1000000000) {
                return (num / 1000000000).toFixed(1) + 'B';
            }
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            }
            if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }
        
        // Add video player functionality
        document.addEventListener('click', function(e) {
            const videoLink = e.target.closest('.play-video');
            if (!videoLink) return;
            
            const videoUrl = videoLink.getAttribute('data-video-url');
            if (!videoUrl) return;
            
            // Create a video element
            const videoContainer = videoLink.parentElement;
            const img = videoLink.querySelector('img');
            const videoElement = document.createElement('video');
            
            videoElement.controls = true;
            videoElement.autoplay = true;
            videoElement.classList.add('card-img-top');
            if (img && img.src) {
                videoElement.poster = img.src;
            }
            
            const sourceElement = document.createElement('source');
            sourceElement.src = videoUrl;
            sourceElement.type = 'video/mp4';
            
            videoElement.appendChild(sourceElement);
            
            // Replace the thumbnail with the video
            videoContainer.innerHTML = '';
            videoContainer.appendChild(videoElement);
        });
    });
</script>
@endsection 