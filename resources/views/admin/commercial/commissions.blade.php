<x-crud-page title="Commercial policies" heading="Commercial policies">
    <p class="text-sm text-lamu-800/70">
        Configure proposal templates for broker commission and Lamu Acres agency fees. Templates are not approved tariffs: staff must still propose and accept a deal-specific agreement before anything can be earned. Changing a template does not alter agreements already on file or earned snapshots.
    </p>

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-lamu-900">Outright sale — broker commission</h2>
        @include('admin.commercial.partials.commission-policy-form', [
            'policy' => $saleCommissionPolicy,
            'action' => route('admin.commercial.commissions.update'),
            'commissionBases' => collect($commissionBases)->filter(fn ($basis) => ! $basis->isLease()),
            'fundingParties' => $fundingParties,
            'submitLabel' => 'Save sale commission policy',
        ])
    </section>

    <section class="mt-10">
        <h2 class="text-lg font-semibold text-lamu-900">Lease — broker commission template</h2>
        <p class="mt-1 text-sm text-lamu-800/70">Lease percentages in the database are draft values until commercial leadership approves them and enables auto-fill below.</p>
        @include('admin.commercial.partials.lease-commission-policy-form', [
            'policy' => $leaseCommissionPolicy,
            'action' => route('admin.commercial.commissions.lease.update'),
            'commissionBases' => collect($commissionBases)->filter(fn ($basis) => $basis->isLease() || $basis === \App\Enums\CommissionCalculationBasis::Fixed),
            'fundingParties' => $fundingParties,
        ])
    </section>

    <section class="mt-10 grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="text-lg font-semibold text-lamu-900">Outright sale — agency fee</h2>
            @include('admin.commercial.partials.agency-fee-policy-form', [
                'policy' => $saleAgencyFeePolicy,
                'action' => route('admin.commercial.agency-fees.update', $saleAgencyFeePolicy->code),
                'agencyFeeBases' => collect($agencyFeeBases)->filter(fn ($basis) => in_array($basis->value, ['agreed_sale_price', 'fixed'], true)),
                'feePayers' => collect($feePayers)->filter(fn ($p) => $p !== \App\Enums\AgencyFeePayer::Lessor),
            ])
        </div>
        <div>
            <h2 class="text-lg font-semibold text-lamu-900">Lease — agency fee template</h2>
            @include('admin.commercial.partials.agency-fee-policy-form', [
                'policy' => $leaseAgencyFeePolicy,
                'action' => route('admin.commercial.agency-fees.update', $leaseAgencyFeePolicy->code),
                'agencyFeeBases' => collect($agencyFeeBases)->filter(fn ($basis) => $basis !== \App\Enums\FeeCalculationBasis::AgreedSalePrice),
                'feePayers' => $feePayers,
            ])
        </div>
    </section>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-sand-200 bg-white p-4 text-sm text-lamu-800/80">
            @if ($canOpenCommissions)
                <a href="{{ route('staff.commissions.index') }}" class="font-semibold text-lamu-800 underline">Open commission management</a>
            @else
                <p>Commission management is on the sales desk. This account is not authorized to open it.</p>
            @endif
        </div>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-lamu-900">Recently recorded agreements</h2>
        <div class="mt-3 overflow-x-auto rounded-lg border border-sand-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-sand-50 text-lamu-800/70">
                    <tr>
                        <th class="px-4 py-2 font-medium">Reference</th>
                        <th class="px-4 py-2 font-medium">Recorded arrangement</th>
                        <th class="px-4 py-2 font-medium">Funding</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agreements as $agreement)
                        <tr class="border-t border-sand-200">
                            <td class="px-4 py-2">
                                @if ($canOpenCommissions)
                                    <a href="{{ route('staff.commissions.show', $agreement) }}" class="font-medium text-lamu-800 underline">{{ $agreement->reference }}</a>
                                @else
                                    {{ $agreement->reference }}
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($agreement->calculation_basis)
                                    {{ $agreement->calculation_basis->label() }}
                                @elseif ($agreement->rate_percent !== null)
                                    {{ $agreement->rate_percent }}% on this agreement
                                @elseif ($agreement->fixed_amount !== null)
                                    Fixed {{ number_format($agreement->fixed_amount) }} on this agreement
                                @else
                                    Arrangement recorded without a rate
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $agreement->funding_party->label() }}</td>
                            <td class="px-4 py-2">{{ $agreement->status->label() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-lamu-800/60">No commission agreement has been recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-crud-page>
