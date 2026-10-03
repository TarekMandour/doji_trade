@extends('admin.layout.master')

@php
    $route = 'admin.stocks';
@endphp

@section('css')
<link href="{{asset('dash/assets/plugins/custom/datatables/datatables.bundle.rtl.css')}}" rel="stylesheet" type="text/css" />
@endsection

@section('style')
@endsection

@section('breadcrumb')
    <!--begin::Toolbar-->
        <div class="toolbar mb-5 mb-lg-7" id="kt_toolbar">
            <!--begin::Page title-->
            <div class="page-title d-flex flex-column me-3">
                <!--begin::Title-->
                <h1 class="d-flex text-gray-900 fw-bold my-1 fs-3">{{ __('lang.stocks') }}</h1>
                <!--end::Title-->
                <!--begin::Breadcrumb-->
                <ul class="breadcrumb breadcrumb-dot fw-semibold text-gray-600 fs-6 my-1">
                    <!--begin::Item-->
                    <li class="breadcrumb-item text-gray-600">
                        <a href="{{route('admin.dashboard')}}" class="text-gray-600 text-hover-primary">{{ __('lang.home') }}</a>
                    </li>
                    <!--end::Item-->
                    <!--begin::Item-->
                    <li class="breadcrumb-item text-gray-600">
                        <a href="{{route($route.'.index')}}" class="text-gray-600 text-hover-primary">{{ __('lang.stocks') }}</a>
                    </li>
                    <!--end::Item-->
                    <!--begin::Item-->
                    <li class="breadcrumb-item text-gray-600">{{ __('lang.list') }}</li>
                    <!--end::Item-->
                </ul>
                <!--end::Breadcrumb-->
            </div>
            <!--end::Page title-->
            <!--begin::Actions-->
            <div class="d-flex align-items-center py-2 py-md-1">
                <!--begin::Button-->
                <a href="{{route($route.'.create')}}" class="btn btn-dark fw-bold"><i class="ki-duotone ki-plus fs-2"></i> {{ __('lang.add_new') }}</a>
                <!--end::Button-->
            </div>
            <!--end::Actions-->
        </div>
        <!--end::Toolbar-->
@endsection

@section('content')

<div class="content flex-column-fluid" id="kt_content">
    <!--begin::Card-->
    <div class="card">
        <!--begin::Card header-->
        <div class="card-header border-0 pt-6">
            <!--begin::Card title-->
            <div class="card-title">
                <!--begin::Search-->
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <input type="text" data-kt-db-table-filter="search" id="search" class="form-control form-control-solid w-250px ps-13" placeholder="{{ __('lang.search') }} ..." />
                </div>
                <!--end::Search-->
            </div>
            <!--begin::Card title-->
            <!--begin::Card toolbar-->
            <div class="card-toolbar">
                <!--begin::Toolbar-->
                <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                    <a href="javascript:;" class="btn btn-icon btn-success me-2" id="btn_export" data-token="{{ csrf_token() }}"><i class="bi bi-file-earmark-arrow-down-fill fs-4"></i></a>
                    <a href="javascript:;" class="btn btn-icon btn-info me-2" data-bs-toggle="modal" data-bs-target="#kt_modal_import"><i class="bi bi-file-earmark-arrow-up-fill fs-4"></i></a>
                    <a href="javascript:;" class="btn btn-icon btn-danger me-2" id="btn_delete" data-token="{{ csrf_token() }}"><i class="bi bi-trash-fill fs-4"></i></a>
                </div>
                <!--end::Toolbar-->
            </div>
            <!--end::Card toolbar-->
        </div>
        <!--end::Card header-->
        <!--begin::Card body-->
        <div class="card-body py-4">
            <!--begin::Table-->
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
                            <th class="text-start">{{ __('lang.symbol') }}</th>
                            <th class="text-start">{{ __('lang.name') }}</th>
                            <th class="text-start">{{ __('lang.market') }}</th>
                            <th class="text-start">{{ __('lang.status') }}</th>
                            <th class="text-start">{{ __('lang.created_at') }}</th>
                            <th class="text-start min-w-100px">{{ __('lang.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-800 fw-semibold">
                    </tbody>
                </table>
            </div>
            <!--end::Table-->
        </div>
        <!--end::Card body-->
    </div>
    <!--end::Card-->
</div>

<div class="modal fade" id="kt_modal_import" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">{{__('lang.import')}}</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body px-5 my-7">
                <form class="form" action="{{route($route.'.import')}}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-semibold form-label mb-2">
                            <span class="required">{{__('lang.import_file')}}</span>
                        </label>
                        <input type="file" class="form-control form-control-solid" name="file" accept=".xlsx,.xls,.csv" required />
                    </div>
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">{{__('lang.cancel')}}</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload fs-4 me-2"></i>{{__('lang.import')}}</button>
                    </div>
                </form>
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
            {data: 'symbol', name: 'symbol'},
            {data: 'name', name: 'name'},
            {data: 'market', name: 'market'},
            {data: 'status', name: 'status'},
            {data: 'date', name: 'date'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    const filterSearch = document.querySelector('[data-kt-db-table-filter="search"]');
    filterSearch.addEventListener('keyup', function (e) {
        table.draw();
    });

    $("#btn_export").click(function (event) {
        event.preventDefault();


        var params = {
            
        };

        window.location = "{{ route($route.'.export') }}?" + $.param(params);

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
                    toastr.error("", "{{ __('lang.delete_cancelled') }}");
                }
            });

        } else {
            toastr.error("", "{{ __('lang.select_items_first') }}");
        }
    });

});
</script>
@endsection
