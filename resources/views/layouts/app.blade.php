<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#192b2a">
    <title>@yield('title', 'Find your people') · PaceKita</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <div class="shell nav-wrap">
            <a class="brand" href="{{ route('home') }}" aria-label="PaceKita home"><span class="brand-mark">↗</span> pace<span>kita</span><span class="brand-dot">.</span></a>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="{{ route('runs.index') }}" @class(['active' => request()->routeIs('runs.*')])>Find runs</a>
                <a href="{{ route('clubs.index') }}" @class(['active' => request()->routeIs('clubs.*')])>Clubs</a>
                @auth
                    <a href="{{ route('dashboard') }}">My runs</a>
                    <a href="{{ route('organizer.index') }}">Organize</a>
                @endauth
            </nav>
            <div class="nav-actions">
                @auth
                    <a class="nav-account" href="{{ route('notices.index') }}">Notices @if(auth()->user()->unreadNotifications()->count())<span class="unread">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</a>
                    <a class="nav-account" href="{{ route('profile.edit') }}">Profile</a>
                    <form method="post" action="{{ route('logout') }}">@csrf <button class="text-button" type="submit">Log out</button></form>
                @else
                    <a class="nav-account" href="{{ route('login') }}">Log in</a>
                    <a class="button button-dark nav-signup" href="{{ route('register') }}">Join the movement <span aria-hidden="true">↗</span></a>
                @endauth
            </div>
        </div>
    </header>
    <main id="main">
        @if(session('status')) <div class="shell flash" role="status">{{ session('status') }}</div> @endif
        @if($errors->any()) <div class="shell flash error" role="alert"><strong>Please check your input.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="shell footer-grid">
            <div><a class="brand footer-brand" href="{{ route('home') }}">↗ pace<span>kita</span><span class="brand-dot">.</span></a><p>Better miles, better together.<br>Built for every kind of runner.</p></div>
            <div><strong>Explore</strong><a href="{{ route('runs.index') }}">Upcoming runs</a><a href="{{ route('clubs.index') }}">Running clubs</a></div>
            <div><strong>Good to know</strong><a href="{{ route('safety') }}">Safety</a><a href="{{ route('privacy') }}">Privacy</a><a href="mailto:hello@pacekita.example">Contact</a></div>
            <p class="footer-copy">© {{ date('Y') }} PaceKita. Move at your pace.</p>
        </div>
    </footer>
</body>
</html>
