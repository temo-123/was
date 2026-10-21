<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.activity', [
            'logs' => ActivityLog::with('user')
                ->when($request->input('action'), fn ($q, $action) => $q->where('action', 'like', "{$action}%"))
                ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
                ->latest('created_at')->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
