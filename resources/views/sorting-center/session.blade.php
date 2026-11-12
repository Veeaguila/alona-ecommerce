@isset($logisticsSession)
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.logisticsSession = {{ Illuminate\Support\Js::from($logisticsSession) }};</script>
@endisset
