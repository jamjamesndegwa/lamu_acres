@php
    $body = 'account.applications.partials.form';
@endphp

@if ($modal)
    <turbo-frame id="modal">
        <x-modal title="Apply as a {{ $capability->label() }}" :close="route('account.applications.index')">
            @include($body, ['capability' => $capability, 'application' => $application])
        </x-modal>
    </turbo-frame>
@elseif ($capability === \App\Enums\PartnerCapability::Broker)
    @include('account.applications.broker-apply', ['capability' => $capability, 'application' => $application])
@elseif ($capability === \App\Enums\PartnerCapability::Seller)
    @include('account.applications.seller-apply', ['capability' => $capability, 'application' => $application])
@else
    <x-account-layout :title="'Apply as a '.$capability->label()">
        <h1 class="text-2xl font-semibold text-lamu-900">Apply as a {{ $capability->label() }}</h1>
        <div class="mt-6">
            @include($body, ['capability' => $capability, 'application' => $application])
        </div>
    </x-account-layout>
@endif
