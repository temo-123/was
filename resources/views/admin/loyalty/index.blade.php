@extends('layouts.admin')

@section('title', __('VIP & rewards'))

@section('content')
    {{-- Client statuses --}}
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title"><i class="bi bi-star me-1"></i> {{ __('Client statuses') }}</h3></div>
        <div class="card-body pb-0">
            <p class="small text-body-secondary">
                {{ __('A status gives a discount on every entry: VIP 50 = half price, Gold 100 = free. "Gear" = discount on rental gear too. Give a status on the visitor\'s page (optionally until a date).') }}
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 60px">{{ __('Colour') }}</th><th>{{ __('Name') }}</th><th>{{ __('Name (KA)') }}</th>
                    <th>{{ __('Entry discount') }}</th><th>{{ __('Gear discount') }}</th><th class="text-center">{{ __('Clients') }}</th>
                    <th>{{ __('Order') }}</th><th class="text-center">{{ __('Active') }}</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($tiers->concat([null]) as $tier)
                    @php($formId = 'tier-'.($tier->id ?? 'new'))
                    <tr @class(['table-light' => ! $tier, 'opacity-50' => $tier && ! $tier->is_active])>
                        <td><input form="{{ $formId }}" type="color" name="color" value="{{ $tier?->color ?? '#f5b83d' }}" class="form-control form-control-sm form-control-color"></td>
                        <td><input form="{{ $formId }}" name="name" value="{{ $tier?->name }}" class="form-control form-control-sm" placeholder="{{ $tier ? '' : __('e.g. VIP') }}" required></td>
                        <td><input form="{{ $formId }}" name="name_ka" value="{{ $tier?->name_ka }}" class="form-control form-control-sm" placeholder="{{ $tier ? '' : 'VIP' }}"></td>
                        <td>
                            <div class="input-group input-group-sm" style="max-width: 120px">
                                <input form="{{ $formId }}" type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ $tier ? (float) $tier->discount_percent : '' }}" class="form-control" placeholder="50" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="max-width: 120px">
                                <input form="{{ $formId }}" type="number" step="0.01" min="0" max="100" name="rental_discount_percent" value="{{ $tier ? (float) $tier->rental_discount_percent : '' }}" class="form-control" placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            @if ($tier)
                                <a href="{{ route('admin.visitors.index', ['status' => $tier->id]) }}">{{ $tier->visitors_count }}</a>
                            @endif
                        </td>
                        <td><input form="{{ $formId }}" type="number" min="0" name="sort_order" value="{{ $tier?->sort_order ?? 0 }}" class="form-control form-control-sm" style="max-width: 70px"></td>
                        <td class="text-center"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($tier ? $tier->is_active : true)></td>
                        <td class="text-end">
                            <form id="{{ $formId }}" method="POST" action="{{ route('admin.loyalty.tiers.save') }}">
                                @csrf
                                @if ($tier)<input type="hidden" name="id" value="{{ $tier->id }}">@endif
                                <button class="btn btn-sm {{ $tier ? 'btn-outline-primary' : 'btn-primary' }}" title="{{ $tier ? __('Save') : __('Add') }}"><i class="bi {{ $tier ? 'bi-save' : 'bi-plus' }}"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Visit rewards --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="bi bi-gift me-1"></i> {{ __('Visit rewards') }}</h3></div>
        <div class="card-body pb-0">
            <p class="small text-body-secondary">
                {{ __('"Every 20" = the visitor\'s 20th, 40th, 60th… visit. The entrance screen shows the reward and adds it to the price (staff can switch it off for that visit). A free entry does not apply to buying a subscription; a free gear item is given when that gear is chosen.') }}
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('Name') }}</th><th>{{ __('Name (KA)') }}</th><th>{{ __('Every … visits') }}</th><th>{{ __('Reward') }}</th>
                    <th>{{ __('Discount') }} / {{ __('Gear') }}</th><th>{{ __('Order') }}</th><th class="text-center">{{ __('Active') }}</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rewards->concat([null]) as $reward)
                    @php($formId = 'reward-'.($reward->id ?? 'new'))
                    <tr @class(['table-light' => ! $reward, 'opacity-50' => $reward && ! $reward->is_active]) data-reward-row>
                        <td><input form="{{ $formId }}" name="name" value="{{ $reward?->name }}" class="form-control form-control-sm" placeholder="{{ $reward ? '' : __('e.g. Every 20th visit free') }}" required></td>
                        <td><input form="{{ $formId }}" name="name_ka" value="{{ $reward?->name_ka }}" class="form-control form-control-sm"></td>
                        <td><input form="{{ $formId }}" type="number" min="2" name="every_visits" value="{{ $reward?->every_visits }}" class="form-control form-control-sm" style="max-width: 90px" placeholder="20" required></td>
                        <td>
                            <select form="{{ $formId }}" name="type" class="form-select form-select-sm" data-reward-type>
                                @foreach ($types as $type => $label)
                                    <option value="{{ $type }}" @selected(($reward?->type ?? 'free_entry') === $type)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="max-width: 120px" data-show-for="discount">
                                <input form="{{ $formId }}" type="number" min="1" max="100" step="0.01" name="value" value="{{ $reward?->value !== null ? (float) $reward?->value : '' }}" class="form-control" placeholder="30">
                                <span class="input-group-text">%</span>
                            </div>
                            <select form="{{ $formId }}" name="rental_item_id" class="form-select form-select-sm" data-show-for="free_rental">
                                <option value="">{{ __('Choose gear…') }}</option>
                                @foreach ($rentalItems as $item)
                                    <option value="{{ $item->id }}" @selected($reward?->rental_item_id === $item->id)>{{ $item->local_name }}</option>
                                @endforeach
                            </select>
                            <span class="small text-body-secondary" data-show-for="free_entry">—</span>
                        </td>
                        <td><input form="{{ $formId }}" type="number" min="0" name="sort_order" value="{{ $reward?->sort_order ?? 0 }}" class="form-control form-control-sm" style="max-width: 70px"></td>
                        <td class="text-center"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($reward ? $reward->is_active : true)></td>
                        <td class="text-end">
                            <form id="{{ $formId }}" method="POST" action="{{ route('admin.loyalty.rewards.save') }}">
                                @csrf
                                @if ($reward)<input type="hidden" name="id" value="{{ $reward->id }}">@endif
                                <button class="btn btn-sm {{ $reward ? 'btn-outline-primary' : 'btn-primary' }}" title="{{ $reward ? __('Save') : __('Add') }}"><i class="bi {{ $reward ? 'bi-save' : 'bi-plus' }}"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Show the discount % only for "discount", the gear list only for "free rental item".
        document.querySelectorAll('[data-reward-row]').forEach((row) => {
            const select = row.querySelector('[data-reward-type]');
            const update = () => row.querySelectorAll('[data-show-for]').forEach((el) => el.classList.toggle('d-none', el.dataset.showFor !== select.value));
            select.addEventListener('change', update);
            update();
        });
    </script>
@endpush
