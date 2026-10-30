@extends('admin.layout')
@section('title', 'Accounts · Admin')

@section('content')
<p class="eyebrow">Administration</p>
<h1 class="h2 admin-title">Accounts</h1>
<p class="mut">Review registered accounts, manage administrator access, or delete an account. You cannot change or delete your own account; the last administrator is protected. Reservations and payment history are retained when an account is deleted.</p>

<section class="panel admin-section">
  <div class="tbl"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Reservations</th><th>Role</th><th>Access</th><th>Account</th></tr></thead>
    <tbody>@foreach ($users as $user)
      <tr>
        <td>{{ $user->name }}</td>
        <td>{{ $user->email }}</td>
        <td>{{ $user->created_at->format('M j, Y') }}</td>
        <td>{{ $user->reservations_count }}</td>
        <td>{{ $user->is_admin ? 'Administrator' : 'Guest' }}</td>
        <td>
          @if ($user->is(auth()->user()))
            <span class="mut small">Current account</span>
          @else
            <form method="POST" action="{{ route('admin.users.role', $user) }}" class="admin-role-form">
              @csrf @method('PUT')
              <input type="hidden" name="is_admin" value="{{ $user->is_admin ? '0' : '1' }}">
              <button class="btn {{ $user->is_admin ? 'danger' : 'ghost' }} sm">{{ $user->is_admin ? 'Remove admin' : 'Make admin' }}</button>
            </form>
          @endif
        </td>
        <td>
          @unless ($user->is(auth()->user()))
            <form method="POST" action="{{ route('admin.users.delete', $user) }}" class="admin-role-form" onsubmit="return confirm('Permanently delete this account? Reservation and payment history will be kept.')">
              @csrf @method('DELETE')
              <button class="btn danger sm">Delete account</button>
            </form>
          @else
            <span class="mut small">Protected</span>
          @endunless
        </td>
      </tr>
    @endforeach</tbody>
  </table></div>
  <div class="admin-pagination">{{ $users->links() }}</div>
</section>
@endsection
