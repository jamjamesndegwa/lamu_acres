@if (session('status'))
    <div hidden data-flash="success" data-message="{{ session('status') }}"></div>
@endif

@if (session('error'))
    <div hidden data-flash="error" data-message="{{ session('error') }}"></div>
@endif

@if (session('warning'))
    <div hidden data-flash="warning" data-message="{{ session('warning') }}"></div>
@endif

@if (session('info'))
    <div hidden data-flash="info" data-message="{{ session('info') }}"></div>
@endif

@if ($errors->any())
    <div hidden data-flash="error" data-message="{{ $errors->first() }}"></div>
@endif
