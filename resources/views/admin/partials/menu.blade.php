@php
    // [route, icon, label, permission, active pattern]  |  'header' => label
    $menu = [
        ['admin.dashboard', 'bi-speedometer2', __('Dashboard'), 'dashboard.view', 'admin.dashboard'],
        ['admin.entry', 'bi-door-open', __('New entrance'), 'visits.create', 'admin.entry'],
        ['header' => __('Visitors')],
        ['admin.visitors.index', 'bi-people', __('Visitors'), 'visitors.view', 'admin.visitors.*'],
        ['admin.visits.index', 'bi-calendar-check', __('Visits'), 'visits.view', 'admin.visits.*'],
        ['admin.subscriptions.index', 'bi-card-checklist', __('Subscriptions'), 'subscriptions.view', 'admin.subscriptions.*'],
        ['header' => __('Prices')],
        ['admin.pricing.index', 'bi-cash-coin', __('Price list'), 'pricing.view', ['admin.pricing.index', 'admin.coefficients.*']],
        ['admin.pricing.settings', 'bi-sliders', __('Age groups & hours'), 'pricing.manage', 'admin.pricing.settings'],
        ['header' => __('Access')],
        ['admin.users.index', 'bi-person-badge', __('Users'), 'users.view', 'admin.users.*'],
        ['admin.roles.index', 'bi-shield-lock', __('Roles'), 'roles.view', 'admin.roles.*'],
        ['admin.permissions.index', 'bi-key', __('Permissions'), 'roles.view', 'admin.permissions.*'],
        ['admin.activity', 'bi-clock-history', __('Activity log'), 'activity.view', 'admin.activity'],
    ];

    // Hide headers that would have no visible items under them.
    $visible = [];
    $pendingHeader = null;
    foreach ($menu as $item) {
        if (isset($item['header'])) {
            $pendingHeader = $item['header'];
            continue;
        }
        if (auth()->user()->can($item[3])) {
            if ($pendingHeader) {
                $visible[] = ['header' => $pendingHeader];
                $pendingHeader = null;
            }
            $visible[] = $item;
        }
    }
@endphp
<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
    @foreach ($visible as $item)
        @if (isset($item['header']))
            <li class="nav-header">{{ $item['header'] }}</li>
        @else
            <li class="nav-item">
                <a href="{{ route($item[0]) }}" @class(['nav-link', 'active' => request()->routeIs(...(array) $item[4])])>
                    <i class="nav-icon bi {{ $item[1] }}"></i>
                    <p>{{ $item[2] }}</p>
                </a>
            </li>
        @endif
    @endforeach
</ul>
