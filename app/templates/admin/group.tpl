@extends('layouts/admin')
      <section class="f-admin">
        <h2>{!! __('Group settings head') !!}</h2>
        <div class="f-fdiv">
          <form class="f-form" method="post" action="{!! $formAction !!}">
            <input type="hidden" name="token" value="{!! $formToken !!}">
            <dl>
@foreach($form as $key => $cur)
              <dt>{!! $cur['title'] !!}</dt>
              <dd>
@if($cur['type'] == 'text')
                <input class="f-ctrl" @if(isset($cur['required'])){!! 'required' !!}@endif type="text" name="{{ $key }}" maxlength="{!! $cur['maxlength'] !!}" value="{{ $cur['value'] }}" tabindex="{!! ++$tabindex !!}">
@elseif($cur['type'] == 'number')
                <input class="f-ctrl" type="number" name="{{ $key }}" min="{!! $cur['min'] !!}" max="{!! $cur['max'] !!}" value="{{ $cur['value'] }}" tabindex="{!! ++$tabindex !!}">
@elseif($cur['type'] == 'select')
                <select class="f-ctrl" name="{{ $key }}" tabindex="{!! ++$tabindex !!}">
@foreach($cur['options'] as $v => $n)
@if($v == $cur['value'])
                  <option value="{{ $v }}" selected>{{ $n }}</option>
@else
                  <option value="{{ $v }}">{{ $n }}</option>
@endif
@endforeach
                </select>
@elseif($cur['type'] == 'radio')
@foreach($cur['values'] as $v => $n)
@if($v == $cur['value'])
                <label class="f-label"><input type="radio" name="{{ $key }}" value="{{ $v }}" checked tabindex="{!! ++$tabindex !!}">{{ $n }}</label>
@else
                <label class="f-label"><input type="radio" name="{{ $key }}" value="{{ $v }}" tabindex="{!! ++$tabindex !!}">{{ $n }}</label>
@endif
@endforeach
@endif
@if(isset($cur['info']))
                <span class="f-child4">{!! $cur['info'] !!}</span>
@endif
              </dd>
@endforeach
            </dl>
@if($warn)
            <p class="f-finfo">{!! $warn !!}</p>
@endif
            <div>
              <input class="f-btn" type="submit" name="submit" value="{!! __('Save') !!}" tabindex="{!! ++$tabindex !!}">
            </div>
          </form>
        </div>
      </section>
