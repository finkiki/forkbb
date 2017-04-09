@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Passphrase reset') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <div>
            <label class="f-child1 f-req" for="id-email">{!! __('Email') !!}</label>
            <input required class="f-ctrl" id="id-email" type="text" name="email" value="{{ $email }}" maxlength="80" pattern=".+@.+" autofocus spellcheck="false" tabindex="1">
            <span class="f-child4">{!! __('Passphrase reset info') !!}</span>
          </div>
          <div>
            <input class="f-btn" type="submit" name="submit" value="{!! __('Send email') !!}" tabindex="2">
          </div>
        </form>
      </div>
    </section>
