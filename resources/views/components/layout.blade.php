@props(['title'=>'Panoramica'])
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#102b32"><title>{{ $title }} · VP Developer Hub</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
<a href="#main" class="skip-link">Vai al contenuto</a>
@auth
<aside class="sidebar" id="sidebar" aria-label="Navigazione principale">
    <a class="brand" href="/dashboard"><span class="brand-mark">vp<span>·</span></span><span>Developer Hub<small>VALERIO PERSIANI</small></span></a>
    <div class="workspace-label"><span class="status-dot"></span>{{ auth()->user()->isAdmin() ? 'CENTRO OPERATIVO' : 'AREA CLIENTE' }}</div>
    <nav>
    @php($nav=[['/dashboard','Panoramica','grid','portal'],['/manage/clients','Clienti','users','admin'],['/manage/projects','Progetti','folder','portal'],['/manage/applications','Applicativi','code','portal'],['/manage/services','Servizi','grid','portal'],['/manage/interventions','Interventi','clock','portal'],['/manage/tickets','Richieste assistenza','message','tickets']])
    @foreach($nav as [$url,$text,$icon,$ability])
        @if($ability==='admin' ? auth()->user()->isAdmin() : auth()->user()->allowed($ability))<a href="{{ $url }}" @class(['nav-link','active'=>request()->is(ltrim($url,'/').'*')])><x-icon :name="$icon"/>{{ $text }}</a>@endif
    @endforeach
    <div class="nav-heading">GESTIONE</div>
    @php($nav2=[['/billing','Consuntivi','wallet','billing'],['/approvals','Approvazioni','check','approvals'],['/manage/contracts','Contratti','file','billing'],['/documents','Documenti','folder','documents'],['/team','Referenti e accessi','users','team'],['/manage/packages','Pacchetti','grid','admin'],['/manage/categories','Categorie','grid','admin'],['/audit','Registro attività','shield','admin']])
    @foreach($nav2 as [$url,$text,$icon,$ability])
        @if($ability==='admin' ? auth()->user()->isAdmin() : auth()->user()->allowed($ability))<a href="{{ $url }}" @class(['nav-link','active'=>request()->is(ltrim($url,'/').'*')])><x-icon :name="$icon"/>{{ $text }}</a>@endif
    @endforeach
    </nav>
    <div class="sidebar-bottom"><div class="private-note"><x-icon name="shield"/><span>Il tuo spazio, riservato.<small>Connessioni che creano valore.</small></span></div><a href="/security" class="user-card"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span>{{ auth()->user()->name }}<small>{{ auth()->user()->isAdmin()?'Amministratore':'Account aziendale' }}</small></span><x-icon name="arrow"/></a></div>
</aside>
<div class="app-shell">
<header class="topbar"><div class="flex items-center gap-3"><button class="icon-button mobile-menu" type="button" data-menu aria-label="Apri o chiudi navigazione" aria-expanded="false" aria-controls="sidebar"><x-icon name="menu"/></button><span class="breadcrumb">Workspace <span>/</span> <strong>{{ $title }}</strong></span></div><div class="topbar-actions"><span class="today">{{ now('Europe/Rome')->translatedFormat('d F Y') }}</span><button type="button" class="icon-button" data-theme-toggle aria-label="Cambia tema chiaro o scuro"><x-icon name="sun"/></button><form method="post" action="/logout">@csrf<button class="icon-button" aria-label="Esci dall’account"><x-icon name="logout"/></button></form></div></header>
<main id="main" class="main-content">
@else
<main id="main" class="auth-page">
@endauth
@if(session('notice'))<div class="notice" role="status"><x-icon name="check"/>{{ session('notice') }}</div>@endif
@if(session('status'))<div class="notice" role="status">{{ is_string(session('status')) ? __(session('status')) : 'Operazione completata.' }}</div>@endif
@if(isset($errors) && $errors->any())<div class="error-box" role="alert"><strong>Controlla i dati inseriti</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
</main>
@auth<footer class="app-footer"><span>VP Developer Hub <span class="muted">/</span> Valerio Persiani Developer</span><span>Area riservata · {{ date('Y') }}</span></footer></div>@endauth
</body></html>
