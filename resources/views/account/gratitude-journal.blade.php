<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
            <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Gratitude Journal</h1>
            <p class="mt-1 text-sm text-brand-navy/70">A place for our members to share what they're grateful for. Public gratitude may appear on the homepage, For Community gratitude is shared within our member community, and Private gratitude is visible only to you.</p>

            @if (session('status'))
                <div class="mt-6 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('account.gratitude-journal.store') }}" class="mt-8" data-gratitude-entry-form>
                @csrf
                <label for="content" class="block text-sm font-medium text-brand-navy">New Entry</label>
                <textarea
                    id="content" name="content" rows="3" maxlength="{{ $maxLength }}"
                    placeholder="What are you grateful for today?"
                    data-gratitude-textarea
                    class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                >{{ old('content') }}</textarea>
                <p data-gratitude-counter class="mt-1 text-right text-xs text-brand-navy/50">0 / {{ $maxLength }}</p>

                <div class="mt-3">
                    <span class="block text-sm font-medium text-brand-navy">Visibility</span>
                    <div class="mt-2 flex flex-wrap gap-4">
                        @foreach ($visibilityOptions as $option)
                            <label class="flex items-center gap-2 text-sm text-brand-navy">
                                <input type="radio" name="visibility" value="{{ $option->value }}" @checked($option === \App\Enums\GratitudeJournalVisibility::Public) class="border-brand-navy/30 text-brand-gold focus:ring-brand-gold">
                                {{ $option->getLabel() }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-brand-navy/50">Public may appear on the homepage. For Community appears in the shared member Gratitude Journal. Private is visible only to you.</p>
                </div>

                <div class="mt-4">
                    <button
                        type="submit"
                        class="rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                    >
                        Save Entry
                    </button>
                </div>
            </form>
        </div>

        @php $reminderTabActive = $errors->has('gratitude_reminder_frequency'); @endphp
        <div class="rounded-2xl bg-white shadow-xl ring-1 ring-brand-navy/5" data-gratitude-tabs>
            <div class="flex border-b border-brand-navy/10 px-2 sm:px-4" role="tablist">
                <button
                    type="button" role="tab"
                    data-gratitude-tab-trigger="entries" aria-selected="{{ $reminderTabActive ? 'false' : 'true' }}"
                    class="gratitude-tab-trigger border-b-2 px-4 py-4 text-sm transition {{ $reminderTabActive ? 'border-transparent font-medium text-brand-navy/60 hover:text-brand-navy' : 'border-brand-gold font-semibold text-brand-navy' }}"
                >
                    Your Entries
                </button>
                <button
                    type="button" role="tab"
                    data-gratitude-tab-trigger="reminder" aria-selected="{{ $reminderTabActive ? 'true' : 'false' }}"
                    class="gratitude-tab-trigger border-b-2 px-4 py-4 text-sm transition {{ $reminderTabActive ? 'border-brand-gold font-semibold text-brand-navy' : 'border-transparent font-medium text-brand-navy/60 hover:text-brand-navy' }}"
                >
                    Reminder Preference
                </button>
            </div>

            <div class="p-6 sm:p-8 {{ $reminderTabActive ? 'hidden' : '' }}" data-gratitude-tab-panel="entries">
                @if ($entries->isEmpty())
                    <p class="text-sm text-brand-navy/60">You haven't written a Gratitude Journal entry yet.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($entries as $entry)
                            <div class="rounded-md border border-brand-navy/10 p-4" data-gratitude-entry>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-brand-navy" data-gratitude-entry-display>{{ $entry->content }}</p>
                                    @php
                                        $visibilityBadgeClass = match ($entry->visibility) {
                                            \App\Enums\GratitudeJournalVisibility::Public => 'border-brand-gold/40 text-brand-gold',
                                            \App\Enums\GratitudeJournalVisibility::Community => 'border-blue-300 text-blue-600',
                                            \App\Enums\GratitudeJournalVisibility::Private => 'border-brand-navy/20 text-brand-navy/60',
                                        };
                                    @endphp
                                    <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold tracking-wide uppercase {{ $visibilityBadgeClass }}">
                                        {{ $entry->visibility->getLabel() }}
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-brand-navy/50">
                                    <span class="tabular-nums">{{ $entry->created_at?->format('M j, Y g:i A') }}</span>
                                    <button type="button" data-gratitude-edit-toggle class="font-semibold text-brand-gold hover:underline">Edit</button>
                                    <form method="POST" action="{{ route('account.gratitude-journal.destroy', $entry) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-semibold text-red-600 hover:underline">Delete</button>
                                    </form>
                                </div>

                                <form
                                    method="POST" action="{{ route('account.gratitude-journal.update', $entry) }}"
                                    class="mt-3 hidden" data-gratitude-entry-form data-gratitude-edit-form
                                >
                                    @csrf
                                    @method('PUT')
                                    <textarea
                                        name="content" rows="3" maxlength="{{ $maxLength }}"
                                        data-gratitude-textarea
                                        class="block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                    >{{ $entry->content }}</textarea>
                                    <p data-gratitude-counter class="mt-1 text-right text-xs text-brand-navy/50">{{ mb_strlen($entry->content) }} / {{ $maxLength }}</p>

                                    <div class="mt-3">
                                        <span class="block text-sm font-medium text-brand-navy">Visibility</span>
                                        <div class="mt-2 flex flex-wrap gap-4">
                                            @foreach ($visibilityOptions as $option)
                                                <label class="flex items-center gap-2 text-sm text-brand-navy">
                                                    <input type="radio" name="visibility" value="{{ $option->value }}" @checked($entry->visibility === $option) class="border-brand-navy/30 text-brand-gold focus:ring-brand-gold">
                                                    {{ $option->getLabel() }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-3 flex gap-3">
                                        <button
                                            type="submit"
                                            class="rounded-md bg-brand-gold px-4 py-2 text-xs font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                                        >
                                            Save Changes
                                        </button>
                                        <button
                                            type="button" data-gratitude-edit-cancel
                                            class="rounded-md border border-brand-navy/20 px-4 py-2 text-xs font-semibold tracking-wide text-brand-navy uppercase transition hover:border-brand-gold hover:text-brand-gold"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-6 sm:p-8 {{ $reminderTabActive ? '' : 'hidden' }}" data-gratitude-tab-panel="reminder">
                <p class="text-sm text-brand-navy/70">How often would you like an email reminder to write in your Gratitude Journal?</p>

                <form method="POST" action="{{ route('account.gratitude-journal.reminder') }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-wrap gap-4">
                        @foreach ($reminderFrequencies as $frequency)
                            <label class="flex items-center gap-2 text-sm text-brand-navy">
                                <input
                                    type="radio" name="gratitude_reminder_frequency" value="{{ $frequency->value }}"
                                    @checked($reminderFrequency === $frequency)
                                    class="border-brand-navy/30 text-brand-gold focus:ring-brand-gold"
                                >
                                {{ $frequency->getLabel() }}
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <button
                            type="submit"
                            class="rounded-md border border-brand-navy/20 px-6 py-3 text-sm font-semibold tracking-wide text-brand-navy uppercase transition hover:border-brand-gold hover:text-brand-gold"
                        >
                            Save Preference
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.account>
