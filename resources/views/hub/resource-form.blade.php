<x-layout :title="$def['title']"><div class="page-heading"><div><div class="eyebrow">{{ $def['title'] }}</div><h1>{{ $record->exists?'Modifica':'Nuovo' }} {{ $def['singular'] }}</h1><p>I campi contrassegnati con * sono obbligatori.</p></div><a href="/manage/{{ $resource }}" class="button secondary">Torna all’elenco</a></div>
<form method="post" action="/manage/{{ $resource }}{{ $record->exists?'/'.$record->id:'' }}" class="card form-card">@csrf @if($record->exists)@method('put')@endif
<div class="form-grid">
@foreach($def['fields'] as $key=>$field)
@if(!auth()->user()->isAdmin() && in_array($key,['tenant_id','status','priority','internal_notes']))@continue @endif
@php
$value=old($key,$record->$key);
if(!old() && $field['type']==='money') $value=number_format(($record->$key ?? 0)/100,2,'.','');
if($value instanceof \DateTimeInterface) $value=$value->format('Y-m-d');
if($value===null && $key==='performed_on') $value=now('Europe/Rome')->toDateString();
$required=str_starts_with($field['rules'],'required');
@endphp
<label @class(['full-width'=>in_array($field['type'],['textarea']),'check-label'=>$field['type']==='checkbox'])>
@if($field['type']!=='checkbox')<span>{{ $field['label'] }}{{ $required?' *':'' }}</span>@endif
@if($field['type']==='textarea')<textarea name="{{ $key }}" rows="4" @required($required) maxlength="10000">{{ $value }}</textarea>
@elseif(in_array($field['type'],['select','tenant','relation']))<select name="{{ $key }}" @required($required) @if($field['type']==='tenant') data-tenant-select @elseif($field['type']==='relation') data-relation-select @endif><option value="">Seleziona…</option>@if($field['type']==='select')@foreach($field['options'] as $v=>$label)<option value="{{ $v }}" @selected($value===$v)>{{ $label }}</option>@endforeach @else @foreach($options[$key] as $option)<option value="{{ $option->id }}" data-tenant="{{ $option->tenant_id ?? '' }}" @selected($value==$option->id)>{{ $option->name ?? $option->title }}</option>@endforeach @endif</select>
@elseif($field['type']==='checkbox')<input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key,$record->$key ?? in_array($key,['billable','uses_package','active','maintenance'])))><span>{{ $field['label'] }}</span>
@else<input type="{{ $field['type']==='money'?'number':$field['type'] }}" name="{{ $key }}" value="{{ $value }}" @required($required) @if(in_array($field['type'],['number','money'])) min="0" step="{{ $field['type']==='money'?'0.01':'1' }}" @endif @if($key==='tenant_id' && $record->exists) readonly @endif>@endif
</label>
@endforeach
</div><div class="form-footer"><p class="muted">{{ $resource==='tickets'?'La richiesta non consuma ore. Le attività vengono registrate separatamente.':'Le modifiche vengono registrate nel registro attività.' }}</p><button class="button primary">{{ $resource==='tickets'?'Invia richiesta':'Salva '.$def['singular'] }}<x-icon name="check"/></button></div></form>
</x-layout>
