{{-- Kolom "klik untuk salin": tidak memicu klik baris (halaman edit). Tetap bekerja di http (Herd) lewat cadangan execCommand. --}}
@php($value = (string) $getState())

@if ($value !== '')
    <div style="padding: .25rem .75rem">
        <button
            type="button"
            x-data="{ ok: false }"
            x-on:click.prevent.stop="
                const t = @js($value);
                const fallback = () => {
                    const a = document.createElement('textarea');
                    a.value = t; a.style.position = 'fixed'; a.style.opacity = '0';
                    document.body.appendChild(a); a.select(); document.execCommand('copy'); a.remove();
                };
                (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(t) : Promise.reject()).catch(fallback).finally(() => { ok = true; setTimeout(() => ok = false, 1500); });
            "
            title="Klik untuk menyalin"
            style="all: unset; cursor: copy; font-size: .875rem; border-bottom: 1px dotted var(--gray-400);"
        >
            <span x-show="! ok">{{ $value }}</span>
            <span x-show="ok" x-cloak style="color: var(--success-600)">Disalin ✓</span>
        </button>
    </div>
@endif
