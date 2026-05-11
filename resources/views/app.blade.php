<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="robots" content="noindex">
    <meta name="googlebot" content="noindex">
    <title>{{config('app.name')}}</title>
    <link rel="icon" type="image/x-icon" href="{{asset('favicon.ico')}}?v=2">
    <link rel="stylesheet" href="{{asset('nepali-datepicker.css')}}?v=5.0.6">
    @vite('resources/js/app.js')
</head>
<body>
<div id="app"></div>
<script src="{{asset('nepali-datepicker.js')}}?v=5.0.6"></script>
</body>
</html>
