<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/update.js') }}" defer></script>
    <script src="{{ asset('js/seatpicker_app.js') }}" defer></script>
    <script src="{{ asset('js/seatLayout.min.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('js/jquery.js') }}" ></script>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/change.css') }}" rel="stylesheet">
    <link href="{{ asset('css/seatLayout.css') }}" rel="stylesheet">

</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light navbar-laravel">
            <div class="container">
            <a class="navbar-brand" href="{{ url('/who/home') }}">
                {{ config('app.name', 'Laravel') }}
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left Side Of Navbar -->
                <ul class="navbar-nav mr-auto">

                </ul>

                <!-- Right Side Of Navbar -->
                <ul class="navbar-nav ml-auto">
                <!-- Authentication Links -->
                    <li class="nav-item dropdown">
                        <a class="dropdown-item" href="/">
                            {{ __('seatpicker') }}
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="dropdown-item" href="/tribune/new">
                            {{ __('add tribune') }}
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="dropdown-item" href="/tribune/update">
                            {{ __('change tribunes') }}
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="dropdown-item" href="/clear-cache">
                            {{ __('clear cache') }}
                        </a>
                    </li>
                </ul>
            </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>
</body>
</html>
