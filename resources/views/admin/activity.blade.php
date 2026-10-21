@extends('layouts.admin')

@section('title', __('Activity log'))

@section('content')
    <div class="card">
        <div class="card-header">
            <form class="row g-2" method="GET">
                <div class="col-md-4">
                    <select name="action" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('All actions') }}</option>
                        @foreach (['auth' => __('Sign in / accounts'), 'visit' => __('Visits'), 'visitor' => __('Visitors'), 'subscription' => __('Subscriptions'), 'pricing' => __('Prices'), 'coefficient' => __('Coefficients'), 'user' => __('Users'), 'role' => __('Roles')] as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Details') }}</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                        <td>
                            @if ($log->user)
                                <a href="{{ route('admin.activity', ['user' => $log->user_id]) }}">{{ $log->user->name }}</a>
                            @else — @endif
                        </td>
                        <td><code>{{ $log->action }}</code></td>
                        <td class="small">
                            @if ($log->subject_type === 'App\Models\Visitor' && $log->subject_id)
                                <a href="{{ route('admin.visitors.show', $log->subject_id) }}">{{ __('Visitor') }} #{{ $log->subject_id }}</a>
                            @elseif ($log->subject_type)
                                {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                            @endif
                        </td>
                        <td class="small text-break" style="max-width: 360px">
                            @if ($log->properties)<code class="small">{{ \Illuminate\Support\Str::limit(json_encode($log->properties, JSON_UNESCAPED_UNICODE), 160) }}</code>@endif
                        </td>
                        <td class="small text-body-secondary">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-body-secondary">{{ __('Nothing logged yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
