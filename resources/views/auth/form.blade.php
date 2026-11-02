@php
$titles=['login'=>'Bentornato nel tuo Hub.','forgot'=>'Recupera il tuo accesso.','reset'=>'Scegli una nuova password.','confirm'=>'Conferma la tua identità.'];
$actions=['login'=>'/login','forgot'=>'/forgot-password','reset'=>'/reset-password','confirm'=>'/user/confirm-password'];
@endphp
<x-layout :title="$titles[$mode]">
<div class="auth-story"><a class="brand" href="/login"><span class="brand-mark">vp<span>·</span></span><span>Developer Hub<small>VALERIO PERSIANI</small></span></a><div><div class="eyebrow light">TECNOLOGIA. RELAZIONI. CONTINUITÀ.</div><h1>Ogni progetto.<br>Un unico<br><em>punto d’incontro.</em></h1><p>Il tuo spazio dedicato per seguire attività, servizi e risultati. Con chiarezza, un passo alla volta.</p><div class="auth-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><span>vp<span>·</span></span><i></i></div></div><small>Valerio Persiani Developer · Area riservata</small></div>
<div class="auth-form-wrap"><div class="auth-form"><span class="eyebrow">VP DEVELOPER HUB</span><h2>{{ $titles[$mode] }}</h2><p class="muted">{{ $mode==='login'?'Accedi al tuo workspace. Tutto il necessario, in un solo posto.':'Segui la procedura per proteggere il tuo account.' }}</p>
<form method="post" action="{{ $actions[$mode] }}" class="stack">@csrf
@if($mode==='login')<label>Email o username<input type="text" name="login" value="{{ old('login') }}" required autocomplete="username" autofocus maxlength="180" autocapitalize="none" spellcheck="false" placeholder="Email o username"></label>@endif
@if(in_array($mode,['forgot','reset']))<label>Email<input type="email" name="email" value="{{ old('email',$request->email ?? '') }}" required autocomplete="username" autofocus placeholder="nome@azienda.it"></label>@endif
@if($mode==='reset')<input type="hidden" name="token" value="{{ $request->route('token') }}">@endif
@if(in_array($mode,['login','reset','confirm']))<label>Password<input type="password" name="password" required autocomplete="{{ $mode==='reset'?'new-password':'current-password' }}" @if($mode==='reset') minlength="12" @endif></label>@endif
@if($mode==='reset')<label>Conferma password<input type="password" name="password_confirmation" required autocomplete="new-password"></label><small class="muted">Almeno 12 caratteri, maiuscole, minuscole, numeri e simboli.</small>@endif
@if($mode==='login')<div class="split"><a class="text-link" href="/forgot-password">Password dimenticata?</a></div>@endif
<button class="button primary wide">{{ $mode==='forgot'?'Invia link di recupero':($mode==='login'?'Accedi al workspace':'Continua') }}<x-icon name="arrow"/></button>
</form><div class="auth-safe"><x-icon name="shield"/><span>Accesso personale e protetto.<br>I tuoi dati sono visibili solo agli utenti autorizzati.</span></div>@if($mode!=='login')<a href="/login" class="text-link">Torna all’accesso</a>@endif
</div></div>
</x-layout>
