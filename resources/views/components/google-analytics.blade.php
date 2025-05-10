@if(config('analytics.google_id'))
    <!-- Preconnect to Google Analytics -->
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('analytics.google_id') }}" defer></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('analytics.google_id') }}', {
            'send_page_view': false,
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=None;Secure'
        });
        
        // Send pageview after page load
        window.addEventListener('load', function() {
            requestIdleCallback(function() {
                gtag('event', 'page_view');
            });
        });
    </script>
@endif 