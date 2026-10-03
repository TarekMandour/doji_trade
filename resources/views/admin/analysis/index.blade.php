@extends('admin.layout.master')

@php
    $route = 'admin.analysis';
@endphp

@section('css')
<link href="{{asset('dash/assets/plugins/custom/datatables/datatables.bundle.rtl.css')}}" rel="stylesheet" type="text/css" />
@endsection

@section('style')
@endsection

@section('breadcrumb')
    <!--begin::Toolbar-->
        <div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
            <div class="page-title d-flex flex-column me-3">
                <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.analysis') }}</h1>
                <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
                    <li class="breadcrumb-item text-gray-600">
                        <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
                    </li>
                    <li class="breadcrumb-item text-gray-600">
                        <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.analysis') }}</a>
                    </li>
                    <li class="breadcrumb-item text-gray-600">{{ __('lang.list') }}</li>
                </ul>
            </div>
            <div class="d-flex align-items-center py-2 py-md-1">
                @can('analysis create')
                <a href="{{route($route.'.create')}}" class="btn btn-dark fw-bold"><i class="ki-duotone ki-plus fs-2"></i> {{ __('lang.run_analysis') }}</a>
                <a href="{{route($route.'.intraday')}}" class="btn btn-dark fw-bold"><i class="ki-duotone ki-plus fs-2"></i> {{ __('lang.run_intraday_analysis') }}</a>
                @endcan
            </div>
        </div>
        <!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <input type="text" data-kt-db-table-filter="search" id="search" class="form-control form-control-solid w-250px ps-13" placeholder="{{ __('lang.search') }} ..." />
                </div>
            </div>
            <div class="card-toolbar">
                <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                    @can('analysis delete')
                    <a href="javascript:;" class="btn btn-icon btn-danger me-2" id="btn_delete" data-token="{{ csrf_token() }}"><i class="bi bi-trash-fill fs-4"></i></a>
                    @endcan
                </div>
            </div>
        </div>
        <div class="card-body py-4">
            <div class="table-responsive">
                <table class="table align-middle table-hover table-row-dashed text-start fs-6 gy-5" id="kt_table_list">
                    <thead>
                        <tr class="text-start text-dark bg-light-dark fw-bold fs-5 text-uppercase gs-0">
                            <th class="text-start w-60px">#</th>
                            <th class="text-start w-60px">
                                <div class="form-check form-check-sm form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" data-kt-check="true" data-kt-check-target="#kt_table_list .form-check-input" value="1" />
                                </div>
                            </th>
                            <th class="text-start">{{ __('lang.title') }}</th>
                            <th class="text-start">{{ __('lang.watchlist') }}</th>
                            <th class="text-start">{{ __('lang.stocks_count') }}</th>
                            <th class="text-start">{{ __('lang.analysis_date') }}</th>
                            <th class="text-start min-w-100px">{{ __('lang.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800 fw-semibold">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="{{asset('dash/assets/plugins/custom/datatables/datatables.bundle.js')}}"></script>

<script>
$(function () {

    var table = $('#kt_table_list').DataTable({
        processing: false,
        searching: false,
        serverSide: true,
        pageLength: 10,
        sort: false,
        language: {
            "loadingRecords": "{{ __('lang.loadingRecords') }}"
        },
        ajax: {
            url: "{{ route($route.'.index') }}",
            data: function (d) {
                d.search = $('#search').val();
            }
        },
        lengthMenu: [
            [10, 25, 50, 100],
            ['10', '25', '50', '100']
        ],
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'checkbox', name: 'checkbox'},
            {data: 'title', name: 'title'},
            {data: 'watchlist_name', name: 'watchlist_name'},
            {data: 'stocks_count', name: 'stocks_count'},
            {data: 'date', name: 'date'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    const filterSearch = document.querySelector('[data-kt-db-table-filter="search"]');
    filterSearch.addEventListener('keyup', function (e) {
        table.draw();
    });

    $("#btn_delete").click(function (event) {
        event.preventDefault();
        var checkIDs = $("#kt_table_list input:checkbox:checked").map(function () {
            return $(this).val();
        }).get();

        if (checkIDs.length > 0) {
            var token = $(this).data("token");

            Swal.fire({
                title: '{{ __("lang.confirm_delete") }}',
                text: "{{ __('lang.cannot_restore') }}",
                icon: "info",
                buttonsStyling: false,
                showCancelButton: true,
                confirmButtonText: "{{ __('lang.confirm') }}",
                cancelButtonText: "{{ __('lang.cancel') }}",
                customClass: {
                    confirmButton: "btn btn-primary",
                    cancelButton: 'btn btn-danger'
                }
            }).then(function (isConfirm) {
                if (isConfirm.value) {
                    $.ajax(
                        {
                            url: "{{route($route.'.delete')}}",
                            type: 'post',
                            dataType: "JSON",
                            data: {
                                "id": checkIDs,
                                "_method": 'post',
                                "_token": token,
                            },
                            success: function (data) {
                                if (data.message == "success") {
                                    table.draw();
                                    toastr.success("", "{{ __('lang.deleted_successfully') }}");
                                } else {
                                    toastr.success("", "{{ __('lang.delete_failed') }}");
                                }
                            },
                            fail: function (xhrerrorThrown) {
                                toastr.success("", "{{ __('lang.delete_failed') }}");
                            }
                        });
                } else {
                    toastr.info("", "{{ __('lang.delete_cancelled') }}");
                }
            });
        } else {
            toastr.warning("", "{{ __('lang.select_items_first') }}");
        }
    });
});
</script>
@endsection
