@props(['label','value','icon','detail','accent'=>false])
<section @class(['stat-card','stat-accent'=>$accent])><div class="split"><span class="stat-label">{{ $label }}</span><span class="stat-icon"><x-icon :name="$icon"/></span></div><div class="stat-value">{{ $value }}</div><small>{{ $detail }}</small></section>
