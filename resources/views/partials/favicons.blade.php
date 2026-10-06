{{-- Favicons / PWA icons. Shared by every layout so the browser tab and the
     installed-app icon use the same brand mark. The static assets are served
     with a 30-day immutable Cache-Control and Cloudflare caches them at the
     edge, so the URLs are keyed on the file mtime to invalidate both caches the
     moment an icon is regenerated. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ filemtime(public_path('favicon-32x32.png')) }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v={{ filemtime(public_path('favicon-16x16.png')) }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v={{ filemtime(public_path('apple-touch-icon.png')) }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#18181b">
