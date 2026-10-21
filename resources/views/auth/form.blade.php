@php
$titles=['login'=>'Bentornato nel tuo Hub.','forgot'=>'Recupera il tuo accesso.','reset'=>'Scegli una nuova password.','confirm'=>'Conferma la tua identità.','challenge'=>'Un ultimo passo.'];
$actions=['login'=>'/login','forgot'=>'/forgot-password','reset'=>'/reset-password','confirm'=>'/user/confirm-password','challenge'=>'/two-factor-challenge'];
@endphp
<x-layout :title="$titles[$mode]">
<div class="auth-story"><a class="brand" href="/login"><span class="brand-mark">vp<span>·</span></span><span>Developer Hub<small>VALERIO PERSIANI</small></span></a><div><div class="eyebrow light">TECNOLOGIA. RELAZIONI. CONTINUITÀ.</div><h1>Ogni progetto.<br>Un unico<br><em>punto d’incontro.</em></h1><p>Il tuo spazio dedicato per seguire attività, servizi e risultati. Con chiarezza, un passo alla volta.</p><div class="auth-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><span>vp<span>·</span></span><i></i></div></div><small>Valerio Persiani Developer · Area riservata</small></div>
<div class="auth-form-wrap"><div class="auth-form"><span class="eyebrow">VP DEVELOPER HUB</span><h2>{{ $titles[$mode] }}</h2><p class="muted">{{ $mode==='login'?'Accedi al tuo workspace. Tutto il necessario, in un solo posto.':($mode==='challenge'?'Inserisci il codice dell’app di autenticazione oppure un codice di recupero.':'Segui la procedura per proteggere il tuo account.') }}</p>
<form method="post" action="{{ $actions[$mode] }}" class="stack">@csrf
@if(in_array($mode,['login','forgot','reset']))<label>Email<input type="email" name="email" value="{{ old('email',$request->email ?? '') }}" required autocomplete="username" autofocus placeholder="nome@azienda.it"></label>@endif
@if($mode==='reset')<input type="hidden" name="token" value="{{ $request->route('token') }}">@endif
@if(in_array($mode,['login','reset','confirm']))<label>Password<input type="password" name="password" required autocomplete="{{ $mode==='reset'?'new-password':'current-password' }}" @if($mode==='reset') minlength="12" @endif></label>@endif
@if($mode==='reset')<label>Conferma password<input type="password" name="password_confirmation" required autocomplete="new-password"></label><small class="muted">Almeno 12 caratteri, maiuscole, minuscole, numeri e simboli.</small>@endif
@if($mode==='login')<div class="split"><label class="check-label"><input type="checkbox" name="remember" value="1">Ricordami</label><a class="text-link" href="/forgot-password">Password dimenticata?</a></div>@endif
@if($mode==='challenge')<label>Codice a 6 cifre<input name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000"></label><details><summary>Usa un codice di recupero</summary><label>Codice di recupero<input name="recovery_code" autocomplete="off"></label></details>@endif
<button class="button primary wide">{{ $mode==='forgot'?'Invia link di recupero':($mode==='login'?'Accedi al workspace':'Continua') }}<x-icon name="arrow"/></button>
</form><div class="auth-safe"><x-icon name="shield"/><span>Accesso personale e protetto.<br>I tuoi dati sono visibili solo agli utenti autorizzati.</span></div>@if($mode!=='login')<a href="/login" class="text-link">Torna all’accesso</a>@endif
</div></div>
</x-layout>
