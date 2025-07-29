{{-- This file is used for menu items by any Backpack v6 theme --}}
{{-- @role('admin') --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

{{-- User Management Section --}}
<x-backpack::menu-dropdown title="User Management" icon="la la-users">
    <x-backpack::menu-dropdown-item title="Users" icon="la la-user" :link="backpack_url('user')" />
    <x-backpack::menu-dropdown-item title="Roles" icon="la la-id-badge" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permissions" icon="la la-key" :link="backpack_url('permission')" />
</x-backpack::menu-dropdown>

{{-- Business Management Section --}}
<x-backpack::menu-dropdown title="Business" icon="la la-business-time">
    <x-backpack::menu-dropdown-item title="Orders" icon="la la-shopping-cart" :link="backpack_url('order')" />
    <x-backpack::menu-dropdown-item title="Transactions" icon="la la-exchange-alt" :link="backpack_url('transaction')" />
</x-backpack::menu-dropdown>

{{-- Reports Section --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('reports') }}"><i class="la la-chart-bar nav-icon"></i> Reports</a></li>
{{-- @endrole --}}
