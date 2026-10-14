<span @class([
    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-lamu-100 text-lamu-900' => $status === 'healthy',
    'bg-sand-100 text-stone-700' => $status === 'configured',
    'bg-red-50 text-red-800' => $status === 'unhealthy',
    'border border-amber-200 bg-amber-50 text-lamu-900' => $status === 'not_configured',
])>{{ $label }}</span>
