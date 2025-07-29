{{-- This file is used to store topbar (right) items --}}

{{-- Permissions Dropdown Menu --}}
@canany(['manage users', 'manage roles', 'manage permissions'])
<li class="nav-item dropdown d-md-down-none">
    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="la la-shield-alt"></i> Permissions
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
        <li class="dropdown-header">Access Control</li>
        @can('manage users')
        <li>
            <a class="dropdown-item" href="{{ backpack_url('user') }}">
                <i class="la la-user me-2"></i>Users
            </a>
        </li>
        @endcan
        @can('manage roles')
        <li>
            <a class="dropdown-item" href="{{ backpack_url('role') }}">
                <i class="la la-id-badge me-2"></i>Roles
            </a>
        </li>
        @endcan
        @can('manage permissions')
        <li>
            <a class="dropdown-item" href="{{ backpack_url('permission') }}">
                <i class="la la-key me-2"></i>Permissions
            </a>
        </li>
        @endcan
    </ul>
</li>
@endcanany

{{-- <li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-bell"></i><span class="badge badge-pill bg-danger">5</span></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-list"></i></a></li>
<li class="nav-item d-md-down-none"><a class="nav-link" href="#"><i class="la la-map"></i></a></li> --}}
