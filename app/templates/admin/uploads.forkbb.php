@section ('pagination')
    @if ($p->pagination)
        <nav class="f-pages">
        @foreach ($p->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{{ __('Previous') }}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{{ __('Next') }}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/admin')
      <section id="fork-uploads" class="f-admin">
        <h2>{!! __('Uploads head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formUploads)
    @include ('layouts/form')
@endif
        </div>
      </section>
@if ($p->pagination)
      <div id="filelist" class="f-nav-links">
        <div class="f-nlinks">
    @yield ('pagination')
        </div>
      </div>
@endif
      <section id="fork-uploads-files" class="f-admin">
        <h2>{!! __('File list head') !!}</h2>
        <div class="f-fdiv">
@if (null !== $p->badPage && $iswev = [FORK_MESS_ERR => [['Page %s missing', $p->badPage]]])
    @include ('layouts/iswev')
@elseif ($form = $p->formFileList)
    @include ('layouts/form')
@endif
        </div>
      </section>
@if ($p->pagination)
      <div class="f-nav-links">
        <div class="f-nlinks">
    @yield ('pagination')
        </div>
      </div>
@endif
