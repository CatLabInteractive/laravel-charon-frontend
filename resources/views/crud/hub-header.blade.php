{{-- Header and tabs of a resource on an app page that is one of its tabs; $hub from FrontCrudController::getHubChrome(). --}}
@include('charonfrontend::crud.chrome', [ 'chrome' => $hub['chrome'] ])
@include('charonfrontend::crud.tabs', [ 'tabs' => $hub['tabs'] ])
