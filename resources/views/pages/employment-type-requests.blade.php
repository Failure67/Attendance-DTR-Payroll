@extends('layouts.app')

@section('content')
<div class="container {{ $pageClass ?? 'employment-type-requests' }} mt-4">
    <h1 class="mb-4">Employment type change requests</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $roleKey = strtolower($roleKey ?? '');
    @endphp

    @if($roleKey === 'hr' || $roleKey === 'admin')
        <div class="card mb-4">
            <div class="card-header">Create employment type change request</div>
            <div class="card-body">
                <form method="POST" action="{{ route('employment-type-requests.store') }}">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="user_id" class="form-label">Employee</label>
                            <select name="user_id" id="user_id" class="form-select" required>
                                <option value="">Select employee</option>
                                @foreach($userOptions as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                        {{ $user->full_name ?? $user->username }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="to_type" class="form-label">Target employment type</label>
                            <select name="to_type" id="to_type" class="form-select" required>
                                <option value="">Select type</option>
                                <option value="regular" @selected(old('to_type') === 'regular')>Regular</option>
                                <option value="part_time" @selected(old('to_type') === 'part_time')>Part-time</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea name="reason" id="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit request</button>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Requests</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Requested by</th>
                            <th>Status</th>
                            <th>Created at</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td>{{ optional($req->user)->full_name ?? optional($req->user)->username ?? 'N/A' }}</td>
                                <td>{{ $req->from_type ?? 'regular' }}</td>
                                <td>{{ $req->to_type }}</td>
                                <td>{{ optional($req->requester)->full_name ?? optional($req->requester)->username ?? 'N/A' }}</td>
                                <td class="text-capitalize">{{ $req->status }}</td>
                                <td>{{ $req->created_at ? $req->created_at->format('Y-m-d H:i') : '—' }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        @if($roleKey === 'manager' || $roleKey === 'admin')
                                            @if($req->status === \App\Models\EmploymentTypeChangeRequest::STATUS_PENDING)
                                                <form method="POST" action="{{ route('employment-type-requests.approve', ['id' => $req->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('employment-type-requests.reject', ['id' => $req->id]) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="manager_reason" value="Request rejected by manager">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                </form>
                                            @endif
                                        @endif

                                        @if($roleKey === 'admin' || $roleKey === 'superadmin')
                                            @if(in_array($req->status, [\App\Models\EmploymentTypeChangeRequest::STATUS_PENDING, \App\Models\EmploymentTypeChangeRequest::STATUS_REJECTED, \App\Models\EmploymentTypeChangeRequest::STATUS_APPROVED], true))
                                                <form method="POST" action="{{ route('employment-type-requests.override', ['id' => $req->id]) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="admin_reason" value="Admin override">
                                                    <button type="submit" class="btn btn-sm btn-warning">Override</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection
