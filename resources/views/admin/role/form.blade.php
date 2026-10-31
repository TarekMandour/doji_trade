@php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Load all admin-guard permissions and group by module (first word)
$allPermissions = Permission::where('guard_name', 'admin')->orderBy('name')->get();

$grouped = [];
foreach ($allPermissions as $perm) {
    $parts = explode(' ', $perm->name, 2);
    $module = $parts[0];
    $action = $parts[1] ?? $perm->name;
    $grouped[$module][] = $perm;
}

// Current role permissions (for edit mode)
$rolePermissionIds = [];
if (isset($data)) {
    $role = Role::find($data->id);
    $rolePermissionIds = $role->permissions->pluck('id')->toArray();
}

// Module label map (falls back to ucfirst of module key)
$moduleLabels = [
    'dashboard'        => __('lang.dashboard'),
    'classrooms'       => __('lang.classrooms'),
    'messages'         => __('lang.messages'),
    'questions'        => __('lang.questions'),
    'exams'            => __('lang.exams'),
    'wallas'           => __('lang.wallas'),
    'blogs'            => __('lang.blogs'),
    'users'            => __('lang.users'),
    'students'         => __('lang.students'),
    'degrees'          => __('lang.degrees'),
    'dailyreports'     => __('lang.daily_reports'),
    'absences'         => __('lang.absences'),
    'buses'            => __('lang.buses'),
    'student-reviews'  => __('lang.student_reviews'),
    'student-payments' => __('lang.student_payments'),
    'payment-fees'     => __('lang.payment_fees'),
    'admins'           => __('lang.admins'),
    'levels'           => __('lang.levels'),
    'subjects'         => __('lang.subjects'),
    'sections'         => __('lang.sections'),
    'notifications'    => __('lang.notifications'),
    'pages'            => __('lang.pages'),
    'scratchvideos'    => __('lang.scratch_videos'),
    'roles'            => __('lang.roles'),
    'setting'          => __('lang.settings'),
    'employee'         => __('lang.admins'),
];

// Action label map
$actionLabels = [
    'view'   => __('lang.view'),
    'create' => __('lang.create'),
    'edit'   => __('lang.edit'),
    'delete' => __('lang.delete'),
    'list'   => __('lang.view'),
    'add'    => __('lang.create'),
];
@endphp

<div class="fv-row mb-10">
    <label class="fs-5 fw-bold form-label mb-2">
        <span class="required">{{ __('lang.name') }}</span>
    </label>
    <input class="form-control form-control-solid" type="text" placeholder="{{ __('lang.name') }}" name="name" value="{{ old('name', $data->name ?? '') }}" />
</div>

<div class="fv-row">
    <label class="fs-5 fw-bold form-label mb-2">{{ __('lang.permissions') }}</label>

    <div class="table-responsive">
        <table class="table align-middle table-row-dashed fs-6 gy-5">
            <thead>
                <tr class="text-start text-muted fw-bold fs-7 text-uppercase">
                    <th class="min-w-150px">{{ __('lang.module') }}</th>
                    <th>{{ __('lang.permissions') }}</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 fw-semibold">

                {{-- Select All row --}}
                <tr class="border-bottom border-gray-200">
                    <td class="text-gray-800 fw-bold">
                        {{ __('lang.select_all') }}
                        <span class="ms-1" data-bs-toggle="tooltip" title="Select / deselect all permissions">
                            <i class="bi bi-info-circle text-gray-500 fs-7"></i>
                        </span>
                    </td>
                    <td>
                        <label class="form-check form-check-sm form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="kt_roles_select_all" />
                            <span class="form-check-label fw-bold" for="kt_roles_select_all">{{ __('lang.select_all') }}</span>
                        </label>
                    </td>
                </tr>

                {{-- Permission rows grouped by module --}}
                @foreach ($grouped as $module => $perms)
                <tr>
                    <td class="text-gray-800">
                        {{ $moduleLabels[$module] ?? ucfirst(str_replace(['-','_'], ' ', $module)) }}
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach ($perms as $perm)
                            @php
                                $parts   = explode(' ', $perm->name, 2);
                                $action  = $parts[1] ?? $perm->name;
                                $label   = $actionLabels[$action] ?? ucfirst($action);
                                $checked = in_array($perm->id, $rolePermissionIds) ? 'checked' : '';
                            @endphp
                            <label class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input perm-checkbox" type="checkbox"
                                       name="permissions[]"
                                       value="{{ $perm->id }}"
                                       {{ $checked }} />
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endforeach

            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('kt_roles_select_all').addEventListener('change', function () {
        document.querySelectorAll('.perm-checkbox').forEach(function (cb) {
            cb.checked = document.getElementById('kt_roles_select_all').checked;
        });
    });
</script>
@endpush
