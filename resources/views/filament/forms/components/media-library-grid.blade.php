{{--
    ADMIN-006 review fix — the single Media Library browser grid, shared by
    every MediaPicker "Select from Media Library" action and RichEditor's
    attach modal (see App\Filament\Support\Media\MediaPicker::libraryBrowserSchema()).
    This is a Filament ViewField's own view: $getStatePath()/$getState() are
    the field's own bound value (the selected media id, or ids for multiple),
    auto-exposed by Filament to every field's Blade view. Clicking a card
    calls Livewire's native $set() magic action directly against this
    field's own state path — no separate hidden field or custom Livewire
    component is needed for selection.
--}}
@php
    $current = $getState();
    $selectedIds = $multiple ? array_values(array_filter((array) $current)) : array_values(array_filter([$current]));
@endphp
<div>
    @if ($items->isEmpty())
        <p style="color:#6b7280;font-size:0.875rem;margin:0;padding:2rem 0;text-align:center">No media found.</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.75rem;max-height:26rem;overflow-y:auto;padding:0.25rem">
            @foreach ($items as $item)
                @php
                    $isSelected = in_array($item->id, $selectedIds, true);
                    $isImage = str_starts_with($item->mime_type, 'image/');
                    $newValue = $multiple
                        ? ($isSelected ? array_values(array_diff($selectedIds, [$item->id])) : [...$selectedIds, $item->id])
                        : ($isSelected ? null : $item->id);
                    $sizeKb = $item->size / 1024;
                    $sizeLabel = $sizeKb >= 1024 ? number_format($sizeKb / 1024, 1).' MB' : number_format($sizeKb, 1).' KB';
                @endphp
                <button
                    type="button"
                    wire:click="$set('{{ $getStatePath() }}', @js($newValue))"
                    title="{{ $item->original_filename }}"
                    style="border:2px solid {{ $isSelected ? '#f59e0b' : '#e5e7eb' }};border-radius:0.5rem;padding:0.5rem;text-align:left;background:{{ $isSelected ? '#fffbeb' : '#fff' }};cursor:pointer;position:relative;display:flex;flex-direction:column;gap:0.25rem"
                >
                    @if ($isSelected)
                        <span style="position:absolute;top:0.375rem;right:0.375rem;width:1.25rem;height:1.25rem;border-radius:9999px;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.6875rem;line-height:1;font-weight:700">&#10003;</span>
                    @endif
                    <div style="width:100%;height:90px;border-radius:0.375rem;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                        @if ($isImage)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($item->disk)->url($item->path) }}" alt="" style="width:100%;height:100%;object-fit:cover" loading="lazy" />
                        @else
                            <span style="font-size:1.75rem">&#128196;</span>
                        @endif
                    </div>
                    <div style="font-size:0.75rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#111827">{{ $item->original_filename }}</div>
                    <div style="font-size:0.6875rem;color:#6b7280">{{ $sizeLabel }}</div>
                </button>
            @endforeach
        </div>
    @endif
</div>
