<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Restaurant POS</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body>
    <header>
        <a href="{{ route(auth('central')->check() ? 'central.home' : 'staff.home') }}">Restaurant POS</a>
        <nav aria-label="Main navigation">
            @if (auth('staff')->check() || auth('central')->check())
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            @endif
        </nav>
    </header>
    <main>
        <h1>@yield('title')</h1>
        @if ($errors->any())
            <ul class="errors" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        @yield('content')
    </main>
</body>
</html>
