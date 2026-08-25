<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Transaction History</h1>
        <p class="mt-1 text-sm text-brand-navy/70">Every purchase and payment on your account.</p>

        @if ($transactions->isEmpty())
            <p class="mt-6 text-sm text-brand-navy/60">You don't have any transactions yet.</p>
        @else
            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-brand-navy/10 text-xs tracking-wide text-brand-navy/50 uppercase">
                            <th class="py-2 pr-4 font-medium">Date</th>
                            <th class="py-2 pr-4 font-medium">Description</th>
                            <th class="py-2 pr-4 font-medium">Type</th>
                            <th class="py-2 pr-4 font-medium">Amount</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            @php
                                $itemTitles = $transaction->order?->items->pluck('item_title')->implode(', ');
                                $description = filled($itemTitles) ? $itemTitles : 'Pro Membership';
                            @endphp
                            <tr class="border-b border-brand-navy/5">
                                <td class="py-3 pr-4 whitespace-nowrap text-brand-navy">{{ $transaction->occurred_at?->format('M j, Y') }}</td>
                                <td class="py-3 pr-4 text-brand-navy">{{ $description }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap text-brand-navy/70">{{ $transaction->type->getLabel() }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap text-brand-navy">{{ $transaction->amount !== null ? '$'.number_format($transaction->amount, 2) : '—' }}</td>
                                <td class="py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase {{ $transaction->status->value === 'succeeded' ? 'bg-brand-gold/10 text-brand-gold' : 'bg-red-50 text-red-700' }}">
                                        {{ $transaction->status->getLabel() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $transactions->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</x-layouts.account>
