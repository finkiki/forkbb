@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Info') !!}</h1>
    </div>
    <div id="fork-ban" class="f-main">
@if ($p->bannedIp)
      <p>{!! __('Your IP is blocked') !!}</p>
@else
      <p>{!! __('You are banned') !!}</p>
@endif
@if ($p->ban['expire'])
      <p>{!! __(['The ban expires %s', dt($p->ban['expire'], true)]) !!}</p>
@endif
@if ($p->ban['message'])
      <p>{!! __('Ban message for you') !!}</p>
      <p><b>{{ $p->ban['message'] }}</b></p>
@endif
      <p>{!! __(['Ban message contact %s', $p->adminEmail]) !!}</p>
    </div>
