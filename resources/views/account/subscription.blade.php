<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Subscription</h1>
        <p class="mt-1 text-sm text-brand-navy/70">Your Pro Membership plan and renewal history.</p>

        @if ($subscription)
            <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 rounded-xl border border-brand-navy/10 p-5 sm:grid-cols-3">
                <div>
                    <p class="text-xs tracking-wide text-brand-navy/50 uppercase">Plan</p>
                    <p class="mt-1 text-sm font-medium text-brand-navy">Pro Membership</p>
                </div>
                <div>
                    <p class="text-xs tracking-wide text-brand-navy/50 uppercase">Status</p>
                    <p class="mt-1">
                        @if ($hasActiveMembership)
                            <span class="inline-flex items-center rounded-full bg-brand-gold/10 px-3 py-1 text-xs font-semibold tracking-wide text-brand-gold uppercase">{{ $subscription->displayStatus()->getLabel() }}</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold tracking-wide text-red-700 uppercase">{{ $subscription->displayStatus()->getLabel() }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs tracking-wide text-brand-navy/50 uppercase">Price</p>
                    <p class="mt-1 text-sm font-medium text-brand-navy">
                        {{ $subscription->price_at_subscription !== null ? '$'.number_format($subscription->price_at_subscription, 2).' / month' : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs tracking-wide text-brand-navy/50 uppercase">Member Since</p>
                    <p class="mt-1 text-sm font-medium text-brand-navy">{{ $subscription->started_at?->format('M j, Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs tracking-wide text-brand-navy/50 uppercase">
                        {{ $subscription->cancel_at_period_end ? 'Access Ends' : 'Next Renewal' }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-brand-navy">{{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs tracking-wide text-brand-navy/50 uppercase">Auto-Renew</p>
                    <p class="mt-1 text-sm font-medium text-brand-navy">{{ $subscription->cancel_at_period_end ? 'Off — cancelling at period end' : 'On' }}</p>
                </div>
            </div>

            <h2 class="mt-8 font-serif text-lg text-brand-navy">Renewal History</h2>

            @if ($renewals->isEmpty())
                <p class="mt-3 text-sm text-brand-navy/60">No renewal payments yet.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full min-w-[500px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-brand-navy/10 text-xs tracking-wide text-brand-navy/50 uppercase">
                                <th class="py-2 pr-4 font-medium">Date</th>
                                <th class="py-2 pr-4 font-medium">Amount</th>
                                <th class="py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($renewals as $renewal)
                                <tr class="border-b border-brand-navy/5">
                                    <td class="py-3 pr-4 text-brand-navy">{{ $renewal->occurred_at?->format('M j, Y') }}</td>
                                    <td class="py-3 pr-4 text-brand-navy">{{ $renewal->amount !== null ? '$'.number_format($renewal->amount, 2) : '—' }}</td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase {{ $renewal->status->value === 'succeeded' ? 'bg-brand-gold/10 text-brand-gold' : 'bg-red-50 text-red-700' }}">
                                            {{ $renewal->status->getLabel() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <div class="mt-6 rounded-xl border border-brand-navy/10 p-5">
                <span class="inline-flex items-center rounded-full bg-brand-navy/10 px-3 py-1 text-xs font-semibold tracking-wide text-brand-navy/70 uppercase">Free Member</span>
                <p class="mt-3 text-sm text-brand-navy/60">You don't have a Pro Membership subscription, so there's no billing or renewal history to show.</p>
            </div>
        @endif
    </div>
</x-layouts.account>
